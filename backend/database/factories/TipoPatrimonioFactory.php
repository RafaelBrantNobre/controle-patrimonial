<?php

namespace Database\Factories;

use App\Models\TipoPatrimonio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Gera tipos de patrimônio falsos para testes.
 *
 * @extends Factory<TipoPatrimonio>
 */
class TipoPatrimonioFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->randomElement([
                'Computador', 'Monitor', 'Cadeira', 'Mesa', 'Armário',
                'Ar condicionado', 'Bebedouro', 'Datashow', 'Impressora', 'Estabilizador',
            ]).' '.fake()->unique()->numerify('###'),
            'ativo' => true,
        ];
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }
}
