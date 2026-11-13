<?php

namespace App\Http\Requests;

use App\Enums\EstadoConservacao;
use App\Models\Bem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/*
| Valida a mudança do estado de conservação de um bem, feita pela ficha.
| A mudança grava uma ocorrência no histórico, como a de sala e a de status.
*/
class AlterarConservacaoRequest extends FormRequest
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
            'estado_conservacao' => ['required', Rule::enum(EstadoConservacao::class)],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var Bem $bem */
                $bem = $this->route('bem');

                if (! $validator->errors()->has('estado_conservacao') && $this->input('estado_conservacao') === $bem->estado_conservacao->value) {
                    $validator->errors()->add('estado_conservacao', "O bem já está em estado \"{$bem->estado_conservacao->rotulo()}\". Escolha outro.");
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
            'estado_conservacao.required' => 'Escolha o novo estado de conservação.',
            'estado_conservacao.enum' => 'Escolha o estado de conservação: novo, bom, regular, péssimo, sucata ou indeterminado.',
        ];
    }
}
