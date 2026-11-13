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
10. [Salas e tipos de patrimônio](#10-salas-e-tipos-de-patrimônio)
11. [Bens patrimoniais](#11-bens-patrimoniais)
12. [Mudança de sala, situação e baixa](#12-mudança-de-sala-situação-e-baixa)
13. [Relatórios](#13-relatórios)
14. [Carga inicial da planilha](#14-carga-inicial-da-planilha)
15. [Telas do sistema](#15-telas-do-sistema)
16. [Conferência dos bens](#16-conferência-dos-bens)

## 1. O que instalar antes

| Programa | Versão | Para que serve | Como conferir |
| --- | --- | --- | --- |
| Git | qualquer versão recente | baixar e versionar o código | `git --version` |
| PHP | 8.4 ou mais nova | rodar o back-end | `php -v` |
| Composer | 2 | instalar as bibliotecas do back-end | `composer -V` |
| Node.js | 22 ou mais nova (o npm vem junto) | rodar o front-end | `node -v` |
| Docker Desktop | qualquer versão recente | rodar o banco MySQL | `docker --version` |

Observações:

- O PHP precisa das extensões `pdo_mysql` e `zip` ligadas. Para conferir, rode `php -m` e procure as duas na lista. A `zip` é usada para ler a planilha da carga e gerar o relatório dela (seção 14).
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

Abra http://localhost:5173/status. A página mostra três linhas e as três devem estar em verde:

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
| `composer install` reclama de `ext-zip`, ou a carga da planilha dá erro | falta ligar a extensão `zip` no `php.ini`: tire o `;` da linha `;extension=zip` e abra o terminal de novo |
| a página do front-end mostra o back-end sem resposta | confira se `php artisan serve` está rodando e se `VITE_API_URL` em `frontend/.env` está certo. Depois de mudar o `.env`, pare e rode `npm run dev` de novo |
| `npm run dev` diz que a porta 5173 está em uso | feche o outro terminal que já está rodando o front-end |

## 9. Login e perfis de acesso

O login é feito por token. A API devolve um token quando o e-mail e a senha estão certos, e o front-end envia esse token em todas as requisições seguintes, no cabeçalho `Authorization: Bearer <token>`. Não há cookie nem sessão.

### 9.1 Usuários de teste

Criados por `php artisan migrate:fresh --seed`. A senha dos dois é `password`.

| E-mail | Perfil | O que pode |
| --- | --- | --- |
| `admin@example.com` | administrador | tudo: consultar, cadastrar, editar, mover, mudar status, dar baixa, importar |
| `consulta@example.com` | consulta | só consultar |

O seeder só cria esses usuários quando `APP_ENV` no `.env` é `local` ou `testing`. Em qualquer outro ambiente, como `production`, ele não cria ninguém e avisa no terminal. Assim, a senha conhecida nunca chega ao servidor.

### 9.1.1 Primeiro administrador em produção

No servidor, o primeiro administrador é criado com um comando, que pede o nome, o e-mail e a senha na hora:

```
php artisan usuario:criar-admin
```

Também dá para informar o nome e o e-mail direto, e só a senha é perguntada:

```
php artisan usuario:criar-admin --nome="Christine" --email=christine@unimontes.br
```

- A senha é digitada sem aparecer na tela e pedida duas vezes. Ela nunca vai como argumento do comando, que ficaria no histórico do terminal.
- A senha precisa ter pelo menos 10 caracteres, com letras e números. A regra fica em `AppServiceProvider` (`Password::defaults()`) e vai valer também na gestão de usuários (tarefa 3.3).
- O comando recusa e-mail repetido ou inválido e senhas diferentes, e não cria nada nesses casos.
- O comando precisa de um terminal, porque pergunta a senha. Com `--no-interaction`, ele não roda.

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
| 2. Leitura | administrador e consulta | listar bens, ver ficha, ver conferências |
| 3. Alteração | só administrador | cadastrar, editar, mover, mudar status, dar baixa, importar |

Na dúvida, a rota vai para o grupo 3. Esconder um botão na tela não basta: a regra que vale é a do back-end (RN10).

Dentro de um controller, o usuário conectado está em `$request->user()`. Para gravar quem fez uma mudança de sala ou de status (RN09), use `$request->user()->id`.

## 10. Salas e tipos de patrimônio

Cadastros que sustentam o resto do sistema: todo bem tem um tipo e fica numa sala, ou sem localização enquanto não se sabe onde está (tarefa 2.5). As duas rotas seguem o mesmo formato.

O cadastro de andares foi retirado em 10/11/2026: o CEAD ocupa um andar só, e a sala basta para localizar o bem.

### 10.1 Rotas

| Método e rota | Quem acessa | O que faz |
| --- | --- | --- |
| `GET /api/salas` | administrador e consulta | lista as salas, em ordem de nome |
| `GET /api/salas/{id}` | administrador e consulta | devolve uma sala |
| `POST /api/salas` | administrador | cadastra. Campos: `nome` (obrigatório), `descricao` e `ativo` |
| `PUT` ou `PATCH /api/salas/{id}` | administrador | edita. Só os campos enviados mudam |
| `DELETE /api/salas/{id}` | administrador | exclui, se a sala não tiver bens |
| `GET /api/tipos-patrimonio` | administrador e consulta | lista os tipos, em ordem alfabética |
| `GET /api/tipos-patrimonio/{id}` | administrador e consulta | devolve um tipo |
| `POST /api/tipos-patrimonio` | administrador | cadastra. Campos: `nome` (obrigatório) e `ativo` |
| `PUT` ou `PATCH /api/tipos-patrimonio/{id}` | administrador | edita. Só os campos enviados mudam |
| `DELETE /api/tipos-patrimonio/{id}` | administrador | exclui, se o tipo não tiver bens |

Inativar e reativar é uma edição: `PATCH` com `{"ativo": false}` ou `{"ativo": true}`.

### 10.2 Listagens

As listagens aceitam estes filtros na URL, que podem ser combinados:

| Filtro | Onde vale | Exemplo |
| --- | --- | --- |
| `busca` | as duas | `?busca=suporte` traz os nomes que contêm "suporte" |
| `ativo` | as duas | `?ativo=1` só os ativos, `?ativo=0` só os inativos. Sem o filtro, vêm todos |
| `pagina` | as duas | `?pagina=2`. Começa em 1 |
| `por_pagina` | as duas | `?por_pagina=50`. Padrão 20, máximo 100 |

Nas telas de cadastro de bem, use `?ativo=1` para montar as listas de escolha, porque sala e tipo inativos não recebem bens novos.

A resposta traz os itens em `data`, os links de navegação em `links` e a contagem em `meta` (`total`, `current_page`, `last_page`, `per_page`). Cada sala e cada tipo traz `bens_count`. O front pode usar esse número para mostrar o botão de excluir só quando ele vai funcionar.

### 10.3 Regras

- Nome da sala e nome do tipo não se repetem. No MySQL, "Cadeira" e "cadeira" contam como o mesmo nome.
- Sala ou tipo com bens não são excluídos (RN08). O caminho é inativar.

Respostas de erro, além das da seção 9.2:

| Código | Quando acontece | O que o front faz |
| --- | --- | --- |
| 404 | o registro não existe, por exemplo porque foi excluído | mostra a mensagem que veio em `message` e atualiza a lista |
| 409 | tentativa de excluir sala ou tipo com bens | mostra a mensagem que veio em `message`, que sugere inativar |
| 422 | campo obrigatório vazio ou nome repetido | mostra a mensagem que veio em `errors`, ao lado do campo |

## 11. Bens patrimoniais

Cadastro e consulta dos bens (tarefa 2.6). Mudar de sala, mudar de situação e dar baixa são a tarefa 2.8.

### 11.1 Rotas

| Método e rota | Quem acessa | O que faz |
| --- | --- | --- |
| `GET /api/bens` | administrador e consulta | lista os bens, com busca, filtros e ordenação (11.3) |
| `GET /api/bens/{id}` | administrador e consulta | ficha do bem, com tipo, sala e quem fez a última mudança |
| `GET /api/salas/{id}/bens` | administrador e consulta | todos os bens de uma sala, sem paginação, agrupados por tipo. Serve para a conferência sala a sala |
| `POST /api/bens` | administrador | cadastra um bem |
| `PUT` ou `PATCH /api/bens/{id}` | administrador | edita os dados do bem. Só os campos enviados mudam |

Não há rota para excluir bem. A saída do patrimônio é a baixa (RN07).

### 11.2 Campos

| Campo | Cadastro | Edição | Observação |
| --- | --- | --- | --- |
| `tipo_patrimonio_id` | obrigatório | pode mudar | tipo inativo não recebe bens novos |
| `sala_id` | opcional | não muda | sem ele (ou `null`), o bem fica **sem localização**. Sala inativa não recebe bens novos |
| `numero_patrimonio` | opcional | pode mudar | número da plaqueta, até 20 caracteres. Não se repete (RN01) |
| `descricao`, `marca`, `origem` | opcionais | podem mudar | em branco quando o bem não tem |
| `quantidade` | opcional, padrão 1 | pode mudar | de 1 a 9999 (PD08) |
| `status` | opcional, padrão `em_uso` | não muda | situação de uso. No cadastro: `em_uso` ou `sem_uso`. A baixa é registrada depois |
| `estado_conservacao` | opcional, padrão `indeterminado` | não muda (usar `PATCH /api/bens/{id}/conservacao`) | condição física: `novo`, `bom`, `regular`, `pessimo`, `sucata` ou `indeterminado` |

O `codigo_interno` é gerado pelo sistema no formato `CP-000123` e não pode ser informado nem editado. Ele existe para todo bem, tenha ou não plaqueta (PD07).

**Situação de uso e estado de conservação são independentes.** A situação diz se o bem está sendo usado (em uso, sem uso ou baixado). A conservação diz em que condição física ele está, na mesma escala da planilha de conferência. Um bem pode estar em uso e péssimo, ou sem uso e novo. Um bem quebrado fica `sem_uso` com conservação `pessimo` ou `sucata`. A conservação muda pela ficha do bem (`PATCH /api/bens/{id}/conservacao`, corpo `{"estado_conservacao": "regular"}`), que grava a ocorrência, e não conta como mudança de situação. A edição do bem recusa a mudança, como faz com sala e situação.

Na edição, `sala_id` e `status` podem vir no formulário desde que iguais aos atuais. Se forem diferentes, a resposta é 422, indicando a opção de mudar de sala ou de situação.

A resposta de um bem traz também `status_rotulo` (texto pronto para a tela), `baixado` (para sinalizar o bem baixado que ainda está no prédio, RN05), `data_ultima_mudanca`, `baixa_data`, `baixa_observacao`, `cadastrado_em` e `atualizado_em`.

### 11.3 Consulta

Filtros de `GET /api/bens`, todos opcionais e combináveis:

| Filtro | Exemplo | O que faz |
| --- | --- | --- |
| `numero` | `?numero=1634456-1` | número de patrimônio ou código interno exato (RF12) |
| `busca` | `?busca=lenovo` | parte do número, do código, da descrição ou da marca |
| `sala_id`, `tipo_patrimonio_id` | `?sala_id=3&tipo_patrimonio_id=5` | só os bens daquela sala ou tipo (RF13) |
| `sem_sala` | `?sem_sala=1` | só os bens sem localização |
| `status` | `?status=em_uso,sem_uso` | uma ou mais situações de uso, separadas por vírgula |
| `estado_conservacao` | `?estado_conservacao=pessimo,sucata` | um ou mais estados de conservação, separados por vírgula |
| `ordenar` | `?ordenar=numero` | `local` (padrão: sala e tipo, a ordem do levantamento), `numero`, `codigo`, `tipo`, `status`, `conservacao` ou `cadastro` |
| `direcao` | `?direcao=desc` | `asc` (padrão) ou `desc` |
| `pagina`, `por_pagina` | `?pagina=2&por_pagina=50` | como nas listagens da seção 10 |

Bem sem localização vem com `sala_id` e `sala` nulos, e as telas mostram "Sem localização". Ele ganha sala pela mudança de sala (seção 12) ou na conferência, na etapa *Sem localização* (seção 16).

Bens baixados aparecem na lista, com `baixado: true`. Para escondê-los, filtre pelas outras situações: `?status=em_uso,sem_uso`.

## 12. Mudança de sala, situação e baixa

Operações que mudam onde o bem está ou em que situação ele se encontra (tarefa 2.8). Só o administrador faz. Toda mudança grava a data (`data_ultima_mudanca`) e quem a fez (`usuario_ultima_mudanca`) (RN09).

### 12.1 Rotas

| Método e rota | Corpo | O que faz |
| --- | --- | --- |
| `PATCH /api/bens/{id}/sala` | `{"sala_id": 5}` | muda o bem de sala e grava a mudança no histórico (tabela `movimentacao`) |
| `PATCH /api/bens/{id}/status` | `{"status": "sem_uso"}` | muda a situação de uso para `em_uso` ou `sem_uso` |
| `PATCH /api/bens/{id}/status` | `{"status": "baixado", "baixa_data": "2026-10-23", "baixa_observacao": "Processo 123"}` | registra a baixa |

As duas rotas devolvem o bem atualizado, no mesmo formato da ficha (seção 11).

### 12.2 Regras

- Bem baixado não muda de sala (RN04).
- O destino precisa ser outra sala, e ativa. O bem sem localização pode ir para qualquer sala ativa.
- A nova situação precisa ser diferente da atual.
- Na baixa, `baixa_data` é obrigatória, no formato `ano-mês-dia`, e não pode ser futura. `baixa_observacao` é opcional, enquanto a PD06 não define quais dados da baixa registrar. Fora da baixa, os dois campos não são aceitos.
- O bem baixado continua aparecendo nas listas, com `baixado: true` (RN05).
- Para desfazer uma baixa registrada por engano, mude a situação para outra. Os dados da baixa são apagados, e a mudança fica registrada com data e usuário.
- Cada mudança de sala vira uma linha na tabela `movimentacao`, com sala de origem, sala de destino, usuário e momento. Só mudanças de sala entram nela: as de situação ficam registradas no próprio bem.
- A ficha do bem traz a mudança mais recente em `ultima_movimentacao` (`sala_origem`, `sala_destino`, `data` e `usuario`), e vem `null` se o bem nunca mudou de sala.
- Sala que aparece no histórico, como origem ou destino, não pode ser excluída, mesmo sem bens. O caminho é inativar.

## 13. Relatórios

O relatório por período (`GET /api/relatorio` e a planilha do levantamento) foi retirado em 10/11/2026. O sistema é de conferência patrimonial, e o relatório passou a ser o resultado de cada conferência concluída (seção 16), que a tela Relatórios lista.

A exportação de cada conferência em planilha, no formato da planilha de novembro de 2024, ainda não existe.

## 14. Carga inicial da planilha

Comando que coloca no sistema os bens da planilha `Patrimônios CEAD NOVEMBRO DE 2024.xlsx` (tarefa 2.12, RF28 e RF29). O objetivo é fidelidade à planilha: corrigir as defasagens dela é o recadastramento físico (tarefa 3.7).

### 14.1 Como rodar

Sempre comece pela simulação. Ela faz tudo e gera o relatório, mas não grava nada:

```
php artisan patrimonio:carregar-planilha "C:\caminho\Patrimônios CEAD NOVEMBRO DE 2024.xlsx" --fonte=principal --simular
```

Com o relatório conferido, rode sem `--simular` para gravar.

| Opção | O que faz |
| --- | --- |
| `--fonte=principal` | lê só a aba Principal (629 linhas). O estado vira situação de uso e conservação pela tabela de conversão |
| `--fonte=salas` | lê as abas de sala (828 linhas). O estado é a conservação, e a sala vem do nome da aba |
| `--situacao-abas=em_uso` | situação de uso dos itens das abas de sala, que a planilha não informa. Padrão `em_uso`. Sucata sempre entra `sem_uso` |
| `--simular` | gera o relatório sem gravar nada |
| `--do-zero` | apaga bens, histórico, salas e tipos antes de carregar. Não roda em produção |

A fonte é obrigatória até a Christine dizer qual das duas é a certa.

A carga é para um sistema vazio. Se já houver bens, o comando recusa, a não ser com `--simular` ou `--do-zero`. Rodar por cima duplicaria os bens sem plaqueta, que não têm número para a repetição ser notada.

Tudo é gravado numa transação só: se algo falhar no meio, nada entra.

### 14.2 O que a carga faz com cada linha

- **Sala:** na aba Principal, vem da coluna Local. Nas abas de sala, vem do nome da aba. As salas e os tipos usados são criados automaticamente.
- **Tipo:** é o começo da descrição. "Cadeira vermelha, convênio" vira tipo Cadeira, descrição "vermelha" e origem "Convênio". Marcas conhecidas, como Thermaltake, HP e Lenovo, vão para o campo marca.
- **Número de patrimônio:** volta ao texto digitado (o Excel guarda 85015792 como `8.5015792E7`). Número no meio de outro texto, como "Série C 85018848" ou "900284315 (Convênio)", é separado, com aviso. "Sem patrimônio" deixa o bem sem número, com o código interno do sistema (PD07). Texto que não é plaqueta, como um ramal ou "Ilegível", deixa o bem sem número, com aviso no relatório da carga.
- **Marco zero:** o bem não guarda de onde veio na planilha. O que a planilha dizia sobre ele já está nos campos do bem (tipo, descrição, marca, origem, sala, situação e conservação), e a aba e a linha de cada bem ficam no relatório da carga (14.4). Depois da carga, o histórico vem das mudanças registradas no sistema (seção 12). O campo `observacao` do bem foi retirado em 28/10/2026 por esse motivo.

### 14.3 Quando a linha é rejeitada

| Motivo | Exemplo |
| --- | --- |
| Número repetido: entra só a primeira linha com o número (RN11) | o mesmo `1634456-1` em duas linhas |
| Número que já está no sistema (RN01) | carga por cima de dados existentes, na simulação |
| A linha conta vários itens: precisa ser separada antes (PD08) | "Monitor" com estado "Em uso 20 Unidades" |
| Tipo não reconhecido na descrição | "Conect" |
| Local não reconhecido (aba Principal) | um local que não está no mapeamento |
| Estado em branco ou que não é estado (aba Principal) | "Sala de projetos" na coluna Estado |

A linha **sem** número de patrimônio **não** é rejeitada, ao contrário do que diz a RN11. O bem entra com o código interno, como decidido na PD07.

### 14.4 Relatório da carga (RF29)

Cada execução gera um Excel em `backend/storage/app/carga/`, com o nome `relatorio_carga_<fonte>_<data>.xlsx`, e `_simulacao` no fim quando for simulação. Ele tem quatro abas:

| Aba | Conteúdo |
| --- | --- |
| Resumo | linhas lidas, bens importados, linhas rejeitadas, o que foi criado e as rejeições agrupadas por motivo |
| Importados | cada bem que entrou, com a aba e a linha de origem e o código interno que recebeu |
| Rejeitados | cada linha que ficou de fora, com o motivo |
| Avisos | bens que entraram, mas merecem conferência |

### 14.5 Ajustar a interpretação

As regras ficam em `backend/database/carga/planilha.php`, separadas do código: salas, locais, abas, tipos, marcas, origens e conversão de estados. Para resolver uma rejeição de tipo ou de local, inclua a palavra no mapeamento e rode a simulação de novo.

## 15. Telas do sistema

As telas seguem os wireframes do Figma *CEAD - Gestão Patrimonial* (página Prototipagem; a Tela Login vem da página Definitivo). Ficam em `frontend/src/paginas`, com React Router, Tailwind e ícones do lucide.

### 15.1 Rotas do front-end

| Endereço | Tela | Quem vê | API usada |
| --- | --- | --- | --- |
| `/login` | Tela Login | todos | `POST /login` |
| `/bens` | Listagem de Bens Patrimoniais | administrador e consulta | `GET /bens` |
| `/bens/novo`, `/bens/{id}/editar` | Cadastro de Bem Patrimonial | administrador | `POST` e `PATCH /bens` |
| `/bens/{id}` | Ficha do bem, com o registro de ocorrências (seção 16.4) | administrador e consulta (as ações, só administrador) | `GET /bens/{id}`, `PATCH /bens/{id}/sala` e `/status` |
| `/ambientes` | Gestão de Ambientes e Salas | administrador e consulta | `GET /salas` |
| `/ambientes/novo`, `/ambientes/{id}/editar` | Cadastrar Ambiente / Sala | administrador | `POST` e `PATCH /salas` |
| `/ambientes/{id}` | Detalhes da sala | administrador e consulta | `GET /salas/{id}`, `GET /salas/{id}/bens` |
| `/conferencias` | Conferências e bens não localizados (seção 16) | administrador e consulta (as ações, só administrador) | `GET /conferencias`, `GET /pendencias` |
| `/conferencias/{id}` | Conferência sala a sala | administrador e consulta (registrar, só administrador) | `/conferencias/{id}/itens` e as rotas da seção 16.1 |
| `/relatorios` | Relatórios: as conferências concluídas | administrador e consulta | `GET /conferencias?situacao=concluida` |
| `/gerencia` | Painel de Gerência, com o cadastro de tipos | administrador | `/tipos-patrimonio` |
| `/usuarios`, `/usuarios/novo` | Usuários do Sistema e Cadastrar Novo Usuário | administrador | nenhuma ainda (tarefa 3.3) |
| `/status` | Verificação do ambiente (seção 2.4) | todos | `GET /status` |

O token do login fica no `sessionStorage` e some ao fechar o navegador. Com **Lembrar meu acesso** marcado, fica no `localStorage`. Quando a API responde 401, o front apaga o token e volta para o login.

### 15.2 Exclusão

Sala e tipo de patrimônio têm botão **Excluir**: o tipo no Painel de Gerência, a sala nos Detalhes da sala. A regra é a da API (seção 10.3): só se exclui o que está vazio.

- Se houver algo ligado (bens da sala ou do tipo), o popup lista o que está ligado e avisa que não é possível excluir. O caminho é mover os itens ou inativar.
- Se estiver vazio, o popup avisa que a exclusão é permanente e só libera o botão depois que a pessoa digita o nome do item.
- Sala vazia que aparece no histórico de mudanças também não é excluída. A mensagem da API aparece no popup.

Bem não é excluído (RN07). O cadastro de bem avisa isso antes de salvar, e a saída do patrimônio é a baixa (seção 12).

### 15.3 O que o wireframe pede e a API ainda não tem

Essas partes aparecem nas telas numa caixa **Desativado** (componente `Desativado`), sem dado inventado e sem explicação para quem usa. O motivo fica em comentário no código, ao lado da caixa:

| Tela | Falta na API |
| --- | --- |
| Login | entrar com CPF, "Esqueci minha senha" |
| Bens | foto, modelo, número de série, impressão da etiqueta, exportar a lista de bens em Excel |
| Ambientes | código ou sigla da sala, tipo de espaço, responsável, capacidade e histórico da sala |
| Relatórios | baixar cada conferência em planilha (XLSX), no formato da planilha de 2024 |
| Conferências | iniciar a conferência a partir de um arquivo (planilha ou SIAD): depende do formato do arquivo |
| Gerência e Usuários | listar, cadastrar, editar e desativar usuários (tarefa 3.3) |

Diferenças de nomes entre o wireframe e a API:

- As situações *Em manutenção* e *Danificado* do wireframe não são situações de uso na API. O bem danificado fica `sem_uso` com conservação `pessimo` ou `sucata` (seção 11.2).
- O perfil *Operador* do wireframe é o perfil `consulta` da API.
- A navegação do wireframe tem **Início**, mas não há wireframe dessa tela. O endereço `/` abre a lista de bens.

## 16. Conferência dos bens

A conferência é o levantamento físico: alguém percorre as salas com a folha impressa, confere bem por bem e depois passa o resultado para o sistema. O sistema registra cada conferência, mostra em cada bem a data e o resultado da última, e transforma o bem não localizado numa pendência da gestora.

Fluxo:

1. **Iniciar** em `/conferencias`, no botão *Iniciar conferência*. A tela pergunta se há um arquivo para importar; por enquanto só funciona *Pular e usar os bens cadastrados*, que inclui todos os bens não baixados. Só existe **uma conferência aberta por vez**.
2. A tela vai direto para a **primeira sala**. Cada sala é uma etapa; os bens sem sala ficam numa etapa final, *Sem localização*; a última etapa é a conclusão.
3. Em cada sala, para cada bem: **encontrado ou não localizado**, a **sala** onde está, o **estado de conservação** e a **condição de uso** (em uso ou sem uso). Os campos já vêm com a situação registrada, como sugestão. *Marcar os pendentes como encontrados* preenche a sala de uma vez, e restam só as exceções.
4. **Salvar sala e ir para a próxima** grava a sala inteira. O progresso fica guardado: dá para sair e voltar, e a tela reabre na primeira sala que ainda tem bens pendentes.
5. **Concluir**, informando o dia em que a conferência foi feita. Só aqui os bens mudam. A conferência também pode ser **cancelada**, sem efeito nos bens.

### 16.1 Rotas

| Método e rota | Quem acessa | O que faz |
| --- | --- | --- |
| `GET /api/conferencias` | administrador e consulta | lista as conferências, da mais recente para a mais antiga, com as contagens. Filtro `?situacao=aberta`, `concluida` ou `cancelada` |
| `GET /api/conferencias/{id}` | administrador e consulta | uma conferência, com as contagens |
| `GET /api/conferencias/{id}/itens` | administrador e consulta | os bens da conferência por sala, com as salas e os pendentes de cada uma. Filtros `?sala_id=` (sala registrada), `?sem_sala=1` e `?resultado=pendente`, `encontrado` ou `nao_localizado`. Com `?resumo=1` vêm só a conferência e as salas |
| `POST /api/conferencias` | administrador | inicia. Corpo `{"nome", "modo": "filtro"}` para todos os bens (é o que a tela usa), `{"nome", "modo": "filtro", "filtros": {...}}` com os filtros de `GET /api/bens` (seção 11.3) ou `{"nome", "modo": "selecionados", "bem_ids": [...]}`. Responde 409 se já houver uma conferência aberta |
| `PUT /api/conferencias/{id}/itens` | administrador | salva os bens de uma sala de uma vez: `{"itens": [{"id", "resultado", "sala_encontrada_id", "estado_conservacao", "condicao_uso"}]}`. Resultado nulo deixa o bem pendente |
| `PATCH /api/conferencias/{id}/itens/{item}` | administrador | lança um bem: `{"resultado": "encontrado", "sala_encontrada_id": 7, "estado_conservacao": "regular"}`, `{"resultado": "nao_localizado"}` ou `{"resultado": null}` para voltar a pendente |
| `POST /api/conferencias/{id}/encontrados` | administrador | marca em lote como encontrados na sala registrada: `{"itens": [10, 11]}` |
| `POST /api/conferencias/{id}/concluir` | administrador | conclui: `{"realizada_em": "2026-11-06"}` |
| `POST /api/conferencias/{id}/cancelar` | administrador | cancela a conferência aberta |
| `GET /api/pendencias` | administrador e consulta | os bens com pendência aberta, cada um uma vez, com a data da primeira conferência em que não foi localizado |
| `POST /api/bens/{id}/pendencia` | administrador | fecha a pendência: `{"resolucao": "localizado", "sala_id": 5, "localizado_em": "2026-11-06"}` ou `{"resolucao": "processo_administrativo", "processo_numero": "123/2026"}` |

`GET /api/bens` ganhou o filtro `?conferencia=nunca`, `encontrado`, `nao_localizado` ou `em_aberto`. As quatro situações não se sobrepõem: o bem que está numa conferência aberta só aparece em `em_aberto`, como no selo da tela. A resposta de um bem traz `data_ultima_conferencia`, `resultado_ultima_conferencia` e, na lista, `em_conferencia` (a conferência aberta em que o bem está). A ficha traz também `pendencia` e `historico_conferencias`.

### 16.2 Regras

- Só existe uma conferência aberta por vez. Para iniciar outra, conclua ou cancele a que está aberta. Bem baixado não entra.
- Enquanto a conferência está aberta, os resultados ficam só nela e nada muda nos bens. Conferência concluída ou cancelada não recebe mais resultados (409).
- Só conclui quando todos os bens têm resultado. O dia da conferência não pode ser futuro, mas pode ser anterior ao dia em que a folha foi lançada.
- Ao concluir, para cada bem:
  - encontrado em outra sala vira mudança de sala, com histórico (seção 12). A sala precisa estar ativa, e bem baixado não muda de sala (RN04). Isso é conferido de novo na conclusão;
  - o estado de conservação e a condição de uso informados passam a valer;
  - cada uma dessas mudanças vira uma ocorrência no histórico do bem, ligada à conferência (seção 16.4). Confirmar o bem como está não gera ocorrência;
  - a sala, o estado e o uso só são aplicados se a conferência é a informação mais nova do bem: se ele mudou depois de registrado na conferência, ou se já tem uma conferência de data mais recente, vale o que é mais novo;
  - a data e o resultado da última conferência são gravados no bem. Uma conferência de data anterior, concluída depois, não passa por cima da mais recente.
- Cancelar não muda nada nos bens, e eles podem entrar numa conferência nova. A conferência cancelada continua registrada.
- Sala em que algum bem foi encontrado numa conferência não pode ser excluída (seção 10.3). O caminho é inativar.

### 16.3 Bem não localizado

Bem não localizado é grave: a gestora responde por ele e precisa abrir processo administrativo. Por isso:

- ao concluir a conferência, o bem não localizado vira uma **pendência**, que aparece em destaque em `/conferencias` e na ficha do bem;
- a situação de uso do bem não muda sozinha;
- a pendência só fecha de dois jeitos: **localizado** (a sala onde estava e o dia, que não pode ser anterior à conferência em que ele sumiu) ou **processo administrativo** (o número do processo, que fica no histórico de conferências do bem). Com o processo, o bem continua como não localizado até a baixa;
- uma conferência nova que encontra o bem fecha as pendências antigas dele, como localizado. Se a conferência em que ele não foi localizado for de data anterior à última em que foi encontrado, não abre pendência.

### 16.4 Ocorrências

Tudo o que acontece com um bem fica no histórico de ocorrências dele (tabela `ocorrencia`): mudança de sala, de estado de conservação, de condição de uso e baixa. Cada linha guarda o valor de antes e o de depois, a data, quem registrou e, quando veio de uma conferência, qual.

- Na tela: localize o bem em `/bens` (pelos filtros, como o de sala) e use **Registrar ocorrência**, na lista ou na ficha. A ficha mostra o histórico.
- Na API, as ocorrências são gravadas pelas rotas que já mudam o bem: `PATCH /api/bens/{id}/sala`, `PATCH /api/bens/{id}/status` (seção 12) e `PATCH /api/bens/{id}/conservacao`. A ficha (`GET /api/bens/{id}`) traz `ocorrencias`.
- A baixa não apaga o bem nem o histórico. Desfazer a baixa também vira ocorrência.
