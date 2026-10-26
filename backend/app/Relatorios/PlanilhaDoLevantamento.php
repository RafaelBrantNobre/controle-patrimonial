<?php

namespace App\Relatorios;

use App\Models\Bem;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\DateTimeCell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\Common\Entity\Sheet;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/*
| Planilha Excel do relatório do levantamento (tarefa 2.10, RF26).
|
| Segue a planilha que o setor já usa ("Patrimônios CEAD", novembro de 2024):
|   - aba "Principal" com todos os bens e filtro nas colunas;
|   - uma aba por sala, para a conferência sala a sala;
|   - cabeçalho congelado em todas as abas;
|   - coluna "Conferido por" em branco, para a equipe preencher, como a
|     coluna "Nome" da planilha atual.
| Bens que mudaram no período ficam com o fundo amarelo (RF25).
|
| Todo texto é gravado como célula de texto. Assim o número de patrimônio
| não vira 8.5015792E7, e um texto que começa com "=" não roda como fórmula.
*/
class PlanilhaDoLevantamento
{
    /** Título e largura de cada coluna, na ordem em que aparecem. */
    private const COLUNAS = [
        'Patrimônio' => 16,
        'Código interno' => 13,
        'Tipo' => 22,
        'Descrição' => 28,
        'Marca' => 16,
        'Local do patrimônio' => 28,
        'Andar' => 14,
        'Situação de uso' => 16,
        'Estado de conservação' => 16,
        'Quantidade' => 11,
        'Última mudança' => 15,
        'Mudou no período' => 16,
        'Veio da sala' => 28,
        'Data da baixa' => 14,
        'Observação' => 40,
        'Conferido por' => 20,
    ];

    private const NOME_MAXIMO_DA_ABA = 31;

    private Style $cabecalho;

    private Style $normal;

    private Style $destaque;

    private Style $data;

    private Style $dataDestaque;

    public function __construct(private readonly RelatorioDoLevantamento $relatorio)
    {
        $this->cabecalho = new Style(fontBold: true, backgroundColor: 'D9E2F3', shouldWrapText: true);
        $this->normal = new Style;
        $this->destaque = new Style(backgroundColor: 'FFF2CC');
        $this->data = new Style(format: 'dd/mm/yyyy');
        $this->dataDestaque = new Style(backgroundColor: 'FFF2CC', format: 'dd/mm/yyyy');
    }

    /** Grava a planilha no caminho informado. */
    public function salvarEm(string $caminho): void
    {
        $writer = new Writer;
        $writer->openToFile($caminho);

        $bens = $this->relatorio->bens();

        $this->preencherAba($writer, $writer->getCurrentSheet(), 'Principal', $bens);

        $nomesUsados = ['principal'];
        foreach ($bens->groupBy('sala_id') as $bensDaSala) {
            $sala = $bensDaSala->first()->sala;
            $nome = $this->nomeDeAba($sala->nome, $sala->andar->nome, $nomesUsados);
            $this->preencherAba($writer, $writer->addNewSheetAndMakeItCurrent(), $nome, $bensDaSala);
        }

        $writer->close();
    }

    /**
     * @param  Collection<int, Bem>  $bens
     */
    private function preencherAba(Writer $writer, Sheet $aba, string $nome, Collection $bens): void
    {
        $aba->setName($nome);
        $aba->setSheetView(new SheetView(freezeRow: 2));

        $coluna = 1;
        foreach (self::COLUNAS as $largura) {
            $aba->setColumnWidth($largura, $coluna++);
        }

        $writer->addRow(new Row(array_map(
            fn (string $titulo) => new StringCell($titulo, $this->cabecalho),
            array_keys(self::COLUNAS),
        )));

        foreach ($bens as $bem) {
            $writer->addRow($this->linha($bem));
        }

        $aba->setAutoFilter(new AutoFilter(0, 1, count(self::COLUNAS) - 1, max(1, $bens->count() + 1)));
    }

    private function linha(Bem $bem): Row
    {
        $mudou = (bool) $bem->mudou_no_periodo;
        $estilo = $mudou ? $this->destaque : $this->normal;
        $estiloData = $mudou ? $this->dataDestaque : $this->data;

        $texto = fn (?string $valor) => filled($valor) ? new StringCell($valor, $estilo) : new EmptyCell(null, $estilo);
        $data = fn (?DateTimeInterface $valor) => $valor ? new DateTimeCell($valor, $estiloData) : new EmptyCell(null, $estilo);

        /** @var array<int, Cell> $celulas */
        $celulas = [
            $texto($bem->numero_patrimonio ?? 'sem patrimônio'),
            $texto($bem->codigo_interno),
            $texto($bem->tipoPatrimonio->nome),
            $texto($bem->descricao),
            $texto($bem->marca),
            $texto($bem->sala->nome),
            $texto($bem->sala->andar->nome),
            $texto($bem->status->rotulo()),
            $texto($bem->estado_conservacao->rotulo()),
            new NumericCell($bem->quantidade, $estilo),
            $data($bem->data_ultima_mudanca),
            $texto($mudou ? 'Sim' : 'Não'),
            $texto($bem->veioDaSala ? "{$bem->veioDaSala->nome} ({$bem->veioDaSala->andar->nome})" : null),
            $data($bem->baixa_data),
            $texto($bem->observacao),
            $texto(null),
        ];

        return new Row($celulas);
    }

    /**
     * Nome de aba válido no Excel: até 31 caracteres, sem \ / ? * : [ ]
     * e sem repetir. Se duas salas tiverem o mesmo nome, como "Sala 05" em
     * andares diferentes, a segunda leva o andar no nome.
     *
     * @param  array<int, string>  $usados  nomes já usados, em minúsculas
     */
    private function nomeDeAba(string $sala, string $andar, array &$usados): string
    {
        $limpar = fn (string $texto) => trim(str_replace(['\\', '/', '?', '*', ':', '[', ']'], '-', $texto), " '");

        $candidatos = [
            $limpar($sala),
            $limpar("{$sala} - {$andar}"),
        ];

        foreach ($candidatos as $candidato) {
            $nome = Str::limit($candidato, self::NOME_MAXIMO_DA_ABA, '');
            if ($nome !== '' && ! in_array(mb_strtolower($nome), $usados, true)) {
                $usados[] = mb_strtolower($nome);

                return $nome;
            }
        }

        // Último recurso: corta e numera até não repetir.
        for ($numero = 2; ; $numero++) {
            $sufixo = " ({$numero})";
            $nome = Str::limit($limpar($sala), self::NOME_MAXIMO_DA_ABA - mb_strlen($sufixo), '').$sufixo;
            if (! in_array(mb_strtolower($nome), $usados, true)) {
                $usados[] = mb_strtolower($nome);

                return $nome;
            }
        }
    }
}
