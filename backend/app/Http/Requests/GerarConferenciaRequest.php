<?php

namespace App\Http\Requests;

use App\Models\Bem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/*
| Valida a geração de uma conferência.
| Os bens vêm de um de dois jeitos:
|   - modo "selecionados": os ids marcados nas caixinhas (bem_ids);
|   - modo "filtro": todos os bens de um filtro da lista de bens (filtros),
|     com os mesmos campos de GET /api/bens. Filtro vazio é o prédio inteiro.
*/
class GerarConferenciaRequest extends FormRequest
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
            'nome' => ['required', 'string', 'max:120'],
            'modo' => ['required', Rule::in(['selecionados', 'filtro'])],
            'bem_ids' => ['exclude_unless:modo,selecionados', 'required', 'array', 'min:1'],
            'bem_ids.*' => ['integer', 'distinct'],
            'filtros' => ['exclude_unless:modo,filtro', 'sometimes', 'array'],
            ...$this->comPrefixo((new ListarBensRequest)->rules()),
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('modo') !== 'selecionados' || $validator->errors()->hasAny(['bem_ids', 'bem_ids.*'])) {
                    return;
                }

                $ids = array_map('intval', $this->input('bem_ids', []));

                if (Bem::whereIn('id', $ids)->count() !== count($ids)) {
                    $validator->errors()->add('bem_ids', 'Algum bem selecionado não existe mais. Atualize a página e selecione de novo.');
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
            'nome.required' => 'Dê um nome à conferência, como "2º semestre de 2026".',
            'nome.string' => 'Dê um nome à conferência, como "2º semestre de 2026".',
            'nome.max' => 'O nome da conferência pode ter no máximo 120 caracteres.',
            'modo.required' => 'Escolha os bens da conferência.',
            'modo.in' => 'Escolha os bens da conferência.',
            'bem_ids.required' => 'Selecione pelo menos um bem para a conferência.',
            'bem_ids.array' => 'Selecione pelo menos um bem para a conferência.',
            'bem_ids.min' => 'Selecione pelo menos um bem para a conferência.',
            'bem_ids.*.integer' => 'A seleção de bens não é válida. Atualize a página e selecione de novo.',
            'bem_ids.*.distinct' => 'A seleção de bens não é válida. Atualize a página e selecione de novo.',
            'filtros.array' => 'O filtro dos bens não é válido.',
            ...$this->comPrefixo((new ListarBensRequest)->messages()),
        ];
    }

    /**
     * Os campos de filtro da lista de bens, dentro de "filtros". A ordenação
     * fica de fora: a ordem dos itens é sempre a da folha de conferência.
     *
     * @param  array<string, mixed>  $itens
     * @return array<string, mixed>
     */
    private function comPrefixo(array $itens): array
    {
        return collect($itens)
            ->reject(fn ($valor, string $chave) => str_starts_with($chave, 'ordenar') || str_starts_with($chave, 'direcao'))
            ->mapWithKeys(fn ($valor, string $chave) => ["filtros.{$chave}" => $valor])
            ->all();
    }
}
