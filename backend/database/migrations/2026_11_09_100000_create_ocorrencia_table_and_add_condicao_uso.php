<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Conferência sala a sala e ocorrências dos bens.
|   - conferencia_item ganha a condição de uso verificada (em uso ou sem
|     uso), ao lado da sala e do estado de conservação.
|   - ocorrencia: o histórico do que aconteceu com cada bem (mudou de sala,
|     mudou a conservação, mudou o uso, baixa), com o valor de antes e o de
|     depois, a data, quem registrou e, quando veio de uma conferência, qual.
|     Os valores ficam em texto, como eram no dia, para o histórico não
|     mudar se uma sala for renomeada depois.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conferencia_item', function (Blueprint $table) {
            $table->string('condicao_uso', 20)->nullable()->after('estado_conservacao'); // em_uso, sem_uso
        });

        Schema::create('ocorrencia', function (Blueprint $table) {
            $table->id();
            // Bem nunca é apagado (RN07), e a ocorrência é o histórico dele.
            $table->foreignId('bem_id')->constrained('bem')->restrictOnDelete();
            $table->string('tipo', 20);                      // sala, conservacao, uso, baixa
            $table->string('valor_anterior', 150)->nullable();
            $table->string('valor_novo', 150)->nullable();
            $table->text('observacao')->nullable();
            $table->date('data');                            // dia em que aconteceu
            $table->foreignId('usuario_id')->constrained('usuario')->restrictOnDelete(); // quem registrou (RN09)
            $table->foreignId('conferencia_id')->nullable()->constrained('conferencia')->restrictOnDelete();
            $table->datetimes();

            $table->index(['bem_id', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocorrencia');

        Schema::table('conferencia_item', function (Blueprint $table) {
            $table->dropColumn('condicao_uso');
        });
    }
};
