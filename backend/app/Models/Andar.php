<?php

namespace App\Models;

use Database\Factories\AndarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/*
| Pavimento do prédio do CEAD. Um andar tem várias salas.
*/
#[Table('andar')]
#[Fillable(['nome', 'ordem', 'ativo'])]
class Andar extends Model
{
    /** @use HasFactory<AndarFactory> */
    use HasFactory;

    // O Laravel lê o padrão do banco só depois de salvar. Repetir aqui faz o
    // andar recém-cadastrado já sair ativo e com ordem 0 na resposta da API.
    protected $attributes = [
        'ordem' => 0,
        'ativo' => true,
    ];

    /**
     * @return HasMany<Sala, $this>
     */
    public function salas(): HasMany
    {
        return $this->hasMany(Sala::class);
    }

    /** Só os andares em uso. Uso: Andar::ativos()->get() */
    #[Scope]
    protected function ativos(Builder $query): void
    {
        $query->where('ativo', true);
    }

    /** Na ordem do relatório (campo ordem), e não em ordem alfabética. */
    #[Scope]
    protected function ordenados(Builder $query): void
    {
        $query->orderBy('ordem')->orderBy('nome');
    }

    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
            'ativo' => 'boolean',
        ];
    }
}
