<?php

namespace App\Http\Resources;

use App\Models\Sala;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
| Dados da sala que o front-end recebe (tarefa 2.5).
| Traz o andar junto, para a tela mostrar "Sala 05, 1º andar" sem outra
| chamada. bens_count só aparece nas respostas que contam os bens (RN08).
|
| @mixin Sala
*/
class SalaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'andar_id' => $this->andar_id,
            'andar' => new AndarResource($this->whenLoaded('andar')),
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'ativo' => $this->ativo,
            'bens_count' => $this->whenCounted('bens'),
        ];
    }
}
