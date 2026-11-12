<?php

namespace App\Models;

use App\Enums\TipoOcorrencia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
| Um acontecimento na vida de um bem: mudou de sala, mudou o estado de
| conservação, mudou a condição de uso ou foi baixado. Guarda o valor de
| antes e o de depois em texto, a data, quem registrou e a conferência de
| onde veio, quando não foi um registro manual.
| As ocorrências são gravadas pelo próprio Bem (mudarDeSala, alterarStatus
| e alterarConservacao), para nenhuma mudança ficar sem histórico.
*/
#[Table('ocorrencia')]
#[Fillable(['bem_id', 'tipo', 'valor_anterior', 'valor_novo', 'observacao', 'data', 'usuario_id', 'conferencia_id'])]
class Ocorrencia extends Model
{
    /**
     * @return BelongsTo<Bem, $this>
     */
    public function bem(): BelongsTo
    {
        return $this->belongsTo(Bem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * @return BelongsTo<Conferencia, $this>
     */
    public function conferencia(): BelongsTo
    {
        return $this->belongsTo(Conferencia::class);
    }

    protected function casts(): array
    {
        return [
            'tipo' => TipoOcorrencia::class,
            'data' => 'date',
        ];
    }
}
