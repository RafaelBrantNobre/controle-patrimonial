<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabela andar: pavimento do prédio do CEAD (página 03, seção 7).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('andar', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 60)->unique();         // Térreo, 1º andar...
            $table->smallInteger('ordem')->default(0);    // posição do andar no relatório; aceita negativo (subsolo)
            $table->boolean('ativo')->default(true);      // andar fora de uso fica inativo (RN08)
            $table->datetimes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('andar');
    }
};
