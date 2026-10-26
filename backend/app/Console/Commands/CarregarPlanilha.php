<?php

namespace App\Console\Commands;

use App\Carga\CargaInicial;
use App\Carga\InterpretadorDeLinha;
use App\Carga\LeitorDaPlanilha;
use App\Carga\RelatorioDaCarga;
use App\Models\Bem;
use Illuminate\Console\Command;

/*
| Carga inicial dos bens a partir da planilha (tarefa 2.12, RF28 e RF29).
|
| Uso:
|   php artisan patrimonio:carregar-planilha caminho.xlsx --fonte=principal --simular
|   php artisan patrimonio:carregar-planilha caminho.xlsx --fonte=salas --do-zero
|
| --simular   faz tudo e gera o relatório, mas não grava nada no banco.
| --do-zero   apaga bens, histórico, salas, andares e tipos antes de carregar.
|             Só no período de testes: em produção, o comando recusa.
| --fonte     principal (aba Principal) ou salas (uma aba por sala). Fica
|             obrigatório até a Christine dizer qual é a fonte certa.
| --situacao-abas  situação de uso dos itens das abas de sala, que a
|             planilha não informa. Padrão em_uso. Sucata entra sem uso.
|
| As regras de interpretação ficam em database/carga/planilha.php.
| O relatório sai em storage/app/carga/.
*/
class CarregarPlanilha extends Command
{
    protected $signature = 'patrimonio:carregar-planilha
                            {arquivo : Caminho da planilha .xlsx}
                            {--fonte= : principal ou salas}
                            {--situacao-abas=em_uso : em_uso ou sem_uso, para as abas de sala}
                            {--simular : Gera o relatório sem gravar nada}
                            {--do-zero : Apaga os dados do patrimônio antes de carregar (não roda em produção)}';

    protected $description = 'Carrega os bens da planilha de patrimônio e gera o relatório da carga';

    public function handle(): int
    {
        $arquivo = (string) $this->argument('arquivo');
        $fonte = (string) $this->option('fonte');
        $situacaoDasAbas = (string) $this->option('situacao-abas');
        $simular = (bool) $this->option('simular');
        $mapeamento = require database_path('carga/planilha.php');

        if (! in_array($fonte, [InterpretadorDeLinha::FONTE_PRINCIPAL, InterpretadorDeLinha::FONTE_SALAS], true)) {
            $this->error('Informe a fonte: --fonte=principal (aba Principal) ou --fonte=salas (uma aba por sala).');

            return self::FAILURE;
        }

        if (! in_array($situacaoDasAbas, ['em_uso', 'sem_uso'], true)) {
            $this->error('A opção --situacao-abas aceita em_uso ou sem_uso.');

            return self::FAILURE;
        }

        if (! is_file($arquivo)) {
            $this->error("Arquivo não encontrado: {$arquivo}");

            return self::FAILURE;
        }

        $leitor = new LeitorDaPlanilha($mapeamento);

        if ($fonte === InterpretadorDeLinha::FONTE_SALAS && ($faltando = $leitor->abasSemMapeamento($arquivo)) !== []) {
            $this->error('Estas abas não estão em abas_de_sala no mapeamento: '.implode(', ', $faltando).'. Inclua cada uma, ou ponha em abas_ignoradas, e rode de novo.');

            return self::FAILURE;
        }

        // A carga é para um sistema vazio. Rodar de novo por cima duplicaria os
        // bens sem plaqueta, que não têm número para a repetição ser notada.
        if (! $simular && ! $this->option('do-zero') && ($existentes = Bem::count()) > 0) {
            $this->error("O sistema já tem {$existentes} bens. A carga inicial é para um sistema vazio: rode com --simular para só ver o relatório, ou com --do-zero para apagar e carregar de novo (não roda em produção).");

            return self::FAILURE;
        }

        if ($this->option('do-zero')) {
            if (app()->isProduction()) {
                $this->error('A opção --do-zero apaga o patrimônio inteiro e não roda em produção.');

                return self::FAILURE;
            }

            if ($simular) {
                $this->warn('Com --simular, a opção --do-zero é ignorada: nada é apagado.');
            } else {
                $this->warn('Apagando bens, histórico, salas, andares e tipos ('.Bem::count().' bens)...');
                CargaInicial::apagarDadosDoPatrimonio();
            }
        }

        $carga = new CargaInicial($mapeamento, new InterpretadorDeLinha($mapeamento, $fonte, $situacaoDasAbas));
        $resultado = $carga->executar($leitor->linhas($arquivo, $fonte), $simular);

        $pasta = storage_path('app/carga');
        if (! is_dir($pasta)) {
            mkdir($pasta, 0775, true);
        }
        $relatorio = $pasta.'/relatorio_carga_'.$fonte.'_'.now()->format('Y-m-d_His').($simular ? '_simulacao' : '').'.xlsx';
        (new RelatorioDaCarga($resultado, $arquivo, $fonte))->salvarEm($relatorio);

        $this->info($simular ? 'SIMULAÇÃO: nada foi gravado no banco.' : 'Carga gravada no banco.');
        $this->table(['', 'Quantidade'], [
            ['Linhas lidas', $resultado->linhasLidas],
            ['Bens importados', count($resultado->importadas)],
            ['Linhas rejeitadas', count($resultado->rejeitadas)],
            ['Importados com aviso', count($resultado->comAvisos())],
            ['Salas criadas', count($resultado->salasCriadas)],
            ['Tipos criados', count($resultado->tiposCriados)],
        ]);

        if ($resultado->rejeitadas !== []) {
            $this->line('Rejeições por motivo:');
            $this->table(['Motivo', 'Linhas'], collect($resultado->rejeicoesPorMotivo())->map(fn ($n, $motivo) => [$motivo, $n])->values()->all());
        }

        $this->line("Relatório completo: {$relatorio}");

        return self::SUCCESS;
    }
}
