<?php

namespace App\Http\Resources;

use App\Models\Bem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
| Dados do bem que o front-end recebe (tarefa 2.6). Serve para a lista e
| para a ficha (RF15): traz tipo e sala juntos, a situação de uso e
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
            'data_ultima_mudanca' => $this->data_ultima_mudanca?->toDateString(),
            'usuario_ultima_mudanca' => $this->whenLoaded('usuarioUltimaMudanca', fn () => $this->usuarioUltimaMudanca ? [
                'id' => $this->usuarioUltimaMudanca->id,
                'nome' => $this->usuarioUltimaMudanca->nome,
            ] : null),
            'baixa_data' => $this->baixa_data?->toDateString(),
            'baixa_observacao' => $this->baixa_observacao,
            // Conferência: a última concluída, a aberta em que o bem está e a
            // pendência, se ele não foi localizado.
            'data_ultima_conferencia' => $this->data_ultima_conferencia?->toDateString(),
            'resultado_ultima_conferencia' => $this->resultado_ultima_conferencia?->value,
            'resultado_ultima_conferencia_rotulo' => $this->resultado_ultima_conferencia?->rotulo(),
            'em_conferencia' => $this->whenLoaded('itemEmConferenciaAberta', fn () => $this->itemEmConferenciaAberta ? [
                'conferencia_id' => $this->itemEmConferenciaAberta->conferencia_id,
                'nome' => $this->itemEmConferenciaAberta->conferencia->nome,
            ] : null),
            'pendencia' => $this->whenLoaded('pendenciaAberta', fn () => $this->pendenciaAberta ? [
                'desde' => $this->pendenciaAberta->conferencia->realizada_em?->toDateString(),
                'conferencia_id' => $this->pendenciaAberta->conferencia_id,
                'conferencia' => $this->pendenciaAberta->conferencia->nome,
            ] : null),
            'historico_conferencias' => $this->whenLoaded('historicoDeConferencias', fn () => $this->historicoDeConferencias->map(fn ($item) => [
                'conferencia_id' => $item->conferencia_id,
                'conferencia' => $item->conferencia->nome,
                'realizada_em' => $item->conferencia->realizada_em?->toDateString(),
                'resultado' => $item->resultado?->value,
                'resultado_rotulo' => $item->resultado?->rotulo(),
                'resolucao_rotulo' => $item->resolucao?->rotulo(),
                'processo_numero' => $item->processo_numero,
            ])),
            // Só na ficha: o histórico de ocorrências do bem.
            'ocorrencias' => $this->whenLoaded('ocorrencias', fn () => $this->ocorrencias->map(fn ($ocorrencia) => [
                'id' => $ocorrencia->id,
                'tipo' => $ocorrencia->tipo->value,
                'tipo_rotulo' => $ocorrencia->tipo->rotulo(),
                'valor_anterior' => $ocorrencia->valor_anterior,
                'valor_novo' => $ocorrencia->valor_novo,
                'observacao' => $ocorrencia->observacao,
                'data' => $ocorrencia->data->toDateString(),
                'usuario' => $ocorrencia->usuario->nome,
                'conferencia' => $ocorrencia->conferencia ? ['id' => $ocorrencia->conferencia->id, 'nome' => $ocorrencia->conferencia->nome] : null,
            ])),
            'cadastrado_em' => $this->created_at?->toIso8601String(),
            'atualizado_em' => $this->updated_at?->toIso8601String(),
        ];
    }
}
