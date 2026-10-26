<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabela movimentacao: cada mudança de sala de um bem (página 03, seção 11).
| Ganha uma linha só quando um bem muda de sala. Começa a ser gravada na
| tarefa 2.8, para o histórico não se perder até a 3.1, que mostra o
| histórico na ficha e no relatório.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimentacao', function (Blueprint $table) {
            $table->id();
            // Bem nunca é apagado (RN07), e sala com histórico também não.
            $table->foreignId('bem_id')->constrained('bem')->restrictOnDelete();
            $table->foreignId('sala_origem_id')->nullable()   // nulo num registro sem origem, como o de uma carga inicial
                ->constrained('sala')->restrictOnDelete();
            $table->foreignId('sala_destino_id')->constrained('sala')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('usuario')->restrictOnDelete(); // quem registrou (RN09)
            $table->dateTime('data')->useCurrent();          // momento da mudança
            $table->datetimes();

            // Histórico de um bem e mudanças de um período (relatório).
            $table->index(['bem_id', 'data']);
            $table->index('data');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentacao');
    }
};
