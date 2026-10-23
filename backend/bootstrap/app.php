<?php

use App\Http\Middleware\ExigirPerfil;
use App\Http\Middleware\GarantirUsuarioAtivo;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Apelidos usados em routes/api.php (tarefa 2.4).
        $middleware->alias([
            'ativo' => GarantirUsuarioAtivo::class,
            'perfil' => ExigirPerfil::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Sem token, com token expirado ou revogado (RNF05, RNF08).
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Você não está conectado ou sua sessão expirou. Entre de novo para continuar.',
                ], 401);
            }
        });

        // Registro que não existe, como /api/salas/999 (tarefa 2.5, RNF08).
        // Sem isto, a resposta traria o nome da classe do model, em inglês.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'O registro procurado não foi encontrado. Ele pode ter sido excluído. Atualize a página e tente de novo.',
                ], 404);
            }
        });
    })->create();
