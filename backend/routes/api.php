<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
| Rotas da API. Todas ficam com o prefixo /api.
| Exemplo: a rota "/status" abaixo responde em http://localhost:8000/api/status
*/

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
