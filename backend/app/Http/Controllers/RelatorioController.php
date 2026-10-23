<?php

namespace App\Http\Controllers;

use App\Http\Requests\RelatorioRequest;
use App\Models\Bem;
use App\Relatorios\RelatorioDoLevantamento;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
| Relatório do levantamento patrimonial (tarefa 2.10, RF22 a RF26).
| Administrador e consulta podem gerar e exportar.
*/
class RelatorioController extends Controller
{
    /** Colunas da planilha, na ordem em que aparecem. */
    private const COLUNAS = [
        'Andar', 'Sala', 'Nº de patrimônio', 'Código interno', 'Tipo', 'Descrição', 'Marca',
        'Quantidade', 'Situação', 'Última mudança', 'Mudou no período', 'Veio da sala',
        'Data da baixa', 'Observação',
    ];

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
     * Mesmos filtros do relatório. Devolve um arquivo CSV que o Excel abre
     * direto: UTF-8 com BOM, para os acentos, e separador ponto e vírgula,
     * o padrão do Excel em português (RF26).
     */
    public function planilha(RelatorioRequest $request): StreamedResponse
    {
        $relatorio = new RelatorioDoLevantamento($request->validated());
        $arquivo = "levantamento_{$relatorio->inicio->toDateString()}_a_{$relatorio->fim->toDateString()}.csv";

        return response()->streamDownload(function () use ($relatorio) {
            $saida = fopen('php://output', 'w');
            fwrite($saida, "\xEF\xBB\xBF");
            fputcsv($saida, self::COLUNAS, ';', '"', '');

            foreach ($relatorio->bens() as $bem) {
                fputcsv($saida, array_map($this->celulaSegura(...), $this->bemNaPlanilha($bem)), ';', '"', '');
            }

            fclose($saida);
        }, $arquivo, ['Content-Type' => 'text/csv; charset=UTF-8']);
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

    /**
     * Uma linha da planilha, na ordem de COLUNAS.
     *
     * @return array<int, string|int|null>
     */
    private function bemNaPlanilha(Bem $bem): array
    {
        return [
            $bem->sala->andar->nome,
            $bem->sala->nome,
            $bem->numero_patrimonio,
            $bem->codigo_interno,
            $bem->tipoPatrimonio->nome,
            $bem->descricao,
            $bem->marca,
            $bem->quantidade,
            $bem->status->rotulo(),
            $bem->data_ultima_mudanca?->format('d/m/Y'),
            $bem->mudou_no_periodo ? 'Sim' : 'Não',
            $bem->veioDaSala ? "{$bem->veioDaSala->nome} ({$bem->veioDaSala->andar->nome})" : null,
            $bem->baixa_data?->format('d/m/Y'),
            $bem->observacao,
        ];
    }

    /**
     * Texto que começa com =, +, -, @ ou tabulação vira fórmula no Excel.
     * Um apóstrofo na frente faz o Excel mostrar o texto como está, e
     * impede que um dado digitado no sistema rode como fórmula na planilha.
     */
    private function celulaSegura(string|int|null $valor): string|int|null
    {
        if (is_string($valor) && $valor !== '' && in_array($valor[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$valor;
        }

        return $valor;
    }
}
