<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Popula o banco local de desenvolvimento.
     */
    public function run(): void
    {
        // Usuários de teste, só para o ambiente local. Senha dos dois: password.
        User::factory()->administrador()->create([
            'nome' => 'Administrador de Teste',
            'email' => 'admin@example.com',
        ]);

        User::factory()->create([
            'nome' => 'Consulta de Teste',
            'email' => 'consulta@example.com',
        ]);
    }
}
