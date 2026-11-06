<?php

namespace App\Models;

use App\Enums\ResultadoConferencia;
use App\Enums\SituacaoConferencia;
use Database\Factories\ConferenciaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/*
| Uma conferência dos bens: os bens escolhidos na tela, conferidos na folha
| em papel e lançados no sistema.
|
| Enquanto está aberta, os resultados ficam só nos itens e nada muda nos
| bens. Ao concluir, cada resultado é aplicado ao bem de uma vez: a sala
| onde ele estava, o estado visto e a data e o resultado da última
| conferência. Cancelar não muda nada nos bens.
*/
#[Table('conferencia')]
#[Fillable(['nome', 'situacao', 'realizada_em', 'criada_por_id', 'concluida_em', 'concluida_por_id'])]
class Conferencia extends Model
{
    /** @use HasFactory<ConferenciaFactory> */
    use HasFactory;

    protected $attributes = [
        'situacao' => 'aberta',
    ];

    /**
     * Contagens que as telas mostram: total de itens, pendentes, encontrados
     * e não localizados. Serve para withCount() e loadCount().
     *
     * @return array<int|string, mixed>
     */
    public static function contagens(): array
    {
        return [
            'itens',
            'itens as pendentes_count' => fn ($query) => $query->whereNull('resultado'),
            'itens as encontrados_count' => fn ($query) => $query->where('resultado', ResultadoConferencia::Encontrado),
            'itens as nao_localizados_count' => fn ($query) => $query->where('resultado', ResultadoConferencia::NaoLocalizado),
        ];
    }

    /**
     * @return HasMany<ConferenciaItem, $this>
     */
    public function itens(): HasMany
    {
        return $this->hasMany(ConferenciaItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function criadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criada_por_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function concluidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'concluida_por_id');
    }

    public function estaAberta(): bool
    {
        return $this->situacao === SituacaoConferencia::Aberta;
    }

    #[Scope]
    protected function abertas(Builder $query): void
    {
        $query->where('situacao', SituacaoConferencia::Aberta);
    }

    #[Scope]
    protected function concluidas(Builder $query): void
    {
        $query->where('situacao', SituacaoConferencia::Concluida);
    }

    /**
     * O que impede concluir: bens ainda sem resultado, bem encontrado numa
     * sala inativa e bem baixado encontrado em outra sala (RN04). As salas e
     * a baixa podem ter mudado depois do lançamento, por isso a conferência
     * olha de novo na hora de concluir.
     *
     * @return list<string>
     */
    public function problemasParaConcluir(): array
    {
        $problemas = [];

        $pendentes = $this->itens()->whereNull('resultado')->count();
        if ($pendentes > 0) {
            $problemas[] = $pendentes === 1
                ? 'Ainda há 1 bem sem resultado. Lance se ele foi encontrado ou não localizado antes de concluir.'
                : "Ainda há {$pendentes} bens sem resultado. Lance se cada um foi encontrado ou não localizado antes de concluir.";
        }

        $mudancas = $this->itens()
            ->where('resultado', ResultadoConferencia::Encontrado)
            ->whereNotNull('sala_encontrada_id')
            ->with(['bem', 'salaEncontrada'])
            ->get()
            ->filter(fn (ConferenciaItem $item) => (int) $item->sala_encontrada_id !== (int) $item->bem->sala_id);

        foreach ($mudancas as $item) {
            $bem = $item->bem->numero_patrimonio ?? $item->bem->codigo_interno;

            if ($item->bem->estaBaixado()) {
                $problemas[] = "O bem {$bem} está baixado e não muda de sala. Lance a sala onde ele está registrado ou desfaça a baixa antes.";
            } elseif (! $item->salaEncontrada->ativo) {
                $problemas[] = "O bem {$bem} foi encontrado na sala {$item->salaEncontrada->nome}, que está inativa. Reative a sala ou corrija o lançamento.";
            }
        }

        return $problemas;
    }

    /**
     * Aplica os resultados aos bens e fecha a conferência. Quem chama confere
     * antes problemasParaConcluir() e usa uma transação, para os bens e a
     * conferência mudarem juntos.
     */
    public function concluir(string $realizadaEm, User $usuario): void
    {
        $this->itens()
            ->with(['bem', 'salaEncontrada'])
            ->chunkById(200, function ($itens) use ($realizadaEm, $usuario) {
                foreach ($itens as $item) {
                    $item->aplicarAoBem($realizadaEm, $usuario);
                }
            });

        $this->situacao = SituacaoConferencia::Concluida;
        $this->realizada_em = $realizadaEm;
        $this->concluida_em = now();
        $this->concluida_por_id = $usuario->id;
        $this->save();
    }

    protected function casts(): array
    {
        return [
            'situacao' => SituacaoConferencia::class,
            'realizada_em' => 'date',
            'concluida_em' => 'datetime',
        ];
    }
}
