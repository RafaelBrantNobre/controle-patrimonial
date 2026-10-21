<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

return [

    /*
    | Configuração da autenticação por token (tarefa 2.4).
    |
    | O front-end recebe um token no login e o envia em toda requisição, no
    | cabeçalho "Authorization: Bearer <token>". Não usamos cookie de sessão,
    | por isso a lista de domínios "stateful" fica vazia.
    */

    'stateful' => [],

    'guard' => [],

    /*
    | Validade do token, em minutos (RNF05). Passado esse tempo, o usuário
    | precisa entrar de novo. O padrão de 480 minutos cobre um turno de 8 horas.
    | Ajuste em SANCTUM_EXPIRACAO_MINUTOS no .env.
    */

    'expiration' => (int) env('SANCTUM_EXPIRACAO_MINUTOS', 480),

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],

];
