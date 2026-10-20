# Controle Patrimonial

Sistema de controle patrimonial do CEAD/Unimontes. Registra os bens do prédio, onde cada um está e em que situação se encontra, para apoiar a declaração de patrimônio feita a cada semestre.

O repositório tem dois projetos:

| Pasta | O que é | Tecnologia | Endereço local |
| --- | --- | --- | --- |
| `backend/` | API, regras de negócio e banco de dados | Laravel 13 (PHP) e MySQL 8.4 | http://localhost:8000 |
| `frontend/` | Telas do sistema | React 19, Vite e TypeScript | http://localhost:5173 |

O front-end não acessa o banco. Ele conversa apenas com a API do back-end.

## Sumário

1. [O que instalar antes](#1-o-que-instalar-antes)
2. [Subir o ambiente do zero](#2-subir-o-ambiente-do-zero)
3. [Dia a dia](#3-dia-a-dia)
4. [Variáveis de ambiente](#4-variáveis-de-ambiente)
5. [Branches](#5-branches)
6. [Padrão de commit](#6-padrão-de-commit)
7. [Revisão por merge request](#7-revisão-por-merge-request)
8. [Problemas comuns](#8-problemas-comuns)
9. [Login e perfis de acesso](#9-login-e-perfis-de-acesso)

## 1. O que instalar antes

| Programa | Versão | Para que serve | Como conferir |
| --- | --- | --- | --- |
| Git | qualquer versão recente | baixar e versionar o código | `git --version` |
| PHP | 8.4 ou mais nova | rodar o back-end | `php -v` |
| Composer | 2 | instalar as bibliotecas do back-end | `composer -V` |
| Node.js | 22 ou mais nova (o npm vem junto) | rodar o front-end | `node -v` |
| Docker Desktop | qualquer versão recente | rodar o banco MySQL | `docker --version` |

Observações:

- O PHP precisa da extensão `pdo_mysql` ligada. Para conferir, rode `php -m` e procure `pdo_mysql` na lista.
- O Docker Desktop precisa estar aberto antes de subir o banco.
- Os comandos deste README funcionam no PowerShell do Windows.

## 2. Subir o ambiente do zero

São três terminais no fim: um para o back-end, um para o front-end e um livre para o Git.

### 2.1 Baixar o código

```powershell
git clone https://gitlab.com/cead-2026/controle-patrimonial.git
cd controle-patrimonial
git checkout develop
```

### 2.2 Back-end e banco

```powershell
cd backend
copy .env.example .env
```

Abra o arquivo `backend/.env` e preencha a linha `DB_PASSWORD=` com uma senha qualquer, escolhida por você. Ela vale só para o banco da sua máquina. Exemplo: `DB_PASSWORD=minhasenha123`.

Depois, ainda dentro de `backend`:

```powershell
docker compose up -d
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

O que cada comando faz:

- `docker compose up -d` cria e liga o banco MySQL, usando o nome, o usuário e a senha que estão no `.env`. Na primeira vez, espere uns 30 segundos antes do próximo passo, porque o banco demora a ficar pronto.
- `composer install` baixa as bibliotecas do back-end.
- `php artisan key:generate` gera a chave de segurança da aplicação e grava no `.env`.
- `php artisan migrate` cria as tabelas no banco.
- `php artisan serve` liga a API em http://localhost:8000. Deixe este terminal aberto.

Para conferir, abra http://localhost:8000/api/status no navegador. A resposta deve trazer `"conectado": true`.

Para ter um usuário de teste no banco local, rode também `php artisan db:seed`. Ele cria o administrador `admin@example.com`, com a senha `password`. Esse usuário existe só no banco da sua máquina.

### 2.3 Front-end

Em outro terminal, a partir da pasta do repositório:

```powershell
cd frontend
copy .env.example .env
npm install
npm run dev
```

- `npm install` baixa as bibliotecas do front-end.
- `npm run dev` liga o front-end em http://localhost:5173. Deixe este terminal aberto.

### 2.4 Conferir se deu certo

Abra http://localhost:5173. A página mostra três linhas e as três devem estar em verde:

- Front-end: no ar
- Back-end: respondendo
- Banco de dados: conectado

Se alguma estiver em vermelho, a própria página diz o que conferir. Veja também a seção [Problemas comuns](#8-problemas-comuns).

## 3. Dia a dia

Depois da primeira vez, para voltar a trabalhar basta:

| Onde | Comando | O que faz |
| --- | --- | --- |
| `backend/` | `docker compose up -d` | liga o banco |
| `backend/` | `php artisan serve` | liga a API |
| `frontend/` | `npm run dev` | liga o front-end |

Outros comandos úteis:

| Onde | Comando | O que faz |
| --- | --- | --- |
| `backend/` | `docker compose stop` | desliga o banco, sem apagar os dados |
| `backend/` | `docker compose down -v` | apaga o banco e todos os dados locais |
| `backend/` | `php artisan migrate` | aplica tabelas novas, depois de um `git pull` |
| `backend/` | `php artisan migrate:fresh --seed` | recria o banco do zero, com os dados de exemplo |
| `backend/` | `php artisan test` | roda os testes do back-end |
| `backend/` | `vendor/bin/pint` | formata o código PHP |
| `frontend/` | `npm run build` | confere se o front-end compila sem erro |
| `frontend/` | `npm run lint` | aponta problemas no código do front-end |

Depois de um `git pull`, rode `composer install` no back-end e `npm install` no front-end se alguém tiver adicionado bibliotecas.

## 4. Variáveis de ambiente

Cada projeto tem um arquivo `.env.example`, que é o modelo, e um arquivo `.env`, que é a cópia de cada pessoa.

- O `.env.example` vai para o Git e nunca contém senha, chave ou token.
- O `.env` fica só na sua máquina. Ele está no `.gitignore` e não deve ser enviado.
- Ao criar uma variável nova, adicione a linha também no `.env.example`, com o valor vazio ou um valor de exemplo, e avise no merge request.

Principais variáveis:

| Arquivo | Variável | Para que serve |
| --- | --- | --- |
| `backend/.env` | `APP_KEY` | chave de segurança, gerada por `php artisan key:generate` |
| `backend/.env` | `FRONTEND_URL` | endereço do front-end autorizado a chamar a API |
| `backend/.env` | `SANCTUM_EXPIRACAO_MINUTOS` | por quanto tempo o login vale, em minutos (padrão 480, um turno de 8 horas) |
| `backend/.env` | `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_PORT` | dados do banco, usados pelo Laravel e pelo Docker |
| `frontend/.env` | `VITE_API_URL` | endereço da API que o front-end consome |

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
- Antes do merge, confira que `php artisan test` e `npm run build` passam.
- Merge request pequeno é mais fácil de revisar. Prefira um por tarefa do quadro.

## 8. Problemas comuns

| Sintoma | O que fazer |
| --- | --- |
| `docker compose up -d` reclama que `DB_PASSWORD` não foi definida | preencha `DB_PASSWORD` em `backend/.env` |
| `docker compose up -d` diz que a porta 3306 já está em uso | já existe um MySQL instalado na máquina, como o do XAMPP. Troque `DB_PORT` para `3307` em `backend/.env` e rode o comando de novo |
| `docker` não é reconhecido ou dá erro com `dockerDesktopLinuxEngine` | o Docker Desktop está fechado. Abra e espere aparecer **Engine running** |
| o Docker Desktop fica parado em **Starting the Docker Engine** | feche o Docker Desktop. No PowerShell como administrador, rode `wsl --shutdown` e `wsl --update` e abra o Docker de novo. Se continuar, confira no Gerenciador de Tarefas, em Desempenho e CPU, se a Virtualização está habilitada |
| `php artisan migrate` dá `Connection refused` | o banco ainda está iniciando. Espere uns 30 segundos e tente de novo. Confira com `docker compose ps` |
| `php artisan migrate` dá `Access denied` e `docker compose ps` mostra a lista vazia | o banco do Docker está desligado e quem respondeu foi outro MySQL instalado na máquina. Troque `DB_PORT` para `3307` em `backend/.env`, rode `docker compose up -d`, espere o `healthy` no `docker compose ps` e rode `php artisan config:clear` antes do `migrate` |
| `php artisan migrate` dá `Access denied` e `docker compose ps` mostra o banco `healthy` | a senha do `.env` foi trocada depois de o banco ter sido criado, e o MySQL guarda a senha da primeira vez. Rode `docker compose down -v` e depois `docker compose up -d`. Isso apaga os dados do banco local |
| `php artisan migrate` dá `could not find driver` | falta ligar a extensão `pdo_mysql` no `php.ini` |
| a página do front-end mostra o back-end sem resposta | confira se `php artisan serve` está rodando e se `VITE_API_URL` em `frontend/.env` está certo. Depois de mudar o `.env`, pare e rode `npm run dev` de novo |
| `npm run dev` diz que a porta 5173 está em uso | feche o outro terminal que já está rodando o front-end |

## 9. Login e perfis de acesso

O login é feito por token. A API devolve um token quando o e-mail e a senha estão certos, e o front-end envia esse token em todas as requisições seguintes, no cabeçalho `Authorization: Bearer <token>`. Não há cookie nem sessão.

### 9.1 Usuários de teste

Criados por `php artisan migrate:fresh --seed`, só no ambiente local. A senha dos dois é `password`.

| E-mail | Perfil | O que pode |
| --- | --- | --- |
| `admin@example.com` | administrador | tudo: consultar, cadastrar, editar, mover, mudar status, dar baixa, importar |
| `consulta@example.com` | consulta | só consultar e gerar relatório |

### 9.2 Rotas de acesso

| Método e rota | Precisa de token | O que faz |
| --- | --- | --- |
| `POST /api/login` | não | recebe `email` e `senha` e devolve `token`, `tipo`, `expira_em` e `usuario` |
| `GET /api/usuario` | sim | devolve o usuário conectado. Serve para o front saber se o token guardado ainda vale |
| `POST /api/logout` | sim | apaga o token usado na requisição. Logins em outros computadores continuam valendo |

O objeto `usuario` traz `id`, `nome`, `email`, `perfil`, `perfil_rotulo` e `pode_alterar`. O front usa `pode_alterar` para esconder botões de cadastro e edição do perfil consulta.

Respostas de erro que o front precisa tratar:

| Código | Quando acontece | O que o front faz |
| --- | --- | --- |
| 422 | e-mail ou senha errados, ou campo vazio | mostra a mensagem que veio em `errors` |
| 401 | sem token, token vencido ou apagado | apaga o token guardado e volta para a tela de login |
| 403 | usuário desativado, ou perfil sem permissão para a ação | mostra a mensagem que veio em `message` |
| 429 | mais de 5 tentativas de login em um minuto | mostra a mensagem que veio em `message` |

Todas as mensagens já vêm em português, prontas para aparecer na tela.

### 9.3 Como proteger uma rota nova

Em `backend/routes/api.php` as rotas ficam em três grupos. Toda rota nova entra em um deles:

| Grupo | Quem acessa | Exemplos |
| --- | --- | --- |
| 1. Públicas | qualquer um, sem login | `status`, `login` |
| 2. Leitura | administrador e consulta | listar bens, ver ficha, gerar relatório |
| 3. Alteração | só administrador | cadastrar, editar, mover, mudar status, dar baixa, importar |

Na dúvida, a rota vai para o grupo 3. Esconder um botão na tela não basta: a regra que vale é a do back-end (RN10).

Dentro de um controller, o usuário conectado está em `$request->user()`. Para gravar quem fez uma mudança de sala ou de status (RN09), use `$request->user()->id`.

