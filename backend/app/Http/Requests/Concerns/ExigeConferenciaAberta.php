<?php

namespace App\Http\Requests\Concerns;

use App\Models\Conferencia;
use Illuminate\Http\Exceptions\HttpResponseException;

/*
| Lançar, marcar e concluir só valem com a conferência aberta. A resposta é
| 409, antes de qualquer validação dos campos, para a tela mostrar o motivo
| certo: a conferência já foi concluída ou cancelada.
*/
trait ExigeConferenciaAberta
{
    protected function exigirConferenciaAberta(): Conferencia
    {
        /** @var Conferencia $conferencia */
        $conferencia = $this->route('conferencia');

        if (! $conferencia->estaAberta()) {
            throw new HttpResponseException(response()->json([
                'message' => "Esta conferência está {$this->situacaoEmTexto($conferencia)} e não recebe mais resultados.",
            ], 409));
        }

        return $conferencia;
    }

    private function situacaoEmTexto(Conferencia $conferencia): string
    {
        return mb_strtolower($conferencia->situacao->rotulo());
    }
}
