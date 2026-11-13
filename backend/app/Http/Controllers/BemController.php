<?php

namespace App\Http\Controllers;

use App\Consultas\FiltroDeBens;
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
     * outras desempatam. "local" é a ordem do levantamento: sala e tipo. Os
     * bens sem localização vêm primeiro.
     */
    private const ORDENACOES = [
        'local' => ['sala.nome', 'tipo_patrimonio.nome', 'bem.numero_patrimonio'],
        'numero' => ['bem.numero_patrimonio'],
        'codigo' => ['bem.codigo_interno'],
        'tipo' => ['tipo_patrimonio.nome', 'bem.numero_patrimonio'],
        'status' => ['bem.status', 'sala.nome'],
        'conservacao' => ['bem.estado_conservacao', 'sala.nome'],
        'cadastro' => ['bem.created_at'],
    ];

    /**
     * GET /api/bens
     * Filtros, todos opcionais e combináveis:
     *   ?numero=      número de patrimônio ou código interno exato (RF12)
     *   ?busca=       parte do número, do código, da descrição ou da marca
     *   ?sala_id=, ?tipo_patrimonio_id=, ?status= (RF13)
     *   ?sem_sala=1   só os bens sem localização
     *   ?estado_conservacao=  um ou mais estados, separados por vírgula
     *   ?conferencia= nunca, encontrado, nao_localizado ou em_aberto
     *   ?ordenar=     local (padrão), numero, codigo, tipo, status, conservacao, cadastro
     *   ?direcao=     asc (padrão) ou desc
     * Bens baixados aparecem, sinalizados em "baixado" (RN05).
     */
    public function index(ListarBensRequest $request): AnonymousResourceCollection
    {
        $filtros = $request->validated();
        $direcao = $filtros['direcao'] ?? 'asc';

        $bens = FiltroDeBens::consulta($filtros)
            ->with(['sala', 'tipoPatrimonio', 'itemEmConferenciaAberta.conferencia'])
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
            ->with(['tipoPatrimonio', 'itemEmConferenciaAberta.conferencia'])
            ->orderBy('tipo_patrimonio.nome')
            ->orderBy('bem.numero_patrimonio')
            ->orderBy('bem.id')
            ->get();

        return BemResource::collection($bens)->additional([
            'sala' => new SalaResource($sala),
            'total' => $bens->count(),
        ]);
    }

    /**
     * GET /api/bens/{bem}
     * Ficha do bem com todos os dados (RF15).
     */
    public function show(Bem $bem): BemResource
    {
        return new BemResource($bem->load(Bem::RELACOES_DA_FICHA));
    }

    /**
     * POST /api/bens
     * O código interno é gerado pelo sistema (PD07). Sem sala_id, o bem
     * entra sem localização, até a sala ser confirmada numa conferência.
     */
    public function store(BemRequest $request): BemResource
    {
        $bem = DB::transaction(fn () => Bem::create($request->validated()));

        return new BemResource($bem->load(Bem::RELACOES_DA_FICHA));
    }

    /**
     * PUT ou PATCH /api/bens/{bem}
     * Edita os dados de identificação do bem. Sala, status e conservação
     * mudam pela ficha (MudancaDeBemController), que grava a ocorrência.
     */
    public function update(BemRequest $request, Bem $bem): BemResource
    {
        $bem->update($request->safe()->except(['sala_id', 'status', 'estado_conservacao']));

        return new BemResource($bem->load(Bem::RELACOES_DA_FICHA));
    }
}
