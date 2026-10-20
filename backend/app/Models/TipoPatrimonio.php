<?php

namespace App\Models;

use Database\Factories\TipoPatrimonioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/*
| Categoria do bem: computador, cadeira, mesa, ar condicionado...
*/
#[Table('tipo_patrimonio')]
#[Fillable(['nome', 'ativo'])]
class TipoPatrimonio extends Model
{
    /** @use HasFactory<TipoPatrimonioFactory> */
    use HasFactory;

    /**
     * @return HasMany<Bem, $this>
     */
    public function bens(): HasMany
    {
        return $this->hasMany(Bem::class);
    }

    #[Scope]
    protected function ativos(Builder $query): void
    {
        $query->where('ativo', true);
    }

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }
}
