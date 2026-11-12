<?php

namespace Database\Factories;

use App\Enums\SituacaoConferencia;
use App\Models\Conferencia;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Gera conferências falsas para testes. Cria quem gerou junto, se não for informado.
 *
 * @extends Factory<Conferencia>
 */
class ConferenciaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => 'Conferência '.fake()->unique()->numerify('###'),
            'situacao' => SituacaoConferencia::Aberta,
            'criada_por_id' => User::factory()->administrador(),
        ];
    }

    public function concluida(string $realizadaEm = '2026-10-01'): static
    {
        return $this->state(fn (array $atributos) => [
            'situacao' => SituacaoConferencia::Concluida,
            'realizada_em' => $realizadaEm,
            'concluida_em' => now(),
            'concluida_por_id' => $atributos['criada_por_id'],
        ]);
    }

    public function cancelada(): static
    {
        return $this->state(fn () => ['situacao' => SituacaoConferencia::Cancelada]);
    }
}
