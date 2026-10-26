<?php

return [

    /*
    | CORS: define quais endereços podem chamar a API pelo navegador.
    | Em desenvolvimento, só o front-end local (FRONTEND_URL no .env).
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(array_map('trim', explode(',', (string) env('FRONTEND_URL', 'http://localhost:5173')))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Deixa o front ler o nome do arquivo da planilha do relatório (tarefa 2.10).
    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    'supports_credentials' => false,

];
