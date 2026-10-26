<?php

namespace App\Carga;

use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\Common\Entity\Sheet;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/*
| Relatório da carga em Excel (tarefa 2.12, RF29).
|
|   - Resumo: totais, o que foi criado e as rejeições agrupadas por motivo.
|   - Importados: cada bem que entrou, com a linha da planilha de onde veio.
|   - Rejeitados: cada linha que ficou de fora, com o motivo.
|   - Avisos: bens que entraram, mas merecem conferência.
|
| Todo texto é gravado como texto, para nada da planilha virar fórmula.
*/
class RelatorioDaCarga
{
    private Style $cabecalho;

    public function __construct(private readonly ResultadoDaCarga $resultado, private readonly string $arquivoLido, private readonly string $fonte)
    {
        $this->cabecalho = new Style(fontBold: true, backgroundColor: 'D9E2F3');
    }

    public function salvarEm(string $caminho): void
    {
        $writer = new Writer;
        $writer->openToFile($caminho);

        $this->resumo($writer, $writer->getCurrentSheet());

        $this->tabela($writer, $writer->addNewSheetAndMakeItCurrent(), 'Importados',
            ['Aba', 'Linha', 'Código interno', 'Patrimônio', 'Tipo', 'Descrição', 'Marca', 'Origem', 'Sala', 'Situação de uso', 'Estado de conservação', 'Avisos'],
            [10, 7, 13, 14, 22, 28, 14, 18, 30, 14, 18, 60],
            array_map(fn ($par) => [
                $par[0]->linha->aba, $par[0]->linha->linha, $par[1], $par[0]->numeroPatrimonio, $par[0]->tipo,
                $par[0]->descricao, $par[0]->marca, $par[0]->origem, $par[0]->sala, $par[0]->status,
                $par[0]->estadoConservacao, implode(' ', $par[0]->avisos),
            ], $this->resultado->importadas));

        $this->tabela($writer, $writer->addNewSheetAndMakeItCurrent(), 'Rejeitados',
            ['Aba', 'Linha', 'Patrimônio', 'Descrição', 'Local', 'Estado', 'Motivo'],
            [22, 7, 18, 30, 26, 22, 80],
            array_map(fn (LinhaInterpretada $l) => [
                $l->linha->aba, $l->linha->linha, $l->linha->patrimonio, $l->linha->descricao,
                $l->linha->local, $l->linha->estado, $l->motivoDaRejeicao,
            ], $this->resultado->rejeitadas));

        $this->tabela($writer, $writer->addNewSheetAndMakeItCurrent(), 'Avisos',
            ['Aba', 'Linha', 'Patrimônio na planilha', 'Descrição', 'Aviso'],
            [22, 7, 22, 30, 90],
            array_map(fn (LinhaInterpretada $l) => [
                $l->linha->aba, $l->linha->linha, $l->linha->patrimonio, $l->linha->descricao, implode(' ', $l->avisos),
            ], $this->resultado->comAvisos()));

        $writer->close();
    }

    private function resumo(Writer $writer, Sheet $aba): void
    {
        $aba->setName('Resumo');
        $aba->setColumnWidth(48, 1);
        $aba->setColumnWidth(70, 2);

        $r = $this->resultado;
        $linhas = [
            ['Carga inicial do patrimônio', $r->simulacao ? 'SIMULAÇÃO: nada foi gravado' : 'Carga gravada no sistema'],
            ['Gerado em', now()->format('d/m/Y H:i')],
            ['Arquivo lido', basename($this->arquivoLido)],
            ['Fonte', $this->fonte === InterpretadorDeLinha::FONTE_PRINCIPAL ? 'Aba Principal' : 'Abas de sala'],
            ['', ''],
            ['Linhas lidas', $r->linhasLidas],
            ['Bens importados', count($r->importadas)],
            ['Linhas rejeitadas', count($r->rejeitadas)],
            ['Bens importados com aviso', count($r->comAvisos())],
            ['Andares criados', implode(', ', $r->andaresCriados) ?: 'nenhum'],
            ['Salas criadas', count($r->salasCriadas)],
            ['Tipos criados', count($r->tiposCriados)],
            ['', ''],
            ['Rejeições por motivo', ''],
        ];
        foreach ($r->rejeicoesPorMotivo() as $motivo => $quantidade) {
            $linhas[] = [$motivo, $quantidade];
        }

        foreach ($linhas as $indice => [$rotulo, $valor]) {
            $estilo = $indice === 0 || $rotulo === 'Rejeições por motivo' ? $this->cabecalho : null;
            $writer->addRow(new Row([
                new StringCell($rotulo, $estilo),
                is_int($valor) ? new NumericCell($valor, $estilo) : new StringCell((string) $valor, $estilo),
            ]));
        }
    }

    /**
     * @param  array<int, string>  $titulos
     * @param  array<int, int>  $larguras
     * @param  array<int, array<int, mixed>>  $linhas
     */
    private function tabela(Writer $writer, Sheet $aba, string $nome, array $titulos, array $larguras, array $linhas): void
    {
        $aba->setName($nome);
        $aba->setSheetView(new SheetView(freezeRow: 2));
        foreach ($larguras as $indice => $largura) {
            $aba->setColumnWidth($largura, $indice + 1);
        }

        $writer->addRow(new Row(array_map(fn ($titulo) => new StringCell($titulo, $this->cabecalho), $titulos)));

        foreach ($linhas as $valores) {
            $writer->addRow(new Row(array_map(
                fn ($valor) => is_int($valor) ? new NumericCell($valor) : new StringCell((string) $valor),
                $valores,
            )));
        }

        $aba->setAutoFilter(new AutoFilter(0, 1, count($titulos) - 1, max(1, count($linhas) + 1)));
    }
}
