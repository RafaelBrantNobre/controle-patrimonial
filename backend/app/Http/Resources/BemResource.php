<?php

namespace App\Http\Resources;

use App\Models\Bem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
| Dados do bem que o front-end recebe (tarefa 2.6). Serve para a lista e
| para a ficha (RF15): traz tipo, sala e andar juntos, a situação de uso e
| o estado de conservação com o texto pronto para a tela, e a data da
| última mudança.
|
| baixado vem separado para a lista poder sinalizar o bem baixado que ainda
| está no prédio (RN05).
|
| @mixin Bem
*/
class BemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero_patrimonio' => $this->numero_patrimonio,
            'codigo_interno' => $this->codigo_interno,
            'tipo_patrimonio_id' => $this->tipo_patrimonio_id,
            'tipo_patrimonio' => new TipoPatrimonioResource($this->whenLoaded('tipoPatrimonio')),
            'sala_id' => $this->sala_id,
            'sala' => new SalaResource($this->whenLoaded('sala')),
            // Só na ficha: a mudança de sala mais recente, com a sala de onde o bem veio.
            'ultima_movimentacao' => new MovimentacaoResource($this->whenLoaded('ultimaMovimentacao')),
            'descricao' => $this->descricao,
            'marca' => $this->marca,
            'origem' => $this->origem,
            'quantidade' => $this->quantidade,
            'status' => $this->status->value,
            'status_rotulo' => $this->status->rotulo(),
            'estado_conservacao' => $this->estado_conservacao->value,
            'estado_conservacao_rotulo' => $this->estado_conservacao->rotulo(),
            'baixado' => $this->estaBaixado(),
            'observacao' => $this->observacao,
            'data_ultima_mudanca' => $this->data_ultima_mudanca?->toDateString(),
            'usuario_ultima_mudanca' => $this->whenLoaded('usuarioUltimaMudanca', fn () => $this->usuarioUltimaMudanca ? [
                'id' => $this->usuarioUltimaMudanca->id,
                'nome' => $this->usuarioUltimaMudanca->nome,
            ] : null),
            'baixa_data' => $this->baixa_data?->toDateString(),
            'baixa_observacao' => $this->baixa_observacao,
            'cadastrado_em' => $this->created_at?->toIso8601String(),
            'atualizado_em' => $this->updated_at?->toIso8601String(),
        ];
    }
}
