<?php

namespace App\Http\Requests;

use App\Enums\StatusBem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/*
| Valida os parâmetros do relatório do levantamento (tarefa 2.10).
|
| O período (inicio e fim) é obrigatório: ele define quais bens aparecem
| em destaque por terem mudado de sala ou de situação (RF25). O relatório
| traz todos os bens que passam nos filtros, tenham mudado ou não.
|
| status aceita um valor ou vários separados por vírgula: ?status=sem_uso,avariado
*/
class RelatorioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->query('status'))) {
            $this->merge([
                'status' => array_values(array_filter(array_map('trim', explode(',', $this->query('status'))))),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'inicio' => ['required', 'date_format:Y-m-d'],
            'fim' => ['required', 'date_format:Y-m-d', 'after_or_equal:inicio'],
            'andar_id' => ['sometimes', 'nullable', 'integer'],
            'sala_id' => ['sometimes', 'nullable', 'integer'],
            'tipo_patrimonio_id' => ['sometimes', 'nullable', 'integer'],
            'status' => ['sometimes', 'array'],
            'status.*' => [Rule::enum(StatusBem::class)],
            'apenas_mudancas' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'inicio.required' => 'Informe a data de início do período.',
            'inicio.date_format' => 'Informe a data de início no formato ano-mês-dia, como 2026-07-01.',
            'fim.required' => 'Informe a data de fim do período.',
            'fim.date_format' => 'Informe a data de fim no formato ano-mês-dia, como 2026-12-31.',
            'fim.after_or_equal' => 'A data de fim não pode ser antes da data de início.',
            'andar_id.integer' => 'O andar do filtro não é válido.',
            'sala_id.integer' => 'A sala do filtro não é válida.',
            'tipo_patrimonio_id.integer' => 'O tipo do filtro não é válido.',
            'status.array' => 'A situação do filtro não é válida.',
            'status.*.enum' => 'A situação do filtro deve ser em_uso, sem_uso, avariado ou baixado.',
            'apenas_mudancas.boolean' => 'Use 1 para ver só os bens que mudaram no período, ou 0 para ver todos.',
        ];
    }
}
