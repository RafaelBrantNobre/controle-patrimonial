<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/*
| Testes da estrutura do banco (tarefa 2.2).
| Conferem as regras que o próprio banco garante, sem depender do back-end.
*/

uses(RefreshDatabase::class);

function criarAndar(string $nome = 'Térreo', int $ordem = 0): int
{
    return DB::table('andar')->insertGetId(['nome' => $nome, 'ordem' => $ordem]);
}

function criarSala(int $andarId, string $nome = 'Sala 05 - Suporte TI'): int
{
    return DB::table('sala')->insertGetId(['andar_id' => $andarId, 'nome' => $nome]);
}

function criarTipo(string $nome = 'Computador'): int
{
    return DB::table('tipo_patrimonio')->insertGetId(['nome' => $nome]);
}

function criarBem(int $salaId, int $tipoId, array $dados = []): int
{
    return DB::table('bem')->insertGetId(array_merge([
        'codigo_interno' => 'CP-'.uniqid(),
        'sala_id' => $salaId,
        'tipo_patrimonio_id' => $tipoId,
    ], $dados));
}

test('um bem novo nasce em uso, com quantidade 1 e ativo nos cadastros', function () {
    $sala = criarSala(criarAndar());
    $bem = DB::table('bem')->find(criarBem($sala, criarTipo()));

    expect($bem->status)->toBe('em_uso')
        ->and((int) $bem->quantidade)->toBe(1)
        ->and($bem->numero_patrimonio)->toBeNull();

    expect((bool) DB::table('sala')->find($sala)->ativo)->toBeTrue();
});

test('o número de patrimônio não se repete (RN01)', function () {
    $sala = criarSala(criarAndar());
    $tipo = criarTipo();
    criarBem($sala, $tipo, ['numero_patrimonio' => '1634456-1']);

    criarBem($sala, $tipo, ['numero_patrimonio' => '1634456-1']);
})->throws(QueryException::class);

test('vários bens podem ficar sem número de patrimônio (PD07)', function () {
    $sala = criarSala(criarAndar());
    $tipo = criarTipo();
    criarBem($sala, $tipo);
    criarBem($sala, $tipo);

    expect(DB::table('bem')->whereNull('numero_patrimonio')->count())->toBe(2);
});

test('o código interno não se repete', function () {
    $sala = criarSala(criarAndar());
    $tipo = criarTipo();
    criarBem($sala, $tipo, ['codigo_interno' => 'CP-00001']);

    criarBem($sala, $tipo, ['codigo_interno' => 'CP-00001']);
})->throws(QueryException::class);

test('o mesmo nome de sala pode existir em andares diferentes, mas não no mesmo andar', function () {
    $terreo = criarAndar('Térreo', 0);
    $primeiro = criarAndar('1º andar', 1);
    criarSala($terreo, 'Sala 05');
    criarSala($primeiro, 'Sala 05');

    expect(DB::table('sala')->where('nome', 'Sala 05')->count())->toBe(2);

    criarSala($terreo, 'Sala 05');
})->throws(QueryException::class);

test('bem não pode apontar para uma sala que não existe (RN02)', function () {
    criarBem(999, criarTipo());
})->throws(QueryException::class);

test('andar com salas não pode ser apagado (RN08)', function () {
    $andar = criarAndar();
    criarSala($andar);

    DB::table('andar')->where('id', $andar)->delete();
})->throws(QueryException::class);

test('sala com bens não pode ser apagada (RN08)', function () {
    $sala = criarSala(criarAndar());
    criarBem($sala, criarTipo());

    DB::table('sala')->where('id', $sala)->delete();
})->throws(QueryException::class);

test('tipo com bens não pode ser apagado (RN08)', function () {
    $tipo = criarTipo();
    criarBem(criarSala(criarAndar()), $tipo);

    DB::table('tipo_patrimonio')->where('id', $tipo)->delete();
})->throws(QueryException::class);

test('o usuário é salvo com a senha em hash e perfil consulta por padrão (RNF04)', function () {
    $usuario = User::create(['nome' => 'Fulano', 'email' => 'fulano@example.com', 'senha' => 'segredo123']);
    $registro = DB::table('usuario')->find($usuario->id);

    expect($registro->senha)->not->toBe('segredo123')
        ->and(Hash::check('segredo123', $registro->senha))->toBeTrue()
        ->and($registro->perfil)->toBe('consulta')
        ->and($usuario->toArray())->not->toHaveKey('senha');
});

test('o e-mail do usuário não se repete', function () {
    User::factory()->create(['email' => 'repetido@example.com']);
    User::factory()->create(['email' => 'repetido@example.com']);
})->throws(QueryException::class);

test('a última mudança guarda quem a fez (RN09)', function () {
    $usuario = User::factory()->administrador()->create();
    $bem = criarBem(criarSala(criarAndar()), criarTipo(), [
        'data_ultima_mudanca' => '2026-10-16',
        'usuario_ultima_mudanca_id' => $usuario->id,
    ]);

    expect((int) DB::table('bem')->find($bem)->usuario_ultima_mudanca_id)->toBe($usuario->id);
});
