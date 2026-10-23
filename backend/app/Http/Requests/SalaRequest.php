<?php

namespace App\Http\Requests;

use App\Models\Andar;
use App\Models\Sala;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/*
| Valida o cadastro e a edição de sala (tarefa 2.5, RF06 e RF07).
| No cadastro (POST) andar e nome são obrigatórios. Na edição (PUT ou PATCH)
| só é validado o que for enviado, então {"ativo": false} basta para inativar.
|
| Regra que vale sempre: sala ativa só existe em andar ativo. Assim, na 2.6,
| basta conferir se a sala está ativa para saber se ela pode receber bens.
*/
class SalaRequest extends FormRequest
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
        $presenca = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'andar_id' => [$presenca, 'integer', Rule::exists('andar', 'id')],
            'nome' => [$presenca, 'string', 'max:120'],
            'descricao' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Regras que olham a sala como vai ficar depois de salva, juntando o que
     * foi enviado com o que já está gravado.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['andar_id', 'nome'])) {
                    return;
                }

                // Nome único dentro do andar. Duas "Sala 05" em andares diferentes
                // são permitidas. Vale também quando só o andar muda.
                $nome = $this->has('nome') ? $this->string('nome')->toString() : $this->salaAtual()?->nome;
                $repetida = Sala::where('andar_id', $this->andarIdFinal())
                    ->where('nome', $nome)
                    ->when($this->salaAtual(), fn ($query, Sala $sala) => $query->whereKeyNot($sala->id))
                    ->exists();

                if ($repetida) {
                    $validator->errors()->add('nome', 'Já existe uma sala com este nome neste andar.');
                }

                // Sala ativa só existe em andar ativo.
                $ativa = $this->has('ativo') ? $this->boolean('ativo') : ($this->salaAtual()?->ativo ?? true);
                $andar = Andar::find($this->andarIdFinal());

                if ($ativa && $andar && ! $andar->ativo) {
                    $validator->errors()->add('andar_id', 'O andar escolhido está inativo. Reative o andar ou escolha outro.');
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
            'andar_id.required' => 'Escolha o andar da sala.',
            'andar_id.integer' => 'Escolha o andar da sala.',
            'andar_id.exists' => 'O andar escolhido não existe. Atualize a página e escolha de novo.',
            'nome.required' => 'Informe o nome da sala.',
            'nome.string' => 'Informe o nome da sala.',
            'nome.max' => 'O nome da sala pode ter no máximo 120 caracteres.',
            'descricao.string' => 'A descrição da sala deve ser um texto.',
            'descricao.max' => 'A descrição da sala pode ter no máximo 255 caracteres.',
            'ativo.boolean' => 'Informe se a sala está ativa com verdadeiro ou falso.',
        ];
    }

    /** O andar que a sala terá depois de salva: o enviado ou o atual. */
    private function andarIdFinal(): ?int
    {
        if ($this->filled('andar_id')) {
            return $this->integer('andar_id');
        }

        return $this->salaAtual()?->andar_id;
    }

    private function salaAtual(): ?Sala
    {
        $sala = $this->route('sala');

        return $sala instanceof Sala ? $sala : null;
    }
}
