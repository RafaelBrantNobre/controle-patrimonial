<?php

namespace App\Http\Requests;

use App\Enums\EstadoConservacao;
use App\Enums\ResultadoConferencia;
use App\Enums\StatusBem;
use App\Http\Requests\Concerns\ExigeConferenciaAberta;
use App\Models\ConferenciaItem;
use App\Models\Sala;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/*
| Valida o "salvar sala" da conferência: os bens de uma sala de uma vez,
| cada um com o resultado (encontrado, não localizado ou nulo, que deixa
| pendente), a sala onde estava, o estado de conservação e a condição de uso.
| As regras de cada bem são as do lançamento um a um
| (LancarResultadoRequest): a sala precisa estar ativa, e bem baixado não
| muda de sala (RN04).
*/
class SalvarItensRequest extends FormRequest
{
    use ExigeConferenciaAberta;

    /** @var Collection<int, ConferenciaItem>|null */
    private ?Collection $itens = null;

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
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.id' => ['required', 'integer', 'distinct'],
            'itens.*.resultado' => ['present', 'nullable', Rule::enum(ResultadoConferencia::class)],
            'itens.*.sala_encontrada_id' => ['sometimes', 'nullable', 'integer', Rule::exists('sala', 'id')],
            'itens.*.estado_conservacao' => ['sometimes', 'nullable', Rule::enum(EstadoConservacao::class)],
            'itens.*.condicao_uso' => ['sometimes', 'nullable', Rule::in([StatusBem::EmUso->value, StatusBem::SemUso->value])],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $itens = $this->itensDaConferencia();

                if ($itens->count() !== count($this->input('itens'))) {
                    $validator->errors()->add('itens', 'Algum bem não faz parte desta conferência. Atualize a página e tente de novo.');

                    return;
                }

                $salas = Sala::whereIn('id', collect($this->input('itens'))->pluck('sala_encontrada_id')->filter())->get()->keyBy('id');

                foreach ($this->input('itens') as $posicao => $dados) {
                    if (($dados['resultado'] ?? null) !== ResultadoConferencia::Encontrado->value) {
                        continue;
                    }

                    $item = $itens[(int) $dados['id']];
                    $bem = $item->bem;
                    $nome = $bem->numero_patrimonio ?? $bem->codigo_interno;
                    $salaId = (int) ($dados['sala_encontrada_id'] ?? $item->sala_registrada_id ?? $bem->sala_id);
                    $campo = "itens.{$posicao}.sala_encontrada_id";

                    if ($salaId === 0) {
                        $validator->errors()->add($campo, "Informe a sala onde o bem {$nome} foi encontrado.");
                    } elseif ($salaId !== (int) $bem->sala_id) {
                        if ($bem->estaBaixado()) {
                            $validator->errors()->add($campo, "O bem {$nome} está baixado e não muda de sala. Deixe a sala onde ele está registrado ou desfaça a baixa antes.");
                        } elseif (! ($salas[$salaId] ?? Sala::find($salaId))->ativo) {
                            $validator->errors()->add($campo, "A sala escolhida para o bem {$nome} está inativa e não recebe bens. Escolha outra sala ou reative-a.");
                        }
                    }
                }
            },
        ];
    }

    /**
     * Os itens enviados que são desta conferência, pela chave id, com o bem.
     *
     * @return Collection<int, ConferenciaItem>
     */
    public function itensDaConferencia(): Collection
    {
        return $this->itens ??= $this->route('conferencia')->itens()
            ->whereIn('id', collect($this->input('itens'))->pluck('id'))
            ->with('bem')
            ->get()
            ->keyBy('id');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'itens.required' => 'Nenhum bem foi enviado.',
            'itens.array' => 'Nenhum bem foi enviado.',
            'itens.min' => 'Nenhum bem foi enviado.',
            'itens.*.id.required' => 'A lista de bens não é válida. Atualize a página e tente de novo.',
            'itens.*.id.integer' => 'A lista de bens não é válida. Atualize a página e tente de novo.',
            'itens.*.id.distinct' => 'A lista de bens não é válida. Atualize a página e tente de novo.',
            'itens.*.resultado.present' => 'Informe se cada bem foi encontrado ou não localizado.',
            'itens.*.resultado.enum' => 'O resultado deve ser encontrado ou não localizado.',
            'itens.*.sala_encontrada_id.integer' => 'Escolha a sala onde o bem estava.',
            'itens.*.sala_encontrada_id.exists' => 'A sala escolhida não existe. Atualize a página e escolha de novo.',
            'itens.*.estado_conservacao.enum' => 'Escolha o estado de conservação: novo, bom, regular, péssimo, sucata ou indeterminado.',
            'itens.*.condicao_uso.in' => 'A condição de uso deve ser em uso ou sem uso.',
        ];
    }
}
