<?php

namespace App\Http\Requests;

use App\Enums\StatusBem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/*
| Valida os filtros da consulta de bens (tarefa 2.6, RF12 e RF13).
| Todos são opcionais e podem ser combinados.
|
| status aceita um valor ou vários separados por vírgula: ?status=sem_uso,avariado
*/
class ListarBensRequest extends FormRequest
{
    /** Ordenações aceitas em ?ordenar=. "local" é a ordem do levantamento. */
    public const ORDENACOES = ['local', 'numero', 'codigo', 'tipo', 'status', 'cadastro'];

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
            'busca' => ['sometimes', 'nullable', 'string', 'max:120'],
            'numero' => ['sometimes', 'nullable', 'string', 'max:20'],
            'andar_id' => ['sometimes', 'nullable', 'integer'],
            'sala_id' => ['sometimes', 'nullable', 'integer'],
            'tipo_patrimonio_id' => ['sometimes', 'nullable', 'integer'],
            'status' => ['sometimes', 'array'],
            'status.*' => [Rule::enum(StatusBem::class)],
            'ordenar' => ['sometimes', 'nullable', Rule::in(self::ORDENACOES)],
            'direcao' => ['sometimes', 'nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'busca.max' => 'A busca pode ter no máximo 120 caracteres.',
            'numero.max' => 'O número de patrimônio pode ter no máximo 20 caracteres.',
            'andar_id.integer' => 'O andar do filtro não é válido.',
            'sala_id.integer' => 'A sala do filtro não é válida.',
            'tipo_patrimonio_id.integer' => 'O tipo do filtro não é válido.',
            'status.array' => 'A situação do filtro não é válida.',
            'status.*.enum' => 'A situação do filtro deve ser em_uso, sem_uso, avariado ou baixado.',
            'ordenar.in' => 'A ordenação deve ser uma destas: '.implode(', ', self::ORDENACOES).'.',
            'direcao.in' => 'A direção da ordenação deve ser asc ou desc.',
        ];
    }
}
