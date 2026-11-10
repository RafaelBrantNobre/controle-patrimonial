<?php

namespace App\Models;

use App\Enums\EstadoConservacao;
use App\Enums\ResolucaoPendencia;
use App\Enums\ResultadoConferencia;
use App\Enums\SituacaoConferencia;
use App\Enums\StatusBem;
use App\Enums\TipoOcorrencia;
use Database\Factories\BemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/*
| O item patrimonial, entidade central do sistema.
| Bem nunca é apagado: a saída do patrimônio ativo é a baixa (RN07).
| Todo bem recebe um codigo_interno gerado pelo sistema (PD07), como
| CP-000123, tenha ou não número de patrimônio.
| sala_id nulo é o bem sem localização: ainda não teve a sala confirmada.
| status é a situação de uso (em uso, sem uso, baixado) e estado_conservacao
| é a condição física (novo a sucata). Uma não depende da outra.
| Mudar de sala e de status passa por mudarDeSala() e alterarStatus()
| (tarefa 2.8), que gravam quem fez e quando (RN09).
| data_ultima_conferencia e resultado_ultima_conferencia vêm da última
| conferência concluída (ver Conferencia::concluir()).
*/
#[Table('bem')]
#[Fillable([
    'numero_patrimonio',
    'codigo_interno',
    'tipo_patrimonio_id',
    'sala_id',
    'descricao',
    'marca',
    'origem',
    'quantidade',
    'status',
    'estado_conservacao',
    'data_ultima_mudanca',
    'usuario_ultima_mudanca_id',
    'baixa_data',
    'baixa_observacao',
    'data_ultima_conferencia',
    'resultado_ultima_conferencia',
])]
class Bem extends Model
{
    /** @use HasFactory<BemFactory> */
    use HasFactory;

    // O Laravel lê o padrão do banco só depois de salvar. Repetir aqui faz um
    // bem novo, ainda não salvo, já nascer em uso, com conservação
    // indeterminada e quantidade 1.
    protected $attributes = [
        'status' => 'em_uso',
        'estado_conservacao' => 'indeterminado',
        'quantidade' => 1,
    ];

    /** Relações carregadas na ficha do bem (RF15), para ela sair igual em todas as rotas. */
    public const RELACOES_DA_FICHA = [
        'sala',
        'tipoPatrimonio',
        'usuarioUltimaMudanca',
        'ultimaMovimentacao.salaOrigem',
        'ultimaMovimentacao.salaDestino',
        'ultimaMovimentacao.usuario',
        'itemEmConferenciaAberta.conferencia',
        'pendenciaAberta.conferencia',
        'historicoDeConferencias.conferencia',
        'ocorrencias.usuario',
        'ocorrencias.conferencia',
    ];

    /** Prefixo do código interno: CP de Controle Patrimonial. */
    public const PREFIXO_CODIGO = 'CP-';

    /*
    | O código interno sai do id, que só existe depois de gravar. Por isso o
    | bem é gravado com um código provisório e, logo em seguida, recebe o
    | definitivo. Quem cria o bem deve fazer isso dentro de uma transação,
    | para o provisório nunca ficar no banco se algo falhar no meio.
    | Um código informado na criação, como na carga da planilha, é mantido.
    */
    protected static function booted(): void
    {
        static::creating(function (Bem $bem) {
            if (blank($bem->codigo_interno)) {
                $bem->codigo_interno = 'TMP-'.Str::random(16);
            }
        });

        static::created(function (Bem $bem) {
            if (str_starts_with($bem->codigo_interno, 'TMP-')) {
                $bem->codigo_interno = self::codigoParaId($bem->id);
                $bem->saveQuietly();
            }
        });
    }

    public static function codigoParaId(int $id): string
    {
        return self::PREFIXO_CODIGO.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return BelongsTo<TipoPatrimonio, $this>
     */
    public function tipoPatrimonio(): BelongsTo
    {
        return $this->belongsTo(TipoPatrimonio::class);
    }

    /**
     * @return BelongsTo<Sala, $this>
     */
    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class);
    }

    /**
     * Mudanças de sala do bem, da mais recente para a mais antiga (tarefa 2.8).
     *
     * @return HasMany<Movimentacao, $this>
     */
    public function movimentacoes(): HasMany
    {
        return $this->hasMany(Movimentacao::class)->latest('data')->latest('id');
    }

    /**
     * A mudança de sala mais recente. A ficha usa para mostrar de onde o bem veio.
     *
     * @return HasOne<Movimentacao, $this>
     */
    public function ultimaMovimentacao(): HasOne
    {
        return $this->hasOne(Movimentacao::class)->latestOfMany('id');
    }

    /**
     * Quem fez a última mudança de sala ou de status (RN09).
     *
     * @return BelongsTo<User, $this>
     */
    public function usuarioUltimaMudanca(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_ultima_mudanca_id');
    }

    /**
     * O bem em todas as conferências em que entrou.
     *
     * @return HasMany<ConferenciaItem, $this>
     */
    public function itensDeConferencia(): HasMany
    {
        return $this->hasMany(ConferenciaItem::class);
    }

    /**
     * O bem na conferência aberta em que está, se estiver. Um bem não entra
     * em duas conferências abertas ao mesmo tempo.
     *
     * @return HasOne<ConferenciaItem, $this>
     */
    public function itemEmConferenciaAberta(): HasOne
    {
        return $this->hasOne(ConferenciaItem::class)
            ->whereHas('conferencia', fn ($query) => $query->where('situacao', SituacaoConferencia::Aberta))
            ->orderBy('conferencia_item.id');
    }

    /**
     * Conferências concluídas em que o bem não foi localizado e a pendência
     * ainda não foi resolvida.
     *
     * @return HasMany<ConferenciaItem, $this>
     */
    public function pendenciasAbertas(): HasMany
    {
        return $this->hasMany(ConferenciaItem::class)
            ->where('resultado', ResultadoConferencia::NaoLocalizado)
            ->whereNull('resolvido_em')
            ->whereHas('conferencia', fn ($query) => $query->where('situacao', SituacaoConferencia::Concluida));
    }

    /**
     * A pendência aberta mais antiga: diz desde quando o bem não é localizado.
     *
     * @return HasOne<ConferenciaItem, $this>
     */
    public function pendenciaAberta(): HasOne
    {
        return $this->hasOne(ConferenciaItem::class)
            ->where('resultado', ResultadoConferencia::NaoLocalizado)
            ->whereNull('resolvido_em')
            ->whereHas('conferencia', fn ($query) => $query->where('situacao', SituacaoConferencia::Concluida))
            ->orderBy(self::dataDaConferencia())
            ->orderBy('conferencia_item.id');
    }

    /**
     * O bem nas conferências concluídas, da mais recente para a mais antiga.
     *
     * @return HasMany<ConferenciaItem, $this>
     */
    public function historicoDeConferencias(): HasMany
    {
        return $this->hasMany(ConferenciaItem::class)
            ->whereHas('conferencia', fn ($query) => $query->where('situacao', SituacaoConferencia::Concluida))
            ->orderByDesc(self::dataDaConferencia())
            ->orderByDesc('conferencia_item.id');
    }

    /**
     * Dia em que foi feita a conferência do item, para ordenar pendências e
     * histórico pela data da conferência física, e não pela ordem em que as
     * folhas foram passadas para o sistema.
     *
     * @return Builder<Conferencia>
     */
    private static function dataDaConferencia(): Builder
    {
        return Conferencia::select('realizada_em')->whereColumn('conferencia.id', 'conferencia_item.conferencia_id');
    }

    /**
     * O histórico do bem, da ocorrência mais recente para a mais antiga.
     *
     * @return HasMany<Ocorrencia, $this>
     */
    public function ocorrencias(): HasMany
    {
        return $this->hasMany(Ocorrencia::class)->latest('data')->latest('id');
    }

    public function estaBaixado(): bool
    {
        return $this->status === StatusBem::Baixado;
    }

    /*
    | Mudanças da tarefa 2.8. As regras (RN04, sala ativa, status diferente
    | do atual) são conferidas antes, na validação da requisição. Aqui só se
    | grava a mudança, sempre com a data e o usuário que a fez (RN09).
    */

    /**
     * Muda o bem de sala (RF17) e grava a mudança na tabela movimentacao,
     * com origem, destino, usuário e momento. Quem chama deve usar uma
     * transação, para o bem e o histórico mudarem juntos.
     * $conferenciaId diz de qual conferência veio a mudança, quando não foi
     * um registro manual. Vale o mesmo em alterarStatus e alterarConservacao.
     */
    public function mudarDeSala(Sala $destino, User $usuario, ?int $conferenciaId = null): void
    {
        $this->registrarOcorrencia(TipoOcorrencia::Sala, $this->sala?->nome ?? 'Sem localização', $destino->nome, $usuario, $conferenciaId);

        $this->movimentacoes()->create([
            'sala_origem_id' => $this->sala_id,
            'sala_destino_id' => $destino->id,
            'usuario_id' => $usuario->id,
            'data' => now(),
        ]);

        $this->sala_id = $destino->id;
        $this->unsetRelation('sala');
        $this->registrarMudanca($usuario);
        $this->save();
    }

    /**
     * Altera o status (RF19). Na baixa, grava a data e a observação (RF20).
     * Ao sair de baixado, os dados da baixa são apagados, porque deixam de
     * valer: é o caminho para desfazer uma baixa registrada por engano.
     */
    public function alterarStatus(StatusBem $novo, User $usuario, ?string $baixaData = null, ?string $baixaObservacao = null, ?int $conferenciaId = null): void
    {
        $this->registrarOcorrencia(
            $novo === StatusBem::Baixado ? TipoOcorrencia::Baixa : TipoOcorrencia::Uso,
            $this->status->rotulo(),
            $novo->rotulo(),
            $usuario,
            $conferenciaId,
            $novo === StatusBem::Baixado ? $baixaData : null,
            $novo === StatusBem::Baixado ? $baixaObservacao : null,
        );

        $this->status = $novo;
        $this->baixa_data = $novo === StatusBem::Baixado ? $baixaData : null;
        $this->baixa_observacao = $novo === StatusBem::Baixado ? $baixaObservacao : null;
        $this->registrarMudanca($usuario);
        $this->save();
    }

    /**
     * Altera o estado de conservação e grava a ocorrência. Não conta como
     * mudança de situação: data_ultima_mudanca fica como está.
     */
    public function alterarConservacao(EstadoConservacao $novo, User $usuario, ?int $conferenciaId = null): void
    {
        if ($this->estado_conservacao === $novo) {
            return;
        }

        $this->registrarOcorrencia(TipoOcorrencia::Conservacao, $this->estado_conservacao->rotulo(), $novo->rotulo(), $usuario, $conferenciaId);

        $this->estado_conservacao = $novo;
        $this->save();
    }

    /**
     * Grava uma linha no histórico do bem, com o valor de antes e o de depois.
     */
    private function registrarOcorrencia(TipoOcorrencia $tipo, ?string $anterior, ?string $novo, User $usuario, ?int $conferenciaId = null, ?string $data = null, ?string $observacao = null): void
    {
        $this->ocorrencias()->create([
            'tipo' => $tipo,
            'valor_anterior' => $anterior,
            'valor_novo' => $novo,
            'observacao' => $observacao,
            'data' => $data ?? now()->toDateString(),
            'usuario_id' => $usuario->id,
            'conferencia_id' => $conferenciaId,
        ]);
    }

    /**
     * Grava a data e o resultado da última conferência. Uma conferência de
     * data anterior, concluída depois, não passa por cima da mais recente.
     */
    public function registrarConferencia(ResultadoConferencia $resultado, string $data): void
    {
        if ($this->data_ultima_conferencia !== null && $this->data_ultima_conferencia->toDateString() > $data) {
            return;
        }

        $this->data_ultima_conferencia = $data;
        $this->resultado_ultima_conferencia = $resultado;
    }

    /**
     * Fecha as pendências abertas do bem: porque ele apareceu (localizado) ou
     * porque a gestora abriu o processo administrativo. Com $ate, só fecha as
     * pendências de conferências feitas até aquela data.
     */
    public function resolverPendencias(ResolucaoPendencia $resolucao, User $usuario, ?string $processo = null, ?string $ate = null): void
    {
        $this->pendenciasAbertas()
            ->when($ate, fn ($query) => $query->whereHas('conferencia', fn ($query) => $query->whereDate('realizada_em', '<=', $ate)))
            ->update([
                'resolucao' => $resolucao,
                'processo_numero' => $processo,
                'resolvido_em' => now(),
                'resolvido_por_id' => $usuario->id,
            ]);
    }

    private function registrarMudanca(User $usuario): void
    {
        $this->data_ultima_mudanca = now()->toDateString();
        $this->usuario_ultima_mudanca_id = $usuario->id;
    }

    protected function casts(): array
    {
        return [
            'status' => StatusBem::class,
            'estado_conservacao' => EstadoConservacao::class,
            'quantidade' => 'integer',
            'data_ultima_mudanca' => 'date',
            'baixa_data' => 'date',
            'data_ultima_conferencia' => 'date',
            'resultado_ultima_conferencia' => ResultadoConferencia::class,
        ];
    }
}
