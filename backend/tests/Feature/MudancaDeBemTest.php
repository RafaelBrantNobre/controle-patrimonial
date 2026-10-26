<?php

use App\Enums\StatusBem;
use App\Models\Bem;
use App\Models\Movimentacao;
use App\Models\Sala;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/*
| Testes da mudança de sala e de status (tarefa 2.8).
| RF17 mudar de sala, RF18 data da última mudança, RF19 status, RF20 baixa,
| RN04 baixado não muda de sala, RN05 baixado continua visível,
| RN09 data e usuário da mudança, RN10 consulta não altera.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 23)->setTime(10, 0));
});

function administradorConectado(string $nome = 'Rafael'): User
{
    return Sanctum::actingAs(User::factory()->administrador()->create(['nome' => $nome]));
}

// --- Mudança de sala (RF17, RF18, RN09) -------------------------------------

test('o bem muda de sala e a mudança entra no histórico com origem, destino e quem mudou', function () {
    $usuario = administradorConectado();
    $origem = Sala::factory()->create(['nome' => 'Sala 03']);
    $destino = Sala::factory()->create(['nome' => 'Sala 05']);
    $bem = Bem::factory()->for($origem)->create();

    $this->patchJson("/api/bens/{$bem->id}/sala", ['sala_id' => $destino->id])
        ->assertOk()
        ->assertJsonPath('data.sala.nome', 'Sala 05')
        ->assertJsonPath('data.ultima_movimentacao.sala_origem.nome', 'Sala 03')
        ->assertJsonPath('data.ultima_movimentacao.sala_destino.nome', 'Sala 05')
        ->assertJsonPath('data.ultima_movimentacao.usuario.nome', 'Rafael')
        ->assertJsonPath('data.data_ultima_mudanca', '2026-10-23')
        ->assertJsonPath('data.usuario_ultima_mudanca.nome', 'Rafael');

    $movimentacao = Movimentacao::sole();
    expect($bem->fresh()->sala_id)->toBe($destino->id)
        ->and($movimentacao->bem_id)->toBe($bem->id)
        ->and($movimentacao->sala_origem_id)->toBe($origem->id)
        ->and($movimentacao->sala_destino_id)->toBe($destino->id)
        ->and($movimentacao->usuario_id)->toBe($usuario->id)
        ->and($movimentacao->data->toDateString())->toBe('2026-10-23');
});

test('cada mudança de sala vira uma linha do histórico, e a ficha mostra a última', function () {
    administradorConectado();
    [$a, $b, $c] = Sala::factory()->count(3)->create();
    $bem = Bem::factory()->for($a)->create();

    $this->patchJson("/api/bens/{$bem->id}/sala", ['sala_id' => $b->id])->assertOk();
    $this->patchJson("/api/bens/{$bem->id}/sala", ['sala_id' => $c->id])->assertOk();

    expect($bem->movimentacoes()->pluck('sala_destino_id')->all())->toBe([$c->id, $b->id]);

    $this->getJson("/api/bens/{$bem->id}")
        ->assertJsonPath('data.ultima_movimentacao.sala_origem.id', $b->id)
        ->assertJsonPath('data.ultima_movimentacao.sala_destino.id', $c->id);
});

test('bem que nunca mudou de sala não tem histórico', function () {
    administradorConectado();
    $bem = Bem::factory()->create();

    $this->getJson("/api/bens/{$bem->id}")->assertJsonPath('data.ultima_movimentacao', null);
    $this->patchJson("/api/bens/{$bem->id}/status", ['status' => 'sem_uso'])->assertOk();

    expect(Movimentacao::count())->toBe(0);
});

test('bem baixado não muda de sala (RN04)', function () {
    administradorConectado();
    $bem = Bem::factory()->baixado()->create();
    $destino = Sala::factory()->create();

    $this->patchJson("/api/bens/{$bem->id}/sala", ['sala_id' => $destino->id])
        ->assertUnprocessable()
        ->assertJsonPath('errors.sala_id.0', 'Este bem está baixado e não muda de sala. Se a baixa foi um engano, altere a situação dele antes.');

    expect($bem->fresh()->sala_id)->not->toBe($destino->id);
});

test('o destino precisa ser outra sala, ativa e existente', function () {
    administradorConectado();
    $bem = Bem::factory()->create();

    $this->patchJson("/api/bens/{$bem->id}/sala", ['sala_id' => $bem->sala_id])
        ->assertUnprocessable()
        ->assertJsonPath('errors.sala_id.0', 'O bem já está nesta sala. Escolha a sala para onde ele foi.');

    $this->patchJson("/api/bens/{$bem->id}/sala", ['sala_id' => Sala::factory()->inativa()->create()->id])
        ->assertUnprocessable()
        ->assertJsonPath('errors.sala_id.0', 'A sala escolhida está inativa e não recebe bens. Escolha outra sala ou reative-a.');

    $this->patchJson("/api/bens/{$bem->id}/sala", ['sala_id' => 999])
        ->assertUnprocessable()
        ->assertJsonPath('errors.sala_id.0', 'A sala escolhida não existe. Atualize a página e escolha de novo.');

    $this->patchJson("/api/bens/{$bem->id}/sala", [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.sala_id.0', 'Escolha a sala para onde o bem foi.');
});

// --- Status (RF19, RN09) ----------------------------------------------------

test('o status muda e grava a data e quem mudou', function () {
    administradorConectado('Christine');
    $bem = Bem::factory()->create();

    $this->patchJson("/api/bens/{$bem->id}/status", ['status' => 'sem_uso'])
        ->assertOk()
        ->assertJsonPath('data.status', 'sem_uso')
        ->assertJsonPath('data.status_rotulo', 'Sem uso')
        ->assertJsonPath('data.data_ultima_mudanca', '2026-10-23')
        ->assertJsonPath('data.usuario_ultima_mudanca.nome', 'Christine');
});

test('o status novo precisa ser diferente do atual e da lista', function () {
    administradorConectado();
    $bem = Bem::factory()->create();

    $this->patchJson("/api/bens/{$bem->id}/status", ['status' => 'em_uso'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.status.0', 'O bem já está com a situação "Em uso". Escolha outra.');

    $this->patchJson("/api/bens/{$bem->id}/status", ['status' => 'perdido'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.status.0', 'A situação de uso deve ser em uso, sem uso ou baixado.');
});

// --- Baixa (RF20, RN05) -----------------------------------------------------

test('a baixa grava a data e a observação, e o bem continua na lista (RN05)', function () {
    administradorConectado();
    $bem = Bem::factory()->create();

    $this->patchJson("/api/bens/{$bem->id}/status", [
        'status' => 'baixado',
        'baixa_data' => '2026-10-20',
        'baixa_observacao' => 'Processo 123/2026. Aguardando recolhimento.',
    ])
        ->assertOk()
        ->assertJsonPath('data.baixado', true)
        ->assertJsonPath('data.baixa_data', '2026-10-20')
        ->assertJsonPath('data.baixa_observacao', 'Processo 123/2026. Aguardando recolhimento.')
        ->assertJsonPath('data.data_ultima_mudanca', '2026-10-23');

    $this->getJson('/api/bens')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.baixado', true);
});

test('a baixa exige a data, que não pode ser futura', function () {
    administradorConectado();
    $bem = Bem::factory()->create();

    $this->patchJson("/api/bens/{$bem->id}/status", ['status' => 'baixado'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.baixa_data.0', 'Informe a data da baixa.');

    $this->patchJson("/api/bens/{$bem->id}/status", ['status' => 'baixado', 'baixa_data' => '2026-10-24'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.baixa_data.0', 'A data da baixa não pode ser depois de hoje.');

    $this->patchJson("/api/bens/{$bem->id}/status", ['status' => 'baixado', 'baixa_data' => '23/10/2026'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.baixa_data.0', 'Informe a data da baixa no formato ano-mês-dia, como 2026-10-23.');

    expect($bem->fresh()->status)->toBe(StatusBem::EmUso);
});

test('dados da baixa só valem para o status baixado', function () {
    administradorConectado();
    $bem = Bem::factory()->create();

    $this->patchJson("/api/bens/{$bem->id}/status", ['status' => 'sem_uso', 'baixa_data' => '2026-10-20'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.baixa_data.0', 'A data da baixa só é informada quando a nova situação é baixado.');
});

test('desfazer a baixa apaga os dados dela e registra a mudança', function () {
    administradorConectado('Rafael');
    $bem = Bem::factory()->baixado()->create();

    $this->patchJson("/api/bens/{$bem->id}/status", ['status' => 'em_uso'])
        ->assertOk()
        ->assertJsonPath('data.baixado', false)
        ->assertJsonPath('data.baixa_data', null)
        ->assertJsonPath('data.baixa_observacao', null)
        ->assertJsonPath('data.usuario_ultima_mudanca.nome', 'Rafael');

    // Depois de desfeita a baixa, o bem volta a poder mudar de sala.
    $this->patchJson("/api/bens/{$bem->id}/sala", ['sala_id' => Sala::factory()->create()->id])->assertOk();
});

// --- Acesso (RN10) e exclusão de sala ---------------------------------------

test('o perfil consulta não muda sala nem status', function () {
    Sanctum::actingAs(User::factory()->create());
    $bem = Bem::factory()->create();
    $destino = Sala::factory()->create();

    $this->patchJson("/api/bens/{$bem->id}/sala", ['sala_id' => $destino->id])->assertForbidden();
    $this->patchJson("/api/bens/{$bem->id}/status", ['status' => 'sem_uso'])->assertForbidden();

    $bem->refresh();
    expect($bem->sala_id)->not->toBe($destino->id)
        ->and($bem->status)->toBe(StatusBem::EmUso)
        ->and($bem->data_ultima_mudanca)->toBeNull();
});

test('sala que aparece no histórico não pode ser excluída, mesmo vazia', function () {
    administradorConectado();
    [$origem, $passagem, $final] = Sala::factory()->count(3)->create();
    $bem = Bem::factory()->for($origem)->create();
    $this->patchJson("/api/bens/{$bem->id}/sala", ['sala_id' => $passagem->id])->assertOk();
    $this->patchJson("/api/bens/{$bem->id}/sala", ['sala_id' => $final->id])->assertOk();

    // Origem e passagem ficaram sem bens, mas continuam no histórico.
    foreach ([$origem, $passagem] as $sala) {
        $this->deleteJson("/api/salas/{$sala->id}")
            ->assertConflict()
            ->assertJsonPath('message', 'Esta sala aparece no histórico de mudanças de bens e não pode ser excluída. Se ela não é mais usada, inative-a.');
    }
});
