<?php

namespace App\Http\Requests;

use App\Models\Sala;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/*
| Valida o cadastro e a edição de sala (tarefa 2.5, RF06 e RF07).
| No cadastro (POST) o nome é obrigatório. Na edição (PUT ou PATCH) só é
| validado o que for enviado, então {"ativo": false} basta para inativar.
| O nome da sala não se repete no prédio.
*/
class SalaRequest extends FormRequest
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
        $sala = $this->route('sala');

        return [
            'nome' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string',
                'max:120',
                Rule::unique('sala', 'nome')->ignore($sala instanceof Sala ? $sala->id : null),
            ],
            'descricao' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome da sala.',
            'nome.string' => 'Informe o nome da sala.',
            'nome.max' => 'O nome da sala pode ter no máximo 120 caracteres.',
            'nome.unique' => 'Já existe uma sala com este nome.',
            'descricao.string' => 'A descrição da sala deve ser um texto.',
            'descricao.max' => 'A descrição da sala pode ter no máximo 255 caracteres.',
            'ativo.boolean' => 'Informe se a sala está ativa com verdadeiro ou falso.',
        ];
    }
}
