<?php

use App\Carga\InterpretadorDeLinha;
use App\Carga\LinhaDaPlanilha;

/*
| Testes das regras de interpretação da planilha (tarefa 2.12).
| Usam o mapeamento de verdade, database/carga/planilha.php.
*/

function interpretar(array $campos, string $fonte = 'principal', string $situacaoDasAbas = 'em_uso')
{
    $linha = new LinhaDaPlanilha(
        aba: $campos['aba'] ?? ($fonte === 'principal' ? 'Principal' : 'Suporte TI sala 5'),
        linha: $campos['linha'] ?? 10,
        patrimonio: $campos['patrimonio'] ?? '85015792',
        descricao: $campos['descricao'] ?? 'Cadeira',
        local: $campos['local'] ?? ($fonte === 'principal' ? 'Sala 05 - Suporte TI' : ''),
        estado: $campos['estado'] ?? ($fonte === 'principal' ? 'OK' : 'Bom'),
        conferente: $campos['conferente'] ?? '',
    );

    return (new InterpretadorDeLinha(require database_path('carga/planilha.php'), $fonte, $situacaoDasAbas))->interpretar($linha);
}

// --- Número de patrimônio ---------------------------------------------------

test('números de plaqueta nos formatos da planilha entram como estão', function (string $patrimonio) {
    $resultado = interpretar(['patrimonio' => $patrimonio]);

    expect($resultado->numeroPatrimonio)->toBe($patrimonio)
        ->and($resultado->avisos)->toBe([]);
})->with(['85015792', '900345721', '1634456-1']);

test('"sem patrimônio" deixa o bem sem número, sem aviso', function (string $patrimonio) {
    $resultado = interpretar(['patrimonio' => $patrimonio]);

    expect($resultado->foiRejeitada())->toBeFalse()
        ->and($resultado->numeroPatrimonio)->toBeNull()
        ->and($resultado->avisos)->toBe([]);
})->with(['sem patrimônio', 'Sem Patrimonio', '']);

test('número no meio de outro texto é separado, com aviso', function () {
    $serie = interpretar(['patrimonio' => 'Série C 85018848']);
    $convenio = interpretar(['patrimonio' => '900284315 (Convênio)']);

    expect($serie->numeroPatrimonio)->toBe('85018848')
        ->and($serie->avisos)->toHaveCount(1)
        ->and($convenio->numeroPatrimonio)->toBe('900284315')
        ->and($convenio->origem)->toBe('Convênio');
});

test('texto que não é plaqueta deixa o bem sem número e vai para a observação', function (string $patrimonio) {
    $resultado = interpretar(['patrimonio' => $patrimonio]);

    expect($resultado->foiRejeitada())->toBeFalse()
        ->and($resultado->numeroPatrimonio)->toBeNull()
        ->and($resultado->avisos[0])->toContain($patrimonio)
        ->and($resultado->observacao)->toContain($patrimonio);
})->with(['3229-8334', 'Ilegível', 'MON0001', 'pegar patrimonio']);

// --- Tipo, descrição, marca e origem ----------------------------------------

test('o tipo sai do começo da descrição, e o resto vira descrição, marca e origem', function (string $texto, string $tipo, ?string $descricao, ?string $marca, ?string $origem) {
    $resultado = interpretar(['descricao' => $texto]);

    expect($resultado->tipo)->toBe($tipo)
        ->and($resultado->descricao)->toBe($descricao)
        ->and($resultado->marca)->toBe($marca)
        ->and($resultado->origem)->toBe($origem);
})->with([
    ['Cadeira vermelha, convênio', 'Cadeira', 'vermelha', null, 'Convênio'],
    ['Ar condicionado Carrier', 'Ar condicionado', null, 'Carrier', null],
    ['Computador Thermaltake', 'Computador', null, 'Thermaltake', null],
    ['Gabinete antigo - Convênio E-TEC', 'Computador', 'antigo', null, 'Convênio E-TEC'],
    ['Cadeira azul giratória Convenio mct x sects', 'Cadeira', 'azul giratória', null, 'Convênio MCT x SECTES'],
    ['Mesa de som', 'Equipamento de áudio', null, null, null],
    ['Mesa L cinza', 'Mesa', 'L cinza', null, null],
    ['headset Logi', 'Fone de ouvido', null, 'Logitech', null],
    ['No-Break internet', 'Nobreak', 'internet', null, null],
]);

test('descrição sem tipo conhecido ou em branco rejeita a linha', function () {
    expect(interpretar(['descricao' => 'Conect'])->motivoDaRejeicao)->toContain('Tipo não reconhecido')
        ->and(interpretar(['descricao' => ''])->motivoDaRejeicao)->toContain('Descrição em branco');
});

// --- Local ------------------------------------------------------------------

test('o local da aba Principal vira a sala do mapeamento', function () {
    expect(interpretar(['local' => 'Sala 07'])->sala)->toBe('Sala 07 - LED')
        ->and(interpretar(['local' => 'Secretária'])->sala)->toBe('Secretaria UAB')
        ->and(interpretar(['local' => 'Sala 99'])->motivoDaRejeicao)->toContain('Local não reconhecido');
});

test('nas abas de sala, a sala vem do nome da aba', function () {
    expect(interpretar(['aba' => 'LED sala 07'], 'salas')->sala)->toBe('Sala 07 - LED');
});

// --- Estado -----------------------------------------------------------------

test('o estado da aba Principal vira situação de uso e conservação', function (string $estado, string $situacao, string $conservacao) {
    $resultado = interpretar(['estado' => $estado]);

    expect([$resultado->status, $resultado->estadoConservacao])->toBe([$situacao, $conservacao]);
})->with([
    ['OK', 'em_uso', 'indeterminado'],
    ['EM USO', 'em_uso', 'indeterminado'],
    ['Novo OK', 'em_uso', 'novo'],
    ['Sem uso', 'sem_uso', 'indeterminado'],
    ['Torta', 'em_uso', 'regular'],
    ['Defeito', 'sem_uso', 'pessimo'],
    ['Sucata', 'sem_uso', 'sucata'],
    ['Ok (não sei se funciona)', 'em_uso', 'indeterminado'],
]);

test('estado em branco ou desconhecido na aba Principal rejeita a linha', function () {
    expect(interpretar(['estado' => ''])->motivoDaRejeicao)->toContain('Estado em branco')
        ->and(interpretar(['estado' => 'Sala de projetos'])->motivoDaRejeicao)->toContain('Estado não reconhecido');
});

test('nas abas de sala o estado é a conservação, e a situação vem da opção', function () {
    $bom = interpretar(['estado' => 'Bom'], 'salas');
    $parado = interpretar(['estado' => 'Regular'], 'salas', 'sem_uso');
    $sucata = interpretar(['estado' => 'Sucata'], 'salas');
    $vazio = interpretar(['estado' => ''], 'salas');

    expect([$bom->status, $bom->estadoConservacao])->toBe(['em_uso', 'bom'])
        ->and([$parado->status, $parado->estadoConservacao])->toBe(['sem_uso', 'regular'])
        ->and([$sucata->status, $sucata->estadoConservacao])->toBe(['sem_uso', 'sucata'])
        ->and($vazio->estadoConservacao)->toBe('indeterminado');
});

// --- Linhas com vários itens (PD08) -----------------------------------------

test('linha que conta vários itens é rejeitada', function (string $descricao, string $estado) {
    expect(interpretar(['descricao' => $descricao, 'estado' => $estado])->motivoDaRejeicao)->toContain('vários itens');
})->with([
    ['Monitor', 'Em uso 20 Unidades'],
    ['Caixa de som', '78'],
    ['Caixa de som', '78.0'],
    ['Mouse', '18 novos'],
    ['Teclado', 'Usado 28'],
    ['Monitor', '17 sem pé'],
    ['3 servidores (T300, T410, HP)', 'OK'],
]);

// --- Observação -------------------------------------------------------------

test('a observação guarda de onde a linha veio e o texto original', function () {
    $resultado = interpretar(['aba' => 'Suporte TI sala 5', 'linha' => 42, 'descricao' => 'Cadeira azul', 'estado' => 'Regular', 'conferente' => 'Grupo 6'], 'salas');

    expect($resultado->observacao)
        ->toContain('aba "Suporte TI sala 5", linha 42')
        ->toContain('Descrição na planilha: "Cadeira azul"')
        ->toContain('Estado na planilha: "Regular"')
        ->toContain('Conferido por: Grupo 6');
});
