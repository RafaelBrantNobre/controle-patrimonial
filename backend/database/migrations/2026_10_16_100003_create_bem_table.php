<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabela bem: o item patrimonial, entidade central do sistema (página 03, seção 10).
| Bem nunca é apagado: a saída do patrimônio ativo é a baixa (RN07).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bem', function (Blueprint $table) {
            $table->id();

            // Identificação
            $table->string('numero_patrimonio', 20)->nullable()->unique(); // plaqueta; nulo quando não tem (PD07)
            $table->string('codigo_interno', 20)->unique();                // gerado pelo sistema, todo bem tem

            // Classificação e local
            $table->foreignId('tipo_patrimonio_id')->constrained('tipo_patrimonio')->restrictOnDelete();
            $table->foreignId('sala_id')->constrained('sala')->restrictOnDelete(); // sala atual (RN02)

            // Características
            $table->string('descricao', 120)->nullable(); // "azul giratória", "L cinza", "2 portas"
            $table->string('marca', 80)->nullable();      // só quando há marca identificável
            $table->string('origem', 120)->nullable();    // "convênio MCT x SECTES"
            $table->unsignedSmallInteger('quantidade')->default(1); // PD08: itens agrupados sem plaqueta

            // Situação
            $table->string('status', 20)->default('em_uso'); // em_uso, sem_uso, avariado, baixado
            $table->text('observacao')->nullable();          // estado original da planilha e notas da conferência

            // Última mudança de sala ou de status (RF18, RN09)
            $table->date('data_ultima_mudanca')->nullable();
            $table->foreignId('usuario_ultima_mudanca_id')->nullable()
                ->constrained('usuario')->restrictOnDelete();

            // Baixa (RF20)
            $table->date('baixa_data')->nullable();
            $table->text('baixa_observacao')->nullable(); // processo, motivo, recolhimento

            $table->datetimes();

            // Filtros do RF13 e do relatório. sala_id e tipo_patrimonio_id já
            // ganham índice pela chave estrangeira.
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bem');
    }
};
