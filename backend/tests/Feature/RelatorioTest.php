<?php

use App\Enums\StatusBem;
use App\Models\Andar;
use App\Models\Bem;
use App\Models\Sala;
use App\Models\TipoPatrimonio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/*
| Testes do relatório do levantamento (tarefa 2.10).
| RF22 dados de cada bem, RF23 agrupado por andar e sala, RF24 filtros,
| RF25 destaque do que mudou no período, RF26 planilha, RN05 baixado visível.
*/

uses(RefreshDatabase::class);

const PERIODO = 'inicio=2026-07-01&fim=2026-12-31';

beforeEach(function () {
    Sanctum::actingAs($this->usuario = User::factory()->create());
});

/** Muda um bem de sala ou de situação numa data, como se fosse pela API. */
function mudarEm(string $data, Closure $mudanca): void
{
    test()->travelTo(now()->parse($data.' 10:00'));
    $mudanca(test()->usuario);
    test()->travelBack();
}

// --- Parâmetros e acesso ----------------------------------------------------

test('o período é obrigatório e o fim não pode ser antes do início', function () {
    $this->getJson('/api/relatorio')
        ->assertUnprocessable()
        ->assertJsonPath('errors.inicio.0', 'Informe a data de início do período.')
        ->assertJsonPath('errors.fim.0', 'Informe a data de fim do período.');

    $this->getJson('/api/relatorio?inicio=2026-12-31&fim=2026-07-01')
        ->assertUnprocessable()
        ->assertJsonPath('errors.fim.0', 'A data de fim não pode ser antes da data de início.');
});

test('o perfil consulta gera o relatório e a planilha', function () {
    $this->getJson('/api/relatorio?'.PERIODO)->assertOk();
    baixarPlanilha();
});

test('sem login o relatório não responde', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/relatorio?'.PERIODO, ['Authorization' => ''])->assertUnauthorized();
});

// --- Conteúdo e agrupamento (RF22, RF23, RN05) ------------------------------

test('os bens vêm agrupados por andar e sala, na ordem da conferência', function () {
    $primeiro = Andar::factory()->create(['nome' => '1º andar', 'ordem' => 1]);
    $terreo = Andar::factory()->create(['nome' => 'Térreo', 'ordem' => 0]);
    $suporte = Sala::factory()->for($primeiro)->create(['nome' => 'Sala 05']);
    $recepcao = Sala::factory()->for($terreo)->create(['nome' => 'Recepção']);
    $arquivo = Sala::factory()->for($terreo)->create(['nome' => 'Arquivo']);
    Bem::factory()->for($suporte)->create();
    Bem::factory()->count(2)->for($recepcao)->create();
    Bem::factory()->for($arquivo)->baixado()->create();

    $resposta = $this->getJson('/api/relatorio?'.PERIODO)->assertOk();

    expect(collect($resposta->json('andares'))->pluck('nome')->all())->toBe(['Térreo', '1º andar'])
        ->and(collect($resposta->json('andares.0.salas'))->pluck('nome')->all())->toBe(['Arquivo', 'Recepção'])
        ->and($resposta->json('andares.0.total'))->toBe(3)
        ->and($resposta->json('andares.0.salas.1.total'))->toBe(2)
        ->and($resposta->json('totais.bens'))->toBe(4)
        ->and($resposta->json('totais.por_status.baixado'))->toBe(1)
        ->and($resposta->json('andares.0.salas.0.bens.0.baixado'))->toBeTrue();
});

test('cada bem traz número, código, tipo, descrição, marca, situação e última mudança', function () {
    $tipo = TipoPatrimonio::factory()->create(['nome' => 'Computador']);
    Bem::factory()->for($tipo, 'tipoPatrimonio')->create([
        'numero_patrimonio' => '1634456-1',
        'descricao' => 'preto',
        'marca' => 'Thermaltake',
        'status' => StatusBem::SemUso,
        'data_ultima_mudanca' => '2026-03-10',
    ]);

    $this->getJson('/api/relatorio?'.PERIODO)
        ->assertJsonPath('andares.0.salas.0.bens.0.numero_patrimonio', '1634456-1')
        ->assertJsonPath('andares.0.salas.0.bens.0.tipo', 'Computador')
        ->assertJsonPath('andares.0.salas.0.bens.0.descricao', 'preto')
        ->assertJsonPath('andares.0.salas.0.bens.0.marca', 'Thermaltake')
        ->assertJsonPath('andares.0.salas.0.bens.0.status_rotulo', 'Sem uso')
        ->assertJsonPath('andares.0.salas.0.bens.0.data_ultima_mudanca', '2026-03-10')
        ->assertJsonPath('andares.0.salas.0.bens.0.mudou_no_periodo', false);
});

// --- Destaque do que mudou (RF25) -------------------------------------------

test('aparece em destaque o bem que mudou de sala no período, com a sala de onde veio', function () {
    [$a, $b, $c] = Sala::factory()->count(3)->create();
    $bem = Bem::factory()->for($a)->create();
    mudarEm('2026-08-10', fn ($usuario) => $bem->mudarDeSala($b, $usuario));
    mudarEm('2026-09-15', fn ($usuario) => $bem->fresh()->mudarDeSala($c, $usuario));

    $item = $this->getJson('/api/relatorio?'.PERIODO)->json('andares.0.salas.0.bens.0');

    // Veio da primeira sala do período, e não da sala de passagem.
    expect($item['mudou_no_periodo'])->toBeTrue()
        ->and($item['mudou_de_sala_no_periodo'])->toBeTrue()
        ->and($item['veio_da_sala']['id'])->toBe($a->id);
});

test('aparece em destaque o bem que mudou de situação no período', function () {
    $bem = Bem::factory()->create();
    mudarEm('2026-10-01', fn ($usuario) => $bem->alterarStatus(StatusBem::Avariado, $usuario));

    $this->getJson('/api/relatorio?'.PERIODO)
        ->assertJsonPath('andares.0.salas.0.bens.0.mudou_no_periodo', true)
        ->assertJsonPath('andares.0.salas.0.bens.0.mudou_de_sala_no_periodo', false)
        ->assertJsonPath('andares.0.salas.0.bens.0.veio_da_sala', null)
        ->assertJsonPath('totais.mudaram_no_periodo', 1);
});

test('mudança fora do período não aparece em destaque', function () {
    [$a, $b] = Sala::factory()->count(2)->create();
    $bem = Bem::factory()->for($a)->create();
    mudarEm('2026-05-20', fn ($usuario) => $bem->mudarDeSala($b, $usuario));

    $this->getJson('/api/relatorio?'.PERIODO)
        ->assertJsonPath('totais.bens', 1)
        ->assertJsonPath('totais.mudaram_no_periodo', 0)
        ->assertJsonPath('andares.0.salas.0.bens.0.mudou_de_sala_no_periodo', false);
});

test('o primeiro e o último dia do período contam', function () {
    [$a, $b] = Sala::factory()->count(2)->create();
    $noInicio = Bem::factory()->for($a)->create();
    $noFim = Bem::factory()->for($a)->create();
    mudarEm('2026-07-01', fn ($usuario) => $noInicio->mudarDeSala($b, $usuario));
    test()->travelTo(now()->parse('2026-12-31 23:30'));
    $noFim->mudarDeSala($b, $this->usuario);
    test()->travelBack();

    $this->getJson('/api/relatorio?'.PERIODO)->assertJsonPath('totais.mudaram_de_sala_no_periodo', 2);
});

// --- Filtros (RF24) ---------------------------------------------------------

test('os filtros de andar, sala, tipo, situação e só mudanças se combinam', function () {
    $terreo = Andar::factory()->create();
    $primeiro = Andar::factory()->create();
    $recepcao = Sala::factory()->for($terreo)->create();
    $suporte = Sala::factory()->for($primeiro)->create();
    $computador = TipoPatrimonio::factory()->create();
    Bem::factory()->for($recepcao)->for($computador, 'tipoPatrimonio')->create();
    Bem::factory()->for($suporte)->for($computador, 'tipoPatrimonio')->avariado()->create();
    $mudou = Bem::factory()->for($suporte)->create();
    mudarEm('2026-09-01', fn ($usuario) => $mudou->alterarStatus(StatusBem::SemUso, $usuario));

    $total = fn (string $filtros) => $this->getJson('/api/relatorio?'.PERIODO.'&'.$filtros)->json('totais.bens');

    expect($total("andar_id={$primeiro->id}"))->toBe(2)
        ->and($total("sala_id={$recepcao->id}"))->toBe(1)
        ->and($total("tipo_patrimonio_id={$computador->id}"))->toBe(2)
        ->and($total('status=avariado,sem_uso'))->toBe(2)
        ->and($total("andar_id={$primeiro->id}&tipo_patrimonio_id={$computador->id}"))->toBe(1)
        ->and($total('apenas_mudancas=1'))->toBe(1);
});

// --- Planilha (RF26) --------------------------------------------------------

/**
 * Baixa a planilha e devolve o conteúdo de cada aba, como [nome => linhas].
 *
 * @return array<string, array<int, array<int, mixed>>>
 */
function baixarPlanilha(string $filtros = ''): array
{
    $resposta = test()->get('/api/relatorio/planilha?'.PERIODO.$filtros)
        ->assertOk()
        ->assertDownload('levantamento_2026-07-01_a_2026-12-31.xlsx');

    $caminho = $resposta->baseResponse->getFile()->getPathname();
    $leitor = new XlsxReader;
    $leitor->open($caminho);

    $abas = [];
    foreach ($leitor->getSheetIterator() as $aba) {
        foreach ($aba->getRowIterator() as $linha) {
            $abas[$aba->getName()][] = $linha->toArray();
        }
    }

    $leitor->close();
    unlink($caminho);

    return $abas;
}

test('a planilha é um Excel com a aba Principal e uma aba por sala', function () {
    $terreo = Andar::factory()->create(['nome' => 'Térreo', 'ordem' => 0]);
    $recepcao = Sala::factory()->for($terreo)->create(['nome' => 'Recepção']);
    $suporte = Sala::factory()->for($terreo)->create(['nome' => 'Sala 05 - Suporte TI']);
    Bem::factory()->count(2)->for($recepcao)->create();
    Bem::factory()->for($suporte)->create();

    $abas = baixarPlanilha();

    expect(array_keys($abas))->toBe(['Principal', 'Recepção', 'Sala 05 - Suporte TI'])
        ->and($abas['Principal'])->toHaveCount(4)
        ->and($abas['Recepção'])->toHaveCount(3)
        ->and($abas['Principal'][0][0])->toBe('Patrimônio')
        ->and(end($abas['Principal'][0]))->toBe('Conferido por');
});

test('cada linha traz os dados do bem, com o número de patrimônio como texto', function () {
    $sala = Sala::factory()->for(Andar::factory()->state(['nome' => 'Térreo']))->create(['nome' => 'Recepção']);
    Bem::factory()->for($sala)->for(TipoPatrimonio::factory()->state(['nome' => 'Cadeira']), 'tipoPatrimonio')->create([
        'numero_patrimonio' => '85015792',
        'descricao' => 'azul giratória',
        'data_ultima_mudanca' => '2026-08-05',
        'status' => StatusBem::SemUso,
    ]);
    Bem::factory()->for($sala)->semPlaqueta()->create();

    $linhas = baixarPlanilha()['Principal'];
    $comPlaqueta = collect($linhas)->firstWhere(0, '85015792');
    $semPlaqueta = collect($linhas)->firstWhere(0, 'sem patrimônio');

    expect($comPlaqueta)->not->toBeNull()
        ->and($comPlaqueta[2])->toBe('Cadeira')
        ->and($comPlaqueta[3])->toBe('azul giratória')
        ->and($comPlaqueta[5])->toBe('Recepção')
        ->and($comPlaqueta[6])->toBe('Térreo')
        ->and($comPlaqueta[7])->toBe('Sem uso')
        ->and($comPlaqueta[9]->format('Y-m-d'))->toBe('2026-08-05')
        ->and($comPlaqueta[10])->toBe('Sim')
        ->and($semPlaqueta[1])->toMatch('/^CP-\d{6}$/');
});

test('texto que começa com = sai como texto, e não como fórmula', function () {
    Bem::factory()->create(['observacao' => '=HYPERLINK("http://exemplo.com","clique")']);

    $linha = baixarPlanilha()['Principal'][1];

    expect($linha[13])->toBe('=HYPERLINK("http://exemplo.com","clique")');
});

test('salas com o mesmo nome em andares diferentes ganham abas diferentes', function () {
    Bem::factory()->for(Sala::factory()->for(Andar::factory()->state(['nome' => 'Térreo', 'ordem' => 0]))->state(['nome' => 'Sala 05']))->create();
    Bem::factory()->for(Sala::factory()->for(Andar::factory()->state(['nome' => '1º andar', 'ordem' => 1]))->state(['nome' => 'Sala 05']))->create();
    Bem::factory()->for(Sala::factory()->state(['nome' => 'Sala: arquivo [antigo] com um nome bem comprido demais']))->create();

    $nomes = array_keys(baixarPlanilha());

    expect($nomes)->toContain('Sala 05', 'Sala 05 - 1º andar')
        ->and(collect($nomes)->every(fn ($nome) => mb_strlen($nome) <= 31 && ! Str::contains($nome, ['\\', '/', '?', '*', ':', '[', ']'])))->toBeTrue();
});

test('a planilha respeita os filtros', function () {
    Bem::factory()->create();
    Bem::factory()->avariado()->create();

    expect(baixarPlanilha('&status=avariado')['Principal'])->toHaveCount(2);
});

test('relatório sem bens gera a planilha só com o cabeçalho', function () {
    $abas = baixarPlanilha();

    expect(array_keys($abas))->toBe(['Principal'])
        ->and($abas['Principal'])->toHaveCount(1);
});
