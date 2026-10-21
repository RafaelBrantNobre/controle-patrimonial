<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabela sala: ambiente dentro de um andar, onde o bem fica (página 03, seção 8).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sala', function (Blueprint $table) {
            $table->id();
            // Sala sempre pertence a um andar. restrictOnDelete impede apagar
            // um andar que ainda tem salas (RN08: inativa, não apaga).
            $table->foreignId('andar_id')->constrained('andar')->restrictOnDelete();
            $table->string('nome', 120);                  // "Sala 05 - Suporte TI"
            $table->string('descricao', 255)->nullable(); // complemento, quando o nome não basta
            $table->boolean('ativo')->default(true);      // sala desativada não recebe bens novos
            $table->datetimes();

            // Nome único dentro do andar: duas "Sala 05" em andares diferentes são permitidas.
            $table->unique(['andar_id', 'nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sala');
    }
};
