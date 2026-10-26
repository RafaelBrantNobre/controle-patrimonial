<?php

namespace App\Carga;

use Illuminate\Support\Str;

/*
| Lê uma linha da planilha e decide o que ela vira no sistema (tarefa 2.12).
|
| Não grava nada e não consulta o banco: só interpreta, a partir do arquivo
| de mapeamento (database/carga/planilha.php). Assim dá para testar cada
| regra isoladamente e rodar a carga em modo de simulação.
|
| A linha é rejeitada quando não dá para saber o que ela é sem inventar
| informação: local, tipo ou estado não reconhecidos, ou uma linha que
| representa vários itens. O relatório da carga mostra o motivo.
*/
class InterpretadorDeLinha
{
    public const FONTE_PRINCIPAL = 'principal';

    public const FONTE_SALAS = 'salas';

    /** @var array<string, array<int, string>> palavra-chave normalizada => [tipo, palavras da chave] */
    private array $chavesDeTipo = [];

    /**
     * @param  array<string, mixed>  $mapeamento  conteúdo de database/carga/planilha.php
     * @param  string  $situacaoDasAbas  situação de uso dada aos itens das abas de sala
     */
    public function __construct(
        private readonly array $mapeamento,
        private readonly string $fonte,
        private readonly string $situacaoDasAbas = 'em_uso',
    ) {
        foreach ($mapeamento['tipos'] as $tipo => $chaves) {
            foreach ($chaves as $chave) {
                $this->chavesDeTipo[self::normalizar($chave)] = $tipo;
            }
        }

        // A chave mais longa primeiro: "caixa de som" ganha de "caixa".
        uksort($this->chavesDeTipo, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
    }

    /** Minúsculas, sem acento e sem espaços repetidos. */
    public static function normalizar(string $texto): string
    {
        return Str::of($texto)->ascii()->lower()->squish()->toString();
    }

    public function interpretar(LinhaDaPlanilha $linha): LinhaInterpretada
    {
        $resultado = new LinhaInterpretada($linha);

        if ($motivo = $this->variosItens($linha)) {
            return $resultado->rejeitar($motivo);
        }

        $resultado->sala = $this->sala($linha);
        if ($resultado->sala === null) {
            return $resultado->rejeitar("Local não reconhecido: \"{$linha->local}\". Inclua em locais_principal no mapeamento.");
        }

        if (! $this->tipoEDescricao($linha, $resultado)) {
            return $resultado;
        }

        if (! $this->estado($linha, $resultado)) {
            return $resultado;
        }

        $this->numero($linha, $resultado);
        $resultado->observacao = $this->observacao($linha, $resultado);

        return $resultado;
    }

    /**
     * Linhas como "Monitor | Em uso 20 Unidades" ou "3 servidores" contam
     * vários itens de uma vez. A página 03 manda separá-las à mão (PD08).
     */
    private function variosItens(LinhaDaPlanilha $linha): ?string
    {
        $descricao = self::normalizar($linha->descricao);
        $estado = self::normalizar($linha->estado);

        $ehQuantidade = preg_match('/^\d+\s+\D/', $descricao)
            || preg_match('/\d+\s*unidades?\b/', $descricao.' '.$estado)
            || preg_match('/^\d+(\.0+)?$/', $estado)
            || preg_match('/^\d+\s+(novos?|sem\b)|^usado\s+\d+$/', $estado);

        return $ehQuantidade
            ? "A linha conta vários itens (\"{$linha->descricao}\", estado \"{$linha->estado}\"). Separe um bem por linha antes da carga (PD08)."
            : null;
    }

    private function sala(LinhaDaPlanilha $linha): ?string
    {
        return $this->fonte === self::FONTE_PRINCIPAL
            ? ($this->mapeamento['locais_principal'][self::normalizar($linha->local)] ?? null)
            : ($this->mapeamento['abas_de_sala'][self::normalizar($linha->aba)] ?? null);
    }

    /**
     * O tipo é o começo da descrição; o resto, sem marca e sem origem, vira
     * a descrição do bem. "Cadeira vermelha, convênio" vira tipo Cadeira,
     * descrição "vermelha" e origem "convênio".
     */
    private function tipoEDescricao(LinhaDaPlanilha $linha, LinhaInterpretada $resultado): bool
    {
        $descricao = trim($linha->descricao);
        if ($descricao === '') {
            $resultado->rejeitar('Descrição em branco: não dá para saber o tipo do bem.');

            return false;
        }

        $normalizada = self::normalizar($descricao);
        foreach ($this->chavesDeTipo as $chave => $tipo) {
            if ($normalizada === $chave || str_starts_with($normalizada, $chave.' ') || str_starts_with($normalizada, $chave.',')) {
                $resultado->tipo = $tipo;
                $palavrasDaChave = count(explode(' ', $chave));
                $resto = implode(' ', array_slice(preg_split('/\s+/', $descricao), $palavrasDaChave));
                $this->separarOrigemEMarca($resto, $resultado);

                return true;
            }
        }

        $resultado->rejeitar("Tipo não reconhecido na descrição \"{$descricao}\". Inclua a palavra em tipos no mapeamento.");

        return false;
    }

    private function separarOrigemEMarca(string $resto, LinhaInterpretada $resultado): void
    {
        // Origem: "convênio", "Convênio E-TEC", "convênio MCT x SECTES".
        if (preg_match('/\bconv[eê]n?io\b[^,;()]*/iu', $resto, $achado)) {
            $resultado->origem = $this->origemPadronizada($achado[0]);
            $resto = str_replace($achado[0], ' ', $resto);
        }

        // Marca: a variação mais longa primeiro, como "ambient homeco" antes de "hp".
        $marcas = $this->mapeamento['marcas'];
        uksort($marcas, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        foreach ($marcas as $variacao => $marca) {
            $padrao = '/\b'.preg_quote($variacao, '/').'\b/iu';
            if (preg_match($padrao, $resto)) {
                $resultado->marca = $marca;
                $resto = preg_replace($padrao, ' ', $resto);
                break;
            }
        }

        $resto = trim(preg_replace('/\s+/', ' ', $resto), ' ,;-()');
        if ($resto === '') {
            return;
        }

        if (mb_strlen($resto) > 120) {
            $resultado->avisos[] = 'Descrição cortada em 120 caracteres; o texto completo está na observação.';
            $resto = rtrim(mb_substr($resto, 0, 120));
        }

        $resultado->descricao = $resto;
    }

    /** Junta as grafias do mesmo convênio, conforme 'origens' no mapeamento. */
    private function origemPadronizada(string $texto): string
    {
        $normalizado = self::normalizar($texto);
        foreach ($this->mapeamento['origens'] as $trecho => $origem) {
            if (str_contains($normalizado, $trecho)) {
                return $origem;
            }
        }

        return 'Convênio';
    }

    private function estado(LinhaDaPlanilha $linha, LinhaInterpretada $resultado): bool
    {
        $estado = self::normalizar($linha->estado);

        if ($this->fonte === self::FONTE_PRINCIPAL) {
            if ($estado === '') {
                $resultado->rejeitar('Estado em branco: não dá para saber a situação do bem.');

                return false;
            }

            $convertido = $this->mapeamento['estados_principal'][$estado] ?? null;
            if ($convertido === null) {
                $resultado->rejeitar("Estado não reconhecido: \"{$linha->estado}\". Inclua em estados_principal no mapeamento.");

                return false;
            }

            [$resultado->status, $resultado->estadoConservacao] = $convertido;

            return true;
        }

        $conservacao = $this->mapeamento['estados_abas'][$estado] ?? null;
        if ($conservacao === null) {
            $resultado->rejeitar("Estado de conservação não reconhecido: \"{$linha->estado}\". Inclua em estados_abas no mapeamento.");

            return false;
        }

        $resultado->estadoConservacao = $conservacao;
        $resultado->status = $conservacao === 'sucata' ? 'sem_uso' : $this->situacaoDasAbas;

        return true;
    }

    /**
     * Número da plaqueta. Aceita os formatos da planilha: 1634456-1, 85015792
     * e 900345721. Quando o número vem no meio de outro texto, como
     * "Série C 85018848" ou "900284315 (Convênio)", ele é separado e a linha
     * ganha um aviso. Texto que não é plaqueta, como "Ilegível" ou um ramal,
     * deixa o bem sem número, e o texto vai para a observação.
     */
    private function numero(LinhaDaPlanilha $linha, LinhaInterpretada $resultado): void
    {
        $texto = trim($linha->patrimonio);

        if (in_array(self::normalizar($texto), $this->mapeamento['sem_plaqueta'], true)) {
            return;
        }

        if (preg_match('/^(\d{7}-\d|\d{6,10})$/', $texto)) {
            $resultado->numeroPatrimonio = $texto;

            return;
        }

        if (preg_match('/(?<![\d-])(\d{7}-\d|\d{8,9})(?![\d-])/', $texto, $achado)) {
            $resultado->numeroPatrimonio = $achado[1];
            $resultado->avisos[] = "Número de patrimônio separado do texto \"{$texto}\".";

            if ($resultado->origem === null && preg_match('/conv[eê]n?io.*/iu', $texto, $origem)) {
                $resultado->origem = $this->origemPadronizada($origem[0]);
            }

            return;
        }

        $resultado->avisos[] = "A coluna Patrimônio tem \"{$texto}\", que não é um número de plaqueta. O bem entrou sem número.";
    }

    private function observacao(LinhaDaPlanilha $linha, LinhaInterpretada $resultado): string
    {
        $partes = ["Carga inicial: planilha de novembro de 2024, aba \"{$linha->aba}\", linha {$linha->linha}."];
        $partes[] = "Patrimônio na planilha: \"{$linha->patrimonio}\".";
        $partes[] = "Descrição na planilha: \"{$linha->descricao}\".";

        if ($this->fonte === self::FONTE_PRINCIPAL || trim($linha->local) !== '') {
            $partes[] = "Local na planilha: \"{$linha->local}\".";
        }

        $partes[] = "Estado na planilha: \"{$linha->estado}\".";

        if (trim($linha->conferente) !== '') {
            $partes[] = "Conferido por: {$linha->conferente}.";
        }

        foreach ($resultado->avisos as $aviso) {
            $partes[] = "Aviso: {$aviso}";
        }

        return implode(' ', $partes);
    }
}
