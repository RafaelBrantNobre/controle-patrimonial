<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
| Dados do usuário que o front-end recebe. A senha nunca sai daqui (RNF04).
| O front usa "perfil" para esconder botões que o usuário não pode usar (RF03),
| mas quem bloqueia de verdade é o back-end (RN10).
|
| @mixin User
*/
class UsuarioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'email' => $this->email,
            'perfil' => $this->perfil->value,
            'perfil_rotulo' => $this->perfil->rotulo(),
            'pode_alterar' => $this->eAdministrador(),
        ];
    }
}
