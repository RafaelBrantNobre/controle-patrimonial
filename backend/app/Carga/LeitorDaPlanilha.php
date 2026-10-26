<?php

namespace App\Carga;

use Generator;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;

/*
| Lê as linhas de bens da planilha (tarefa 2.12), sem interpretar nada.
|
|   - fonte "principal": só a aba Principal.
|   - fonte "salas": todas as abas, menos as ignoradas no mapeamento.
|
| Pula o cabeçalho (linha 1) e as linhas sem patrimônio, descrição e estado.
| O número da linha é o mesmo que aparece no Excel.
*/
class LeitorDaPlanilha
{
    /**
     * @param  array<string, mixed>  $mapeamento  conteúdo de database/carga/planilha.php
     */
    public function __construct(private readonly array $mapeamento) {}

    /**
     * @return Generator<int, LinhaDaPlanilha>
     */
    public function linhas(string $caminho, string $fonte): Generator
    {
        if (! is_file($caminho)) {
            throw new RuntimeException("Arquivo não encontrado: {$caminho}");
        }

        $leitor = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));
        $leitor->open($caminho);

        try {
            foreach ($leitor->getSheetIterator() as $aba) {
                $nome = trim($aba->getName());
                if (! $this->deveLer($nome, $fonte)) {
                    continue;
                }

                foreach ($aba->getRowIterator() as $numero => $linha) {
                    if ($numero === 1) {
                        continue;
                    }

                    $valores = $linha->toArray();
                    [$patrimonio, $descricao, $local, $estado, $conferente] = array_map(
                        fn (int $coluna) => $this->texto($valores[$coluna] ?? null),
                        [0, 1, 2, 3, 4],
                    );

                    if ($patrimonio === '' && $descricao === '' && $estado === '') {
                        continue;
                    }

                    yield new LinhaDaPlanilha($nome, $numero, $patrimonio, $descricao, $local, $estado, $conferente);
                }
            }
        } finally {
            $leitor->close();
        }
    }

    /**
     * Abas de sala que a planilha tem e o mapeamento não conhece. A carga
     * para antes de começar se houver alguma, para nenhuma sala sumir calada.
     *
     * @return array<int, string>
     */
    public function abasSemMapeamento(string $caminho): array
    {
        $leitor = new Reader;
        $leitor->open($caminho);

        $faltando = [];
        foreach ($leitor->getSheetIterator() as $aba) {
            $nome = trim($aba->getName());
            $chave = InterpretadorDeLinha::normalizar($nome);
            if (! in_array($chave, $this->mapeamento['abas_ignoradas'], true) && ! isset($this->mapeamento['abas_de_sala'][$chave])) {
                $faltando[] = $nome;
            }
        }

        $leitor->close();

        return $faltando;
    }

    private function deveLer(string $aba, string $fonte): bool
    {
        $chave = InterpretadorDeLinha::normalizar($aba);

        return $fonte === InterpretadorDeLinha::FONTE_PRINCIPAL
            ? $chave === 'principal'
            : ! in_array($chave, $this->mapeamento['abas_ignoradas'], true);
    }

    /**
     * O Excel guarda o número da plaqueta como número (85015792 aparece como
     * 8.5015792E7). Aqui ele volta a ser o texto que a pessoa digitou.
     */
    private function texto(mixed $valor): string
    {
        if (is_float($valor) && floor($valor) === $valor) {
            return sprintf('%.0f', $valor);
        }

        if (is_int($valor) || is_float($valor)) {
            return (string) $valor;
        }

        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('d/m/Y');
        }

        return trim((string) $valor);
    }
}
