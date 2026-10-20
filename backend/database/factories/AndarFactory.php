<?php

namespace Database\Factories;

use App\Models\Andar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Gera andares falsos para testes.
 *
 * @extends Factory<Andar>
 */
class AndarFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ordem = fake()->unique()->numberBetween(0, 50);

        return [
            'nome' => $ordem === 0 ? 'Térreo' : "{$ordem}º andar",
            'ordem' => $ordem,
            'ativo' => true,
        ];
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }
}
