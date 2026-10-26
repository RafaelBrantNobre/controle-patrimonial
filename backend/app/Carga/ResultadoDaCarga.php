<?php

namespace App\Carga;

/*
| O que aconteceu numa carga (tarefa 2.12, RF29): quantas linhas foram
| lidas, quais entraram e com qual código interno, quais foram rejeitadas e
| por quê, e o que foi criado de andar, sala e tipo.
*/
final class ResultadoDaCarga
{
    public int $linhasLidas = 0;

    /** @var array<int, array{0: LinhaInterpretada, 1: string}> linha e código interno do bem criado */
    public array $importadas = [];

    /** @var array<int, LinhaInterpretada> */
    public array $rejeitadas = [];

    /** @var array<int, string> */
    public array $andaresCriados = [];

    /** @var array<int, string> */
    public array $salasCriadas = [];

    /** @var array<int, string> */
    public array $tiposCriados = [];

    public function __construct(public readonly bool $simulacao) {}

    /** @return array<int, LinhaInterpretada> linhas que entraram com algum aviso */
    public function comAvisos(): array
    {
        return array_values(array_filter(
            array_column($this->importadas, 0),
            fn (LinhaInterpretada $linha) => $linha->avisos !== [],
        ));
    }

    /**
     * Rejeições agrupadas pelo começo do motivo, para o resumo no terminal.
     *
     * @return array<string, int>
     */
    public function rejeicoesPorMotivo(): array
    {
        $grupos = [];
        foreach ($this->rejeitadas as $linha) {
            // "Número de patrimônio 123 repetido: ..." e "... 456 repetido: ..." caem no mesmo grupo.
            $semDetalhes = preg_replace('/"[^"]*"|\d[\d-]*/u', '', $linha->motivoDaRejeicao);
            $grupo = trim(preg_replace('/\s+/', ' ', strtok($semDetalhes, ':.(')));
            $grupos[$grupo] = ($grupos[$grupo] ?? 0) + 1;
        }
        arsort($grupos);

        return $grupos;
    }
}
