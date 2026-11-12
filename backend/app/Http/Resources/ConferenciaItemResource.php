<?php

namespace App\Http\Resources;

use App\Models\ConferenciaItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
| Um bem dentro de uma conferência: o bem como está no sistema e o que foi
| lançado da folha (resultado, sala onde estava, estado visto).
|
| @mixin ConferenciaItem
*/
class ConferenciaItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conferencia_id' => $this->conferencia_id,
            'bem' => new BemResource($this->whenLoaded('bem')),
            'sala_registrada_id' => $this->sala_registrada_id,
            'sala_registrada' => new SalaResource($this->whenLoaded('salaRegistrada')),
            'resultado' => $this->resultado?->value,
            'resultado_rotulo' => $this->resultado?->rotulo(),
            'sala_encontrada_id' => $this->sala_encontrada_id,
            'sala_encontrada' => new SalaResource($this->whenLoaded('salaEncontrada')),
            'estado_conservacao' => $this->estado_conservacao?->value,
            'estado_conservacao_rotulo' => $this->estado_conservacao?->rotulo(),
            'condicao_uso' => $this->condicao_uso?->value,
            'condicao_uso_rotulo' => $this->condicao_uso?->rotulo(),
            'conferido_em' => $this->conferido_em?->toIso8601String(),
            'conferido_por' => $this->whenLoaded('conferidoPor', fn () => $this->conferidoPor ? [
                'id' => $this->conferidoPor->id,
                'nome' => $this->conferidoPor->nome,
            ] : null),
        ];
    }
}
