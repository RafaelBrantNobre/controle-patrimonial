<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Guarda no item a sala em que o bem estava registrado quando a conferência
| foi gerada. É a sala da folha impressa: a tela agrupa, filtra e compara
| por ela, mesmo depois de a conclusão mudar o bem de sala.
| Os itens que já existem recebem a sala atual do bem.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conferencia_item', function (Blueprint $table) {
            $table->foreignId('sala_registrada_id')->nullable()->after('bem_id')->constrained('sala')->restrictOnDelete();
        });

        DB::table('conferencia_item')->update([
            'sala_registrada_id' => DB::raw('(select bem.sala_id from bem where bem.id = conferencia_item.bem_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('conferencia_item', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sala_registrada_id');
        });
    }
};
