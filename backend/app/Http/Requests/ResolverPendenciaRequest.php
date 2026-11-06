<?php

namespace App\Http\Requests;

use App\Enums\ResolucaoPendencia;
use App\Models\Bem;
use App\Models\Sala;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/*
| Valida a resolução da pendência de um bem não localizado.
|   - localizado: o bem apareceu. Informa a sala onde estava e o dia.
|     O dia não pode ser anterior à conferência em que ele não foi
|     localizado, nem futuro.
|   - processo_administrativo: informa o número do processo.
*/
class ResolverPendenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->bem()->pendenciasAbertas()->exists()) {
            throw new HttpResponseException(response()->json([
                'message' => 'Este bem não tem pendência aberta.',
            ], 409));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $localizado = 'exclude_unless:resolucao,'.ResolucaoPendencia::Localizado->value;
        $processo = 'exclude_unless:resolucao,'.ResolucaoPendencia::ProcessoAdministrativo->value;

        return [
            'resolucao' => ['required', Rule::enum(ResolucaoPendencia::class)],
            'sala_id' => [$localizado, 'required', 'integer', Rule::exists('sala', 'id')],
            'localizado_em' => [$localizado, 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'processo_numero' => [$processo, 'required', 'string', 'max:60'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('resolucao') !== ResolucaoPendencia::Localizado->value || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $bem = $this->bem();
                $ultimaVez = $bem->pendenciasAbertas()->with('conferencia')->get()
                    ->max(fn ($item) => $item->conferencia->realizada_em?->toDateString());

                if ($ultimaVez !== null && $this->input('localizado_em') < $ultimaVez) {
                    $data = implode('/', array_reverse(explode('-', $ultimaVez)));
                    $validator->errors()->add('localizado_em', "O dia em que o bem foi localizado não pode ser anterior à conferência em que ele não foi localizado ({$data}).");
                }

                $sala = Sala::find($this->integer('sala_id'));

                if ($sala->id !== $bem->sala_id) {
                    if ($bem->estaBaixado()) {
                        $validator->errors()->add('sala_id', 'Este bem está baixado e não muda de sala. Informe a sala onde ele está registrado ou desfaça a baixa antes.');
                    } elseif (! $sala->ativo) {
                        $validator->errors()->add('sala_id', 'A sala escolhida está inativa e não recebe bens. Escolha outra sala ou reative-a.');
                    }
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
            'resolucao.required' => 'Informe se o bem foi localizado ou se foi aberto processo administrativo.',
            'resolucao.enum' => 'Informe se o bem foi localizado ou se foi aberto processo administrativo.',
            'sala_id.required' => 'Informe a sala onde o bem foi localizado.',
            'sala_id.integer' => 'Informe a sala onde o bem foi localizado.',
            'sala_id.exists' => 'A sala escolhida não existe. Atualize a página e escolha de novo.',
            'localizado_em.required' => 'Informe o dia em que o bem foi localizado.',
            'localizado_em.date_format' => 'Informe o dia no formato ano-mês-dia.',
            'localizado_em.before_or_equal' => 'O dia em que o bem foi localizado não pode ser futuro.',
            'processo_numero.required' => 'Informe o número do processo administrativo.',
            'processo_numero.string' => 'Informe o número do processo administrativo.',
            'processo_numero.max' => 'O número do processo pode ter no máximo 60 caracteres.',
        ];
    }

    private function bem(): Bem
    {
        return $this->route('bem');
    }
}
