<?php

namespace App\Http\Requests;

use App\Enums\StatusBem;
use App\Models\Bem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/*
| Valida a alteração de status de um bem (tarefa 2.8, RF19 e RF20).
|
| Para baixar, a data da baixa é obrigatória e não pode ser futura. A
| observação (processo, motivo, recolhimento) é opcional enquanto a PD06
| não define quais dados da baixa registrar.
|
| Sair de baixado para outro status desfaz a baixa, para corrigir um
| engano. Os dados da baixa são apagados nesse caso.
*/
class AlterarStatusRequest extends FormRequest
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
        $baixando = $this->input('status') === StatusBem::Baixado->value;

        return [
            'status' => ['required', Rule::enum(StatusBem::class)],
            'baixa_data' => $baixando
                ? ['required', 'date_format:Y-m-d', 'before_or_equal:today']
                : ['prohibited'],
            'baixa_observacao' => $baixando
                ? ['nullable', 'string', 'max:5000']
                : ['prohibited'],
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

                if (! $validator->errors()->has('status') && $this->input('status') === $bem->status->value) {
                    $validator->errors()->add('status', "O bem já está com a situação \"{$bem->status->rotulo()}\". Escolha outra.");
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
            'status.required' => 'Escolha a nova situação do bem.',
            'status.enum' => 'A situação deve ser em uso, sem uso, avariado ou baixado.',
            'baixa_data.required' => 'Informe a data da baixa.',
            'baixa_data.date_format' => 'Informe a data da baixa no formato ano-mês-dia, como 2026-10-23.',
            'baixa_data.before_or_equal' => 'A data da baixa não pode ser depois de hoje.',
            'baixa_data.prohibited' => 'A data da baixa só é informada quando a nova situação é baixado.',
            'baixa_observacao.string' => 'A observação da baixa deve ser um texto.',
            'baixa_observacao.max' => 'A observação da baixa pode ter no máximo 5000 caracteres.',
            'baixa_observacao.prohibited' => 'A observação da baixa só é informada quando a nova situação é baixado.',
        ];
    }
}
