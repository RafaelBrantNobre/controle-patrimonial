<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/*
| Usuário do sistema. A classe mantém o nome User porque o Laravel a usa na
| autenticação (config/auth.php), mas os dados ficam na tabela "usuario".
*/
#[Fillable(['nome', 'email', 'senha', 'perfil', 'ativo'])]
#[Hidden(['senha'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'usuario';

    // A coluna da senha se chama "senha", e não "password" como o Laravel espera.
    protected $authPasswordName = 'senha';

    // A tabela não tem a coluna remember_token (o login será por token, tarefa 2.4).
    protected $rememberTokenName = '';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'senha' => 'hashed',
            'ativo' => 'boolean',
        ];
    }
}
