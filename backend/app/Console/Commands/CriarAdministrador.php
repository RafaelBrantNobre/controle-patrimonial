<?php

namespace App\Console\Commands;

use App\Enums\PerfilUsuario;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/*
| Cria um usuário administrador (RNF04).
|
| É o caminho para criar o primeiro administrador em produção, onde o seeder
| não cria ninguém. A senha é digitada na hora, sem aparecer na tela, e nunca
| passa por argumento de linha de comando, que ficaria no histórico do
| terminal, nem por arquivo do repositório.
|
| Uso: php artisan usuario:criar-admin
|      php artisan usuario:criar-admin --nome="Christine" --email=christine@unimontes.br
*/
class CriarAdministrador extends Command
{
    protected $signature = 'usuario:criar-admin
                            {--nome= : Nome do administrador}
                            {--email= : E-mail usado no login}';

    protected $description = 'Cria um usuário administrador, pedindo a senha na hora';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Este comando pede a senha na hora e precisa ser rodado num terminal, sem a opção --no-interaction.');

            return self::FAILURE;
        }

        $nome = trim((string) ($this->option('nome') ?: $this->ask('Nome')));
        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('E-mail'))));

        $erros = Validator::make(['nome' => $nome, 'email' => $email], [
            'nome' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', Rule::unique('usuario', 'email')],
        ], [
            'nome.required' => 'Informe o nome.',
            'nome.max' => 'O nome pode ter no máximo 120 caracteres.',
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Informe um e-mail válido, como nome@unimontes.br.',
            'email.max' => 'O e-mail pode ter no máximo 150 caracteres.',
            'email.unique' => 'Já existe um usuário com este e-mail.',
        ])->errors();

        if ($erros->isNotEmpty()) {
            foreach ($erros->all() as $erro) {
                $this->error($erro);
            }

            return self::FAILURE;
        }

        $senha = (string) $this->secret('Senha (pelo menos 10 caracteres, com letras e números)');
        $confirmacao = (string) $this->secret('Repita a senha');

        if ($senha !== $confirmacao) {
            $this->error('As duas senhas não são iguais. Nada foi criado.');

            return self::FAILURE;
        }

        $errosDaSenha = Validator::make(['senha' => $senha], ['senha' => ['required', Password::defaults()]], [
            'senha.required' => 'Informe a senha.',
            'senha.min' => 'A senha precisa ter pelo menos 10 caracteres.',
            'senha.password.letters' => 'A senha precisa ter pelo menos uma letra.',
            'senha.password.numbers' => 'A senha precisa ter pelo menos um número.',
        ])->errors();

        if ($errosDaSenha->isNotEmpty()) {
            foreach ($errosDaSenha->all() as $erro) {
                $this->error($erro);
            }
            $this->line('Nada foi criado.');

            return self::FAILURE;
        }

        User::create([
            'nome' => $nome,
            'email' => $email,
            'senha' => $senha,
            'perfil' => PerfilUsuario::Administrador,
            'ativo' => true,
        ]);

        $this->info("Administrador {$nome} ({$email}) criado. Ele já pode entrar no sistema.");

        return self::SUCCESS;
    }
}
