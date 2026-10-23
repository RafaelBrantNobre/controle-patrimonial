<?php

namespace App\Http\Requests;

use App\Enums\StatusBem;
use App\Models\Bem;
use App\Models\Sala;
use App\Models\TipoPatrimonio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/*
| Valida o cadastro (RF10) e a edição (RF11) de bem (tarefa 2.6).
|
| Obrigatórios no cadastro: tipo e sala. O resto é opcional, porque nem todo
| bem tem plaqueta (PD07), descrição, marca ou origem.
|
| Sala e status ficam fora da edição: mudar de sala, mudar de status e dar
| baixa têm regras próprias (RN04, RN09) e são a tarefa 2.8. A edição aceita
| os dois campos no formulário só se vierem iguais aos atuais. No cadastro,
| o bem já nasce numa sala e com status inicial, que não pode ser baixado.
*/
class BemRequest extends FormRequest
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
        $cadastro = $this->isMethod('POST');
        $presenca = $cadastro ? 'required' : 'sometimes';

        return [
            'numero_patrimonio' => ['sometimes', 'nullable', 'string', 'max:20'],
            'tipo_patrimonio_id' => [$presenca, 'integer', Rule::exists('tipo_patrimonio', 'id')],
            // Na edição, sala e status podem vir no formulário, desde que iguais aos
            // atuais. A mudança é recusada mais abaixo, em after().
            'sala_id' => [$presenca, 'integer', Rule::exists('sala', 'id')],
            'status' => $cadastro
                ? ['sometimes', Rule::enum(StatusBem::class)->except(StatusBem::Baixado)]
                : ['sometimes', Rule::enum(StatusBem::class)],
            'descricao' => ['sometimes', 'nullable', 'string', 'max:120'],
            'marca' => ['sometimes', 'nullable', 'string', 'max:80'],
            'origem' => ['sometimes', 'nullable', 'string', 'max:120'],
            'quantidade' => ['sometimes', 'integer', 'between:1,9999'],
            'observacao' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Regras que precisam consultar o banco depois das validações simples.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $erros = $validator->errors();

                // RN01: número de patrimônio único, com o código do bem que já o usa.
                if (! $erros->has('numero_patrimonio') && $this->filled('numero_patrimonio')) {
                    $existente = Bem::where('numero_patrimonio', $this->string('numero_patrimonio')->toString())
                        ->when($this->bemAtual(), fn ($query, Bem $bem) => $query->whereKeyNot($bem->id))
                        ->first();

                    if ($existente) {
                        $erros->add('numero_patrimonio', "Já existe um bem com este número de patrimônio (código {$existente->codigo_interno}). Confira a plaqueta.");
                    }
                }

                // Tipo inativo não recebe bens novos. Ao editar, só vale se o tipo mudar.
                if (! $erros->has('tipo_patrimonio_id') && $this->filled('tipo_patrimonio_id')) {
                    $tipoId = $this->integer('tipo_patrimonio_id');
                    $mudou = $this->bemAtual()?->tipo_patrimonio_id !== $tipoId;

                    if ($mudou && ! TipoPatrimonio::whereKey($tipoId)->value('ativo')) {
                        $erros->add('tipo_patrimonio_id', 'O tipo escolhido está inativo. Escolha outro tipo ou reative-o.');
                    }
                }

                $bem = $this->bemAtual();

                // Edição: sala e status não mudam por aqui (tarefa 2.8).
                if ($bem) {
                    if (! $erros->has('sala_id') && $this->filled('sala_id') && $this->integer('sala_id') !== $bem->sala_id) {
                        $erros->add('sala_id', 'A sala não muda na edição do bem. Use a opção de mudar o bem de sala.');
                    }

                    if (! $erros->has('status') && $this->filled('status') && $this->string('status')->toString() !== $bem->status->value) {
                        $erros->add('status', 'A situação não muda na edição do bem. Use a opção de alterar a situação.');
                    }

                    return;
                }

                // Cadastro: sala inativa não recebe bens novos. Sala ativa só existe em andar ativo (tarefa 2.5).
                if (! $erros->has('sala_id') && $this->filled('sala_id')) {
                    if (! Sala::whereKey($this->integer('sala_id'))->value('ativo')) {
                        $erros->add('sala_id', 'A sala escolhida está inativa e não recebe bens novos. Escolha outra sala ou reative-a.');
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
            'numero_patrimonio.string' => 'Informe o número de patrimônio como aparece na plaqueta.',
            'numero_patrimonio.max' => 'O número de patrimônio pode ter no máximo 20 caracteres.',
            'tipo_patrimonio_id.required' => 'Escolha o tipo do bem.',
            'tipo_patrimonio_id.integer' => 'Escolha o tipo do bem.',
            'tipo_patrimonio_id.exists' => 'O tipo escolhido não existe. Atualize a página e escolha de novo.',
            'sala_id.required' => 'Escolha a sala onde o bem está.',
            'sala_id.integer' => 'Escolha a sala onde o bem está.',
            'sala_id.exists' => 'A sala escolhida não existe. Atualize a página e escolha de novo.',
            'status.enum' => 'Escolha a situação do bem: em uso, sem uso ou avariado. A baixa é registrada depois do cadastro.',
            'descricao.string' => 'A descrição deve ser um texto.',
            'descricao.max' => 'A descrição pode ter no máximo 120 caracteres.',
            'marca.string' => 'A marca deve ser um texto.',
            'marca.max' => 'A marca pode ter no máximo 80 caracteres.',
            'origem.string' => 'A origem deve ser um texto.',
            'origem.max' => 'A origem pode ter no máximo 120 caracteres.',
            'quantidade.integer' => 'A quantidade deve ser um número inteiro.',
            'quantidade.between' => 'A quantidade deve ficar entre 1 e 9999.',
            'observacao.string' => 'A observação deve ser um texto.',
            'observacao.max' => 'A observação pode ter no máximo 5000 caracteres.',
        ];
    }

    private function bemAtual(): ?Bem
    {
        $bem = $this->route('bem');

        return $bem instanceof Bem ? $bem : null;
    }
}
