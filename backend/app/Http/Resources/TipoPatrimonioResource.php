<?php

namespace App\Http\Resources;

use App\Models\TipoPatrimonio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
| Dados do tipo de patrimônio que o front-end recebe (tarefa 2.5).
| bens_count só aparece nas respostas que contam os bens (RN08).
|
| @mixin TipoPatrimonio
*/
class TipoPatrimonioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'ativo' => $this->ativo,
            'bens_count' => $this->whenCounted('bens'),
        ];
    }
}
