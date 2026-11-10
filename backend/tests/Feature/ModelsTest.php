<?php

use App\Enums\PerfilUsuario;
use App\Enums\StatusBem;
use App\Models\Bem;
use App\Models\Sala;
use App\Models\TipoPatrimonio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Testes dos models (tarefa 2.2): relacionamentos, conversões de tipo e filtros.
*/

uses(RefreshDatabase::class);

test('o bem chega ao tipo e à sala pelos relacionamentos', function () {
    $sala = Sala::factory()->create(['nome' => 'Sala 05 - Suporte TI']);
    $tipo = TipoPatrimonio::factory()->create(['nome' => 'Computador']);
    $bem = Bem::factory()->for($sala)->for($tipo)->create();

    expect($bem->tipoPatrimonio->nome)->toBe('Computador')
        ->and($bem->sala->nome)->toBe('Sala 05 - Suporte TI')
        ->and($sala->bens)->toHaveCount(1)
        ->and($tipo->bens)->toHaveCount(1);
});

test('o bem pode ficar sem localização', function () {
    $bem = Bem::factory()->create(['sala_id' => null]);

    expect($bem->fresh()->sala)->toBeNull();
});

test('o status do bem vira o enum StatusBem', function () {
    $bem = Bem::factory()->baixado()->create();

    expect($bem->fresh()->status)->toBe(StatusBem::Baixado)
        ->and($bem->estaBaixado())->toBeTrue()
        ->and($bem->status->rotulo())->toBe('Baixado')
        ->and($bem->baixa_data)->not->toBeNull();
});

test('um bem novo nasce em uso e com quantidade 1 mesmo antes de ser salvo', function () {
    $bem = new Bem;

    expect($bem->status)->toBe(StatusBem::EmUso)
        ->and($bem->quantidade)->toBe(1);
});

test('status fora da lista é recusado antes de chegar ao banco', function () {
    Bem::factory()->create(['status' => 'perdido']);
})->throws(ValueError::class);

test('o bem guarda quem fez a última mudança (RN09)', function () {
    $usuario = User::factory()->administrador()->create(['nome' => 'Fulana']);
    $bem = Bem::factory()->create([
        'data_ultima_mudanca' => '2026-10-20',
        'usuario_ultima_mudanca_id' => $usuario->id,
    ]);

    expect($bem->usuarioUltimaMudanca->nome)->toBe('Fulana')
        ->and($bem->data_ultima_mudanca->format('d/m/Y'))->toBe('20/10/2026');
});

test('os filtros de ativos deixam de fora o que foi inativado (RN08)', function () {
    Sala::factory()->create();
    Sala::factory()->inativa()->create();
    TipoPatrimonio::factory()->create();
    TipoPatrimonio::factory()->inativo()->create();

    expect(Sala::ativas()->count())->toBe(1)
        ->and(TipoPatrimonio::ativos()->count())->toBe(1);
});

test('o perfil do usuário vira o enum PerfilUsuario', function () {
    $admin = User::factory()->administrador()->create();
    $consulta = User::factory()->create();

    expect($admin->fresh()->perfil)->toBe(PerfilUsuario::Administrador)
        ->and($admin->eAdministrador())->toBeTrue()
        ->and($consulta->eAdministrador())->toBeFalse();
});
