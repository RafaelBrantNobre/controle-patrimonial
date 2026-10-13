<?php

use Illuminate\Support\Facades\Route;

// O back-end é só API. As telas ficam no projeto frontend.
Route::get('/', function () {
    return response()->json([
        'aplicacao' => config('app.name'),
        'mensagem' => 'API no ar. As rotas ficam em /api.',
    ]);
});
