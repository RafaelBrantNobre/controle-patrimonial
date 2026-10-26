<?php

use App\Models\Andar;
use App\Models\Bem;
use App\Models\Sala;
use App\Models\TipoPatrimonio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/*
| Testes dos cadastros de andares, salas e tipos de patrimônio (tarefa 2.5).
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
    $this->getJson('/api/andares')->assertUnauthorized();
    $this->getJson('/api/salas')->assertUnauthorized();
    $this->getJson('/api/tipos-patrimonio')->assertUnauthorized();
});

test('o perfil consulta lista e vê, mas não cadastra, edita nem exclui', function () {
    comoConsulta();
    $andar = Andar::factory()->create();
    $sala = Sala::factory()->create();
    $tipo = TipoPatrimonio::factory()->create();

    $this->getJson('/api/andares')->assertOk();
    $this->getJson("/api/salas/{$sala->id}")->assertOk();
    $this->getJson('/api/tipos-patrimonio')->assertOk();

    $this->postJson('/api/andares', ['nome' => 'Térreo'])->assertForbidden();
    $this->patchJson("/api/salas/{$sala->id}", ['ativo' => false])->assertForbidden();
    $this->deleteJson("/api/tipos-patrimonio/{$tipo->id}")->assertForbidden();
    $this->deleteJson("/api/andares/{$andar->id}")->assertForbidden();

    expect(Andar::count())->toBe(2)
        ->and($sala->fresh()->ativo)->toBeTrue()
        ->and(TipoPatrimonio::count())->toBe(1);
});

test('registro inexistente responde 404 com mensagem em português', function () {
    comoConsulta();

    $this->getJson('/api/salas/999')
        ->assertNotFound()
        ->assertJsonPath('message', 'O registro procurado não foi encontrado. Ele pode ter sido excluído. Atualize a página e tente de novo.');
});

// --- Andares (RF05, RF07) ---------------------------------------------------

test('o administrador cadastra um andar', function () {
    comoAdministrador();

    $this->postJson('/api/andares', ['nome' => '1º andar', 'ordem' => 1])
        ->assertCreated()
        ->assertJsonPath('data.nome', '1º andar')
        ->assertJsonPath('data.ordem', 1)
        ->assertJsonPath('data.ativo', true)
        ->assertJsonPath('data.salas_count', 0);
});

test('o nome do andar é obrigatório e não se repete', function () {
    comoAdministrador();
    Andar::factory()->create(['nome' => 'Térreo']);

    $this->postJson('/api/andares', [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.nome.0', 'Informe o nome do andar.');

    $this->postJson('/api/andares', ['nome' => 'Térreo'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.nome.0', 'Já existe um andar com este nome.');
});

test('o andar pode ser editado mantendo o próprio nome', function () {
    comoAdministrador();
    $andar = Andar::factory()->create(['nome' => 'Térreo', 'ordem' => 0]);

    $this->putJson("/api/andares/{$andar->id}", ['nome' => 'Térreo', 'ordem' => 5])
        ->assertOk()
        ->assertJsonPath('data.ordem', 5);
});

test('os andares saem na ordem do relatório', function () {
    comoConsulta();
    Andar::factory()->create(['nome' => '2º andar', 'ordem' => 2]);
    Andar::factory()->create(['nome' => 'Térreo', 'ordem' => 0]);
    Andar::factory()->create(['nome' => '1º andar', 'ordem' => 1]);

    $nomes = collect($this->getJson('/api/andares')->json('data'))->pluck('nome')->all();

    expect($nomes)->toBe(['Térreo', '1º andar', '2º andar']);
});

test('andar sem sala ativa pode ser inativado e reativado', function () {
    comoAdministrador();
    $andar = Andar::factory()->create();
    Sala::factory()->inativa()->for($andar)->create();

    $this->patchJson("/api/andares/{$andar->id}", ['ativo' => false])->assertOk()->assertJsonPath('data.ativo', false);
    $this->patchJson("/api/andares/{$andar->id}", ['ativo' => true])->assertOk()->assertJsonPath('data.ativo', true);
});

test('andar com sala ativa não pode ser inativado', function () {
    comoAdministrador();
    $andar = Andar::factory()->create();
    Sala::factory()->for($andar)->create();

    $this->patchJson("/api/andares/{$andar->id}", ['ativo' => false])
        ->assertUnprocessable()
        ->assertJsonPath('errors.ativo.0', 'Este andar tem salas ativas. Inative as salas antes de inativar o andar.');

    expect($andar->fresh()->ativo)->toBeTrue();
});

test('andar sem salas é excluído, andar com salas não (RN08)', function () {
    comoAdministrador();
    $vazio = Andar::factory()->create();
    $comSala = Sala::factory()->create()->andar;

    $this->deleteJson("/api/andares/{$vazio->id}")->assertOk()->assertJsonPath('message', 'Andar excluído.');
    $this->deleteJson("/api/andares/{$comSala->id}")
        ->assertConflict()
        ->assertJsonPath('message', 'Este andar tem salas cadastradas e não pode ser excluído. Se ele não é mais usado, inative-o.');

    expect(Andar::find($vazio->id))->toBeNull()
        ->and(Andar::find($comSala->id))->not->toBeNull();
});

// --- Salas (RF06, RF07) -----------------------------------------------------

test('o administrador cadastra uma sala num andar', function () {
    comoAdministrador();
    $andar = Andar::factory()->create(['nome' => '1º andar']);

    $this->postJson('/api/salas', ['andar_id' => $andar->id, 'nome' => 'Sala 05 - Suporte TI'])
        ->assertCreated()
        ->assertJsonPath('data.nome', 'Sala 05 - Suporte TI')
        ->assertJsonPath('data.andar.nome', '1º andar')
        ->assertJsonPath('data.descricao', null)
        ->assertJsonPath('data.ativo', true)
        ->assertJsonPath('data.bens_count', 0);
});

test('a sala exige andar e nome, com mensagens em português', function () {
    comoAdministrador();

    $this->postJson('/api/salas', [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.andar_id.0', 'Escolha o andar da sala.')
        ->assertJsonPath('errors.nome.0', 'Informe o nome da sala.');

    $this->postJson('/api/salas', ['andar_id' => 999, 'nome' => 'Sala 01'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.andar_id.0', 'O andar escolhido não existe. Atualize a página e escolha de novo.');
});

test('o nome da sala é único dentro do andar, mas pode repetir em outro andar', function () {
    comoAdministrador();
    $primeiro = Andar::factory()->create();
    $segundo = Andar::factory()->create();
    Sala::factory()->for($primeiro)->create(['nome' => 'Sala 05']);

    $this->postJson('/api/salas', ['andar_id' => $primeiro->id, 'nome' => 'Sala 05'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.nome.0', 'Já existe uma sala com este nome neste andar.');

    $this->postJson('/api/salas', ['andar_id' => $segundo->id, 'nome' => 'Sala 05'])->assertCreated();
});

test('mudar a sala de andar respeita o nome único do andar de destino', function () {
    comoAdministrador();
    $origem = Andar::factory()->create();
    $destino = Andar::factory()->create();
    Sala::factory()->for($destino)->create(['nome' => 'Sala 05']);
    $sala = Sala::factory()->for($origem)->create(['nome' => 'Sala 05']);

    $this->patchJson("/api/salas/{$sala->id}", ['andar_id' => $destino->id])
        ->assertUnprocessable()
        ->assertJsonPath('errors.nome.0', 'Já existe uma sala com este nome neste andar.');
});

test('sala ativa não pode ficar em andar inativo', function () {
    comoAdministrador();
    $inativo = Andar::factory()->inativo()->create();
    $sala = Sala::factory()->inativa()->for($inativo)->create();

    $mensagem = 'O andar escolhido está inativo. Reative o andar ou escolha outro.';

    // Cadastro de sala nova num andar inativo.
    $this->postJson('/api/salas', ['andar_id' => $inativo->id, 'nome' => 'Sala 10'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.andar_id.0', $mensagem);

    // Reativar uma sala que está num andar inativo.
    $this->patchJson("/api/salas/{$sala->id}", ['ativo' => true])
        ->assertUnprocessable()
        ->assertJsonPath('errors.andar_id.0', $mensagem);

    // Uma sala inativa pode ser cadastrada e editada nesse andar.
    $this->postJson('/api/salas', ['andar_id' => $inativo->id, 'nome' => 'Sala 11', 'ativo' => false])->assertCreated();
    $this->patchJson("/api/salas/{$sala->id}", ['descricao' => 'Depósito'])->assertOk();
});

test('a lista de salas filtra por andar, nome e situação', function () {
    comoConsulta();
    $terreo = Andar::factory()->create(['ordem' => 0]);
    $primeiro = Andar::factory()->create(['ordem' => 1]);
    Sala::factory()->for($terreo)->create(['nome' => 'Recepção']);
    Sala::factory()->for($primeiro)->create(['nome' => 'Sala 05 - Suporte TI']);
    Sala::factory()->for($primeiro)->inativa()->create(['nome' => 'Sala 06 - Arquivo']);

    expect($this->getJson("/api/salas?andar_id={$primeiro->id}")->json('meta.total'))->toBe(2)
        ->and($this->getJson('/api/salas?busca=suporte')->json('data.0.nome'))->toBe('Sala 05 - Suporte TI')
        ->and($this->getJson('/api/salas?ativo=0')->json('data.0.nome'))->toBe('Sala 06 - Arquivo')
        ->and($this->getJson('/api/salas?ativo=1')->json('meta.total'))->toBe(2);
});

test('as salas saem agrupadas por andar, na ordem do relatório', function () {
    comoConsulta();
    $segundo = Andar::factory()->create(['ordem' => 2]);
    $terreo = Andar::factory()->create(['ordem' => 0]);
    Sala::factory()->for($segundo)->create(['nome' => 'A - Auditório']);
    Sala::factory()->for($terreo)->create(['nome' => 'B - Recepção']);
    Sala::factory()->for($terreo)->create(['nome' => 'A - Portaria']);

    $nomes = collect($this->getJson('/api/salas')->json('data'))->pluck('nome')->all();

    expect($nomes)->toBe(['A - Portaria', 'B - Recepção', 'A - Auditório']);
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
