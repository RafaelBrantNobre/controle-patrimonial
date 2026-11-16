<?php

namespace App\Http\Resources;

use App\Models\Ocorrencia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
| Uma ocorrência na lista geral, com o bem a que pertence. Na ficha do bem,
| as ocorrências vêm pelo BemResource, sem o bem.
|
| @mixin Ocorrencia
*/
class OcorrenciaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo->value,
            'tipo_rotulo' => $this->tipo->rotulo(),
            'valor_anterior' => $this->valor_anterior,
            'valor_novo' => $this->valor_novo,
            'observacao' => $this->observacao,
            'data' => $this->data->toDateString(),
            'usuario' => $this->usuario->nome,
            'conferencia' => $this->conferencia ? ['id' => $this->conferencia->id, 'nome' => $this->conferencia->nome] : null,
            'bem' => [
                'id' => $this->bem->id,
                'numero_patrimonio' => $this->bem->numero_patrimonio,
                'codigo_interno' => $this->bem->codigo_interno,
                'tipo' => $this->bem->tipoPatrimonio->nome,
                'sala' => $this->bem->sala?->nome,
            ],
        ];
    }
}
