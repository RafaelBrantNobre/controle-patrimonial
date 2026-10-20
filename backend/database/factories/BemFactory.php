<?php

namespace Database\Factories;

use App\Enums\StatusBem;
use App\Models\Bem;
use App\Models\Sala;
use App\Models\TipoPatrimonio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Gera bens falsos para testes. Cria sala e tipo juntos, se não forem informados.
 *
 * @extends Factory<Bem>
 */
class BemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero_patrimonio' => fake()->unique()->numerify('#######-#'),
            // Provisório: a regra definitiva de geração do código fica na tarefa 2.6.
            'codigo_interno' => 'CP-'.fake()->unique()->numerify('#####'),
            'tipo_patrimonio_id' => TipoPatrimonio::factory(),
            'sala_id' => Sala::factory(),
            'descricao' => fake()->randomElement(['azul giratória', 'L cinza', '2 portas', 'preto', null]),
            'marca' => null,
            'origem' => null,
            'quantidade' => 1,
            'status' => StatusBem::EmUso,
            'observacao' => null,
        ];
    }

    /** Bem sem plaqueta (PD07). */
    public function semPlaqueta(): static
    {
        return $this->state(fn () => ['numero_patrimonio' => null]);
    }

    public function avariado(): static
    {
        return $this->state(fn () => ['status' => StatusBem::Avariado]);
    }

    public function baixado(): static
    {
        return $this->state(fn () => [
            'status' => StatusBem::Baixado,
            'baixa_data' => now()->toDateString(),
            'baixa_observacao' => 'Aguardando recolhimento',
        ]);
    }
}
