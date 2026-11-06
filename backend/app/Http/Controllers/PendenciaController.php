<?php

namespace App\Http\Controllers;

use App\Enums\ResolucaoPendencia;
use App\Enums\ResultadoConferencia;
use App\Http\Requests\ResolverPendenciaRequest;
use App\Http\Resources\BemResource;
use App\Models\Bem;
use App\Models\Sala;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/*
| Pendências da gestora: bens que não foram localizados numa conferência
| concluída. Bem perdido é grave: a gestora responde por ele e abre
| processo administrativo. A pendência só fecha de dois jeitos: o bem
| apareceu (localizado) ou o processo foi aberto (com o número).
*/
class PendenciaController extends Controller
{
    /**
     * GET /api/pendencias
     * Os bens com pendência aberta, cada um uma vez, com a data da primeira
     * conferência em que não foi localizado.
     */
    public function index(): AnonymousResourceCollection
    {
        $bens = Bem::query()
            ->whereHas('pendenciasAbertas')
            ->with(['sala.andar', 'tipoPatrimonio', 'pendenciaAberta.conferencia'])
            ->orderBy('numero_patrimonio')
            ->orderBy('id')
            ->get();

        return BemResource::collection($bens);
    }

    /**
     * POST /api/bens/{bem}/pendencia
     * Corpo: {"resolucao": "localizado", "sala_id": 5, "localizado_em": "2026-11-06"}
     *     ou {"resolucao": "processo_administrativo", "processo_numero": "123/2026"}
     */
    public function resolver(ResolverPendenciaRequest $request, Bem $bem): BemResource
    {
        $usuario = $request->user();
        $resolucao = ResolucaoPendencia::from($request->validated('resolucao'));

        DB::transaction(function () use ($request, $bem, $usuario, $resolucao) {
            if ($resolucao === ResolucaoPendencia::ProcessoAdministrativo) {
                $bem->resolverPendencias($resolucao, $usuario, $request->validated('processo_numero'));

                return;
            }

            $sala = Sala::findOrFail($request->validated('sala_id'));
            if ($sala->id !== $bem->sala_id) {
                $bem->mudarDeSala($sala, $usuario);
            }

            $bem->resolverPendencias($resolucao, $usuario);
            $bem->registrarConferencia(ResultadoConferencia::Encontrado, $request->validated('localizado_em'));
            $bem->save();
        });

        return new BemResource($bem->fresh()->load(Bem::RELACOES_DA_FICHA));
    }
}
