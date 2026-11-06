<?php

namespace Database\Factories;

use App\Enums\ResultadoConferencia;
use App\Models\Bem;
use App\Models\Conferencia;
use App\Models\ConferenciaItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Gera bens dentro de conferências, para testes. Sem resultado, o item fica pendente.
 *
 * @extends Factory<ConferenciaItem>
 */
class ConferenciaItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conferencia_id' => Conferencia::factory(),
            'bem_id' => Bem::factory(),
        ];
    }

    public function naoLocalizado(): static
    {
        return $this->state(fn () => [
            'resultado' => ResultadoConferencia::NaoLocalizado,
            'conferido_em' => now(),
        ]);
    }
}
