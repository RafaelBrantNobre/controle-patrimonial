<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalaRequest;
use App\Http\Resources\SalaResource;
use App\Models\Sala;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/*
| Salas do prédio (tarefa 2.5).
| RF06 cadastrar e editar. RF07 inativar. RN08 sala com bens não é excluída.
*/
class SalaController extends Controller
{
    /**
     * GET /api/salas
     * Filtros: ?busca= (parte do nome) e ?ativo=1 ou 0. Sai em ordem de nome.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $salas = Sala::query()
            ->withCount('bens')
            ->when($request->filled('busca'), fn ($query) => $query->where('nome', 'like', $this->padraoDeBusca($request->string('busca')->toString())))
            ->when($request->filled('ativo'), fn ($query) => $query->where('ativo', $request->boolean('ativo')))
            ->orderBy('nome')
            ->paginate($this->porPagina($request), pageName: 'pagina')
            ->withQueryString();

        return SalaResource::collection($salas);
    }

    /**
     * GET /api/salas/{sala}
     */
    public function show(Sala $sala): SalaResource
    {
        return new SalaResource($sala->loadCount('bens'));
    }

    /**
     * POST /api/salas
     */
    public function store(SalaRequest $request): SalaResource
    {
        $sala = Sala::create($request->validated());

        return new SalaResource($sala->loadCount('bens'));
    }

    /**
     * PUT ou PATCH /api/salas/{sala}
     * Também inativa e reativa: {"ativo": false} ou {"ativo": true}.
     */
    public function update(SalaRequest $request, Sala $sala): SalaResource
    {
        $sala->update($request->validated());

        return new SalaResource($sala->loadCount('bens'));
    }

    /**
     * DELETE /api/salas/{sala}
     * Só exclui sala sem bens. Com bens, o caminho é inativar (RN08).
     * Também não exclui sala que aparece no histórico de mudanças de bens.
     */
    public function destroy(Sala $sala): JsonResponse
    {
        if ($sala->bens()->exists()) {
            return response()->json([
                'message' => 'Esta sala tem bens cadastrados e não pode ser excluída. Se ela não é mais usada, inative-a.',
            ], 409);
        }

        if ($sala->apareceNoHistorico()) {
            return response()->json([
                'message' => 'Esta sala aparece no histórico de mudanças ou de conferências de bens e não pode ser excluída. Se ela não é mais usada, inative-a.',
            ], 409);
        }

        $sala->delete();

        return response()->json([
            'message' => 'Sala excluída.',
        ]);
    }
}
