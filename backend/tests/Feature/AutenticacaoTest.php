<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;

/*
| Testes da autenticação e do controle de acesso (tarefa 2.4).
| RF01 login, RF02 logout, RF03 perfis, RN10 consulta não altera,
| RNF04 senha nunca exposta, RNF05 token com validade e revogação.
*/

uses(RefreshDatabase::class);

// Rotas só de teste, uma para cada grupo de acesso de routes/api.php.
beforeEach(function () {
    Route::middleware(['api', 'auth:sanctum', 'ativo'])->prefix('api')->group(function () {
        Route::get('/teste/leitura', fn () => ['ok' => true]);
        Route::post('/teste/alteracao', fn () => ['ok' => true])->middleware('perfil:administrador');
    });
});

function entrar(string $email, string $senha = 'password')
{
    return test()->postJson('/api/login', ['email' => $email, 'senha' => $senha]);
}

// --- RF01 · login -----------------------------------------------------------

test('o usuário entra com e-mail e senha e recebe um token', function () {
    $usuario = User::factory()->administrador()->create(['email' => 'admin@cead.br']);

    entrar('admin@cead.br')
        ->assertOk()
        ->assertJsonStructure(['token', 'tipo', 'expira_em', 'usuario' => ['id', 'nome', 'email', 'perfil', 'perfil_rotulo', 'pode_alterar']])
        ->assertJsonPath('tipo', 'Bearer')
        ->assertJsonPath('usuario.id', $usuario->id)
        ->assertJsonPath('usuario.perfil', 'administrador')
        ->assertJsonPath('usuario.pode_alterar', true);

    expect($usuario->tokens()->count())->toBe(1);
});

test('a resposta do login nunca traz a senha', function () {
    User::factory()->create(['email' => 'consulta@cead.br']);

    $resposta = entrar('consulta@cead.br');

    expect($resposta->getContent())->not->toContain('senha');
});

test('senha errada e e-mail inexistente recebem a mesma mensagem', function () {
    User::factory()->create(['email' => 'admin@cead.br']);

    $senhaErrada = entrar('admin@cead.br', 'outra-senha')->assertUnprocessable();
    $emailInexistente = entrar('ninguem@cead.br')->assertUnprocessable();

    expect($senhaErrada->json('errors.email.0'))
        ->toBe('E-mail ou senha incorretos. Confira os dados e tente de novo.')
        ->toBe($emailInexistente->json('errors.email.0'));
});

test('o login exige e-mail e senha, com mensagens em português', function () {
    $this->postJson('/api/login', [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'Informe o seu e-mail.')
        ->assertJsonPath('errors.senha.0', 'Informe a sua senha.');
});

test('usuário desativado não consegue entrar', function () {
    $usuario = User::factory()->inativo()->create(['email' => 'antigo@cead.br']);

    entrar('antigo@cead.br')->assertForbidden();

    expect($usuario->tokens()->count())->toBe(0);
});

test('depois de 5 tentativas erradas em um minuto o login é bloqueado', function () {
    User::factory()->create(['email' => 'admin@cead.br']);

    foreach (range(1, 5) as $tentativa) {
        entrar('admin@cead.br', 'errada')->assertUnprocessable();
    }

    entrar('admin@cead.br')->assertTooManyRequests();
});

// --- Token: uso, validade e revogação (RNF05) -------------------------------

test('com o token o usuário acessa as rotas protegidas', function () {
    User::factory()->create(['email' => 'consulta@cead.br']);
    $token = entrar('consulta@cead.br')->json('token');

    $this->withToken($token)->getJson('/api/usuario')
        ->assertOk()
        ->assertJsonPath('usuario.email', 'consulta@cead.br');
});

test('sem token as rotas protegidas respondem 401 com mensagem clara', function () {
    $this->getJson('/api/usuario')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Você não está conectado ou sua sessão expirou. Entre de novo para continuar.');
});

test('o token vence depois do tempo configurado', function () {
    config(['sanctum.expiration' => 60]);
    User::factory()->create(['email' => 'consulta@cead.br']);
    $token = entrar('consulta@cead.br')->json('token');

    $this->travel(61)->minutes();

    $this->withToken($token)->getJson('/api/usuario')->assertUnauthorized();
});

// --- RF02 · logout ----------------------------------------------------------

test('o logout apaga o token, que deixa de funcionar', function () {
    $usuario = User::factory()->create(['email' => 'consulta@cead.br']);
    $token = entrar('consulta@cead.br')->json('token');

    $this->withToken($token)->postJson('/api/logout')->assertOk();

    expect($usuario->tokens()->count())->toBe(0);

    // Limpa o usuário guardado pelo guard, como acontece entre duas requisições reais.
    $this->app['auth']->forgetGuards();

    $this->withToken($token)->getJson('/api/usuario')->assertUnauthorized();
});

test('o logout num computador não derruba o login em outro', function () {
    $usuario = User::factory()->create(['email' => 'consulta@cead.br']);
    $tokenA = entrar('consulta@cead.br')->json('token');
    entrar('consulta@cead.br');

    $this->withToken($tokenA)->postJson('/api/logout')->assertOk();

    expect($usuario->tokens()->count())->toBe(1);
});

// --- Usuário desativado com o login aberto ----------------------------------

test('usuário desativado depois de entrar é barrado e perde o token', function () {
    $usuario = User::factory()->create(['email' => 'consulta@cead.br']);
    $token = entrar('consulta@cead.br')->json('token');

    $usuario->update(['ativo' => false]);

    $this->withToken($token)->getJson('/api/teste/leitura')->assertForbidden();

    expect(PersonalAccessToken::count())->toBe(0);
});

// --- RF03 e RN10 · perfis -----------------------------------------------------

test('o perfil consulta lê mas não altera', function () {
    User::factory()->create(['email' => 'consulta@cead.br']);
    $token = entrar('consulta@cead.br')->json('token');

    $this->withToken($token)->getJson('/api/teste/leitura')->assertOk();

    $this->withToken($token)->postJson('/api/teste/alteracao')
        ->assertForbidden()
        ->assertJsonPath('message', 'Seu perfil não permite esta ação. Se precisar dela, procure a gestora do setor.');
});

test('o perfil administrador lê e altera', function () {
    User::factory()->administrador()->create(['email' => 'admin@cead.br']);
    $token = entrar('admin@cead.br')->json('token');

    $this->withToken($token)->getJson('/api/teste/leitura')->assertOk();
    $this->withToken($token)->postJson('/api/teste/alteracao')->assertOk();
});

test('a troca de perfil vale na hora, sem novo login', function () {
    $usuario = User::factory()->administrador()->create(['email' => 'admin@cead.br']);
    $token = entrar('admin@cead.br')->json('token');

    $usuario->update(['perfil' => 'consulta']);

    $this->withToken($token)->postJson('/api/teste/alteracao')->assertForbidden();
});
