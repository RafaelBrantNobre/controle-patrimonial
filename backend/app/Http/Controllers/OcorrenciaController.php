<?php

namespace App\Http\Controllers;

use App\Consultas\FiltroDeBens;
use App\Http\Requests\ListarOcorrenciasRequest;
use App\Http\Resources\OcorrenciaResource;
use App\Models\Ocorrencia;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/*
| Lista de ocorrências de todos os bens: mudança de sala, de conservação,
| de condição de uso e baixa. As ocorrências são gravadas pelo próprio Bem;
| aqui só se consulta.
*/
class OcorrenciaController extends Controller
{
    /**
     * GET /api/ocorrencias
     * Filtros do bem, os mesmos de GET /api/bens: ?busca=, ?numero=,
     *   ?sala_id= (sala atual do bem), ?sem_sala=1, ?tipo_patrimonio_id=,
     *   ?status=, ?estado_conservacao=, ?conferencia=
     * Filtros da ocorrência: ?tipo=sala,conservacao,uso,baixa  ?de=  ?ate=
     * Mais recentes primeiro.
     */
    public function index(ListarOcorrenciasRequest $request): AnonymousResourceCollection
    {
        $filtros = $request->validated();
        $filtrosDoBem = array_diff_key($filtros, array_flip(['tipo', 'de', 'ate']));

        $ocorrencias = Ocorrencia::query()
            ->when($filtrosDoBem !== [], fn ($query) => $query->whereIn('bem_id', FiltroDeBens::consulta($filtrosDoBem)->select('bem.id')))
            ->when($filtros['tipo'] ?? null, fn ($query, array $tipos) => $query->whereIn('tipo', $tipos))
            ->when($filtros['de'] ?? null, fn ($query, string $de) => $query->whereDate('data', '>=', $de))
            ->when($filtros['ate'] ?? null, fn ($query, string $ate) => $query->whereDate('data', '<=', $ate))
            ->with(['bem.tipoPatrimonio', 'bem.sala', 'usuario', 'conferencia'])
            ->latest('data')
            ->latest('id')
            ->paginate($this->porPagina($request), pageName: 'pagina')
            ->withQueryString();

        return OcorrenciaResource::collection($ocorrencias);
    }
}
