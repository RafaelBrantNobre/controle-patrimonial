<?php

use App\Models\Bem;
use App\Models\Sala;
use App\Models\TipoPatrimonio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/*
| Testes da lista de ocorrências (GET /api/ocorrencias): os filtros do bem,
| os mesmos da lista de bens, e os da própria ocorrência.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    Sanctum::actingAs(User::factory()->administrador()->create(['nome' => 'Rafael']));

    $this->origem = Sala::factory()->create(['nome' => 'Sala 03']);
    $this->destino = Sala::factory()->create(['nome' => 'Sala 05']);
    $this->cadeira = TipoPatrimonio::factory()->create(['nome' => 'Cadeira']);
    $this->monitor = TipoPatrimonio::factory()->create(['nome' => 'Monitor']);

    $this->bemCadeira = Bem::factory()->for($this->origem)->for($this->cadeira, 'tipoPatrimonio')->create(['numero_patrimonio' => '111']);
    $this->bemMonitor = Bem::factory()->for($this->origem)->for($this->monitor, 'tipoPatrimonio')->create(['numero_patrimonio' => '222']);

    $this->travelTo(now()->setDate(2026, 11, 1));
    $this->patchJson("/api/bens/{$this->bemCadeira->id}/sala", ['sala_id' => $this->destino->id])->assertOk();
    $this->travelTo(now()->setDate(2026, 11, 10));
    $this->patchJson("/api/bens/{$this->bemMonitor->id}/status", ['status' => 'sem_uso'])->assertOk();
});

test('lista as ocorrências de todos os bens, mais recentes primeiro, com o bem', function () {
    $this->getJson('/api/ocorrencias')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.tipo', 'uso')
        ->assertJsonPath('data.0.bem.numero_patrimonio', '222')
        ->assertJsonPath('data.0.bem.tipo', 'Monitor')
        ->assertJsonPath('data.0.usuario', 'Rafael')
        ->assertJsonPath('data.1.tipo', 'sala')
        ->assertJsonPath('data.1.valor_anterior', 'Sala 03')
        ->assertJsonPath('data.1.valor_novo', 'Sala 05');
});

test('aceita os filtros da lista de bens', function () {
    $this->getJson("/api/ocorrencias?tipo_patrimonio_id={$this->cadeira->id}")->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.tipo', 'sala');
    $this->getJson("/api/ocorrencias?sala_id={$this->destino->id}")->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.bem.numero_patrimonio', '111');
    $this->getJson('/api/ocorrencias?busca=222')->assertJsonPath('meta.total', 1);
    $this->getJson('/api/ocorrencias?status=sem_uso')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.bem.numero_patrimonio', '222');
});

test('filtra pelo tipo e pelo período da ocorrência', function () {
    $this->getJson('/api/ocorrencias?tipo=sala')->assertJsonPath('meta.total', 1);
    $this->getJson('/api/ocorrencias?tipo=sala,uso')->assertJsonPath('meta.total', 2);
    $this->getJson('/api/ocorrencias?de=2026-11-05')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.tipo', 'uso');
    $this->getJson('/api/ocorrencias?ate=2026-11-05')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.tipo', 'sala');
});

test('recusa filtro inválido', function () {
    $this->getJson('/api/ocorrencias?tipo=quebrou')->assertUnprocessable()->assertJsonValidationErrors('tipo.0');
    $this->getJson('/api/ocorrencias?de=2026-11-10&ate=2026-11-01')->assertUnprocessable()->assertJsonValidationErrors('ate');
});

test('o perfil de consulta também vê as ocorrências', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/ocorrencias')->assertOk()->assertJsonPath('meta.total', 2);
});
