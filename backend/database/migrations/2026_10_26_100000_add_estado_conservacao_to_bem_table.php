<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Separa a condição física do bem da situação de uso.
|   - Campo novo estado_conservacao: novo, bom, regular, pessimo, sucata ou
|     indeterminado. Padrão indeterminado, até a conferência.
|   - O status deixa de ter "avariado": quebrado é estado de conservação.
|     Bem que estava avariado passa a "sem uso" com conservação "péssimo".
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bem', function (Blueprint $table) {
            $table->string('estado_conservacao', 20)->default('indeterminado')->after('status');
            $table->index('estado_conservacao'); // filtro da consulta e do relatório
        });

        DB::table('bem')->where('status', 'avariado')->update([
            'status' => 'sem_uso',
            'estado_conservacao' => 'pessimo',
        ]);
    }

    public function down(): void
    {
        // Volta o que der para voltar: sem uso em estado péssimo ou sucata
        // era o que o antigo "avariado" representava.
        DB::table('bem')
            ->where('status', 'sem_uso')
            ->whereIn('estado_conservacao', ['pessimo', 'sucata'])
            ->update(['status' => 'avariado']);

        Schema::table('bem', function (Blueprint $table) {
            $table->dropIndex(['estado_conservacao']);
            $table->dropColumn('estado_conservacao');
        });
    }
};
