<?php

namespace App\Http\Controllers;

use App\Enums\StatusBem;
use App\Http\Requests\AlterarStatusRequest;
use App\Http\Requests\MudarSalaRequest;
use App\Http\Resources\BemResource;
use App\Models\Bem;
use App\Models\Sala;
use Illuminate\Support\Facades\DB;

/*
| Mudança de sala e de status dos bens (tarefa 2.8).
| RF17 mudar de sala, RF18 data da última mudança, RF19 alterar status,
| RF20 dados da baixa, RN04 baixado não muda de sala, RN09 quem e quando.
*/
class MudancaDeBemController extends Controller
{
    /**
     * PATCH /api/bens/{bem}/sala
     * Corpo: {"sala_id": 5}
     */
    public function mudarSala(MudarSalaRequest $request, Bem $bem): BemResource
    {
        DB::transaction(fn () => $bem->mudarDeSala(Sala::findOrFail($request->integer('sala_id')), $request->user()));

        return $this->resposta($bem);
    }

    /**
     * PATCH /api/bens/{bem}/status
     * Corpo: {"status": "sem_uso"}
     * Baixa: {"status": "baixado", "baixa_data": "2026-10-23", "baixa_observacao": "Processo 123"}
     */
    public function alterarStatus(AlterarStatusRequest $request, Bem $bem): BemResource
    {
        DB::transaction(fn () => $bem->alterarStatus(
            StatusBem::from($request->string('status')->toString()),
            $request->user(),
            $request->input('baixa_data'),
            $request->input('baixa_observacao'),
        ));

        return $this->resposta($bem);
    }

    private function resposta(Bem $bem): BemResource
    {
        return new BemResource($bem->load(Bem::RELACOES_DA_FICHA));
    }
}
