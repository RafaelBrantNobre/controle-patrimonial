<?php

namespace Database\Factories;

use App\Models\Andar;
use App\Models\Sala;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Gera salas falsas para testes. Cria o andar junto, se nenhum for informado.
 *
 * @extends Factory<Sala>
 */
class SalaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'andar_id' => Andar::factory(),
            'nome' => 'Sala '.fake()->unique()->numerify('###'),
            'descricao' => null,
            'ativo' => true,
        ];
    }

    public function inativa(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }
}
