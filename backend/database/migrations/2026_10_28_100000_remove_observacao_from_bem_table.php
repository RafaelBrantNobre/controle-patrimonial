<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tira o campo observacao da tabela bem.
| Na prática ele só guardava o que a carga inicial copiava da planilha (aba,
| linha, textos originais e conferente), e tudo isso já está no próprio bem
| ou no relatório da carga. A carga é o marco zero: dali em diante o
| histórico vem das mudanças registradas (data_ultima_mudanca e movimentacao).
| A observação da baixa (baixa_observacao) continua.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bem', function (Blueprint $table) {
            $table->dropColumn('observacao');
        });
    }

    public function down(): void
    {
        // Volta a coluna, vazia: o texto apagado não tem como voltar.
        Schema::table('bem', function (Blueprint $table) {
            $table->text('observacao')->nullable()->after('estado_conservacao');
        });
    }
};
