<?php

use App\Enums\EstadoConservacao;
use App\Enums\StatusBem;
use App\Models\Bem;
use App\Models\Sala;
use App\Models\TipoPatrimonio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/*
| Testes do estado de conservação, separado da situação de uso.
| Um bem pode estar em uso e em estado ruim, ou sem uso e novo.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    Sanctum::actingAs(User::factory()->administrador()->create());
});

test('o bem novo fica com estado indeterminado se ninguém informar', function () {
    $this->postJson('/api/bens', [
        'tipo_patrimonio_id' => TipoPatrimonio::factory()->create()->id,
        'sala_id' => Sala::factory()->create()->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.estado_conservacao', 'indeterminado')
        ->assertJsonPath('data.estado_conservacao_rotulo', 'Indeterminado');
});

test('situação de uso e estado de conservação se combinam livremente', function () {
    $dados = ['tipo_patrimonio_id' => TipoPatrimonio::factory()->create()->id, 'sala_id' => Sala::factory()->create()->id];

    $this->postJson('/api/bens', [...$dados, 'status' => 'em_uso', 'estado_conservacao' => 'pessimo'])
        ->assertCreated()
        ->assertJsonPath('data.status_rotulo', 'Em uso')
        ->assertJsonPath('data.estado_conservacao_rotulo', 'Péssimo');

    $this->postJson('/api/bens', [...$dados, 'status' => 'sem_uso', 'estado_conservacao' => 'novo'])
        ->assertCreated()
        ->assertJsonPath('data.status_rotulo', 'Sem uso')
        ->assertJsonPath('data.estado_conservacao_rotulo', 'Novo');
});

test('estado de conservação fora da lista é recusado com mensagem clara', function () {
    $this->postJson('/api/bens', [
        'tipo_patrimonio_id' => TipoPatrimonio::factory()->create()->id,
        'sala_id' => Sala::factory()->create()->id,
        'estado_conservacao' => 'otimo',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.estado_conservacao.0', 'Escolha o estado de conservação: novo, bom, regular, péssimo, sucata ou indeterminado.');
});

test('avariado não é mais situação de uso', function () {
    $this->postJson('/api/bens', [
        'tipo_patrimonio_id' => TipoPatrimonio::factory()->create()->id,
        'sala_id' => Sala::factory()->create()->id,
        'status' => 'avariado',
    ])->assertUnprocessable()->assertJsonValidationErrors('status');

    $bem = Bem::factory()->create();
    $this->patchJson("/api/bens/{$bem->id}/status", ['status' => 'avariado'])->assertUnprocessable();
});

test('o estado de conservação muda pela edição do bem, sem contar como mudança de situação', function () {
    $bem = Bem::factory()->create(['data_ultima_mudanca' => null]);

    $this->patchJson("/api/bens/{$bem->id}", ['estado_conservacao' => 'regular'])
        ->assertOk()
        ->assertJsonPath('data.estado_conservacao', 'regular')
        ->assertJsonPath('data.data_ultima_mudanca', null);

    expect($bem->fresh()->estado_conservacao)->toBe(EstadoConservacao::Regular)
        ->and($bem->fresh()->status)->toBe(StatusBem::EmUso);
});

test('a consulta filtra e ordena pelo estado de conservação', function () {
    Bem::factory()->conservacao(EstadoConservacao::Pessimo)->create();
    Bem::factory()->conservacao(EstadoConservacao::Sucata)->create();
    Bem::factory()->conservacao(EstadoConservacao::Bom)->create();

    $this->getJson('/api/bens?estado_conservacao=pessimo,sucata')->assertJsonPath('meta.total', 2);
    $this->getJson('/api/bens?estado_conservacao=quebrado')->assertUnprocessable();
    $this->getJson('/api/bens?ordenar=conservacao')->assertOk()->assertJsonPath('data.0.estado_conservacao', 'bom');
});

test('o relatório filtra, conta e exporta o estado de conservação', function () {
    Bem::factory()->conservacao(EstadoConservacao::Pessimo)->create();
    Bem::factory()->semUso()->conservacao(EstadoConservacao::Novo)->create();

    $periodo = 'inicio=2026-07-01&fim=2026-12-31';

    $this->getJson("/api/relatorio?{$periodo}")
        ->assertJsonPath('totais.por_estado_conservacao.pessimo', 1)
        ->assertJsonPath('totais.por_estado_conservacao.novo', 1)
        ->assertJsonPath('totais.por_status.sem_uso', 1);

    $this->getJson("/api/relatorio?{$periodo}&estado_conservacao=novo")
        ->assertJsonPath('totais.bens', 1)
        ->assertJsonPath('andares.0.salas.0.bens.0.estado_conservacao_rotulo', 'Novo')
        ->assertJsonPath('andares.0.salas.0.bens.0.status_rotulo', 'Sem uso');
});
