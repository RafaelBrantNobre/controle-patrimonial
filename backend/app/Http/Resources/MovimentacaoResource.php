<?php

namespace App\Http\Resources;

use App\Models\Movimentacao;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
| Uma mudança de sala de um bem (tarefa 2.8): de onde, para onde, quando
| e quem registrou.
|
| @mixin Movimentacao
*/
class MovimentacaoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sala_origem' => new SalaResource($this->whenLoaded('salaOrigem')),
            'sala_destino' => new SalaResource($this->whenLoaded('salaDestino')),
            'data' => $this->data->toIso8601String(),
            'usuario' => $this->whenLoaded('usuario', fn () => [
                'id' => $this->usuario->id,
                'nome' => $this->usuario->nome,
            ]),
        ];
    }
}
