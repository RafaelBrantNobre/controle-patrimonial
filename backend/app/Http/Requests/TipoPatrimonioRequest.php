<?php

namespace App\Http\Requests;

use App\Models\TipoPatrimonio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/*
| Valida o cadastro e a edição de tipo de patrimônio (tarefa 2.5, RF08 e RF09).
| No cadastro (POST) o nome é obrigatório. Na edição (PUT ou PATCH) só é
| validado o que for enviado, então {"ativo": false} basta para inativar.
*/
class TipoPatrimonioRequest extends FormRequest
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
        $presenca = $this->isMethod('POST') ? 'required' : 'sometimes';
        $tipo = $this->route('tipo');

        return [
            // O banco compara sem diferenciar maiúsculas nem acentos, então
            // "Cadeira" e "cadeira" contam como o mesmo tipo.
            'nome' => [
                $presenca, 'string', 'max:80',
                Rule::unique('tipo_patrimonio', 'nome')->ignore($tipo instanceof TipoPatrimonio ? $tipo : null),
            ],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome do tipo de patrimônio.',
            'nome.string' => 'Informe o nome do tipo de patrimônio.',
            'nome.max' => 'O nome do tipo pode ter no máximo 80 caracteres.',
            'nome.unique' => 'Já existe um tipo de patrimônio com este nome.',
            'ativo.boolean' => 'Informe se o tipo está ativo com verdadeiro ou falso.',
        ];
    }
}
