<?php

namespace App\Http\Requests;

use App\Enums\TipoOcorrencia;
use Illuminate\Validation\Rule;

/*
| Valida os filtros da lista de ocorrências. Aceita os mesmos filtros da
| lista de bens, que valem para o bem de cada ocorrência (a sala é a atual
| do bem), e mais os da própria ocorrência: tipo e período.
|
| tipo aceita um valor ou vários separados por vírgula: ?tipo=sala,baixa
*/
class ListarOcorrenciasRequest extends ListarBensRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if (is_string($this->query('tipo'))) {
            $this->merge(['tipo' => array_values(array_filter(array_map('trim', explode(',', $this->query('tipo')))))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $regras = parent::rules();
        unset($regras['ordenar'], $regras['direcao']);

        return $regras + [
            'tipo' => ['sometimes', 'array'],
            'tipo.*' => [Rule::enum(TipoOcorrencia::class)],
            'de' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'ate' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:de'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return parent::messages() + [
            'tipo.array' => 'O tipo de ocorrência do filtro não é válido.',
            'tipo.*.enum' => 'O tipo de ocorrência deve ser sala, conservacao, uso ou baixa.',
            'de.date_format' => 'Informe a data inicial no formato ano-mês-dia, como 2026-11-01.',
            'ate.date_format' => 'Informe a data final no formato ano-mês-dia, como 2026-11-30.',
            'ate.after_or_equal' => 'A data final não pode ser antes da inicial.',
        ];
    }
}
