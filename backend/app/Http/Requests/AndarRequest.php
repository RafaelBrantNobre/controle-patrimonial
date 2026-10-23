<?php

namespace App\Http\Requests;

use App\Models\Andar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/*
| Valida o cadastro e a edição de andar (tarefa 2.5, RF05 e RF07).
| No cadastro (POST) o nome é obrigatório. Na edição (PUT ou PATCH) só é
| validado o que for enviado, então {"ativo": false} basta para inativar.
*/
class AndarRequest extends FormRequest
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

        return [
            'nome' => [$presenca, 'string', 'max:60', Rule::unique('andar', 'nome')->ignore($this->andarAtual())],
            'ordem' => ['sometimes', 'integer', 'between:-99,999'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Andar com sala ativa não pode ser inativado. Sem essa regra, uma sala
     * ativa ficaria num andar inativo e continuaria recebendo bens.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $andar = $this->andarAtual();

                if ($andar && $this->has('ativo') && ! $this->boolean('ativo') && $andar->salas()->ativas()->exists()) {
                    $validator->errors()->add('ativo', 'Este andar tem salas ativas. Inative as salas antes de inativar o andar.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome do andar.',
            'nome.string' => 'Informe o nome do andar.',
            'nome.max' => 'O nome do andar pode ter no máximo 60 caracteres.',
            'nome.unique' => 'Já existe um andar com este nome.',
            'ordem.integer' => 'A ordem do andar deve ser um número inteiro.',
            'ordem.between' => 'A ordem do andar deve ficar entre -99 e 999.',
            'ativo.boolean' => 'Informe se o andar está ativo com verdadeiro ou falso.',
        ];
    }

    private function andarAtual(): ?Andar
    {
        $andar = $this->route('andar');

        return $andar instanceof Andar ? $andar : null;
    }
}
