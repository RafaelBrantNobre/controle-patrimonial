<?php

namespace App\Models;

use App\Enums\StatusBem;
use Database\Factories\BemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/*
| O item patrimonial, entidade central do sistema.
| Bem nunca é apagado: a saída do patrimônio ativo é a baixa (RN07).
| Todo bem recebe um codigo_interno gerado pelo sistema (PD07), como
| CP-000123, tenha ou não número de patrimônio.
| As regras de mudança de sala e de status ficam na tarefa 2.8.
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
    'observacao',
    'data_ultima_mudanca',
    'usuario_ultima_mudanca_id',
    'baixa_data',
    'baixa_observacao',
])]
class Bem extends Model
{
    /** @use HasFactory<BemFactory> */
    use HasFactory;

    // O Laravel lê o padrão do banco só depois de salvar. Repetir aqui faz um
    // bem novo, ainda não salvo, já nascer em uso e com quantidade 1.
    protected $attributes = [
        'status' => 'em_uso',
        'quantidade' => 1,
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
     * Quem fez a última mudança de sala ou de status (RN09).
     *
     * @return BelongsTo<User, $this>
     */
    public function usuarioUltimaMudanca(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_ultima_mudanca_id');
    }

    public function estaBaixado(): bool
    {
        return $this->status === StatusBem::Baixado;
    }

    protected function casts(): array
    {
        return [
            'status' => StatusBem::class,
            'quantidade' => 'integer',
            'data_ultima_mudanca' => 'date',
            'baixa_data' => 'date',
        ];
    }
}
