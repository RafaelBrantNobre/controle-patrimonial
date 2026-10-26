# Controle Patrimonial

Sistema de controle patrimonial do CEAD/Unimontes, feito com Laravel, Blade, Tailwind CSS e MySQL.

A aplicação fica em `backend/`. O Laravel gera as páginas Blade e acessa o banco. O projeto React separado foi removido. O Vite permanece como ferramenta de compilação do CSS e do JavaScript, integrado ao Laravel pelo plugin oficial.

## 1. O que instalar antes

- PHP 8.4 ou superior (PHP 8.5 recomendado), com `pdo_mysql` e `pdo_sqlite` para os testes.
- Composer 2.
- Node.js 22.12 ou superior e npm.
- Docker Desktop para o MySQL.
- Git.

Confira `php -v`, `composer -V` e `node -v`. No Windows, se houver várias instalações, use `Get-Command php -All` e `Get-Command node -All` para conferir qual vem primeiro no PATH.

## 2. Subir o ambiente do zero

Na pasta do repositório:

```powershell
cd backend
copy .env.example .env
```

Preencha `DB_PASSWORD` no `.env` com uma senha para seu banco local. Não sobrescreva o `.env` se já tiver configurado o ambiente.

```powershell
docker compose up -d
composer install
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

Espere o banco ficar saudável em `docker compose ps` antes de migrar. Abra **http://localhost:8000** para ver o login Blade. O build permite abrir a página sem manter o Vite rodando.

Para criar o administrador de desenvolvimento, execute `php artisan db:seed`: e-mail `admin@example.com`, senha `password`. A tela atual ainda é demonstrativa e não autentica esse usuário.

## 3. Dia a dia

Execute todos os comandos na pasta `backend/`.

No primeiro terminal:

```powershell
docker compose up -d
php artisan serve
```

No segundo terminal, durante a edição das telas:

```powershell
npm run dev
```

Abra **http://localhost:8000**, inclusive quando o Vite estiver rodando. A porta 5173 serve apenas os estilos e scripts de desenvolvimento. Blade continua sendo renderizado pelo Laravel.

| Comando | Para que serve |
| --- | --- |
| `npm run dev` | atualiza os estilos e recarrega ao editar Blade |
| `npm run build` | gera CSS e JavaScript em `public/build` |
| `php artisan test --compact` | roda os testes |
| `vendor/bin/pint` | formata o PHP |
| `docker compose stop` | desliga o banco sem apagar dados |
| `docker compose down -v` | apaga o banco local e seus dados |
| `php artisan migrate` | aplica novas migrações |

Depois de atualizar o código, rode `composer install` e `npm install` se as dependências mudaram.

### Onde editar as telas

- `resources/views/layouts/app.blade.php`: estrutura HTML e `@vite`.
- `resources/views/login.blade.php`: tela de login com classes Tailwind.
- `resources/css/app.css`: import do Tailwind e estilos globais.
- `resources/js/app.js`: interação para mostrar/ocultar a senha, sem React.
- `routes/web.php`: rotas das páginas e do formulário.
- `app/Http/Controllers/LoginExemploController.php`: validação do formulário demonstrativo.
- `vite.config.js`: compilação integrada ao Laravel.

O formulário usa POST com `@csrf`, valida e-mail e senha no Laravel e retorna mensagens em português. Ele **ainda não autentica** nem cria sessão de usuário. A senha não é reapresentada no formulário. O link de diagnóstico mantém a rota `/api/status` para verificar o banco.

## 4. Variáveis de ambiente

Existe apenas o ambiente da aplicação Laravel em `backend/.env`. O `.env.example` é o modelo versionado e não contém segredos. Não é mais necessário `VITE_API_URL` nem um endereço de front-end separado.

| Variável | Para que serve |
| --- | --- |
| `APP_KEY` | chave gerada por `php artisan key:generate` |
| `APP_URL` | endereço da aplicação, localmente `http://localhost:8000` |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_PORT` | conexão com o banco e configuração do Docker |
| `SESSION_DRIVER` | sessões; por padrão usa as tabelas do banco |

## 5. Branches

| Branch | Para que serve |
| --- | --- |
| `main` | versão estável, a que pode ser apresentada ou publicada |
| `develop` | versão em desenvolvimento, onde as tarefas prontas se juntam |
| `feature/nome-da-tarefa` | trabalho de uma tarefa do quadro |

Regras:

- Ninguém envia commit direto para `main` nem para `develop`. Tudo entra por merge request.
- Toda tarefa começa em uma branch `feature/`, criada a partir da `develop` atualizada.
- O nome da branch descreve a tarefa, em minúsculas, sem acento e com hífen. Exemplos: `feature/cadastro-de-bens`, `feature/tela-de-login`.
- A `develop` vai para a `main` por merge request, ao fim de cada marco do projeto.

Começar uma tarefa:

```powershell
git checkout develop
git pull
git checkout -b feature/nome-da-tarefa
```

## 6. Padrão de commit

Formato:

```
tipo(escopo): descrição curta
```

- **tipo**: um dos tipos da tabela abaixo.
- **escopo**: opcional. Use `back` ou `front` quando o commit mexe em um projeto só.
- **descrição**: em português, com letra minúscula, verbo no infinitivo e sem ponto final. Deve caber em uma linha.

| Tipo | Quando usar |
| --- | --- |
| `feat` | funcionalidade nova |
| `fix` | correção de erro |
| `docs` | documentação |
| `style` | formatação, sem mudar o comportamento |
| `refactor` | reorganização do código, sem mudar o comportamento |
| `test` | testes |
| `chore` | configuração, dependências e tarefas de manutenção |

Exemplos:

```
feat(back): criar cadastro de bens
feat(front): adicionar tela de login
fix(back): corrigir validação do número de patrimônio
docs: atualizar passos de instalação
chore: atualizar dependências
```

Faça commits pequenos, cada um com uma mudança só. Fica mais fácil revisar e desfazer.

## 7. Revisão por merge request

No GitLab, o pedido de revisão se chama merge request. É o mesmo que o pull request do GitHub.

Toda tarefa entra na `develop` por merge request, mesmo quando não há outro revisor. O merge request registra o que mudou, quando e em qual tarefa.

1. Termine a tarefa na sua branch `feature/` e envie: `git push -u origin feature/nome-da-tarefa`.
2. No GitLab, abra um merge request da sua branch para a `develop`.
3. No título, use o mesmo padrão dos commits. Na descrição, diga o que foi feito, como testar e qual tarefa do quadro ele atende.
4. Revise o próprio diff na aba **Changes**, como se o código fosse de outra pessoa.
5. Se a tarefa afeta o trabalho do outro desenvolvedor, marque-o como revisor e espere a aprovação dele (veja as regras abaixo).
6. Faça o merge sem marcar *Squash commits*, para os commits da tarefa ficarem no histórico. A branch `feature/` pode ser apagada.

Regras:

- Quem é responsável pela tarefa no quadro aprova e faz o merge dela.
- A exceção é a mudança que afeta o trabalho do outro desenvolvedor, por exemplo algo que ele vai usar no código dele ou que muda a forma de subir o ambiente. Nesse caso, o outro revisa antes do merge.
- Antes do merge, confira que `php artisan test` e `npm run build` (ambos em `backend/`) passam.
- Merge request pequeno é mais fácil de revisar. Prefira um por tarefa do quadro.

## 8. Problemas comuns

| Sintoma | O que fazer |
| --- | --- |
| `Vite manifest not found` | rode `npm install` e `npm run build` em `backend/`, ou mantenha `npm run dev` rodando |
| página sem estilos após parar o Vite | confira se o processo encerrou corretamente; se sobrou `backend/public/hot`, apague apenas esse arquivo e rode `npm run build` |
| erro no import do Tailwind após mudar dependências | pare e inicie novamente `npm run dev` em `backend/` |
| erro de versão do Node | use Node 22.12 ou superior; confira a instalação escolhida pelo PATH |
| PowerShell bloqueia `npm.ps1` | use `npm.cmd install` e `npm.cmd run dev` |
| porta 5173 em uso | encerre o antigo servidor React/Vite antes de iniciar o Vite do Laravel |
| `Connection refused` ao acessar o banco | ligue o Docker, rode `docker compose up -d` e confira `docker compose ps` |
| porta 3306 em uso | altere `DB_PORT` para `3307` no `.env` e recrie o contêiner |
| `Access denied` no MySQL | confira a senha e a porta; trocar o `.env` não altera a senha de um banco já criado |
| `could not find driver` | habilite a extensão PHP correspondente: `pdo_mysql` para a aplicação e `pdo_sqlite` para os testes |

Referência da integração de assets: [documentação oficial do Laravel](https://laravel.com/framework/docs/13.x/vite).
