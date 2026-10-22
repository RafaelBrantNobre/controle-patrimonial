<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/*
| Valida os dados enviados na tela de login (RF01).
*/
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:150'],
            'senha' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Mensagens em português e sem termos técnicos (RNF08).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Informe o seu e-mail.',
            'email.email' => 'Informe um e-mail válido, como nome@unimontes.br.',
            'email.max' => 'O e-mail pode ter no máximo 150 caracteres.',
            'senha.required' => 'Informe a sua senha.',
            'senha.max' => 'A senha pode ter no máximo 255 caracteres.',
        ];
    }
}
