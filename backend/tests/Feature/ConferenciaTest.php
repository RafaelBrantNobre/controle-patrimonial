<?php

use App\Enums\EstadoConservacao;
use App\Enums\ResolucaoPendencia;
use App\Enums\ResultadoConferencia;
use App\Enums\SituacaoConferencia;
use App\Enums\StatusBem;
use App\Models\Bem;
use App\Models\Conferencia;
use App\Models\ConferenciaItem;
use App\Models\Movimentacao;
use App\Models\Ocorrencia;
use App\Models\Sala;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/*
| Testes da conferência dos bens e das pendências de bem não localizado.
| Gerar por seleção ou por filtro, lançar a folha, marcar em lote, concluir
| (os bens só mudam aqui), cancelar, resolver pendências, filtros da lista
| de bens e o acesso (RN10).
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 11, 6)->setTime(10, 0));
});

function gestoraConectada(string $nome = 'Christine'): User
{
    return Sanctum::actingAs(User::factory()->administrador()->create(['nome' => $nome]));
}

/** Gera uma conferência pela API com os bens informados e devolve o id. */
function gerarConferencia(array $bens, string $nome = '2º semestre de 2026'): int
{
    return test()->postJson('/api/conferencias', [
        'nome' => $nome,
        'modo' => 'selecionados',
        'bem_ids' => collect($bens)->pluck('id')->all(),
    ])->assertCreated()->json('data.id');
}

function itemDe(int $conferenciaId, Bem $bem): ConferenciaItem
{
    return ConferenciaItem::where('conferencia_id', $conferenciaId)->where('bem_id', $bem->id)->sole();
}

// --- Gerar ------------------------------------------------------------------

test('gerar uma conferência pelos bens selecionados cria os itens pendentes', function () {
    gestoraConectada();
    $bens = Bem::factory()->count(3)->create();

    $resposta = $this->postJson('/api/conferencias', [
        'nome' => '2º semestre de 2026',
        'modo' => 'selecionados',
        'bem_ids' => $bens->pluck('id')->all(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.nome', '2º semestre de 2026')
        ->assertJsonPath('data.situacao', 'aberta')
        ->assertJsonPath('data.criada_por.nome', 'Christine')
        ->assertJsonPath('data.totais.itens', 3)
        ->assertJsonPath('data.totais.pendentes', 3)
        ->assertJsonPath('incluidos', 3);

    expect(ConferenciaItem::where('conferencia_id', $resposta->json('data.id'))->whereNull('resultado')->count())->toBe(3);
});

test('gerar pelo filtro usa os mesmos filtros da lista de bens', function () {
    gestoraConectada();
    $sala = Sala::factory()->create();
    Bem::factory()->count(2)->for($sala)->create();
    Bem::factory()->count(3)->create();

    $this->postJson('/api/conferencias', [
        'nome' => 'Sala 05',
        'modo' => 'filtro',
        'filtros' => ['sala_id' => $sala->id],
    ])->assertCreated()->assertJsonPath('incluidos', 2);
});

test('gerar pelo filtro vazio pega o prédio inteiro', function () {
    gestoraConectada();
    Bem::factory()->count(4)->create();

    $this->postJson('/api/conferencias', ['nome' => 'Prédio', 'modo' => 'filtro'])
        ->assertCreated()
        ->assertJsonPath('incluidos', 4);
});

test('bens baixados ficam de fora, e a resposta diz quantos', function () {
    gestoraConectada();
    $livre = Bem::factory()->create();
    $baixado = Bem::factory()->baixado()->create();

    $this->postJson('/api/conferencias', [
        'nome' => 'Nova',
        'modo' => 'selecionados',
        'bem_ids' => [$livre->id, $baixado->id],
    ])
        ->assertCreated()
        ->assertJsonPath('incluidos', 1)
        ->assertJsonPath('ignorados.baixados', 1);
});

test('só existe uma conferência aberta por vez', function () {
    gestoraConectada();
    $bem = Bem::factory()->create();
    $aberta = gerarConferencia([$bem], 'Em andamento');

    $this->postJson('/api/conferencias', ['nome' => 'Outra', 'modo' => 'filtro'])
        ->assertConflict()
        ->assertJsonPath('conferencia_id', $aberta);

    $this->postJson("/api/conferencias/{$aberta}/cancelar")->assertOk();
    $this->postJson('/api/conferencias', ['nome' => 'Outra', 'modo' => 'filtro'])->assertCreated();
});

test('sem nenhum bem que possa entrar, a conferência não é criada', function () {
    gestoraConectada();
    $baixado = Bem::factory()->baixado()->create();

    $this->postJson('/api/conferencias', ['nome' => 'Nova', 'modo' => 'selecionados', 'bem_ids' => [$baixado->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('bem_ids');

    expect(Conferencia::count())->toBe(0);
});

test('gerar exige nome e bens válidos', function () {
    gestoraConectada();

    $this->postJson('/api/conferencias', ['modo' => 'selecionados', 'bem_ids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nome', 'bem_ids']);

    $this->postJson('/api/conferencias', ['nome' => 'X', 'modo' => 'selecionados', 'bem_ids' => [999]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('bem_ids');
});

// --- Lançar -----------------------------------------------------------------

test('encontrado sem sala fica na sala registrada, e nada muda no bem antes de concluir', function () {
    gestoraConectada();
    $bem = Bem::factory()->create();
    $id = gerarConferencia([$bem]);
    $item = itemDe($id, $bem);

    $this->patchJson("/api/conferencias/{$id}/itens/{$item->id}", ['resultado' => 'encontrado', 'estado_conservacao' => 'regular'])
        ->assertOk()
        ->assertJsonPath('data.resultado', 'encontrado')
        ->assertJsonPath('data.sala_encontrada_id', $bem->sala_id)
        ->assertJsonPath('data.estado_conservacao', 'regular')
        ->assertJsonPath('data.conferido_por.nome', 'Christine');

    $bem->refresh();
    expect($bem->estado_conservacao)->toBe(EstadoConservacao::Indeterminado)
        ->and($bem->data_ultima_conferencia)->toBeNull();
});

test('não localizado não guarda sala nem estado, e resultado nulo volta a pendente', function () {
    gestoraConectada();
    $bem = Bem::factory()->create();
    $id = gerarConferencia([$bem]);
    $item = itemDe($id, $bem);

    $this->patchJson("/api/conferencias/{$id}/itens/{$item->id}", [
        'resultado' => 'nao_localizado',
        'sala_encontrada_id' => $bem->sala_id,
        'estado_conservacao' => 'bom',
    ])
        ->assertOk()
        ->assertJsonPath('data.sala_encontrada_id', null)
        ->assertJsonPath('data.estado_conservacao', null);

    $this->patchJson("/api/conferencias/{$id}/itens/{$item->id}", ['resultado' => null])
        ->assertOk()
        ->assertJsonPath('data.resultado', null)
        ->assertJsonPath('data.conferido_em', null);
});

test('o bem só pode ser encontrado em sala ativa, e bem baixado não muda de sala', function () {
    gestoraConectada();
    $bem = Bem::factory()->create();
    $id = gerarConferencia([$bem]);
    $item = itemDe($id, $bem);
    $inativa = Sala::factory()->inativa()->create();

    $this->patchJson("/api/conferencias/{$id}/itens/{$item->id}", ['resultado' => 'encontrado', 'sala_encontrada_id' => $inativa->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sala_encontrada_id');

    $bem->update(['status' => 'baixado', 'baixa_data' => '2026-11-01']);
    $outra = Sala::factory()->create();

    $this->patchJson("/api/conferencias/{$id}/itens/{$item->id}", ['resultado' => 'encontrado', 'sala_encontrada_id' => $outra->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sala_encontrada_id');
});

test('item de outra conferência dá 404', function () {
    gestoraConectada();
    [$a, $b] = Bem::factory()->count(2)->create();
    $id = gerarConferencia([$a], 'A');
    $deOutra = ConferenciaItem::factory()->for(Conferencia::factory()->cancelada())->for($b)->create();

    $this->patchJson("/api/conferencias/{$id}/itens/{$deOutra->id}", ['resultado' => 'encontrado'])
        ->assertNotFound();

    $this->putJson("/api/conferencias/{$id}/itens", ['itens' => [['id' => $deOutra->id, 'resultado' => 'encontrado']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('itens');
});

test('marcar selecionados como encontrados mantém o que já tinha sido lançado em outra sala', function () {
    gestoraConectada();
    [$a, $b, $c] = Bem::factory()->count(3)->create();
    $id = gerarConferencia([$a, $b, $c]);
    $outraSala = Sala::factory()->create();
    $this->patchJson("/api/conferencias/{$id}/itens/".itemDe($id, $b)->id, [
        'resultado' => 'encontrado',
        'sala_encontrada_id' => $outraSala->id,
        'estado_conservacao' => 'pessimo',
    ])->assertOk();

    $this->postJson("/api/conferencias/{$id}/encontrados", ['itens' => [itemDe($id, $a)->id, itemDe($id, $b)->id]])
        ->assertOk()
        ->assertJsonPath('data.totais.encontrados', 2)
        ->assertJsonPath('data.totais.pendentes', 1);

    expect(itemDe($id, $a)->sala_encontrada_id)->toBe($a->sala_id)
        ->and(itemDe($id, $b)->sala_encontrada_id)->toBe($outraSala->id)
        ->and(itemDe($id, $b)->estado_conservacao)->toBe(EstadoConservacao::Pessimo);
});

test('a lista de itens vem na ordem da folha, com as salas e os pendentes de cada uma', function () {
    gestoraConectada();
    $salaA = Sala::factory()->create(['nome' => 'A - Recepção']);
    $salaB = Sala::factory()->create(['nome' => 'B - Suporte']);
    $bemB = Bem::factory()->for($salaB)->create();
    $bemA = Bem::factory()->for($salaA)->create();
    $id = gerarConferencia([$bemB, $bemA]);
    $this->patchJson("/api/conferencias/{$id}/itens/".itemDe($id, $bemA)->id, ['resultado' => 'encontrado'])->assertOk();

    $resposta = $this->getJson("/api/conferencias/{$id}/itens")->assertOk();

    expect($resposta->json('data.*.bem.id'))->toBe([$bemA->id, $bemB->id])
        ->and($resposta->json('salas'))->toBe([
            ['id' => $salaA->id, 'nome' => 'A - Recepção', 'total' => 1, 'pendentes' => 0],
            ['id' => $salaB->id, 'nome' => 'B - Suporte', 'total' => 1, 'pendentes' => 1],
        ]);

    $this->getJson("/api/conferencias/{$id}/itens?resultado=pendente")->assertJsonCount(1, 'data');
    $this->getJson("/api/conferencias/{$id}/itens?sala_id={$salaA->id}")->assertJsonCount(1, 'data');
});

// --- Salvar a sala (conferência sala a sala) ---------------------------------

test('salvar a sala grava todos os bens de uma vez e guarda o progresso', function () {
    gestoraConectada();
    $sala = Sala::factory()->create();
    $outraSala = Sala::factory()->create();
    [$a, $b, $c] = Bem::factory()->count(3)->for($sala)->create();
    $id = gerarConferencia([$a, $b, $c]);

    $this->putJson("/api/conferencias/{$id}/itens", ['itens' => [
        ['id' => itemDe($id, $a)->id, 'resultado' => 'encontrado', 'estado_conservacao' => 'bom', 'condicao_uso' => 'em_uso'],
        ['id' => itemDe($id, $b)->id, 'resultado' => 'encontrado', 'sala_encontrada_id' => $outraSala->id, 'estado_conservacao' => 'pessimo', 'condicao_uso' => 'sem_uso'],
        ['id' => itemDe($id, $c)->id, 'resultado' => null],
    ]])
        ->assertOk()
        ->assertJsonPath('data.totais.encontrados', 2)
        ->assertJsonPath('data.totais.pendentes', 1);

    // Ao voltar depois, o que foi salvo continua lá, e a sala mostra o que falta.
    $resposta = $this->getJson("/api/conferencias/{$id}/itens?sala_id={$sala->id}")->assertOk();
    expect($resposta->json('salas.0.pendentes'))->toBe(1)
        ->and(collect($resposta->json('data'))->firstWhere('bem.id', $b->id))
        ->toMatchArray(['resultado' => 'encontrado', 'sala_encontrada_id' => $outraSala->id, 'estado_conservacao' => 'pessimo', 'condicao_uso' => 'sem_uso']);

    expect($b->fresh()->sala_id)->toBe($sala->id);
});

test('salvar a sala recusa sala inativa e condição de uso inválida, sem gravar nada', function () {
    gestoraConectada();
    [$a, $b] = Bem::factory()->count(2)->create();
    $inativa = Sala::factory()->inativa()->create();
    $id = gerarConferencia([$a, $b]);

    $this->putJson("/api/conferencias/{$id}/itens", ['itens' => [
        ['id' => itemDe($id, $a)->id, 'resultado' => 'encontrado'],
        ['id' => itemDe($id, $b)->id, 'resultado' => 'encontrado', 'sala_encontrada_id' => $inativa->id],
    ]])->assertUnprocessable()->assertJsonValidationErrors('itens.1.sala_encontrada_id');

    $this->putJson("/api/conferencias/{$id}/itens", ['itens' => [
        ['id' => itemDe($id, $a)->id, 'resultado' => 'encontrado', 'condicao_uso' => 'baixado'],
    ]])->assertUnprocessable()->assertJsonValidationErrors('itens.0.condicao_uso');

    expect(itemDe($id, $a)->resultado)->toBeNull();
});

test('concluir aplica a condição de uso e grava as ocorrências com a conferência de origem', function () {
    gestoraConectada();
    $origem = Sala::factory()->create(['nome' => 'Sala 03']);
    $destino = Sala::factory()->create(['nome' => 'Sala 05']);
    $bem = Bem::factory()->for($origem)->conservacao(EstadoConservacao::Bom)->create();
    $id = gerarConferencia([$bem], 'Semestre');

    $this->putJson("/api/conferencias/{$id}/itens", ['itens' => [
        ['id' => itemDe($id, $bem)->id, 'resultado' => 'encontrado', 'sala_encontrada_id' => $destino->id, 'estado_conservacao' => 'regular', 'condicao_uso' => 'sem_uso'],
    ]])->assertOk();
    $this->postJson("/api/conferencias/{$id}/concluir", ['realizada_em' => '2026-11-06'])->assertOk();

    $bem->refresh();
    expect($bem->status)->toBe(StatusBem::SemUso)
        ->and($bem->sala_id)->toBe($destino->id)
        ->and($bem->estado_conservacao)->toBe(EstadoConservacao::Regular);

    $ocorrencias = $this->getJson("/api/bens/{$bem->id}")->json('data.ocorrencias');
    expect(collect($ocorrencias)->map(fn ($o) => [$o['tipo'], $o['valor_anterior'], $o['valor_novo'], $o['conferencia']['nome'] ?? null])->sortBy(0)->values()->all())->toBe([
        ['conservacao', 'Bom', 'Regular', 'Semestre'],
        ['sala', 'Sala 03', 'Sala 05', 'Semestre'],
        ['uso', 'Em uso', 'Sem uso', 'Semestre'],
    ]);
});

test('confirmar o bem como está não gera ocorrência', function () {
    gestoraConectada();
    $bem = Bem::factory()->conservacao(EstadoConservacao::Bom)->create();
    $id = gerarConferencia([$bem]);

    $this->putJson("/api/conferencias/{$id}/itens", ['itens' => [
        ['id' => itemDe($id, $bem)->id, 'resultado' => 'encontrado', 'sala_encontrada_id' => $bem->sala_id, 'estado_conservacao' => 'bom', 'condicao_uso' => 'em_uso'],
    ]])->assertOk();
    $this->postJson("/api/conferencias/{$id}/concluir", ['realizada_em' => '2026-11-06'])->assertOk();

    expect(Ocorrencia::count())->toBe(0)
        ->and($bem->fresh()->resultado_ultima_conferencia)->toBe(ResultadoConferencia::Encontrado);
});

// --- Ocorrências manuais -----------------------------------------------------

test('mudar de sala, mudar a conservação, mudar o uso e dar baixa pela ficha gravam ocorrências', function () {
    gestoraConectada();
    $origem = Sala::factory()->create(['nome' => 'Sala 03']);
    $destino = Sala::factory()->create(['nome' => 'Sala 05']);
    $bem = Bem::factory()->for($origem)->conservacao(EstadoConservacao::Bom)->create();

    $this->patchJson("/api/bens/{$bem->id}/sala", ['sala_id' => $destino->id])->assertOk();
    $this->patchJson("/api/bens/{$bem->id}", ['estado_conservacao' => 'pessimo'])->assertOk();
    $this->patchJson("/api/bens/{$bem->id}/status", ['status' => 'sem_uso'])->assertOk();
    $this->patchJson("/api/bens/{$bem->id}/status", ['status' => 'baixado', 'baixa_data' => '2026-11-05', 'baixa_observacao' => 'Processo 9'])
        ->assertOk()
        ->assertJsonPath('data.ocorrencias.0.usuario', 'Christine');

    expect(Ocorrencia::orderBy('id')->get()->map(fn ($o) => [$o->tipo->value, $o->valor_anterior, $o->valor_novo, $o->data->toDateString(), $o->observacao])->all())->toBe([
        ['sala', 'Sala 03', 'Sala 05', '2026-11-06', null],
        ['conservacao', 'Bom', 'Péssimo', '2026-11-06', null],
        ['uso', 'Em uso', 'Sem uso', '2026-11-06', null],
        ['baixa', 'Sem uso', 'Baixado', '2026-11-05', 'Processo 9'],
    ]);
});

test('o bem sem localização entra na etapa "Sem localização", e a sala onde foi encontrado é obrigatória', function () {
    gestoraConectada();
    $comSala = Bem::factory()->create();
    $semSala = Bem::factory()->create(['sala_id' => null]);
    $sala = Sala::factory()->create(['nome' => 'Sala 05']);
    $id = $this->postJson('/api/conferencias', ['nome' => 'Prédio', 'modo' => 'filtro'])->assertCreated()->json('data.id');
    $item = itemDe($id, $semSala);

    $resumo = $this->getJson("/api/conferencias/{$id}/itens?resumo=1")->assertOk();
    expect($resumo->json('data'))->toBe([])
        ->and($resumo->json('salas'))->toBe([
            ['id' => $comSala->sala_id, 'nome' => $comSala->sala->nome, 'total' => 1, 'pendentes' => 1],
            ['id' => null, 'nome' => 'Sem localização', 'total' => 1, 'pendentes' => 1],
        ]);

    $this->getJson("/api/conferencias/{$id}/itens?sem_sala=1")
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.bem.id', $semSala->id);

    $this->putJson("/api/conferencias/{$id}/itens", ['itens' => [['id' => $item->id, 'resultado' => 'encontrado']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('itens.0.sala_encontrada_id');
    $this->patchJson("/api/conferencias/{$id}/itens/{$item->id}", ['resultado' => 'encontrado'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sala_encontrada_id');

    $this->putJson("/api/conferencias/{$id}/itens", ['itens' => [
        ['id' => $item->id, 'resultado' => 'encontrado', 'sala_encontrada_id' => $sala->id],
        ['id' => itemDe($id, $comSala)->id, 'resultado' => 'encontrado'],
    ]])->assertOk();
    $this->postJson("/api/conferencias/{$id}/concluir", ['realizada_em' => '2026-11-06'])->assertOk();

    expect($semSala->fresh()->sala_id)->toBe($sala->id)
        ->and(Ocorrencia::sole()->only(['valor_anterior', 'valor_novo']))->toBe(['valor_anterior' => 'Sem localização', 'valor_novo' => 'Sala 05']);
});

// --- Concluir ---------------------------------------------------------------

test('não conclui com bens sem resultado nem com data futura', function () {
    gestoraConectada();
    $bem = Bem::factory()->create();
    $id = gerarConferencia([$bem]);

    $this->postJson("/api/conferencias/{$id}/concluir", ['realizada_em' => '2026-11-05'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('conferencia');

    $this->postJson("/api/conferencias/{$id}/concluir", ['realizada_em' => '2026-11-07'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('realizada_em');
});

test('concluir aplica os resultados aos bens: sala com histórico, conservação, data e resultado', function () {
    $gestora = gestoraConectada();
    $origem = Sala::factory()->create(['nome' => 'Sala 03']);
    $destino = Sala::factory()->create(['nome' => 'Sala 05']);
    $movido = Bem::factory()->for($origem)->create();
    $noLugar = Bem::factory()->for($origem)->create();
    $perdido = Bem::factory()->for($origem)->create();
    $id = gerarConferencia([$movido, $noLugar, $perdido]);

    $this->patchJson("/api/conferencias/{$id}/itens/".itemDe($id, $movido)->id, [
        'resultado' => 'encontrado', 'sala_encontrada_id' => $destino->id, 'estado_conservacao' => 'regular',
    ])->assertOk();
    $this->patchJson("/api/conferencias/{$id}/itens/".itemDe($id, $noLugar)->id, ['resultado' => 'encontrado'])->assertOk();
    $this->patchJson("/api/conferencias/{$id}/itens/".itemDe($id, $perdido)->id, ['resultado' => 'nao_localizado'])->assertOk();

    $this->postJson("/api/conferencias/{$id}/concluir", ['realizada_em' => '2026-11-04'])
        ->assertOk()
        ->assertJsonPath('data.situacao', 'concluida')
        ->assertJsonPath('data.realizada_em', '2026-11-04')
        ->assertJsonPath('data.concluida_por.nome', 'Christine');

    $movido->refresh();
    expect($movido->sala_id)->toBe($destino->id)
        ->and($movido->estado_conservacao)->toBe(EstadoConservacao::Regular)
        ->and($movido->data_ultima_conferencia->toDateString())->toBe('2026-11-04')
        ->and($movido->resultado_ultima_conferencia)->toBe(ResultadoConferencia::Encontrado)
        ->and($movido->data_ultima_mudanca->toDateString())->toBe('2026-11-06')
        ->and(Movimentacao::sole()->only(['bem_id', 'sala_origem_id', 'sala_destino_id', 'usuario_id']))->toBe([
            'bem_id' => $movido->id, 'sala_origem_id' => $origem->id, 'sala_destino_id' => $destino->id, 'usuario_id' => $gestora->id,
        ]);

    $noLugar->refresh();
    expect($noLugar->sala_id)->toBe($origem->id)
        ->and($noLugar->data_ultima_mudanca)->toBeNull()
        ->and($noLugar->resultado_ultima_conferencia)->toBe(ResultadoConferencia::Encontrado);

    expect($perdido->fresh()->resultado_ultima_conferencia)->toBe(ResultadoConferencia::NaoLocalizado);
});

test('conferência concluída não recebe mais resultados (409)', function () {
    gestoraConectada();
    $bem = Bem::factory()->create();
    $id = gerarConferencia([$bem]);
    $item = itemDe($id, $bem);
    $this->patchJson("/api/conferencias/{$id}/itens/{$item->id}", ['resultado' => 'encontrado'])->assertOk();
    $this->postJson("/api/conferencias/{$id}/concluir", ['realizada_em' => '2026-11-06'])->assertOk();

    $this->patchJson("/api/conferencias/{$id}/itens/{$item->id}", ['resultado' => 'nao_localizado'])->assertConflict();
    $this->postJson("/api/conferencias/{$id}/encontrados", ['itens' => [$item->id]])->assertConflict();
    $this->postJson("/api/conferencias/{$id}/concluir", ['realizada_em' => '2026-11-06'])->assertConflict();
    $this->postJson("/api/conferencias/{$id}/cancelar")->assertConflict();
});

test('concluir confere de novo a sala: se ela foi inativada depois do lançamento, não conclui', function () {
    gestoraConectada();
    $bem = Bem::factory()->create();
    $destino = Sala::factory()->create();
    $id = gerarConferencia([$bem]);
    $this->patchJson("/api/conferencias/{$id}/itens/".itemDe($id, $bem)->id, ['resultado' => 'encontrado', 'sala_encontrada_id' => $destino->id])->assertOk();
    $destino->update(['ativo' => false]);

    $this->postJson("/api/conferencias/{$id}/concluir", ['realizada_em' => '2026-11-06'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('conferencia');

    expect($bem->fresh()->sala_id)->not->toBe($destino->id);
});

test('uma conferência de data anterior, concluída depois, não passa por cima da mais recente nem abre pendência', function () {
    gestoraConectada();
    $bem = Bem::factory()->create();
    $recente = gerarConferencia([$bem], 'Recente');
    $this->patchJson("/api/conferencias/{$recente}/itens/".itemDe($recente, $bem)->id, ['resultado' => 'encontrado'])->assertOk();
    $this->postJson("/api/conferencias/{$recente}/concluir", ['realizada_em' => '2026-11-05'])->assertOk();

    // A folha de outubro só foi passada para o sistema agora.
    $antiga = gerarConferencia([$bem], 'Outubro');
    $this->patchJson("/api/conferencias/{$antiga}/itens/".itemDe($antiga, $bem)->id, ['resultado' => 'nao_localizado'])->assertOk();
    $this->postJson("/api/conferencias/{$antiga}/concluir", ['realizada_em' => '2026-10-01'])->assertOk();

    $bem->refresh();
    expect($bem->data_ultima_conferencia->toDateString())->toBe('2026-11-05')
        ->and($bem->resultado_ultima_conferencia)->toBe(ResultadoConferencia::Encontrado)
        ->and(itemDe($antiga, $bem)->resolucao)->toBe(ResolucaoPendencia::Localizado);

    $this->getJson('/api/pendencias')->assertJsonCount(0, 'data');
});

test('mudança feita no bem depois do lançamento vale mais que a folha', function () {
    gestoraConectada();
    $registrada = Sala::factory()->create();
    $nova = Sala::factory()->create();
    $bem = Bem::factory()->for($registrada)->create();
    $id = gerarConferencia([$bem]);
    $this->patchJson("/api/conferencias/{$id}/itens/".itemDe($id, $bem)->id, ['resultado' => 'encontrado', 'estado_conservacao' => 'bom'])->assertOk();

    $this->travel(1)->minutes();
    $this->patchJson("/api/bens/{$bem->id}/sala", ['sala_id' => $nova->id])->assertOk();
    $this->patchJson("/api/bens/{$bem->id}", ['estado_conservacao' => 'sucata'])->assertOk();

    $this->travel(1)->minutes();
    $this->postJson("/api/conferencias/{$id}/concluir", ['realizada_em' => '2026-11-06'])->assertOk();

    $bem->refresh();
    expect($bem->sala_id)->toBe($nova->id)
        ->and($bem->estado_conservacao)->toBe(EstadoConservacao::Sucata)
        ->and($bem->resultado_ultima_conferencia)->toBe(ResultadoConferencia::Encontrado)
        ->and(Movimentacao::count())->toBe(1);
});

test('conferência de data anterior, concluída depois, não muda a sala nem o estado do bem', function () {
    gestoraConectada();
    $antiga = Sala::factory()->create();
    $atual = Sala::factory()->create();
    $bem = Bem::factory()->for($antiga)->create();
    $recente = gerarConferencia([$bem], 'Recente');
    $this->patchJson("/api/conferencias/{$recente}/itens/".itemDe($recente, $bem)->id, ['resultado' => 'encontrado', 'sala_encontrada_id' => $atual->id, 'estado_conservacao' => 'bom'])->assertOk();
    $this->postJson("/api/conferencias/{$recente}/concluir", ['realizada_em' => '2026-11-05'])->assertOk();

    $this->travel(1)->minutes();
    $outubro = gerarConferencia([$bem], 'Outubro');
    $this->patchJson("/api/conferencias/{$outubro}/itens/".itemDe($outubro, $bem)->id, ['resultado' => 'encontrado', 'sala_encontrada_id' => $antiga->id, 'estado_conservacao' => 'pessimo'])->assertOk();
    $this->postJson("/api/conferencias/{$outubro}/concluir", ['realizada_em' => '2026-10-01'])->assertOk();

    $bem->refresh();
    expect($bem->sala_id)->toBe($atual->id)
        ->and($bem->estado_conservacao)->toBe(EstadoConservacao::Bom)
        ->and(Movimentacao::count())->toBe(1);
});

test('a pendência diz desde a conferência de data mais antiga, e o histórico vem pela data', function () {
    gestoraConectada();
    $bem = Bem::factory()->create();
    $novembro = gerarConferencia([$bem], 'Novembro');
    $this->patchJson("/api/conferencias/{$novembro}/itens/".itemDe($novembro, $bem)->id, ['resultado' => 'nao_localizado'])->assertOk();
    $this->postJson("/api/conferencias/{$novembro}/concluir", ['realizada_em' => '2026-11-05'])->assertOk();

    $outubro = gerarConferencia([$bem], 'Outubro');
    $this->patchJson("/api/conferencias/{$outubro}/itens/".itemDe($outubro, $bem)->id, ['resultado' => 'nao_localizado'])->assertOk();
    $this->postJson("/api/conferencias/{$outubro}/concluir", ['realizada_em' => '2026-10-20'])->assertOk();

    $this->getJson('/api/pendencias')
        ->assertJsonPath('data.0.pendencia.desde', '2026-10-20')
        ->assertJsonPath('data.0.pendencia.conferencia', 'Outubro');

    expect($this->getJson("/api/bens/{$bem->id}")->json('data.historico_conferencias.*.conferencia'))->toBe(['Novembro', 'Outubro']);
});

test('marcar em lote não desfaz um não localizado já lançado', function () {
    gestoraConectada();
    [$a, $b] = Bem::factory()->count(2)->create();
    $id = gerarConferencia([$a, $b]);
    $this->patchJson("/api/conferencias/{$id}/itens/".itemDe($id, $b)->id, ['resultado' => 'nao_localizado'])->assertOk();

    $this->postJson("/api/conferencias/{$id}/encontrados", ['itens' => [itemDe($id, $a)->id, itemDe($id, $b)->id]])
        ->assertOk()
        ->assertJsonPath('data.totais.encontrados', 1)
        ->assertJsonPath('data.totais.nao_localizados', 1);
});

test('a conferência guarda a sala da folha: depois de concluir, o bem achado em outra sala continua listado na sala registrada', function () {
    gestoraConectada();
    $registrada = Sala::factory()->create(['nome' => 'Sala 03']);
    $outra = Sala::factory()->create(['nome' => 'Sala 05']);
    $bem = Bem::factory()->for($registrada)->create();
    $id = gerarConferencia([$bem]);
    $this->patchJson("/api/conferencias/{$id}/itens/".itemDe($id, $bem)->id, ['resultado' => 'encontrado', 'sala_encontrada_id' => $outra->id])->assertOk();
    $this->postJson("/api/conferencias/{$id}/concluir", ['realizada_em' => '2026-11-06'])->assertOk();

    expect($bem->fresh()->sala_id)->toBe($outra->id);

    $this->getJson("/api/conferencias/{$id}/itens?sala_id={$registrada->id}")
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.sala_registrada.nome', 'Sala 03')
        ->assertJsonPath('data.0.sala_encontrada.nome', 'Sala 05')
        ->assertJsonPath('salas.0.nome', 'Sala 03');
});

// --- Cancelar ---------------------------------------------------------------

test('cancelar não muda nada nos bens e libera os bens para uma conferência nova', function () {
    gestoraConectada();
    $bem = Bem::factory()->create();
    $destino = Sala::factory()->create();
    $id = gerarConferencia([$bem]);
    $this->patchJson("/api/conferencias/{$id}/itens/".itemDe($id, $bem)->id, ['resultado' => 'encontrado', 'sala_encontrada_id' => $destino->id])->assertOk();

    $this->postJson("/api/conferencias/{$id}/cancelar")
        ->assertOk()
        ->assertJsonPath('data.situacao', 'cancelada');

    expect($bem->fresh()->sala_id)->not->toBe($destino->id)
        ->and($bem->fresh()->data_ultima_conferencia)->toBeNull();

    gerarConferencia([$bem], 'De novo');
});

// --- Pendências -------------------------------------------------------------

test('bem não localizado vira pendência só depois de concluída a conferência', function () {
    gestoraConectada();
    $bem = Bem::factory()->create();
    $id = gerarConferencia([$bem]);
    $this->patchJson("/api/conferencias/{$id}/itens/".itemDe($id, $bem)->id, ['resultado' => 'nao_localizado'])->assertOk();

    $this->getJson('/api/pendencias')->assertJsonCount(0, 'data');

    $this->postJson("/api/conferencias/{$id}/concluir", ['realizada_em' => '2026-11-04'])->assertOk();

    $this->getJson('/api/pendencias')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $bem->id)
        ->assertJsonPath('data.0.pendencia.desde', '2026-11-04')
        ->assertJsonPath('data.0.pendencia.conferencia', '2º semestre de 2026');
});

test('com processo administrativo a pendência fecha e o bem continua não localizado', function () {
    gestoraConectada();
    $bem = Bem::factory()->create();
    $id = gerarConferencia([$bem]);
    $this->patchJson("/api/conferencias/{$id}/itens/".itemDe($id, $bem)->id, ['resultado' => 'nao_localizado'])->assertOk();
    $this->postJson("/api/conferencias/{$id}/concluir", ['realizada_em' => '2026-11-04'])->assertOk();

    $this->postJson("/api/bens/{$bem->id}/pendencia", ['resolucao' => 'processo_administrativo'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('processo_numero');

    $this->postJson("/api/bens/{$bem->id}/pendencia", ['resolucao' => 'processo_administrativo', 'processo_numero' => '123/2026'])
        ->assertOk()
        ->assertJsonPath('data.pendencia', null)
        ->assertJsonPath('data.resultado_ultima_conferencia', 'nao_localizado')
        ->assertJsonPath('data.historico_conferencias.0.resolucao_rotulo', 'Processo administrativo')
        ->assertJsonPath('data.historico_conferencias.0.processo_numero', '123/2026');

    $this->getJson('/api/pendencias')->assertJsonCount(0, 'data');
    $this->postJson("/api/bens/{$bem->id}/pendencia", ['resolucao' => 'processo_administrativo', 'processo_numero' => '1'])->assertConflict();
});

test('localizado depois move o bem se preciso e volta a constar como encontrado', function () {
    gestoraConectada();
    $bem = Bem::factory()->create();
    $achadoEm = Sala::factory()->create();
    $id = gerarConferencia([$bem]);
    $this->patchJson("/api/conferencias/{$id}/itens/".itemDe($id, $bem)->id, ['resultado' => 'nao_localizado'])->assertOk();
    $this->postJson("/api/conferencias/{$id}/concluir", ['realizada_em' => '2026-11-04'])->assertOk();

    $this->postJson("/api/bens/{$bem->id}/pendencia", ['resolucao' => 'localizado', 'sala_id' => $achadoEm->id, 'localizado_em' => '2026-11-03'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('localizado_em');

    $this->postJson("/api/bens/{$bem->id}/pendencia", ['resolucao' => 'localizado', 'sala_id' => $achadoEm->id, 'localizado_em' => '2026-11-06'])
        ->assertOk()
        ->assertJsonPath('data.sala_id', $achadoEm->id)
        ->assertJsonPath('data.resultado_ultima_conferencia', 'encontrado')
        ->assertJsonPath('data.data_ultima_conferencia', '2026-11-06')
        ->assertJsonPath('data.pendencia', null);

    expect(itemDe($id, $bem)->resolucao)->toBe(ResolucaoPendencia::Localizado);
});

test('uma conferência nova que encontra o bem fecha a pendência antiga', function () {
    gestoraConectada();
    $bem = Bem::factory()->create();
    $primeira = gerarConferencia([$bem], 'Primeira');
    $this->patchJson("/api/conferencias/{$primeira}/itens/".itemDe($primeira, $bem)->id, ['resultado' => 'nao_localizado'])->assertOk();
    $this->postJson("/api/conferencias/{$primeira}/concluir", ['realizada_em' => '2026-11-01'])->assertOk();

    $segunda = gerarConferencia([$bem], 'Segunda');
    $this->patchJson("/api/conferencias/{$segunda}/itens/".itemDe($segunda, $bem)->id, ['resultado' => 'encontrado'])->assertOk();
    $this->postJson("/api/conferencias/{$segunda}/concluir", ['realizada_em' => '2026-11-05'])->assertOk();

    $this->getJson('/api/pendencias')->assertJsonCount(0, 'data');
    expect(itemDe($primeira, $bem)->resolucao)->toBe(ResolucaoPendencia::Localizado);
});

// --- Lista de bens e ficha --------------------------------------------------

test('a lista de bens filtra pela situação de conferência e mostra a conferência aberta', function () {
    gestoraConectada();
    $nunca = Bem::factory()->create();
    $encontrado = Bem::factory()->create(['data_ultima_conferencia' => '2026-10-01', 'resultado_ultima_conferencia' => 'encontrado']);
    $perdido = Bem::factory()->create(['data_ultima_conferencia' => '2026-10-01', 'resultado_ultima_conferencia' => 'nao_localizado']);
    $emAberto = Bem::factory()->create();
    gerarConferencia([$emAberto], 'Em andamento');

    expect($this->getJson('/api/bens?conferencia=nunca')->json('data.*.id'))->toEqualCanonicalizing([$nunca->id, $emAberto->id])
        ->and($this->getJson('/api/bens?conferencia=encontrado')->json('data.*.id'))->toBe([$encontrado->id])
        ->and($this->getJson('/api/bens?conferencia=nao_localizado')->json('data.*.id'))->toBe([$perdido->id])
        ->and($this->getJson('/api/bens?conferencia=em_aberto')->json('data.*.id'))->toBe([$emAberto->id]);

    $this->getJson("/api/bens?numero={$emAberto->numero_patrimonio}")
        ->assertJsonPath('data.0.em_conferencia.nome', 'Em andamento');

    $this->getJson('/api/bens?conferencia=xyz')->assertUnprocessable()->assertJsonValidationErrors('conferencia');
});

test('a ficha do bem traz o histórico das conferências concluídas', function () {
    gestoraConectada();
    $bem = Bem::factory()->create();
    $id = gerarConferencia([$bem], 'Semestre');
    $this->patchJson("/api/conferencias/{$id}/itens/".itemDe($id, $bem)->id, ['resultado' => 'encontrado'])->assertOk();
    $this->postJson("/api/conferencias/{$id}/concluir", ['realizada_em' => '2026-11-06'])->assertOk();

    $this->getJson("/api/bens/{$bem->id}")
        ->assertOk()
        ->assertJsonPath('data.data_ultima_conferencia', '2026-11-06')
        ->assertJsonPath('data.resultado_ultima_conferencia_rotulo', 'Encontrado')
        ->assertJsonPath('data.em_conferencia', null)
        ->assertJsonPath('data.historico_conferencias.0.conferencia', 'Semestre')
        ->assertJsonPath('data.historico_conferencias.0.realizada_em', '2026-11-06');
});

test('sala em que um bem foi encontrado numa conferência não pode ser excluída', function () {
    gestoraConectada();
    $bem = Bem::factory()->create();
    $id = gerarConferencia([$bem]);
    $this->patchJson("/api/conferencias/{$id}/itens/".itemDe($id, $bem)->id, ['resultado' => 'encontrado'])->assertOk();
    $sala = $bem->sala;
    $outra = Sala::factory()->create();
    $bem->update(['sala_id' => $outra->id]);

    $this->deleteJson("/api/salas/{$sala->id}")->assertConflict();
});

// --- Acesso (RN10) ----------------------------------------------------------

test('o perfil consulta acompanha, mas não gera, não lança, não conclui e não resolve', function () {
    $bem = Bem::factory()->create();
    $conferencia = Conferencia::factory()->create();
    $item = ConferenciaItem::factory()->for($conferencia)->for($bem)->create();
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/conferencias')->assertOk();
    $this->getJson("/api/conferencias/{$conferencia->id}")->assertOk();
    $this->getJson("/api/conferencias/{$conferencia->id}/itens")->assertOk();
    $this->getJson('/api/pendencias')->assertOk();

    $this->postJson('/api/conferencias', ['nome' => 'X', 'modo' => 'filtro'])->assertForbidden();
    $this->patchJson("/api/conferencias/{$conferencia->id}/itens/{$item->id}", ['resultado' => 'encontrado'])->assertForbidden();
    $this->postJson("/api/conferencias/{$conferencia->id}/encontrados", ['itens' => [$item->id]])->assertForbidden();
    $this->postJson("/api/conferencias/{$conferencia->id}/concluir", ['realizada_em' => '2026-11-06'])->assertForbidden();
    $this->postJson("/api/conferencias/{$conferencia->id}/cancelar")->assertForbidden();
    $this->postJson("/api/bens/{$bem->id}/pendencia", ['resolucao' => 'processo_administrativo', 'processo_numero' => '1'])->assertForbidden();

    expect($conferencia->fresh()->situacao)->toBe(SituacaoConferencia::Aberta);
});

test('sem login, nada de conferência', function () {
    $this->getJson('/api/conferencias')->assertUnauthorized();
    $this->getJson('/api/pendencias')->assertUnauthorized();
});
