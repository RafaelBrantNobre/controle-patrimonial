<?php

namespace App\Models;

use Database\Factories\SalaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/*
| Ambiente dentro de um andar. É onde o bem fica.
*/
#[Table('sala')]
#[Fillable(['andar_id', 'nome', 'descricao', 'ativo'])]
class Sala extends Model
{
    /** @use HasFactory<SalaFactory> */
    use HasFactory;

    // O Laravel lê o padrão do banco só depois de salvar. Repetir aqui faz a
    // sala recém-cadastrada já sair ativa na resposta da API.
    protected $attributes = [
        'ativo' => true,
    ];

    /**
     * @return BelongsTo<Andar, $this>
     */
    public function andar(): BelongsTo
    {
        return $this->belongsTo(Andar::class);
    }

    /**
     * @return HasMany<Bem, $this>
     */
    public function bens(): HasMany
    {
        return $this->hasMany(Bem::class);
    }

    /** Se algum bem já entrou ou saiu desta sala por uma mudança (tarefa 2.8). */
    public function apareceNoHistorico(): bool
    {
        return Movimentacao::where('sala_origem_id', $this->id)
            ->orWhere('sala_destino_id', $this->id)
            ->exists();
    }

    #[Scope]
    protected function ativas(Builder $query): void
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
