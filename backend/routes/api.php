<?php

use App\Http\Controllers\AndarController;
use App\Http\Controllers\AutenticacaoController;
use App\Http\Controllers\BemController;
use App\Http\Controllers\MudancaDeBemController;
use App\Http\Controllers\RelatorioController;
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
    Route::apiResource('andares', AndarController::class)->only(['index', 'show'])->parameters(['andares' => 'andar']);
    Route::apiResource('salas', SalaController::class)->only(['index', 'show'])->parameters(['salas' => 'sala']);
    Route::apiResource('tipos-patrimonio', TipoPatrimonioController::class)->only(['index', 'show'])->parameters(['tipos-patrimonio' => 'tipo']);

    // Bens (2.6): consultar, filtrar, ver a ficha e listar os bens de uma sala.
    Route::apiResource('bens', BemController::class)->only(['index', 'show'])->parameters(['bens' => 'bem']);
    Route::get('/salas/{sala}/bens', [BemController::class, 'daSala']);

    // Relatório do levantamento (2.10): na tela e em planilha.
    Route::get('/relatorio', [RelatorioController::class, 'index']);
    Route::get('/relatorio/planilha', [RelatorioController::class, 'planilha']);

    // 3. Alteração: só administrador -----------------------------------------
    Route::middleware('perfil:administrador')->group(function () {

        // Locais e tipos (2.5): cadastrar, editar, inativar e excluir.
        // Inativar e reativar é um PATCH com {"ativo": false} ou {"ativo": true}.
        Route::apiResource('andares', AndarController::class)->except(['index', 'show'])->parameters(['andares' => 'andar']);
        Route::apiResource('salas', SalaController::class)->except(['index', 'show'])->parameters(['salas' => 'sala']);
        Route::apiResource('tipos-patrimonio', TipoPatrimonioController::class)->except(['index', 'show'])->parameters(['tipos-patrimonio' => 'tipo']);

        // Bens (2.6): cadastrar e editar. Não há exclusão: a saída é a baixa (RN07).
        Route::apiResource('bens', BemController::class)->only(['store', 'update'])->parameters(['bens' => 'bem']);

        // Mudança de sala e de status, incluindo a baixa (2.8).
        Route::patch('/bens/{bem}/sala', [MudancaDeBemController::class, 'mudarSala']);
        Route::patch('/bens/{bem}/status', [MudancaDeBemController::class, 'alterarStatus']);

        // Importação da planilha (2.12) entra aqui.

    });
});
