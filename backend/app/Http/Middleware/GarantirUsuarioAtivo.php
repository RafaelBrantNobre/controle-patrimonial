<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
| Barra quem foi desativado depois de já ter entrado no sistema.
| O token continua válido até expirar, então a checagem é feita em toda
| requisição. Ao barrar, o token é apagado.
|
| Uso nas rotas: ->middleware('ativo')
*/
class GarantirUsuarioAtivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && ! $usuario->ativo) {
            $usuario->currentAccessToken()?->delete();

            return response()->json([
                'message' => 'Seu acesso ao sistema está desativado. Procure a gestora do setor.',
            ], 403);
        }

        return $next($request);
    }
}
