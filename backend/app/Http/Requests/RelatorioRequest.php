<?php

namespace App\Http\Requests;

use App\Enums\EstadoConservacao;
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
| status e estado_conservacao aceitam um valor ou vários separados por
| vírgula: ?status=em_uso,sem_uso&estado_conservacao=pessimo,sucata
*/
class RelatorioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['status', 'estado_conservacao'] as $campo) {
            if (is_string($this->query($campo))) {
                $this->merge([
                    $campo => array_values(array_filter(array_map('trim', explode(',', $this->query($campo))))),
                ]);
            }
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
            'estado_conservacao' => ['sometimes', 'array'],
            'estado_conservacao.*' => [Rule::enum(EstadoConservacao::class)],
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
            'status.*.enum' => 'A situação de uso do filtro deve ser em_uso, sem_uso ou baixado.',
            'estado_conservacao.array' => 'O estado de conservação do filtro não é válido.',
            'estado_conservacao.*.enum' => 'O estado de conservação do filtro deve ser novo, bom, regular, pessimo, sucata ou indeterminado.',
            'apenas_mudancas.boolean' => 'Use 1 para ver só os bens que mudaram no período, ou 0 para ver todos.',
        ];
    }
}
