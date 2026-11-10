<?php

namespace App\Carga;

use App\Models\Bem;
use App\Models\Movimentacao;
use App\Models\Sala;
use App\Models\TipoPatrimonio;
use Illuminate\Support\Facades\DB;
use Throwable;

/*
| Carga inicial dos bens a partir da planilha (tarefa 2.12, RF28, RN11).
|
|   1. Interpreta todas as linhas (InterpretadorDeLinha).
|   2. Recusa número de patrimônio repetido: a primeira linha com o número
|      entra e as seguintes são rejeitadas, apontando para a primeira (RN11).
|      Número que já existe no sistema também é recusado (RN01).
|   3. Cria as salas e os tipos que as linhas aceitas usam (RF28).
|   4. Grava os bens.
|
| Tudo acontece numa transação só: ou a carga inteira entra, ou nada entra.
| Na simulação, a transação é desfeita no fim, mas o relatório sai igual
| ao de uma carga de verdade.
*/
class CargaInicial
{
    /**
     * @param  array<string, mixed>  $mapeamento  conteúdo de database/carga/planilha.php
     */
    public function __construct(
        private readonly array $mapeamento,
        private readonly InterpretadorDeLinha $interpretador,
    ) {}

    /**
     * @param  iterable<LinhaDaPlanilha>  $linhas
     */
    public function executar(iterable $linhas, bool $simular): ResultadoDaCarga
    {
        $resultado = new ResultadoDaCarga($simular);

        DB::beginTransaction();

        try {
            $aceitas = $this->interpretarEFiltrar($linhas, $resultado);
            $this->gravar($aceitas, $resultado);

            $simular ? DB::rollBack() : DB::commit();
        } catch (Throwable $erro) {
            DB::rollBack();

            throw $erro;
        }

        return $resultado;
    }

    /**
     * Apaga bens, histórico, salas e tipos, para a carga começar do
     * zero. Só para o período de testes: quem chama confere o ambiente.
     */
    public static function apagarDadosDoPatrimonio(): void
    {
        DB::transaction(function () {
            Movimentacao::query()->delete();
            Bem::query()->delete();
            Sala::query()->delete();
            TipoPatrimonio::query()->delete();
        });
    }

    /**
     * @param  iterable<LinhaDaPlanilha>  $linhas
     * @return array<int, LinhaInterpretada>
     */
    private function interpretarEFiltrar(iterable $linhas, ResultadoDaCarga $resultado): array
    {
        $aceitas = [];
        $primeiraComNumero = [];

        foreach ($linhas as $linha) {
            $resultado->linhasLidas++;
            $interpretada = $this->interpretador->interpretar($linha);

            if (! $interpretada->foiRejeitada() && $interpretada->numeroPatrimonio !== null) {
                $numero = $interpretada->numeroPatrimonio;

                if (isset($primeiraComNumero[$numero])) {
                    $interpretada->rejeitar("Número de patrimônio {$numero} repetido: já aparece em {$primeiraComNumero[$numero]} (RN11).");
                } elseif (Bem::where('numero_patrimonio', $numero)->exists()) {
                    $interpretada->rejeitar("Número de patrimônio {$numero} já está cadastrado no sistema (RN01).");
                } else {
                    $primeiraComNumero[$numero] = $linha->origem();
                }
            }

            if ($interpretada->foiRejeitada()) {
                $resultado->rejeitadas[] = $interpretada;
            } else {
                $aceitas[] = $interpretada;
            }
        }

        return $aceitas;
    }

    /**
     * @param  array<int, LinhaInterpretada>  $aceitas
     */
    private function gravar(array $aceitas, ResultadoDaCarga $resultado): void
    {
        $salas = [];
        $tipos = [];

        foreach ($aceitas as $linha) {
            if (! in_array($linha->sala, $this->mapeamento['salas'], true)) {
                throw new \RuntimeException("A sala \"{$linha->sala}\" não está em 'salas' no mapeamento.");
            }

            $salas[$linha->sala] ??= $this->sala($linha->sala, $resultado);
            $tipos[$linha->tipo] ??= $this->tipo($linha->tipo, $resultado);

            $bem = Bem::create([
                'numero_patrimonio' => $linha->numeroPatrimonio,
                'tipo_patrimonio_id' => $tipos[$linha->tipo]->id,
                'sala_id' => $salas[$linha->sala]->id,
                'descricao' => $linha->descricao,
                'marca' => $linha->marca,
                'origem' => $linha->origem,
                'status' => $linha->status,
                'estado_conservacao' => $linha->estadoConservacao,
            ]);

            $resultado->importadas[] = [$linha, $bem->codigo_interno];
        }
    }

    private function sala(string $nome, ResultadoDaCarga $resultado): Sala
    {
        $sala = Sala::firstOrCreate(['nome' => $nome]);
        if ($sala->wasRecentlyCreated) {
            $resultado->salasCriadas[] = $nome;
        }

        return $sala;
    }

    private function tipo(string $nome, ResultadoDaCarga $resultado): TipoPatrimonio
    {
        $tipo = TipoPatrimonio::firstOrCreate(['nome' => $nome]);
        if ($tipo->wasRecentlyCreated) {
            $resultado->tiposCriados[] = $nome;
        }

        return $tipo;
    }
}
