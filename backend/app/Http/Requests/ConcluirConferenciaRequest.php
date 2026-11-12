<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ExigeConferenciaAberta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/*
| Valida a conclusão de uma conferência.
| realizada_em é o dia da conferência física, que pode ser anterior ao dia
| em que a folha foi passada para o sistema. Não pode ser futura.
| Todos os bens precisam ter resultado, e o que mudaria de sala precisa
| poder mudar (Conferencia::problemasParaConcluir()).
*/
class ConcluirConferenciaRequest extends FormRequest
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
            'realizada_em' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('realizada_em')) {
                    return;
                }

                foreach ($this->route('conferencia')->problemasParaConcluir($this->input('realizada_em')) as $problema) {
                    $validator->errors()->add('conferencia', $problema);
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
            'realizada_em.required' => 'Informe o dia em que a conferência foi feita.',
            'realizada_em.date_format' => 'Informe o dia da conferência no formato ano-mês-dia.',
            'realizada_em.before_or_equal' => 'O dia da conferência não pode ser futuro.',
        ];
    }
}
