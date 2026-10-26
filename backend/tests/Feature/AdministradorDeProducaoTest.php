<?php

use App\Enums\PerfilUsuario;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/*
| Preparação para produção: usuários de teste só no ambiente local, e o
| primeiro administrador criado por comando, sem senha no código (RNF04).
*/

uses(RefreshDatabase::class);

// --- Seeder -----------------------------------------------------------------

test('no ambiente local o seeder cria os dois usuários de teste', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::pluck('email')->sort()->values()->all())->toBe(['admin@example.com', 'consulta@example.com']);
});

test('em produção o seeder não cria nenhum usuário', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('db:seed', ['--force' => true])
        ->expectsOutputToContain('nenhum usuário de teste foi criado')
        ->assertSuccessful();

    expect(User::count())->toBe(0);
});

// --- Comando usuario:criar-admin --------------------------------------------

test('o comando cria um administrador com a senha guardada em hash', function () {
    $this->artisan('usuario:criar-admin')
        ->expectsQuestion('Nome', 'Christine')
        ->expectsQuestion('E-mail', 'Christine@Unimontes.br')
        ->expectsQuestion('Senha (pelo menos 10 caracteres, com letras e números)', 'segredo2026x')
        ->expectsQuestion('Repita a senha', 'segredo2026x')
        ->expectsOutput('Administrador Christine (christine@unimontes.br) criado. Ele já pode entrar no sistema.')
        ->assertSuccessful();

    $usuario = User::sole();
    expect($usuario->email)->toBe('christine@unimontes.br')
        ->and($usuario->perfil)->toBe(PerfilUsuario::Administrador)
        ->and($usuario->ativo)->toBeTrue()
        ->and($usuario->senha)->not->toBe('segredo2026x')
        ->and(Hash::check('segredo2026x', $usuario->senha))->toBeTrue();
});

test('o administrador criado pelo comando consegue entrar', function () {
    $this->artisan('usuario:criar-admin', ['--nome' => 'Christine', '--email' => 'christine@unimontes.br'])
        ->expectsQuestion('Senha (pelo menos 10 caracteres, com letras e números)', 'segredo2026x')
        ->expectsQuestion('Repita a senha', 'segredo2026x')
        ->assertSuccessful();

    $this->postJson('/api/login', ['email' => 'christine@unimontes.br', 'senha' => 'segredo2026x'])
        ->assertOk()
        ->assertJsonPath('usuario.perfil', 'administrador');
});

test('senhas diferentes não criam ninguém', function () {
    $this->artisan('usuario:criar-admin', ['--nome' => 'Christine', '--email' => 'christine@unimontes.br'])
        ->expectsQuestion('Senha (pelo menos 10 caracteres, com letras e números)', 'segredo2026x')
        ->expectsQuestion('Repita a senha', 'segredo2026y')
        ->expectsOutput('As duas senhas não são iguais. Nada foi criado.')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

test('senha fraca é recusada com o motivo', function (string $senha, string $motivo) {
    $this->artisan('usuario:criar-admin', ['--nome' => 'Christine', '--email' => 'christine@unimontes.br'])
        ->expectsQuestion('Senha (pelo menos 10 caracteres, com letras e números)', $senha)
        ->expectsQuestion('Repita a senha', $senha)
        ->expectsOutput($motivo)
        ->assertFailed();

    expect(User::count())->toBe(0);
})->with([
    'curta' => ['abc123', 'A senha precisa ter pelo menos 10 caracteres.'],
    'sem número' => ['senhasemnumero', 'A senha precisa ter pelo menos um número.'],
    'sem letra' => ['1234567890', 'A senha precisa ter pelo menos uma letra.'],
]);

test('e-mail repetido ou inválido é recusado antes de pedir a senha', function () {
    User::factory()->create(['email' => 'christine@unimontes.br']);

    $this->artisan('usuario:criar-admin', ['--nome' => 'Christine', '--email' => 'christine@unimontes.br'])
        ->expectsOutput('Já existe um usuário com este e-mail.')
        ->assertFailed();

    $this->artisan('usuario:criar-admin', ['--nome' => 'Christine', '--email' => 'christine'])
        ->expectsOutput('Informe um e-mail válido, como nome@unimontes.br.')
        ->assertFailed();

    expect(User::count())->toBe(1);
});

test('o comando não roda sem terminal, porque precisa pedir a senha', function () {
    $this->artisan('usuario:criar-admin', ['--nome' => 'Christine', '--email' => 'christine@unimontes.br', '--no-interaction' => true])
        ->assertFailed();

    expect(User::count())->toBe(0);
});
