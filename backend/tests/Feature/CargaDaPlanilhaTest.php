<?php

use App\Enums\EstadoConservacao;
use App\Enums\StatusBem;
use App\Models\Andar;
use App\Models\Bem;
use App\Models\Sala;
use App\Models\TipoPatrimonio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;

/*
| Testes do comando patrimonio:carregar-planilha (tarefa 2.12, RF28, RF29,
| RN11). Cada teste monta uma planilha pequena, no formato da planilha de
| novembro de 2024: colunas Patrimônio, Descrição, Local, Estado e Nome.
*/

uses(RefreshDatabase::class);

// Cada teste grava os relatórios numa pasta temporária própria, e não em
// storage/app/carga, para nunca apagar o relatório de uma carga de verdade.
beforeEach(function () {
    $this->pastaDeTeste = sys_get_temp_dir().DIRECTORY_SEPARATOR.'carga-teste-'.uniqid();
    $this->app->useStoragePath($this->pastaDeTeste);
});

afterEach(function () {
    File::deleteDirectory($this->pastaDeTeste);
});

/**
 * @param  array<string, array<int, array<int, mixed>>>  $abas  nome da aba => linhas, sem o cabeçalho
 */
function planilha(array $abas): string
{
    $caminho = tempnam(sys_get_temp_dir(), 'planilha_').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($caminho);

    $primeira = true;
    foreach ($abas as $nome => $linhas) {
        $aba = $primeira ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
        $aba->setName($nome);
        $primeira = false;

        $writer->addRow(Row::fromValues(['Patrimonio ', 'Descrição do patrimonio ', 'Local do patrimonio ', 'Estado do patrimonio ', 'Nome']));
        foreach ($linhas as $linha) {
            $writer->addRow(Row::fromValues($linha));
        }
    }

    $writer->close();

    return $caminho;
}

function planilhaPadrao(): string
{
    return planilha([
        'Principal ' => [
            [85015792.0, 'Bancada', 'Videoconferência ', 'Ok', ''],               // número como o Excel guarda
            ['1634456-1', 'Computador Thermaltake', 'Sala 05 - Suporte TI', 'OK', ''],
            ['1634456-1', 'Computador', 'Sala de equipamentos', 'OK', ''],      // repetido (RN11)
            ['sem patrimônio', 'Cadeira azul, convênio', 'Sala 07', 'Defeito', ''],
            ['Sem patrimonio', 'Monitor', 'Almoxarifado', 'Em uso 20 Unidades', ''], // vários itens
            ['900258748', 'Conect', 'Videoconferência', 'Ok', ''],             // tipo desconhecido
        ],
        'Suporte TI sala 5' => [
            ['16339827', 'No break APC', '', 'Bom', 'Grupo 6'],
            ['sem patrimônio', 'Gabinete antigo', '', 'Sucata', 'Grupo 6'],
        ],
        'GRUPOS' => [['GRUPO 1', 'Wallison e Henrique']],
    ]);
}

// --- Carga a partir da aba Principal ----------------------------------------

test('a carga da aba Principal cria salas, tipos e bens, e rejeita o que não dá para importar', function () {
    $this->artisan('patrimonio:carregar-planilha', ['arquivo' => planilhaPadrao(), '--fonte' => 'principal'])
        ->expectsOutputToContain('Carga gravada no banco.')
        ->assertSuccessful();

    expect(Bem::count())->toBe(3)
        ->and(Andar::pluck('nome')->all())->toBe(['Andar a definir'])
        ->and(Sala::pluck('nome')->sort()->values()->all())->toBe(['Sala 01 - Videoconferência', 'Sala 05 - Suporte TI', 'Sala 07 - LED'])
        ->and(TipoPatrimonio::pluck('nome')->sort()->values()->all())->toBe(['Bancada', 'Cadeira', 'Computador']);

    $bancada = Bem::where('numero_patrimonio', '85015792')->sole();
    $cadeira = Bem::whereNull('numero_patrimonio')->sole();

    expect($bancada->codigo_interno)->toMatch('/^CP-\d{6}$/')
        ->and($bancada->status)->toBe(StatusBem::EmUso)
        ->and($cadeira->descricao)->toBe('azul')
        ->and($cadeira->origem)->toBe('Convênio')
        ->and($cadeira->status)->toBe(StatusBem::SemUso)
        ->and($cadeira->estado_conservacao)->toBe(EstadoConservacao::Pessimo)
        ->and($cadeira->observacao)->toContain('aba "Principal", linha 5');
});

test('o número repetido entra só na primeira linha (RN11)', function () {
    $this->artisan('patrimonio:carregar-planilha', ['arquivo' => planilhaPadrao(), '--fonte' => 'principal'])->assertSuccessful();

    $computador = Bem::where('numero_patrimonio', '1634456-1')->sole();

    expect($computador->marca)->toBe('Thermaltake')
        ->and($computador->sala->nome)->toBe('Sala 05 - Suporte TI');
});

test('o relatório da carga lista importados, rejeitados com o motivo e o resumo (RF29)', function () {
    $this->artisan('patrimonio:carregar-planilha', ['arquivo' => planilhaPadrao(), '--fonte' => 'principal'])->assertSuccessful();

    $arquivos = File::glob(storage_path('app/carga/relatorio_carga_principal_*.xlsx'));
    expect($arquivos)->toHaveCount(1);

    $leitor = new Reader;
    $leitor->open($arquivos[0]);
    $abas = [];
    foreach ($leitor->getSheetIterator() as $aba) {
        foreach ($aba->getRowIterator() as $linha) {
            $abas[$aba->getName()][] = $linha->toArray();
        }
    }
    $leitor->close();

    $motivos = array_column(array_slice($abas['Rejeitados'], 1), 6);

    expect(array_keys($abas))->toBe(['Resumo', 'Importados', 'Rejeitados', 'Avisos'])
        ->and($abas['Importados'])->toHaveCount(4)
        ->and($abas['Rejeitados'])->toHaveCount(4)
        ->and(collect($motivos)->contains(fn ($m) => str_contains($m, 'repetido: já aparece em Principal, linha 3')))->toBeTrue()
        ->and(collect($motivos)->contains(fn ($m) => str_contains($m, 'vários itens')))->toBeTrue()
        ->and(collect($motivos)->contains(fn ($m) => str_contains($m, 'Tipo não reconhecido')))->toBeTrue();
});

// --- Carga a partir das abas de sala ----------------------------------------

test('a carga das abas de sala usa o nome da aba como sala e o estado como conservação', function () {
    $this->artisan('patrimonio:carregar-planilha', ['arquivo' => planilhaPadrao(), '--fonte' => 'salas'])->assertSuccessful();

    $nobreak = Bem::where('numero_patrimonio', '16339827')->sole();
    $gabinete = Bem::whereNull('numero_patrimonio')->sole();

    expect(Bem::count())->toBe(2)
        ->and($nobreak->sala->nome)->toBe('Sala 05 - Suporte TI')
        ->and($nobreak->tipoPatrimonio->nome)->toBe('Nobreak')
        ->and($nobreak->marca)->toBe('APC')
        ->and($nobreak->estado_conservacao)->toBe(EstadoConservacao::Bom)
        ->and($nobreak->status)->toBe(StatusBem::EmUso)
        ->and($nobreak->observacao)->toContain('Conferido por: Grupo 6')
        ->and($gabinete->status)->toBe(StatusBem::SemUso)
        ->and($gabinete->estado_conservacao)->toBe(EstadoConservacao::Sucata);
});

test('aba de sala que o mapeamento não conhece para a carga antes de começar', function () {
    $arquivo = planilha(['Principal' => [], 'Sala nova 99' => [['123', 'Mesa', '', 'Bom', '']]]);

    $this->artisan('patrimonio:carregar-planilha', ['arquivo' => $arquivo, '--fonte' => 'salas'])
        ->expectsOutputToContain('Sala nova 99')
        ->assertFailed();

    expect(Bem::count())->toBe(0);
});

// --- Simulação, recarga e proteções -----------------------------------------

test('a simulação gera o relatório e não grava nada', function () {
    $this->artisan('patrimonio:carregar-planilha', ['arquivo' => planilhaPadrao(), '--fonte' => 'principal', '--simular' => true])
        ->expectsOutputToContain('SIMULAÇÃO: nada foi gravado no banco.')
        ->assertSuccessful();

    expect(Bem::count())->toBe(0)
        ->and(Sala::count())->toBe(0)
        ->and(TipoPatrimonio::count())->toBe(0)
        ->and(File::glob(storage_path('app/carga/*_simulacao.xlsx')))->toHaveCount(1);
});

test('com bens no sistema, a carga só roda com --do-zero ou --simular', function () {
    $this->artisan('patrimonio:carregar-planilha', ['arquivo' => planilhaPadrao(), '--fonte' => 'principal'])->assertSuccessful();

    // Rodar de novo por cima duplicaria a cadeira sem plaqueta.
    $this->artisan('patrimonio:carregar-planilha', ['arquivo' => planilhaPadrao(), '--fonte' => 'principal'])
        ->expectsOutputToContain('O sistema já tem 3 bens')
        ->assertFailed();

    $this->artisan('patrimonio:carregar-planilha', ['arquivo' => planilhaPadrao(), '--fonte' => 'principal', '--simular' => true])
        ->assertSuccessful();

    expect(Bem::count())->toBe(3);
});

test('um número que já está no sistema é recusado (RN01)', function () {
    $this->artisan('patrimonio:carregar-planilha', ['arquivo' => planilhaPadrao(), '--fonte' => 'principal'])->assertSuccessful();

    // A simulação por cima enxerga os números já cadastrados.
    $this->artisan('patrimonio:carregar-planilha', ['arquivo' => planilhaPadrao(), '--fonte' => 'principal', '--simular' => true])
        ->expectsOutputToContain('já está cadastrado no sistema')
        ->assertSuccessful();
});

test('--do-zero apaga o patrimônio e carrega de novo', function () {
    Bem::factory()->count(3)->create();

    $this->artisan('patrimonio:carregar-planilha', ['arquivo' => planilhaPadrao(), '--fonte' => 'principal', '--do-zero' => true])
        ->assertSuccessful();

    expect(Bem::count())->toBe(3)
        ->and(Sala::count())->toBe(3);
});

test('--do-zero não roda em produção', function () {
    Bem::factory()->create();
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('patrimonio:carregar-planilha', ['arquivo' => planilhaPadrao(), '--fonte' => 'principal', '--do-zero' => true])
        ->expectsOutputToContain('não roda em produção')
        ->assertFailed();

    expect(Bem::count())->toBe(1);
});

test('a fonte é obrigatória e o arquivo precisa existir', function () {
    $this->artisan('patrimonio:carregar-planilha', ['arquivo' => planilhaPadrao()])
        ->expectsOutputToContain('Informe a fonte')
        ->assertFailed();

    $this->artisan('patrimonio:carregar-planilha', ['arquivo' => 'nao-existe.xlsx', '--fonte' => 'principal'])
        ->expectsOutputToContain('Arquivo não encontrado')
        ->assertFailed();
});
