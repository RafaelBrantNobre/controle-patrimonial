<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabela usuario: quem acessa o sistema (página 03, seção 6).
| Substitui a tabela "users" que vem pronta no Laravel.
| A tabela "sessions" continua aqui porque o Laravel guarda as sessões no banco
| (SESSION_DRIVER=database no .env).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120);
            $table->string('email', 150)->unique();
            $table->string('senha', 255);                      // hash da senha (RNF04), nunca o texto puro
            $table->string('perfil', 20)->default('consulta'); // administrador ou consulta
            $table->boolean('ativo')->default(true);           // usuário desativado não entra no sistema
            $table->datetimes();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('usuario');
    }
};
