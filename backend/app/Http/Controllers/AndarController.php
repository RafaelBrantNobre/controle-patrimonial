<?php

namespace App\Http\Controllers;

use App\Http\Requests\AndarRequest;
use App\Http\Resources\AndarResource;
use App\Models\Andar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/*
| Andares do prédio (tarefa 2.5).
| RF05 cadastrar e editar, RF07 inativar, RN08 andar com salas não é excluído.
*/
class AndarController extends Controller
{
    /**
     * GET /api/andares
     * Filtros: ?busca= (parte do nome) e ?ativo=1 ou 0.
     * Sai na ordem do relatório (campo ordem), e não em ordem alfabética.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $andares = Andar::query()
            ->withCount('salas')
            ->when($request->filled('busca'), fn ($query) => $query->where('nome', 'like', $this->padraoDeBusca($request->string('busca')->toString())))
            ->when($request->filled('ativo'), fn ($query) => $query->where('ativo', $request->boolean('ativo')))
            ->ordenados()
            ->paginate($this->porPagina($request), pageName: 'pagina')
            ->withQueryString();

        return AndarResource::collection($andares);
    }

    /**
     * GET /api/andares/{andar}
     */
    public function show(Andar $andar): AndarResource
    {
        return new AndarResource($andar->loadCount('salas'));
    }

    /**
     * POST /api/andares
     */
    public function store(AndarRequest $request): AndarResource
    {
        $andar = Andar::create($request->validated());

        return new AndarResource($andar->loadCount('salas'));
    }

    /**
     * PUT ou PATCH /api/andares/{andar}
     * Também inativa e reativa: {"ativo": false} ou {"ativo": true}.
     */
    public function update(AndarRequest $request, Andar $andar): AndarResource
    {
        $andar->update($request->validated());

        return new AndarResource($andar->loadCount('salas'));
    }

    /**
     * DELETE /api/andares/{andar}
     * Só exclui andar sem salas. Com salas, o caminho é inativar (RN08).
     */
    public function destroy(Andar $andar): JsonResponse
    {
        if ($andar->salas()->exists()) {
            return response()->json([
                'message' => 'Este andar tem salas cadastradas e não pode ser excluído. Se ele não é mais usado, inative-o.',
            ], 409);
        }

        $andar->delete();

        return response()->json([
            'message' => 'Andar excluído.',
        ]);
    }
}
