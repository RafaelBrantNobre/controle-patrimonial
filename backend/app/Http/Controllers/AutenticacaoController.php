<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/*
| Entrada e saída do sistema (tarefa 2.4).
| RF01 login, RF02 encerrar sessão, RNF05 token com validade e revogação.
*/
class AutenticacaoController extends Controller
{
    /**
     * POST /api/login
     * Confere e-mail e senha e devolve um token novo.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $usuario = User::where('email', $request->string('email')->toString())->first();

        // A mesma mensagem para e-mail inexistente e senha errada, para não
        // revelar quais e-mails têm cadastro.
        if (! $usuario || ! Hash::check($request->string('senha')->toString(), $usuario->senha)) {
            throw ValidationException::withMessages([
                'email' => 'E-mail ou senha incorretos. Confira os dados e tente de novo.',
            ]);
        }

        if (! $usuario->ativo) {
            return response()->json([
                'message' => 'Seu acesso ao sistema está desativado. Procure a gestora do setor.',
            ], 403);
        }

        $expiraEm = now()->addMinutes((int) config('sanctum.expiration'));
        $token = $usuario->createToken('navegador', ['*'], $expiraEm);

        return response()->json([
            'token' => $token->plainTextToken,
            'tipo' => 'Bearer',
            'expira_em' => $expiraEm->toIso8601String(),
            'usuario' => new UsuarioResource($usuario),
        ]);
    }

    /**
     * GET /api/usuario
     * Diz quem está conectado. O front chama ao abrir, para saber se o token
     * guardado ainda vale e qual é o perfil.
     */
    public function usuario(Request $request): JsonResponse
    {
        return response()->json([
            'usuario' => new UsuarioResource($request->user()),
        ]);
    }

    /**
     * POST /api/logout
     * Apaga o token usado nesta requisição. Os logins em outros computadores
     * continuam valendo.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sessão encerrada.',
        ]);
    }
}
