<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ExigeConferenciaAberta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/*
| Valida o "marcar selecionados como encontrados" da tela de lançamento:
| os itens marcados nas caixinhas, todos desta conferência.
*/
class MarcarEncontradosRequest extends FormRequest
{
    use ExigeConferenciaAberta;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->exigirConferenciaAberta();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'itens' => ['required', 'array', 'min:1'],
            'itens.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $ids = array_map('intval', $this->input('itens'));
                $daConferencia = $this->route('conferencia')->itens()->whereIn('id', $ids)->count();

                if ($daConferencia !== count($ids)) {
                    $validator->errors()->add('itens', 'Algum bem selecionado não faz parte desta conferência. Atualize a página e selecione de novo.');
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
            'itens.required' => 'Selecione pelo menos um bem.',
            'itens.array' => 'Selecione pelo menos um bem.',
            'itens.min' => 'Selecione pelo menos um bem.',
            'itens.*.integer' => 'A seleção de bens não é válida. Atualize a página e selecione de novo.',
            'itens.*.distinct' => 'A seleção de bens não é válida. Atualize a página e selecione de novo.',
        ];
    }
}
