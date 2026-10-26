<?php

use App\Enums\StatusBem;
use App\Models\Andar;
use App\Models\Bem;
use App\Models\Sala;
use App\Models\TipoPatrimonio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/*
| Testes do cadastro e da consulta de bens (tarefa 2.6).
| RF10 cadastrar, RF11 editar, RF12 localizar pelo número, RF13 filtrar,
| RF14 bens da sala, RF15 ficha, RN01 número único, RN05 baixado visível,
| RN07 sem exclusão, RN10 consulta não altera, PD07 código interno.
*/

uses(RefreshDatabase::class);

function entrarComoAdministrador(): User
{
    return Sanctum::actingAs(User::factory()->administrador()->create());
}

function entrarComoConsulta(): User
{
    return Sanctum::actingAs(User::factory()->create());
}

// --- Cadastro (RF10, PD07) --------------------------------------------------

test('o bem é cadastrado só com tipo e sala, e o resto fica em branco', function () {
    entrarComoAdministrador();
    $sala = Sala::factory()->create(['nome' => 'Sala 05']);
    $tipo = TipoPatrimonio::factory()->create(['nome' => 'Cadeira']);

    $this->postJson('/api/bens', ['tipo_patrimonio_id' => $tipo->id, 'sala_id' => $sala->id])
        ->assertCreated()
        ->assertJsonPath('data.numero_patrimonio', null)
        ->assertJsonPath('data.descricao', null)
        ->assertJsonPath('data.marca', null)
        ->assertJsonPath('data.origem', null)
        ->assertJsonPath('data.quantidade', 1)
        ->assertJsonPath('data.status', 'em_uso')
        ->assertJsonPath('data.status_rotulo', 'Em uso')
        ->assertJsonPath('data.baixado', false)
        ->assertJsonPath('data.tipo_patrimonio.nome', 'Cadeira')
        ->assertJsonPath('data.sala.nome', 'Sala 05');
});

test('o bem recebe um código interno gerado pelo sistema (PD07)', function () {
    entrarComoAdministrador();
    $dados = ['tipo_patrimonio_id' => TipoPatrimonio::factory()->create()->id, 'sala_id' => Sala::factory()->create()->id];

    $primeiro = $this->postJson('/api/bens', $dados)->json('data');
    $segundo = $this->postJson('/api/bens', [...$dados, 'codigo_interno' => 'ESCOLHIDO'])->json('data');

    expect($primeiro['codigo_interno'])->toBe(Bem::codigoParaId($primeiro['id']))
        ->and($primeiro['codigo_interno'])->toMatch('/^CP-\d{6}$/')
        ->and($segundo['codigo_interno'])->toBe(Bem::codigoParaId($segundo['id']));
});

test('o bem é cadastrado com todos os dados', function () {
    entrarComoAdministrador();
    $sala = Sala::factory()->create();
    $tipo = TipoPatrimonio::factory()->create();

    $this->postJson('/api/bens', [
        'numero_patrimonio' => '1634456-1',
        'tipo_patrimonio_id' => $tipo->id,
        'sala_id' => $sala->id,
        'descricao' => 'preto',
        'marca' => 'Thermaltake',
        'origem' => 'convênio MCT x SECTES',
        'quantidade' => 2,
        'status' => 'sem_uso',
        'observacao' => 'Estado na planilha: Sem uso',
    ])
        ->assertCreated()
        ->assertJsonPath('data.numero_patrimonio', '1634456-1')
        ->assertJsonPath('data.marca', 'Thermaltake')
        ->assertJsonPath('data.quantidade', 2)
        ->assertJsonPath('data.status', 'sem_uso');
});

test('tipo e sala são obrigatórios, com mensagens em português', function () {
    entrarComoAdministrador();

    $this->postJson('/api/bens', [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.tipo_patrimonio_id.0', 'Escolha o tipo do bem.')
        ->assertJsonPath('errors.sala_id.0', 'Escolha a sala onde o bem está.');
});

test('o número de patrimônio não se repete e a mensagem diz qual bem já o usa (RN01)', function () {
    entrarComoAdministrador();
    $existente = Bem::factory()->create(['numero_patrimonio' => '1634456-1']);

    $this->postJson('/api/bens', [
        'numero_patrimonio' => '1634456-1',
        'tipo_patrimonio_id' => TipoPatrimonio::factory()->create()->id,
        'sala_id' => Sala::factory()->create()->id,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.numero_patrimonio.0', "Já existe um bem com este número de patrimônio (código {$existente->codigo_interno}). Confira a plaqueta.");
});

test('vários bens podem ficar sem número de patrimônio', function () {
    entrarComoAdministrador();
    $dados = [
        'numero_patrimonio' => '',
        'tipo_patrimonio_id' => TipoPatrimonio::factory()->create()->id,
        'sala_id' => Sala::factory()->create()->id,
    ];

    $this->postJson('/api/bens', $dados)->assertCreated()->assertJsonPath('data.numero_patrimonio', null);
    $this->postJson('/api/bens', $dados)->assertCreated();
});

test('sala inativa e tipo inativo não recebem bens novos', function () {
    entrarComoAdministrador();

    $this->postJson('/api/bens', [
        'tipo_patrimonio_id' => TipoPatrimonio::factory()->inativo()->create()->id,
        'sala_id' => Sala::factory()->inativa()->create()->id,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.tipo_patrimonio_id.0', 'O tipo escolhido está inativo. Escolha outro tipo ou reative-o.')
        ->assertJsonPath('errors.sala_id.0', 'A sala escolhida está inativa e não recebe bens novos. Escolha outra sala ou reative-a.');
});

test('o bem não nasce baixado: a baixa é registrada depois', function () {
    entrarComoAdministrador();

    $this->postJson('/api/bens', [
        'tipo_patrimonio_id' => TipoPatrimonio::factory()->create()->id,
        'sala_id' => Sala::factory()->create()->id,
        'status' => 'baixado',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.status.0', 'Escolha a situação de uso do bem: em uso ou sem uso. A baixa é registrada depois do cadastro.');
});

// --- Edição (RF11) ----------------------------------------------------------

test('o administrador edita os dados do bem', function () {
    entrarComoAdministrador();
    $bem = Bem::factory()->create(['descricao' => 'azul', 'marca' => null]);
    $outroTipo = TipoPatrimonio::factory()->create(['nome' => 'Mesa']);

    $this->patchJson("/api/bens/{$bem->id}", ['descricao' => 'L cinza', 'marca' => 'HP', 'tipo_patrimonio_id' => $outroTipo->id])
        ->assertOk()
        ->assertJsonPath('data.descricao', 'L cinza')
        ->assertJsonPath('data.marca', 'HP')
        ->assertJsonPath('data.tipo_patrimonio.nome', 'Mesa')
        ->assertJsonPath('data.codigo_interno', $bem->codigo_interno);
});

test('a edição mantém o próprio número de patrimônio, mas não aceita o de outro bem', function () {
    entrarComoAdministrador();
    $bem = Bem::factory()->create(['numero_patrimonio' => '111']);
    Bem::factory()->create(['numero_patrimonio' => '222']);

    $this->putJson("/api/bens/{$bem->id}", ['numero_patrimonio' => '111'])->assertOk();
    $this->putJson("/api/bens/{$bem->id}", ['numero_patrimonio' => '222'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.numero_patrimonio.0', fn (string $mensagem) => str_starts_with($mensagem, 'Já existe um bem com este número'));
});

test('a edição não muda sala nem status, que são da tarefa 2.8', function () {
    entrarComoAdministrador();
    $bem = Bem::factory()->create();
    $outraSala = Sala::factory()->create();

    $this->patchJson("/api/bens/{$bem->id}", ['sala_id' => $outraSala->id, 'status' => 'sem_uso'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.sala_id.0', 'A sala não muda na edição do bem. Use a opção de mudar o bem de sala.')
        ->assertJsonPath('errors.status.0', 'A situação não muda na edição do bem. Use a opção de alterar a situação.');

    // O formulário completo, com a sala e o status atuais, é aceito.
    $this->putJson("/api/bens/{$bem->id}", ['sala_id' => $bem->sala_id, 'status' => 'em_uso', 'descricao' => 'nova'])->assertOk();

    expect($bem->fresh()->sala_id)->toBe($bem->sala_id)
        ->and($bem->fresh()->status)->toBe(StatusBem::EmUso);
});

test('bem de tipo que foi inativado continua editável', function () {
    entrarComoAdministrador();
    $tipo = TipoPatrimonio::factory()->inativo()->create();
    $bem = Bem::factory()->for($tipo, 'tipoPatrimonio')->create();

    $this->putJson("/api/bens/{$bem->id}", ['tipo_patrimonio_id' => $tipo->id, 'descricao' => 'preto'])->assertOk();
});

// --- Acesso (RN07, RN10) ----------------------------------------------------

test('o perfil consulta vê os bens, mas não cadastra nem edita', function () {
    entrarComoConsulta();
    $bem = Bem::factory()->create();

    $this->getJson('/api/bens')->assertOk();
    $this->getJson("/api/bens/{$bem->id}")->assertOk();
    $this->getJson("/api/salas/{$bem->sala_id}/bens")->assertOk();

    $this->postJson('/api/bens', ['tipo_patrimonio_id' => $bem->tipo_patrimonio_id, 'sala_id' => $bem->sala_id])->assertForbidden();
    $this->patchJson("/api/bens/{$bem->id}", ['descricao' => 'outra'])->assertForbidden();

    expect(Bem::count())->toBe(1);
});

test('não existe rota para excluir bem (RN07)', function () {
    entrarComoAdministrador();
    $bem = Bem::factory()->create();

    $this->deleteJson("/api/bens/{$bem->id}")->assertMethodNotAllowed();

    expect(Bem::find($bem->id))->not->toBeNull();
});

// --- Consulta (RF12, RF13, RF15) --------------------------------------------

test('o bem é localizado pelo número de patrimônio ou pelo código interno (RF12)', function () {
    entrarComoConsulta();
    $comPlaqueta = Bem::factory()->create(['numero_patrimonio' => '1634456-1']);
    $semPlaqueta = Bem::factory()->semPlaqueta()->create();
    Bem::factory()->count(3)->create();

    $this->getJson('/api/bens?numero=1634456-1')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $comPlaqueta->id);

    $this->getJson("/api/bens?numero={$semPlaqueta->codigo_interno}")
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $semPlaqueta->id);
});

test('a busca encontra parte do número, da descrição ou da marca', function () {
    entrarComoConsulta();
    Bem::factory()->create(['numero_patrimonio' => '1634456-1', 'descricao' => null, 'marca' => null]);
    Bem::factory()->create(['numero_patrimonio' => '999', 'descricao' => 'azul giratória', 'marca' => null]);
    Bem::factory()->create(['numero_patrimonio' => '888', 'descricao' => null, 'marca' => 'Lenovo']);

    expect($this->getJson('/api/bens?busca=34456')->json('meta.total'))->toBe(1)
        ->and($this->getJson('/api/bens?busca=girat')->json('meta.total'))->toBe(1)
        ->and($this->getJson('/api/bens?busca=lenovo')->json('data.0.marca'))->toBe('Lenovo');
});

test('os filtros de andar, sala, tipo e situação se combinam (RF13)', function () {
    entrarComoConsulta();
    $terreo = Andar::factory()->create();
    $primeiro = Andar::factory()->create();
    $recepcao = Sala::factory()->for($terreo)->create();
    $suporte = Sala::factory()->for($primeiro)->create();
    $laboratorio = Sala::factory()->for($primeiro)->create();
    $computador = TipoPatrimonio::factory()->create();
    $cadeira = TipoPatrimonio::factory()->create();

    Bem::factory()->for($recepcao)->for($computador, 'tipoPatrimonio')->create();
    Bem::factory()->for($suporte)->for($computador, 'tipoPatrimonio')->create();
    Bem::factory()->for($suporte)->for($computador, 'tipoPatrimonio')->semUso()->create();
    Bem::factory()->for($laboratorio)->for($cadeira, 'tipoPatrimonio')->baixado()->create();

    expect($this->getJson("/api/bens?andar_id={$primeiro->id}")->json('meta.total'))->toBe(3)
        ->and($this->getJson("/api/bens?sala_id={$suporte->id}")->json('meta.total'))->toBe(2)
        ->and($this->getJson("/api/bens?tipo_patrimonio_id={$computador->id}")->json('meta.total'))->toBe(3)
        ->and($this->getJson("/api/bens?andar_id={$primeiro->id}&tipo_patrimonio_id={$computador->id}&status=em_uso")->json('meta.total'))->toBe(1)
        ->and($this->getJson('/api/bens?status=sem_uso,baixado')->json('meta.total'))->toBe(2);
});

test('bem baixado continua na lista, sinalizado (RN05)', function () {
    entrarComoConsulta();
    Bem::factory()->baixado()->create();

    $this->getJson('/api/bens')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.baixado', true)
        ->assertJsonPath('data.0.status_rotulo', 'Baixado');
});

test('filtro de situação ou ordenação inválidos respondem com mensagem clara', function () {
    entrarComoConsulta();

    $this->getJson('/api/bens?status=perdido')
        ->assertUnprocessable()
        ->assertJsonPath('errors', fn (array $erros) => collect($erros)->flatten()->contains('A situação de uso do filtro deve ser em_uso, sem_uso ou baixado.'));

    $this->getJson('/api/bens?ordenar=preco')->assertUnprocessable();
});

test('a lista sai na ordem do levantamento e pode ser ordenada de outro jeito (RNF03)', function () {
    entrarComoConsulta();
    $terreo = Andar::factory()->create(['ordem' => 0]);
    $primeiro = Andar::factory()->create(['ordem' => 1]);
    $tipo = TipoPatrimonio::factory()->create();
    $noPrimeiro = Bem::factory()->for(Sala::factory()->for($primeiro))->for($tipo, 'tipoPatrimonio')->create(['numero_patrimonio' => '100']);
    $noTerreo = Bem::factory()->for(Sala::factory()->for($terreo))->for($tipo, 'tipoPatrimonio')->create(['numero_patrimonio' => '200']);

    $ids = fn (string $url) => collect($this->getJson($url)->json('data'))->pluck('id')->all();

    expect($ids('/api/bens'))->toBe([$noTerreo->id, $noPrimeiro->id])
        ->and($ids('/api/bens?ordenar=numero'))->toBe([$noPrimeiro->id, $noTerreo->id])
        ->and($ids('/api/bens?ordenar=numero&direcao=desc'))->toBe([$noTerreo->id, $noPrimeiro->id]);
});

test('a listagem é paginada', function () {
    entrarComoConsulta();
    Bem::factory()->count(25)->create();

    $this->getJson('/api/bens')
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('meta.total', 25);

    $this->getJson('/api/bens?pagina=2')->assertJsonCount(5, 'data');
});

test('a ficha traz todos os dados, com andar e quem fez a última mudança (RF15)', function () {
    entrarComoConsulta();
    $quemMudou = User::factory()->administrador()->create(['nome' => 'Rafael']);
    $sala = Sala::factory()->for(Andar::factory()->state(['nome' => '1º andar']))->create(['nome' => 'Sala 05']);
    $bem = Bem::factory()->for($sala)->create([
        'data_ultima_mudanca' => '2026-10-20',
        'usuario_ultima_mudanca_id' => $quemMudou->id,
    ]);

    $this->getJson("/api/bens/{$bem->id}")
        ->assertOk()
        ->assertJsonPath('data.codigo_interno', $bem->codigo_interno)
        ->assertJsonPath('data.sala.nome', 'Sala 05')
        ->assertJsonPath('data.sala.andar.nome', '1º andar')
        ->assertJsonPath('data.data_ultima_mudanca', '2026-10-20')
        ->assertJsonPath('data.usuario_ultima_mudanca.nome', 'Rafael');
});

// --- Bens de uma sala (RF14) ------------------------------------------------

test('a sala lista todos os seus bens, sem paginação, agrupados por tipo', function () {
    entrarComoConsulta();
    $sala = Sala::factory()->create(['nome' => 'Laboratório']);
    $cadeira = TipoPatrimonio::factory()->create(['nome' => 'Cadeira']);
    $computador = TipoPatrimonio::factory()->create(['nome' => 'Computador']);
    Bem::factory()->count(20)->for($sala)->for($computador, 'tipoPatrimonio')->create();
    Bem::factory()->count(15)->for($sala)->for($cadeira, 'tipoPatrimonio')->create();
    Bem::factory()->count(3)->create();

    $resposta = $this->getJson("/api/salas/{$sala->id}/bens")
        ->assertOk()
        ->assertJsonPath('total', 35)
        ->assertJsonPath('sala.nome', 'Laboratório')
        ->assertJsonCount(35, 'data');

    $tipos = collect($resposta->json('data'))->pluck('tipo_patrimonio.nome')->unique()->values()->all();

    expect($tipos)->toBe(['Cadeira', 'Computador']);
});
