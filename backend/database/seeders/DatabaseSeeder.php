<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Ambientes em que os usuários de teste podem existir. */
    public const AMBIENTES_DE_TESTE = ['local', 'testing'];

    /**
     * Popula o banco de desenvolvimento.
     *
     * Os usuários de teste têm senha conhecida (password) e por isso só são
     * criados nos ambientes da lista acima, conferidos pelo APP_ENV do .env.
     * Em produção este seeder não cria nada: o primeiro administrador é
     * criado com php artisan usuario:criar-admin, que pede a senha na hora.
     */
    public function run(): void
    {
        if (! app()->environment(self::AMBIENTES_DE_TESTE)) {
            $this->command?->warn('Ambiente "'.app()->environment().'": nenhum usuário de teste foi criado. Para criar o primeiro administrador, use php artisan usuario:criar-admin.');

            return;
        }

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
