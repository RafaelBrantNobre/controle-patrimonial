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
        // Administrador de teste, só para o ambiente local (senha: password).
        User::factory()->administrador()->create([
            'nome' => 'Administrador de Teste',
            'email' => 'admin@example.com',
        ]);
    }
}
