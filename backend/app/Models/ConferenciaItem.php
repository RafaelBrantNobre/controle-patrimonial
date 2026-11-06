<?php

namespace App\Models;

use App\Enums\EstadoConservacao;
use App\Enums\ResolucaoPendencia;
use App\Enums\ResultadoConferencia;
use Database\Factories\ConferenciaItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
| Um bem dentro de uma conferência, com o que foi visto: se ele foi
| encontrado, em que sala e em que estado.
|
| Quando o bem não é localizado e a conferência é concluída, o item vira
| uma pendência da gestora. Ela fecha a pendência informando que o bem
| apareceu depois ou o número do processo administrativo.
*/
#[Table('conferencia_item')]
#[Fillable([
    'conferencia_id',
    'bem_id',
    'resultado',
    'sala_encontrada_id',
    'estado_conservacao',
    'conferido_em',
    'conferido_por_id',
    'resolucao',
    'processo_numero',
    'resolvido_em',
    'resolvido_por_id',
])]
class ConferenciaItem extends Model
{
    /** @use HasFactory<ConferenciaItemFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Conferencia, $this>
     */
    public function conferencia(): BelongsTo
    {
        return $this->belongsTo(Conferencia::class);
    }

    /**
     * @return BelongsTo<Bem, $this>
     */
    public function bem(): BelongsTo
    {
        return $this->belongsTo(Bem::class);
    }

    /**
     * @return BelongsTo<Sala, $this>
     */
    public function salaEncontrada(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_encontrada_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function conferidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conferido_por_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolvido_por_id');
    }

    /**
     * Grava o resultado lançado a partir da folha. Resultado nulo volta o
     * item a pendente. Bem não localizado não tem sala nem estado vistos.
     */
    public function lancar(?ResultadoConferencia $resultado, ?int $salaId, ?EstadoConservacao $estado, User $usuario): void
    {
        $encontrado = $resultado === ResultadoConferencia::Encontrado;

        $this->resultado = $resultado;
        $this->sala_encontrada_id = $encontrado ? ($salaId ?? $this->bem->sala_id) : null;
        $this->estado_conservacao = $encontrado ? $estado : null;
        $this->conferido_em = $resultado ? now() : null;
        $this->conferido_por_id = $resultado ? $usuario->id : null;
        $this->save();
    }

    /**
     * Aplica o resultado ao bem, na conclusão da conferência.
     * Encontrado em outra sala vira mudança de sala, com histórico (RN09).
     * O estado visto atualiza a conservação, sem contar como mudança de
     * situação. Encontrar o bem fecha as pendências antigas dele.
     */
    public function aplicarAoBem(string $realizadaEm, User $usuario): void
    {
        $bem = $this->bem;

        if ($this->resultado === ResultadoConferencia::Encontrado) {
            if ($this->sala_encontrada_id !== null && (int) $this->sala_encontrada_id !== (int) $bem->sala_id) {
                $bem->mudarDeSala($this->salaEncontrada, $usuario);
            }

            if ($this->estado_conservacao !== null) {
                $bem->estado_conservacao = $this->estado_conservacao;
            }

            $bem->resolverPendencias(ResolucaoPendencia::Localizado, $usuario, ate: $realizadaEm);
        }

        $bem->registrarConferencia($this->resultado, $realizadaEm);
        $bem->save();

        // Não localizado numa conferência de data anterior à última em que o
        // bem foi encontrado: ele já apareceu, então não há pendência.
        if ($this->resultado === ResultadoConferencia::NaoLocalizado
            && $bem->resultado_ultima_conferencia === ResultadoConferencia::Encontrado
            && $bem->data_ultima_conferencia->toDateString() > $realizadaEm) {
            $this->update([
                'resolucao' => ResolucaoPendencia::Localizado,
                'resolvido_em' => now(),
                'resolvido_por_id' => $usuario->id,
            ]);
        }
    }

    protected function casts(): array
    {
        return [
            'resultado' => ResultadoConferencia::class,
            'estado_conservacao' => EstadoConservacao::class,
            'resolucao' => ResolucaoPendencia::class,
            'conferido_em' => 'datetime',
            'resolvido_em' => 'datetime',
        ];
    }
}
