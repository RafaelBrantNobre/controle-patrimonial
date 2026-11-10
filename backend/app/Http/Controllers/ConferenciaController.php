<?php

namespace App\Http\Controllers;

use App\Consultas\FiltroDeBens;
use App\Enums\EstadoConservacao;
use App\Enums\ResultadoConferencia;
use App\Enums\SituacaoConferencia;
use App\Enums\StatusBem;
use App\Http\Requests\ConcluirConferenciaRequest;
use App\Http\Requests\GerarConferenciaRequest;
use App\Http\Requests\LancarResultadoRequest;
use App\Http\Requests\MarcarEncontradosRequest;
use App\Http\Requests\SalvarItensRequest;
use App\Http\Resources\ConferenciaItemResource;
use App\Http\Resources\ConferenciaResource;
use App\Models\Bem;
use App\Models\Conferencia;
use App\Models\ConferenciaItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/*
| Conferência dos bens: gerar, lançar os resultados da folha, concluir e
| cancelar. Só o administrador altera (RN10); o perfil consulta acompanha.
|
| Fluxo: gerar a conferência com os bens escolhidos na lista (caixinhas ou
| todos de um filtro), imprimir a folha, conferir no papel, lançar só as
| exceções e marcar o resto como encontrado, e concluir. Os bens só mudam
| na conclusão (Conferencia::concluir()).
*/
class ConferenciaController extends Controller
{
    /** Relações que a resposta de uma conferência traz. */
    private const RELACOES = ['criadaPor', 'concluidaPor'];

    /** Relações que a resposta de um item traz. */
    private const RELACOES_DO_ITEM = ['bem.sala.andar', 'bem.tipoPatrimonio', 'salaRegistrada.andar', 'salaEncontrada.andar', 'conferidoPor'];

    /**
     * GET /api/conferencias
     * As conferências, da mais recente para a mais antiga, com as contagens.
     * Filtro opcional: ?situacao=aberta, concluida ou cancelada.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(
            ['situacao' => ['sometimes', 'nullable', Rule::enum(SituacaoConferencia::class)]],
            ['situacao.enum' => 'A situação do filtro deve ser aberta, concluida ou cancelada.'],
        );

        $conferencias = Conferencia::query()
            ->withCount(Conferencia::contagens())
            ->with(self::RELACOES)
            ->when($request->query('situacao'), fn ($query, string $situacao) => $query->where('situacao', $situacao))
            ->latest('id')
            ->paginate($this->porPagina($request), pageName: 'pagina')
            ->withQueryString();

        return ConferenciaResource::collection($conferencias);
    }

    /**
     * GET /api/conferencias/{conferencia}
     */
    public function show(Conferencia $conferencia): ConferenciaResource
    {
        return $this->resposta($conferencia);
    }

    /**
     * POST /api/conferencias
     * Corpo: {"nome": "...", "modo": "selecionados", "bem_ids": [1, 2]}
     *     ou {"nome": "...", "modo": "filtro", "filtros": {"sala_id": 5}}
     * Modo "filtro" sem filtros é o prédio inteiro: é como a tela inicia uma
     * conferência com os bens cadastrados.
     * Só existe uma conferência aberta por vez (409 se já houver).
     * Ficam de fora os bens baixados. A resposta diz quantos entraram.
     */
    public function store(GerarConferenciaRequest $request): JsonResponse
    {
        $aberta = Conferencia::abertas()->first();

        if ($aberta) {
            return response()->json([
                'message' => "Já existe uma conferência aberta: {$aberta->nome}. Conclua ou cancele essa conferência antes de iniciar outra.",
                'conferencia_id' => $aberta->id,
            ], 409);
        }

        $consulta = $request->input('modo') === 'filtro'
            ? FiltroDeBens::consulta($request->validated('filtros') ?? [])
            : Bem::query()->whereIn('bem.id', $request->validated('bem_ids'));

        $ids = (clone $consulta)->pluck('bem.id');
        $baixados = (clone $consulta)->where('bem.status', StatusBem::Baixado)->pluck('bem.id');
        $emOutra = ConferenciaItem::query()
            ->whereIn('bem_id', $ids)
            ->whereHas('conferencia', fn ($query) => $query->abertas())
            ->pluck('bem_id');

        $incluir = $ids->diff($baixados)->diff($emOutra)->values();

        if ($incluir->isEmpty()) {
            throw ValidationException::withMessages([
                'bem_ids' => $ids->isEmpty()
                    ? 'Nenhum bem corresponde a esta seleção.'
                    : 'Nenhum bem pode entrar nesta conferência: os escolhidos estão baixados ou já estão em outra conferência aberta.',
            ]);
        }

        // A sala de cada bem agora, que é a da folha impressa.
        $salaDe = Bem::whereIn('id', $incluir)->pluck('sala_id', 'id');

        $conferencia = DB::transaction(function () use ($request, $incluir, $salaDe) {
            $conferencia = Conferencia::create([
                'nome' => $request->validated('nome'),
                'criada_por_id' => $request->user()->id,
            ]);

            $agora = now();
            foreach ($incluir->chunk(500) as $lote) {
                ConferenciaItem::insert($lote->map(fn (int $bemId) => [
                    'conferencia_id' => $conferencia->id,
                    'bem_id' => $bemId,
                    'sala_registrada_id' => $salaDe[$bemId],
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ])->values()->all());
            }

            return $conferencia;
        });

        return $this->resposta($conferencia)
            ->additional([
                'incluidos' => $incluir->count(),
                'ignorados' => [
                    'baixados' => $baixados->count(),
                    'em_outra_conferencia' => $emOutra->unique()->diff($baixados)->count(),
                ],
            ])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/conferencias/{conferencia}/itens
     * Os bens da conferência na ordem da folha: sala, tipo e número.
     * A sala é a registrada quando a conferência foi gerada (a da folha),
     * mesmo que a conclusão tenha mudado o bem de sala. Os bens sem sala
     * registrada vêm por último, no grupo "Sem localização" (id nulo).
     * Filtros opcionais: ?sala_id= (sala registrada), ?sem_sala=1 e
     * ?resultado=pendente, encontrado ou nao_localizado.
     * Com ?resumo=1 vêm só a conferência e as salas, sem os bens.
     * Traz junto as salas da conferência, com total e pendentes de cada uma,
     * para a tela lançar sala por sala.
     */
    public function itens(Request $request, Conferencia $conferencia): AnonymousResourceCollection
    {
        $request->validate(
            [
                'sala_id' => ['sometimes', 'nullable', 'integer'],
                'resultado' => ['sometimes', 'nullable', Rule::in(['pendente', ...array_column(ResultadoConferencia::cases(), 'value')])],
            ],
            [
                'sala_id.integer' => 'A sala do filtro não é válida.',
                'resultado.in' => 'O resultado do filtro deve ser pendente, encontrado ou nao_localizado.',
            ],
        );

        $itens = $this->itensNaOrdemDaFolha($conferencia)
            ->select('conferencia_item.*')
            ->when($request->boolean('resumo'), fn ($query) => $query->whereRaw('1 = 0'))
            ->when($request->integer('sala_id'), fn ($query, int $salaId) => $query->where('conferencia_item.sala_registrada_id', $salaId))
            ->when($request->boolean('sem_sala'), fn ($query) => $query->whereNull('conferencia_item.sala_registrada_id'))
            ->when($request->query('resultado'), fn ($query, string $resultado) => $resultado === 'pendente'
                ? $query->whereNull('conferencia_item.resultado')
                : $query->where('conferencia_item.resultado', $resultado))
            ->with(self::RELACOES_DO_ITEM)
            ->orderBy('tipo_patrimonio.nome')
            ->orderBy('bem.numero_patrimonio')
            ->orderBy('conferencia_item.id')
            ->get();

        $salas = $this->itensNaOrdemDaFolha($conferencia)
            ->toBase()
            ->select('sala.id', 'sala.nome', 'andar.nome as andar')
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when conferencia_item.resultado is null then 1 else 0 end) as pendentes')
            ->groupBy('sala.id', 'sala.nome', 'andar.nome', 'andar.ordem')
            ->get()
            ->map(fn ($sala) => [
                'id' => $sala->id === null ? null : (int) $sala->id,
                'nome' => $sala->nome ?? 'Sem localização',
                'andar' => $sala->andar,
                'total' => (int) $sala->total,
                'pendentes' => (int) $sala->pendentes,
            ]);

        return ConferenciaItemResource::collection($itens)->additional([
            'conferencia' => $this->resposta($conferencia),
            'salas' => $salas,
        ]);
    }

    /**
     * PATCH /api/conferencias/{conferencia}/itens/{item}
     * Corpo: {"resultado": "encontrado", "sala_encontrada_id": 7, "estado_conservacao": "regular"}
     *     ou {"resultado": "nao_localizado"}, ou {"resultado": null} para voltar a pendente.
     */
    public function lancar(LancarResultadoRequest $request, Conferencia $conferencia, ConferenciaItem $item): ConferenciaItemResource
    {
        $this->lancarItem($item, $request->validated(), $request->user());

        return new ConferenciaItemResource($item->load(self::RELACOES_DO_ITEM));
    }

    /**
     * PUT /api/conferencias/{conferencia}/itens
     * Corpo: {"itens": [{"id": 10, "resultado": "encontrado", "sala_encontrada_id": 7,
     *                    "estado_conservacao": "bom", "condicao_uso": "em_uso"}, ...]}
     * Salva os bens de uma sala de uma vez: é o "salvar sala" da conferência.
     * Resultado nulo deixa o bem pendente. Nada muda nos bens até concluir.
     */
    public function salvarItens(SalvarItensRequest $request, Conferencia $conferencia): ConferenciaResource
    {
        $itens = $request->itensDaConferencia();

        DB::transaction(function () use ($request, $itens) {
            foreach ($request->validated('itens') as $dados) {
                $this->lancarItem($itens[(int) $dados['id']], $dados, $request->user());
            }
        });

        return $this->resposta($conferencia);
    }

    /**
     * @param  array<string, mixed>  $dados  resultado, sala_encontrada_id, estado_conservacao e condicao_uso
     */
    private function lancarItem(ConferenciaItem $item, array $dados, User $usuario): void
    {
        $item->lancar(
            ($dados['resultado'] ?? null) ? ResultadoConferencia::from($dados['resultado']) : null,
            $dados['sala_encontrada_id'] ?? null,
            ($dados['estado_conservacao'] ?? null) ? EstadoConservacao::from($dados['estado_conservacao']) : null,
            $usuario,
            ($dados['condicao_uso'] ?? null) ? StatusBem::from($dados['condicao_uso']) : null,
        );
    }

    /**
     * POST /api/conferencias/{conferencia}/encontrados
     * Corpo: {"itens": [10, 11, 12]}
     * Marca como encontrados, na sala registrada, os itens ainda pendentes.
     * Os que já têm resultado (encontrado em outra sala, não localizado)
     * ficam como estão: para mudar, lança-se o item.
     */
    public function marcarEncontrados(MarcarEncontradosRequest $request, Conferencia $conferencia): ConferenciaResource
    {
        $usuario = $request->user();

        DB::transaction(function () use ($request, $conferencia, $usuario) {
            $conferencia->itens()
                ->whereIn('id', $request->validated('itens'))
                ->whereNull('resultado')
                ->with('bem')
                ->get()
                ->each(fn (ConferenciaItem $item) => $item->lancar(ResultadoConferencia::Encontrado, null, null, $usuario));
        });

        return $this->resposta($conferencia);
    }

    /**
     * POST /api/conferencias/{conferencia}/concluir
     * Corpo: {"realizada_em": "2026-11-06"}
     * Aplica os resultados aos bens: sala onde estavam, estado de conservação
     * e condição de uso (cada mudança vira uma ocorrência no histórico do
     * bem), e data e resultado da última conferência. Os não localizados
     * viram pendências da gestora.
     */
    public function concluir(ConcluirConferenciaRequest $request, Conferencia $conferencia): ConferenciaResource
    {
        DB::transaction(fn () => $conferencia->concluir($request->validated('realizada_em'), $request->user()));

        return $this->resposta($conferencia);
    }

    /**
     * POST /api/conferencias/{conferencia}/cancelar
     * Só a conferência aberta. Nada muda nos bens, e eles podem entrar numa
     * conferência nova.
     */
    public function cancelar(Conferencia $conferencia): ConferenciaResource|JsonResponse
    {
        if (! $conferencia->estaAberta()) {
            return response()->json([
                'message' => 'Esta conferência já foi concluída ou cancelada e não pode ser cancelada.',
            ], 409);
        }

        $conferencia->update(['situacao' => SituacaoConferencia::Cancelada]);

        return $this->resposta($conferencia);
    }

    /**
     * Itens da conferência com bem, sala registrada, andar e tipo juntos, já
     * na ordem de andar e sala da folha.
     */
    private function itensNaOrdemDaFolha(Conferencia $conferencia)
    {
        return $conferencia->itens()
            ->join('bem', 'bem.id', '=', 'conferencia_item.bem_id')
            ->leftJoin('sala', 'sala.id', '=', 'conferencia_item.sala_registrada_id')
            ->leftJoin('andar', 'andar.id', '=', 'sala.andar_id')
            ->join('tipo_patrimonio', 'tipo_patrimonio.id', '=', 'bem.tipo_patrimonio_id')
            ->orderByRaw('case when sala.id is null then 1 else 0 end')
            ->orderBy('andar.ordem')
            ->orderBy('sala.nome');
    }

    private function resposta(Conferencia $conferencia): ConferenciaResource
    {
        return new ConferenciaResource($conferencia->loadCount(Conferencia::contagens())->load(self::RELACOES));
    }
}
