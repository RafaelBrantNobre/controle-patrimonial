<?php

namespace App\Http\Middleware;

use App\Enums\PerfilUsuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
| Libera a rota só para os perfis informados (RF03, RN10).
| O perfil é lido do banco a cada requisição, então uma troca de perfil vale
| na hora, sem precisar sair e entrar de novo.
|
| Uso nas rotas: ->middleware('perfil:administrador')
| Mais de um perfil: ->middleware('perfil:administrador,consulta')
*/
class ExigirPerfil
{
    public function handle(Request $request, Closure $next, string ...$perfis): Response
    {
        $usuario = $request->user();

        $permitidos = array_map(fn (string $perfil) => PerfilUsuario::from($perfil), $perfis);

        if (! $usuario || ! in_array($usuario->perfil, $permitidos, true)) {
            return response()->json([
                'message' => 'Seu perfil não permite esta ação. Se precisar dela, procure a gestora do setor.',
            ], 403);
        }

        return $next($request);
    }
}
