<?php

use App\Http\Controllers\AutenticacaoController;
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

    // Consultas de locais, tipos e bens (2.5 e 2.6) e relatório (2.10) entram aqui.
    // Exemplo: Route::get('/bens', [BemController::class, 'index']);

    // 3. Alteração: só administrador -----------------------------------------
    Route::middleware('perfil:administrador')->group(function () {

        // Cadastros e edições (2.5 e 2.6), mudança de sala, status e baixa (2.8)
        // e importação da planilha (2.12) entram aqui.
        // Exemplo: Route::post('/bens', [BemController::class, 'store']);

    });
});
