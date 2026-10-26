<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
| Uma mudança de sala de um bem (tarefa 2.8, página 03, seção 11).
| É gravada por Bem::mudarDeSala() e não é editada depois: é histórico.
*/
#[Table('movimentacao')]
#[Fillable(['bem_id', 'sala_origem_id', 'sala_destino_id', 'usuario_id', 'data'])]
class Movimentacao extends Model
{
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
    public function salaOrigem(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_origem_id');
    }

    /**
     * @return BelongsTo<Sala, $this>
     */
    public function salaDestino(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_destino_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    protected function casts(): array
    {
        return [
            'data' => 'datetime',
        ];
    }
}
