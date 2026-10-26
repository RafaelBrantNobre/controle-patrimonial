<?php

namespace App\Http\Controllers;

use App\Http\Requests\TipoPatrimonioRequest;
use App\Http\Resources\TipoPatrimonioResource;
use App\Models\TipoPatrimonio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/*
| Tipos de patrimônio: computador, cadeira, ar condicionado... (tarefa 2.5).
| RF08 cadastrar e editar, RF09 inativar, RN08 tipo com bens não é excluído.
*/
class TipoPatrimonioController extends Controller
{
    /**
     * GET /api/tipos-patrimonio
     * Filtros: ?busca= (parte do nome) e ?ativo=1 ou 0. Sai em ordem alfabética.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $tipos = TipoPatrimonio::query()
            ->withCount('bens')
            ->when($request->filled('busca'), fn ($query) => $query->where('nome', 'like', $this->padraoDeBusca($request->string('busca')->toString())))
            ->when($request->filled('ativo'), fn ($query) => $query->where('ativo', $request->boolean('ativo')))
            ->orderBy('nome')
            ->paginate($this->porPagina($request), pageName: 'pagina')
            ->withQueryString();

        return TipoPatrimonioResource::collection($tipos);
    }

    /**
     * GET /api/tipos-patrimonio/{tipo}
     */
    public function show(TipoPatrimonio $tipo): TipoPatrimonioResource
    {
        return new TipoPatrimonioResource($tipo->loadCount('bens'));
    }

    /**
     * POST /api/tipos-patrimonio
     */
    public function store(TipoPatrimonioRequest $request): TipoPatrimonioResource
    {
        $tipo = TipoPatrimonio::create($request->validated());

        return new TipoPatrimonioResource($tipo->loadCount('bens'));
    }

    /**
     * PUT ou PATCH /api/tipos-patrimonio/{tipo}
     * Também inativa e reativa: {"ativo": false} ou {"ativo": true}.
     */
    public function update(TipoPatrimonioRequest $request, TipoPatrimonio $tipo): TipoPatrimonioResource
    {
        $tipo->update($request->validated());

        return new TipoPatrimonioResource($tipo->loadCount('bens'));
    }

    /**
     * DELETE /api/tipos-patrimonio/{tipo}
     * Só exclui tipo sem bens. Com bens, o caminho é inativar (RN08).
     */
    public function destroy(TipoPatrimonio $tipo): JsonResponse
    {
        if ($tipo->bens()->exists()) {
            return response()->json([
                'message' => 'Este tipo tem bens cadastrados e não pode ser excluído. Se ele não é mais usado, inative-o.',
            ], 409);
        }

        $tipo->delete();

        return response()->json([
            'message' => 'Tipo de patrimônio excluído.',
        ]);
    }
}
