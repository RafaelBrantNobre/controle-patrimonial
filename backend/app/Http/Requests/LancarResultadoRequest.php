<?php

namespace App\Http\Requests;

use App\Enums\EstadoConservacao;
use App\Enums\ResultadoConferencia;
use App\Enums\StatusBem;
use App\Http\Requests\Concerns\ExigeConferenciaAberta;
use App\Models\ConferenciaItem;
use App\Models\Sala;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/*
| Valida o resultado de um bem lançado a partir da folha de conferência.
|   - resultado: encontrado, nao_localizado ou nulo (volta a pendente);
|   - sala_encontrada_id: onde o bem estava, se não era a sala registrada;
|   - estado_conservacao: o estado visto, se mudou.
| Bem encontrado em outra sala vira mudança de sala ao concluir. Por isso a
| sala precisa estar ativa, e bem baixado não muda de sala (RN04).
*/
class LancarResultadoRequest extends FormRequest
{
    use ExigeConferenciaAberta;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $conferencia = $this->exigirConferenciaAberta();

        abort_unless($this->item()->conferencia_id === $conferencia->id, 404, 'Este bem não faz parte desta conferência.');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resultado' => ['present', 'nullable', Rule::enum(ResultadoConferencia::class)],
            'sala_encontrada_id' => ['sometimes', 'nullable', 'integer', Rule::exists('sala', 'id')],
            'estado_conservacao' => ['sometimes', 'nullable', Rule::enum(EstadoConservacao::class)],
            'condicao_uso' => ['sometimes', 'nullable', Rule::in([StatusBem::EmUso->value, StatusBem::SemUso->value])],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('resultado') !== ResultadoConferencia::Encontrado->value || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $bem = $this->item()->bem;
                $salaId = $this->integer('sala_encontrada_id') ?: $bem->sala_id;

                if ($salaId === $bem->sala_id) {
                    return;
                }

                if ($bem->estaBaixado()) {
                    $validator->errors()->add('sala_encontrada_id', 'Este bem está baixado e não muda de sala. Lance a sala onde ele está registrado ou desfaça a baixa antes.');
                } elseif (! Sala::find($salaId)->ativo) {
                    $validator->errors()->add('sala_encontrada_id', 'A sala escolhida está inativa e não recebe bens. Escolha outra sala ou reative-a.');
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
            'resultado.present' => 'Informe se o bem foi encontrado ou não localizado.',
            'resultado.enum' => 'O resultado deve ser encontrado ou não localizado.',
            'sala_encontrada_id.integer' => 'Escolha a sala onde o bem estava.',
            'sala_encontrada_id.exists' => 'A sala escolhida não existe. Atualize a página e escolha de novo.',
            'condicao_uso.in' => 'A condição de uso deve ser em uso ou sem uso.',
            'estado_conservacao.enum' => 'Escolha o estado de conservação: novo, bom, regular, péssimo, sucata ou indeterminado.',
        ];
    }

    private function item(): ConferenciaItem
    {
        return $this->route('item');
    }
}
