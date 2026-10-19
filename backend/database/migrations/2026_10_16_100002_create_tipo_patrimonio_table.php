<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabela tipo_patrimonio: categoria do bem (página 03, seção 9).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_patrimonio', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 80)->unique();     // computador, cadeira, mesa, ar condicionado...
            $table->boolean('ativo')->default(true);  // tipo sem uso fica inativo (RN08)
            $table->datetimes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_patrimonio');
    }
};
