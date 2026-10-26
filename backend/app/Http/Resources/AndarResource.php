<?php

namespace App\Http\Resources;

use App\Models\Andar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
| Dados do andar que o front-end recebe (tarefa 2.5).
| salas_count só aparece nas respostas que contam as salas. O front usa esse
| número para saber se o botão de excluir faz sentido (RN08).
|
| @mixin Andar
*/
class AndarResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'ordem' => $this->ordem,
            'ativo' => $this->ativo,
            'salas_count' => $this->whenCounted('salas'),
        ];
    }
}
