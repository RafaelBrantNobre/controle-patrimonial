<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabela dos tokens de acesso (tarefa 2.4, RNF05).
| Cada login gera um token; o logout apaga o token (revogação).
| O nome em inglês é o que o Laravel Sanctum espera, por isso foge da
| convenção das outras tabelas (singular e em português).
| Só o hash do token fica no banco: quem ler a tabela não consegue usá-lo.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');                 // dono do token (sempre um usuario no MVP)
            $table->text('name');                        // de onde veio o login, ex.: "navegador"
            $table->string('token', 64)->unique();       // hash SHA-256 do token
            $table->text('abilities')->nullable();
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('expires_at')->nullable()->index(); // depois disso o token não vale mais
            $table->datetimes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
