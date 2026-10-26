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
        // Do 10º andar em diante, para nunca repetir os nomes que os testes
        // usam de propósito, como "Térreo", "1º andar" e "2º andar". O nome
        // do andar é único, e a repetição fazia testes falharem ao acaso.
        $ordem = fake()->unique()->numberBetween(10, 99);

        return [
            'nome' => "{$ordem}º andar",
            'ordem' => $ordem,
            'ativo' => true,
        ];
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }
}
