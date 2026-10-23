<?php

namespace App\Http\Requests;

use App\Models\Bem;
use App\Models\Sala;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/*
| Valida a mudança de sala de um bem (tarefa 2.8, RF17).
| RN04: bem baixado não muda de sala. O destino precisa ser outra sala, e
| ativa, porque sala inativa não recebe bens (tarefa 2.5).
*/
class MudarSalaRequest extends FormRequest
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
            'sala_id' => ['required', 'integer', Rule::exists('sala', 'id')],
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

                if ($bem->estaBaixado()) {
                    $validator->errors()->add('sala_id', 'Este bem está baixado e não muda de sala. Se a baixa foi um engano, altere a situação dele antes.');

                    return;
                }

                if ($validator->errors()->has('sala_id')) {
                    return;
                }

                $destino = Sala::find($this->integer('sala_id'));

                if ($destino->id === $bem->sala_id) {
                    $validator->errors()->add('sala_id', 'O bem já está nesta sala. Escolha a sala para onde ele foi.');
                } elseif (! $destino->ativo) {
                    $validator->errors()->add('sala_id', 'A sala escolhida está inativa e não recebe bens. Escolha outra sala ou reative-a.');
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
            'sala_id.required' => 'Escolha a sala para onde o bem foi.',
            'sala_id.integer' => 'Escolha a sala para onde o bem foi.',
            'sala_id.exists' => 'A sala escolhida não existe. Atualize a página e escolha de novo.',
        ];
    }
}
