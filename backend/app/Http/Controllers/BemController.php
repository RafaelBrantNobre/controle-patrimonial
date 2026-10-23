<?php

namespace App\Http\Controllers;

use App\Http\Requests\BemRequest;
use App\Http\Requests\ListarBensRequest;
use App\Http\Resources\BemResource;
use App\Http\Resources\SalaResource;
use App\Models\Bem;
use App\Models\Sala;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/*
| Bens patrimoniais (tarefa 2.6).
| RF10 cadastrar, RF11 editar, RF12 localizar pelo número, RF13 filtrar,
| RF14 listar os bens de uma sala, RF15 ficha do bem.
| Não há exclusão: a saída do patrimônio é a baixa (RN07), na tarefa 2.8.
*/
class BemController extends Controller
{
    /**
     * Colunas de cada ordenação aceita em ?ordenar=. A primeira decide, as
     * outras desempatam. "local" é a ordem do levantamento: andar, sala, tipo.
     */
    private const ORDENACOES = [
        'local' => ['andar.ordem', 'sala.nome', 'tipo_patrimonio.nome', 'bem.numero_patrimonio'],
        'numero' => ['bem.numero_patrimonio'],
        'codigo' => ['bem.codigo_interno'],
        'tipo' => ['tipo_patrimonio.nome', 'bem.numero_patrimonio'],
        'status' => ['bem.status', 'andar.ordem', 'sala.nome'],
        'cadastro' => ['bem.created_at'],
    ];

    /**
     * GET /api/bens
     * Filtros, todos opcionais e combináveis:
     *   ?numero=      número de patrimônio ou código interno exato (RF12)
     *   ?busca=       parte do número, do código, da descrição ou da marca
     *   ?andar_id=, ?sala_id=, ?tipo_patrimonio_id=, ?status= (RF13)
     *   ?ordenar=     local (padrão), numero, codigo, tipo, status, cadastro
     *   ?direcao=     asc (padrão) ou desc
     * Bens baixados aparecem, sinalizados em "baixado" (RN05).
     */
    public function index(ListarBensRequest $request): AnonymousResourceCollection
    {
        $filtros = $request->validated();
        $direcao = $filtros['direcao'] ?? 'asc';

        $bens = Bem::query()
            ->select('bem.*')
            ->join('sala', 'sala.id', '=', 'bem.sala_id')
            ->join('andar', 'andar.id', '=', 'sala.andar_id')
            ->join('tipo_patrimonio', 'tipo_patrimonio.id', '=', 'bem.tipo_patrimonio_id')
            ->with(['sala.andar', 'tipoPatrimonio'])
            ->when($filtros['numero'] ?? null, function ($query, string $numero) {
                $query->where(fn ($query) => $query
                    ->where('bem.numero_patrimonio', $numero)
                    ->orWhere('bem.codigo_interno', $numero));
            })
            ->when($filtros['busca'] ?? null, function ($query, string $busca) {
                $padrao = $this->padraoDeBusca($busca);
                $query->where(fn ($query) => $query
                    ->where('bem.numero_patrimonio', 'like', $padrao)
                    ->orWhere('bem.codigo_interno', 'like', $padrao)
                    ->orWhere('bem.descricao', 'like', $padrao)
                    ->orWhere('bem.marca', 'like', $padrao));
            })
            ->when($filtros['andar_id'] ?? null, fn ($query, int $andarId) => $query->where('andar.id', $andarId))
            ->when($filtros['sala_id'] ?? null, fn ($query, int $salaId) => $query->where('bem.sala_id', $salaId))
            ->when($filtros['tipo_patrimonio_id'] ?? null, fn ($query, int $tipoId) => $query->where('bem.tipo_patrimonio_id', $tipoId))
            ->when($filtros['status'] ?? null, fn ($query, array $status) => $query->whereIn('bem.status', $status))
            ->tap(function ($query) use ($filtros, $direcao) {
                foreach (self::ORDENACOES[$filtros['ordenar'] ?? 'local'] as $coluna) {
                    $query->orderBy($coluna, $direcao);
                }
            })
            ->orderBy('bem.id', $direcao)
            ->paginate($this->porPagina($request), pageName: 'pagina')
            ->withQueryString();

        return BemResource::collection($bens);
    }

    /**
     * GET /api/salas/{sala}/bens
     * Todos os bens de uma sala, sem paginação, na ordem da conferência:
     * por tipo e, dentro do tipo, pelo número de patrimônio (RF14).
     */
    public function daSala(Sala $sala): AnonymousResourceCollection
    {
        $bens = $sala->bens()
            ->select('bem.*')
            ->join('tipo_patrimonio', 'tipo_patrimonio.id', '=', 'bem.tipo_patrimonio_id')
            ->with('tipoPatrimonio')
            ->orderBy('tipo_patrimonio.nome')
            ->orderBy('bem.numero_patrimonio')
            ->orderBy('bem.id')
            ->get();

        return BemResource::collection($bens)->additional([
            'sala' => new SalaResource($sala->load('andar')),
            'total' => $bens->count(),
        ]);
    }

    /**
     * GET /api/bens/{bem}
     * Ficha do bem com todos os dados (RF15).
     */
    public function show(Bem $bem): BemResource
    {
        return new BemResource($bem->load(['sala.andar', 'tipoPatrimonio', 'usuarioUltimaMudanca']));
    }

    /**
     * POST /api/bens
     * O código interno é gerado pelo sistema (PD07).
     */
    public function store(BemRequest $request): BemResource
    {
        $bem = DB::transaction(fn () => Bem::create($request->validated()));

        return new BemResource($bem->load(['sala.andar', 'tipoPatrimonio', 'usuarioUltimaMudanca']));
    }

    /**
     * PUT ou PATCH /api/bens/{bem}
     * Edita os dados do bem. Sala e status mudam pela tarefa 2.8.
     */
    public function update(BemRequest $request, Bem $bem): BemResource
    {
        $bem->update($request->safe()->except(['sala_id', 'status']));

        return new BemResource($bem->load(['sala.andar', 'tipoPatrimonio', 'usuarioUltimaMudanca']));
    }
}
