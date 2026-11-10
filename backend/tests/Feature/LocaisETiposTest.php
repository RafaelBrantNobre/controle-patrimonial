<?php

use App\Models\Bem;
use App\Models\Sala;
use App\Models\TipoPatrimonio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/*
| Testes dos cadastros de salas e tipos de patrimônio (tarefa 2.5).
| RF05 a RF09 cadastrar, editar e inativar, RN08 não excluir o que tem
| vínculo, RN10 consulta não altera, RNF03 listagem paginada, RNF08
| mensagens em português.
*/

uses(RefreshDatabase::class);

function comoAdministrador(): User
{
    return Sanctum::actingAs(User::factory()->administrador()->create());
}

function comoConsulta(): User
{
    return Sanctum::actingAs(User::factory()->create());
}

// --- Acesso (RN10) ----------------------------------------------------------

test('sem login nenhuma rota de locais e tipos responde', function () {
    $this->getJson('/api/salas')->assertUnauthorized();
    $this->getJson('/api/tipos-patrimonio')->assertUnauthorized();
});

test('o perfil consulta lista e vê, mas não cadastra, edita nem exclui', function () {
    comoConsulta();
    $sala = Sala::factory()->create();
    $tipo = TipoPatrimonio::factory()->create();

    $this->getJson('/api/salas')->assertOk();
    $this->getJson("/api/salas/{$sala->id}")->assertOk();
    $this->getJson('/api/tipos-patrimonio')->assertOk();

    $this->postJson('/api/salas', ['nome' => 'Recepção'])->assertForbidden();
    $this->patchJson("/api/salas/{$sala->id}", ['ativo' => false])->assertForbidden();
    $this->deleteJson("/api/tipos-patrimonio/{$tipo->id}")->assertForbidden();
    $this->deleteJson("/api/salas/{$sala->id}")->assertForbidden();

    expect(Sala::count())->toBe(1)
        ->and($sala->fresh()->ativo)->toBeTrue()
        ->and(TipoPatrimonio::count())->toBe(1);
});

test('as rotas de andares não existem mais', function () {
    comoAdministrador();

    $this->getJson('/api/andares')->assertNotFound();
    $this->postJson('/api/andares', ['nome' => 'Térreo'])->assertNotFound();
});

test('registro inexistente responde 404 com mensagem em português', function () {
    comoConsulta();

    $this->getJson('/api/salas/999')
        ->assertNotFound()
        ->assertJsonPath('message', 'O registro procurado não foi encontrado. Ele pode ter sido excluído. Atualize a página e tente de novo.');
});

// --- Salas (RF06, RF07) -----------------------------------------------------

test('o administrador cadastra uma sala', function () {
    comoAdministrador();

    $this->postJson('/api/salas', ['nome' => 'Sala 05 - Suporte TI'])
        ->assertCreated()
        ->assertJsonPath('data.nome', 'Sala 05 - Suporte TI')
        ->assertJsonPath('data.descricao', null)
        ->assertJsonPath('data.ativo', true)
        ->assertJsonPath('data.bens_count', 0)
        ->assertJsonMissingPath('data.andar_id');
});

test('a sala exige nome, com mensagem em português', function () {
    comoAdministrador();

    $this->postJson('/api/salas', [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.nome.0', 'Informe o nome da sala.');
});

test('o nome da sala não se repete, mas a sala pode ser editada mantendo o próprio nome', function () {
    comoAdministrador();
    $sala = Sala::factory()->create(['nome' => 'Sala 05']);

    $this->postJson('/api/salas', ['nome' => 'Sala 05'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.nome.0', 'Já existe uma sala com este nome.');

    $this->putJson("/api/salas/{$sala->id}", ['nome' => 'Sala 05', 'descricao' => 'Suporte'])
        ->assertOk()
        ->assertJsonPath('data.descricao', 'Suporte');
});

test('a sala pode ser inativada e reativada', function () {
    comoAdministrador();
    $sala = Sala::factory()->create();

    $this->patchJson("/api/salas/{$sala->id}", ['ativo' => false])->assertOk()->assertJsonPath('data.ativo', false);
    $this->patchJson("/api/salas/{$sala->id}", ['ativo' => true])->assertOk()->assertJsonPath('data.ativo', true);
});

test('a lista de salas filtra por nome e situação, e sai em ordem de nome', function () {
    comoConsulta();
    Sala::factory()->create(['nome' => 'Sala 05 - Suporte TI']);
    Sala::factory()->create(['nome' => 'Recepção']);
    Sala::factory()->inativa()->create(['nome' => 'Sala 06 - Arquivo']);

    expect(collect($this->getJson('/api/salas')->json('data'))->pluck('nome')->all())->toBe(['Recepção', 'Sala 05 - Suporte TI', 'Sala 06 - Arquivo'])
        ->and($this->getJson('/api/salas?busca=suporte')->json('data.0.nome'))->toBe('Sala 05 - Suporte TI')
        ->and($this->getJson('/api/salas?ativo=0')->json('data.0.nome'))->toBe('Sala 06 - Arquivo')
        ->and($this->getJson('/api/salas?ativo=1')->json('meta.total'))->toBe(2);
});

test('sala sem bens é excluída, sala com bens não (RN08)', function () {
    comoAdministrador();
    $vazia = Sala::factory()->create();
    $comBem = Bem::factory()->create()->sala;

    $this->deleteJson("/api/salas/{$vazia->id}")->assertOk()->assertJsonPath('message', 'Sala excluída.');
    $this->deleteJson("/api/salas/{$comBem->id}")
        ->assertConflict()
        ->assertJsonPath('message', 'Esta sala tem bens cadastrados e não pode ser excluída. Se ela não é mais usada, inative-a.');

    expect(Sala::find($comBem->id))->not->toBeNull();
});

// --- Tipos de patrimônio (RF08, RF09) ---------------------------------------

test('o administrador cadastra, edita e inativa um tipo de patrimônio', function () {
    comoAdministrador();

    $id = $this->postJson('/api/tipos-patrimonio', ['nome' => 'Bebedouro'])
        ->assertCreated()
        ->assertJsonPath('data.ativo', true)
        ->json('data.id');

    $this->putJson("/api/tipos-patrimonio/{$id}", ['nome' => 'Bebedouro elétrico'])
        ->assertOk()
        ->assertJsonPath('data.nome', 'Bebedouro elétrico');

    $this->patchJson("/api/tipos-patrimonio/{$id}", ['ativo' => false])
        ->assertOk()
        ->assertJsonPath('data.ativo', false)
        ->assertJsonPath('data.nome', 'Bebedouro elétrico');
});

// Os testes rodam em SQLite, que diferencia maiúsculas. No MySQL, "cadeira"
// também é recusado, porque o banco compara sem diferenciar caixa e acento.
test('o nome do tipo não se repete', function () {
    comoAdministrador();
    TipoPatrimonio::factory()->create(['nome' => 'Cadeira']);

    $this->postJson('/api/tipos-patrimonio', ['nome' => 'Cadeira'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.nome.0', 'Já existe um tipo de patrimônio com este nome.');
});

test('tipo sem bens é excluído, tipo com bens não (RN08)', function () {
    comoAdministrador();
    $vazio = TipoPatrimonio::factory()->create();
    $comBem = Bem::factory()->create()->tipoPatrimonio;

    $this->deleteJson("/api/tipos-patrimonio/{$vazio->id}")->assertOk();
    $this->deleteJson("/api/tipos-patrimonio/{$comBem->id}")
        ->assertConflict()
        ->assertJsonPath('message', 'Este tipo tem bens cadastrados e não pode ser excluído. Se ele não é mais usado, inative-o.');

    expect(TipoPatrimonio::find($comBem->id))->not->toBeNull();
});

test('a lista de tipos busca por nome e conta os bens de cada um', function () {
    comoConsulta();
    $computador = TipoPatrimonio::factory()->create(['nome' => 'Computador']);
    TipoPatrimonio::factory()->create(['nome' => 'Cadeira']);
    Bem::factory()->count(3)->for($computador, 'tipoPatrimonio')->create();

    $this->getJson('/api/tipos-patrimonio?busca=comp')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.nome', 'Computador')
        ->assertJsonPath('data.0.bens_count', 3);
});

test('o % digitado na busca vale como texto, e não como curinga', function () {
    comoConsulta();
    TipoPatrimonio::factory()->create(['nome' => 'Computador']);

    $this->getJson('/api/tipos-patrimonio?busca=%')->assertJsonPath('meta.total', 0);
});

// --- Paginação (RNF03) ------------------------------------------------------

test('as listagens são paginadas, com tamanho de página limitado a 100', function () {
    comoConsulta();
    TipoPatrimonio::factory()->count(25)->create();

    $this->getJson('/api/tipos-patrimonio')
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('meta.total', 25)
        ->assertJsonPath('meta.last_page', 2);

    $this->getJson('/api/tipos-patrimonio?pagina=2')->assertJsonCount(5, 'data');
    $this->getJson('/api/tipos-patrimonio?por_pagina=5')->assertJsonCount(5, 'data');
    $this->getJson('/api/tipos-patrimonio?por_pagina=500')->assertJsonPath('meta.per_page', 100);
});
