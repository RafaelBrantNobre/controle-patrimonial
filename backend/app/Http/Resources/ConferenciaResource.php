<?php

namespace App\Http\Resources;

use App\Models\Conferencia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
| Dados da conferência que o front-end recebe: situação, quem gerou e quem
| concluiu, e as contagens de itens (Conferencia::contagens()), quando a
| consulta as traz.
|
| @mixin Conferencia
*/
class ConferenciaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'situacao' => $this->situacao->value,
            'situacao_rotulo' => $this->situacao->rotulo(),
            'realizada_em' => $this->realizada_em?->toDateString(),
            'criada_por' => $this->whenLoaded('criadaPor', fn () => ['id' => $this->criadaPor->id, 'nome' => $this->criadaPor->nome]),
            'criada_em' => $this->created_at?->toIso8601String(),
            'concluida_em' => $this->concluida_em?->toIso8601String(),
            'concluida_por' => $this->whenLoaded('concluidaPor', fn () => $this->concluidaPor ? [
                'id' => $this->concluidaPor->id,
                'nome' => $this->concluidaPor->nome,
            ] : null),
            'totais' => $this->when($this->itens_count !== null, fn () => [
                'itens' => $this->itens_count,
                'pendentes' => $this->pendentes_count,
                'encontrados' => $this->encontrados_count,
                'nao_localizados' => $this->nao_localizados_count,
            ]),
        ];
    }
}
