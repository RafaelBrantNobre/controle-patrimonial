<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Conferência dos bens.
|   - conferencia: cada conferência gerada, com os bens escolhidos na tela
|     (uma sala, um andar, o prédio inteiro). Aberta, concluída ou cancelada.
|   - conferencia_item: um bem dentro de uma conferência, com o resultado
|     (encontrado ou não localizado), a sala onde estava e o estado visto.
|     Quando o bem não é localizado, a pendência da gestora fica no próprio
|     item: como foi resolvida, o número do processo, quem e quando.
|   - bem ganha a data e o resultado da última conferência concluída, para
|     a lista mostrar e filtrar sem consultar o histórico (o mesmo padrão
|     de data_ultima_mudanca).
| A ligação com a carga do SIAD entra junto com a importação da carga.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conferencia', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120);                    // "2º semestre de 2026", "Sala 05 - Suporte TI"
            $table->string('situacao', 20)->default('aberta'); // aberta, concluida, cancelada
            $table->date('realizada_em')->nullable();       // dia da conferência física, informado ao concluir
            $table->foreignId('criada_por_id')->constrained('usuario')->restrictOnDelete();
            $table->dateTime('concluida_em')->nullable();
            $table->foreignId('concluida_por_id')->nullable()->constrained('usuario')->restrictOnDelete();
            $table->datetimes();

            $table->index('situacao');
        });

        Schema::create('conferencia_item', function (Blueprint $table) {
            $table->id();
            // Conferência e bem nunca são apagados (RN07), e o item é o registro do que foi visto.
            $table->foreignId('conferencia_id')->constrained('conferencia')->restrictOnDelete();
            $table->foreignId('bem_id')->constrained('bem')->restrictOnDelete();

            // Resultado lançado a partir da folha. Nulo enquanto pendente.
            $table->string('resultado', 20)->nullable();     // encontrado, nao_localizado
            $table->foreignId('sala_encontrada_id')->nullable()->constrained('sala')->restrictOnDelete();
            $table->string('estado_conservacao', 20)->nullable(); // estado visto na conferência
            $table->dateTime('conferido_em')->nullable();    // quando o resultado foi lançado
            $table->foreignId('conferido_por_id')->nullable()->constrained('usuario')->restrictOnDelete();

            // Pendência do bem não localizado, depois de concluída a conferência.
            $table->string('resolucao', 30)->nullable();     // localizado, processo_administrativo
            $table->string('processo_numero', 60)->nullable();
            $table->dateTime('resolvido_em')->nullable();
            $table->foreignId('resolvido_por_id')->nullable()->constrained('usuario')->restrictOnDelete();

            $table->datetimes();

            $table->unique(['conferencia_id', 'bem_id']);    // o bem entra uma vez em cada conferência
            $table->index(['bem_id', 'resultado']);           // conferência aberta e pendências do bem
            $table->index(['resultado', 'resolvido_em']);     // lista de pendências
        });

        Schema::table('bem', function (Blueprint $table) {
            $table->date('data_ultima_conferencia')->nullable()->after('usuario_ultima_mudanca_id');
            $table->string('resultado_ultima_conferencia', 20)->nullable()->after('data_ultima_conferencia');

            $table->index('resultado_ultima_conferencia'); // filtro da lista de bens
        });
    }

    public function down(): void
    {
        Schema::table('bem', function (Blueprint $table) {
            $table->dropIndex(['resultado_ultima_conferencia']);
            $table->dropColumn(['data_ultima_conferencia', 'resultado_ultima_conferencia']);
        });

        Schema::dropIfExists('conferencia_item');
        Schema::dropIfExists('conferencia');
    }
};
