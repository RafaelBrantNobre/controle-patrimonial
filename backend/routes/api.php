<?php

use App\Http\Controllers\AutenticacaoController;
use App\Http\Controllers\BemController;
use App\Http\Controllers\ConferenciaController;
use App\Http\Controllers\MudancaDeBemController;
use App\Http\Controllers\OcorrenciaController;
use App\Http\Controllers\PendenciaController;
use App\Http\Controllers\SalaController;
use App\Http\Controllers\TipoPatrimonioController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
| Rotas da API. Todas ficam com o prefixo /api.
| Exemplo: a rota "/status" abaixo responde em http://localhost:8000/api/status
|
| Organização do acesso (tarefa 2.4, RF03 e RN10):
|   1. Rotas públicas: não pedem login.
|   2. Rotas de leitura: qualquer usuário conectado (administrador e consulta).
|   3. Rotas de alteração: só o perfil administrador.
| Toda rota nova entra num desses três grupos. Na dúvida, no grupo 3.
*/

// ---------------------------------------------------------------------------
// 1. Públicas
// ---------------------------------------------------------------------------

// Verificação do ambiente: confirma que a API está no ar e que o banco responde.
Route::get('/status', function () {
    $conectado = true;
    $nome = null;

    try {
        DB::connection()->getPdo();
        $nome = DB::connection()->getDatabaseName();
    } catch (Throwable $e) {
        report($e);
        $conectado = false;
    }

    return response()->json([
        'aplicacao' => config('app.name'),
        'ambiente' => config('app.env'),
        'banco' => [
            'conectado' => $conectado,
            'conexao' => config('database.default'),
            'nome' => $nome,
        ],
        'horario' => now()->toIso8601String(),
    ]);
});

// Login (RF01). Limitado a 5 tentativas por minuto.
Route::post('/login', [AutenticacaoController::class, 'login'])
    ->middleware('throttle:login');

// ---------------------------------------------------------------------------
// Daqui para baixo, só com token válido de usuário ativo.
// ---------------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'ativo'])->group(function () {

    // 2. Leitura: administrador e consulta -----------------------------------

    Route::get('/usuario', [AutenticacaoController::class, 'usuario']);
    Route::post('/logout', [AutenticacaoController::class, 'logout']); // RF02

    // Locais e tipos (2.5): listar e ver um.
    Route::apiResource('salas', SalaController::class)->only(['index', 'show'])->parameters(['salas' => 'sala']);
    Route::apiResource('tipos-patrimonio', TipoPatrimonioController::class)->only(['index', 'show'])->parameters(['tipos-patrimonio' => 'tipo']);

    // Bens (2.6): consultar, filtrar, ver a ficha e listar os bens de uma sala.
    Route::apiResource('bens', BemController::class)->only(['index', 'show'])->parameters(['bens' => 'bem']);
    Route::get('/salas/{sala}/bens', [BemController::class, 'daSala']);

    // Ocorrências de todos os bens, com os mesmos filtros da lista de bens.
    Route::get('/ocorrencias', [OcorrenciaController::class, 'index']);

    // Conferência dos bens: acompanhar as conferências e as pendências.
    Route::get('/conferencias', [ConferenciaController::class, 'index']);
    Route::get('/conferencias/{conferencia}', [ConferenciaController::class, 'show']);
    Route::get('/conferencias/{conferencia}/itens', [ConferenciaController::class, 'itens']);
    Route::get('/pendencias', [PendenciaController::class, 'index']);

    // 3. Alteração: só administrador -----------------------------------------
    Route::middleware('perfil:administrador')->group(function () {

        // Locais e tipos (2.5): cadastrar, editar, inativar e excluir.
        // Inativar e reativar é um PATCH com {"ativo": false} ou {"ativo": true}.
        Route::apiResource('salas', SalaController::class)->except(['index', 'show'])->parameters(['salas' => 'sala']);
        Route::apiResource('tipos-patrimonio', TipoPatrimonioController::class)->except(['index', 'show'])->parameters(['tipos-patrimonio' => 'tipo']);

        // Bens (2.6): cadastrar e editar. Não há exclusão: a saída é a baixa (RN07).
        Route::apiResource('bens', BemController::class)->only(['store', 'update'])->parameters(['bens' => 'bem']);

        // Mudança de sala e de status, incluindo a baixa (2.8).
        Route::patch('/bens/{bem}/sala', [MudancaDeBemController::class, 'mudarSala']);
        Route::patch('/bens/{bem}/status', [MudancaDeBemController::class, 'alterarStatus']);
        Route::patch('/bens/{bem}/conservacao', [MudancaDeBemController::class, 'alterarConservacao']);

        // Conferência dos bens: gerar, lançar a folha, concluir e cancelar.
        Route::post('/conferencias', [ConferenciaController::class, 'store']);
        Route::patch('/conferencias/{conferencia}/itens/{item}', [ConferenciaController::class, 'lancar']);
        Route::put('/conferencias/{conferencia}/itens', [ConferenciaController::class, 'salvarItens']);
        Route::post('/conferencias/{conferencia}/encontrados', [ConferenciaController::class, 'marcarEncontrados']);
        Route::post('/conferencias/{conferencia}/concluir', [ConferenciaController::class, 'concluir']);
        Route::post('/conferencias/{conferencia}/cancelar', [ConferenciaController::class, 'cancelar']);

        // Pendência do bem não localizado: localizado depois ou processo administrativo.
        Route::post('/bens/{bem}/pendencia', [PendenciaController::class, 'resolver']);

        // Importação da planilha (2.12) entra aqui.

    });
});
