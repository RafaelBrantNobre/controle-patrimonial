<?php

namespace Database\Factories;

use App\Enums\PerfilUsuario;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * Gera usuários falsos para testes.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $senha;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'senha' => static::$senha ??= Hash::make('password'),
            'perfil' => PerfilUsuario::Consulta,
            'ativo' => true,
        ];
    }

    public function administrador(): static
    {
        return $this->state(fn () => ['perfil' => PerfilUsuario::Administrador]);
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }
}
