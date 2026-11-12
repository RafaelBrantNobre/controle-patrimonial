<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| O sistema deixa de controlar andares, e o bem pode ficar sem localização.
|   - sala perde o andar: a sala sozinha já diz onde o bem está, e quem
|     confere conhece o prédio. O nome da sala passa a ser único no prédio.
|   - a tabela andar é apagada.
|   - bem.sala_id passa a aceitar nulo: é o bem "sem localização", que ainda
|     não teve a sala confirmada numa conferência (por exemplo, um bem que
|     acabou de entrar por importação).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sala', function (Blueprint $table) {
            $table->dropForeign(['andar_id']);
        });

        Schema::table('sala', function (Blueprint $table) {
            $table->dropUnique(['andar_id', 'nome']);
        });

        Schema::table('sala', function (Blueprint $table) {
            $table->dropColumn('andar_id');
            $table->unique('nome');
        });

        Schema::dropIfExists('andar');

        Schema::table('bem', function (Blueprint $table) {
            $table->unsignedBigInteger('sala_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Volta a estrutura, com todas as salas num andar só: o andar de
        // cada sala não tem como voltar. O bem sem sala continua permitido,
        // porque pode já existir algum.
        Schema::create('andar', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 60)->unique();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->datetimes();
        });

        $andarId = DB::table('andar')->insertGetId(['nome' => 'Andar a definir', 'created_at' => now(), 'updated_at' => now()]);

        Schema::table('sala', function (Blueprint $table) use ($andarId) {
            $table->dropUnique(['nome']);
            $table->foreignId('andar_id')->default($andarId)->after('id')->constrained('andar')->restrictOnDelete();
            $table->unique(['andar_id', 'nome']);
        });
    }
};
