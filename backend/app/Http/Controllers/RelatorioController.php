<?php

namespace App\Http\Controllers;

use App\Http\Requests\RelatorioRequest;
use App\Models\Bem;
use App\Relatorios\PlanilhaDoLevantamento;
use App\Relatorios\RelatorioDoLevantamento;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/*
| Relatório do levantamento patrimonial (tarefa 2.10, RF22 a RF26).
| Administrador e consulta podem gerar e exportar.
*/
class RelatorioController extends Controller
{
    /**
     * GET /api/relatorio?inicio=2026-07-01&fim=2026-12-31
     * Filtros opcionais: andar_id, sala_id, tipo_patrimonio_id, status e
     * apenas_mudancas=1. Os bens vêm agrupados por andar e sala (RF23).
     */
    public function index(RelatorioRequest $request): JsonResponse
    {
        $relatorio = new RelatorioDoLevantamento($request->validated());

        $andares = $relatorio->bens()
            ->groupBy(fn (Bem $bem) => $bem->sala->andar_id)
            ->map(function ($bensDoAndar) {
                $andar = $bensDoAndar->first()->sala->andar;

                return [
                    'id' => $andar->id,
                    'nome' => $andar->nome,
                    'total' => $bensDoAndar->count(),
                    'salas' => $bensDoAndar->groupBy('sala_id')->map(fn ($bensDaSala) => [
                        'id' => $bensDaSala->first()->sala->id,
                        'nome' => $bensDaSala->first()->sala->nome,
                        'total' => $bensDaSala->count(),
                        'bens' => $bensDaSala->map(fn (Bem $bem) => $this->bemNoRelatorio($bem))->values(),
                    ])->values(),
                ];
            })
            ->values();

        return response()->json([
            'periodo' => [
                'inicio' => $relatorio->inicio->toDateString(),
                'fim' => $relatorio->fim->toDateString(),
            ],
            'totais' => $relatorio->totais(),
            'andares' => $andares,
        ]);
    }

    /**
     * GET /api/relatorio/planilha?inicio=2026-07-01&fim=2026-12-31
     * Mesmos filtros do relatório. Devolve uma planilha Excel (.xlsx) com a
     * aba Principal e uma aba por sala (RF26). Ver PlanilhaDoLevantamento.
     */
    public function planilha(RelatorioRequest $request): BinaryFileResponse
    {
        $relatorio = new RelatorioDoLevantamento($request->validated());
        $arquivo = "levantamento_{$relatorio->inicio->toDateString()}_a_{$relatorio->fim->toDateString()}.xlsx";

        $caminho = tempnam(sys_get_temp_dir(), 'levantamento_');
        (new PlanilhaDoLevantamento($relatorio))->salvarEm($caminho);

        return response()->download($caminho, $arquivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /**
     * @return array<string, mixed>
     */
    private function bemNoRelatorio(Bem $bem): array
    {
        return [
            'id' => $bem->id,
            'numero_patrimonio' => $bem->numero_patrimonio,
            'codigo_interno' => $bem->codigo_interno,
            'tipo' => $bem->tipoPatrimonio->nome,
            'descricao' => $bem->descricao,
            'marca' => $bem->marca,
            'quantidade' => $bem->quantidade,
            'status' => $bem->status->value,
            'status_rotulo' => $bem->status->rotulo(),
            'baixado' => $bem->estaBaixado(),
            'baixa_data' => $bem->baixa_data?->toDateString(),
            'data_ultima_mudanca' => $bem->data_ultima_mudanca?->toDateString(),
            'mudou_no_periodo' => $bem->mudou_no_periodo,
            'mudou_de_sala_no_periodo' => $bem->mudou_de_sala_no_periodo,
            'veio_da_sala' => $bem->veioDaSala ? [
                'id' => $bem->veioDaSala->id,
                'nome' => $bem->veioDaSala->nome,
                'andar' => $bem->veioDaSala->andar->nome,
            ] : null,
            'observacao' => $bem->observacao,
        ];
    }
}
