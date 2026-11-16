# Banco de teste

Cópia do banco local em 16/11/2026, para testar o sistema com dados reais da carga da planilha.

- 819 bens, 16 salas, 42 tipos, 1 conferência aberta (11/11/2026) e 3 ocorrências.
- Usuários: só os dois de teste do seeder, `admin@example.com` e `consulta@example.com`, senha `password`.
- Ficam de fora os dados de tokens de login, sessões e cache. As tabelas vão vazias.

## Como carregar

Num banco vazio, com o MySQL do `docker-compose` no ar:

```bash
docker exec -i controle-patrimonial-banco mysql -u patrimonio -p controle_patrimonial < backend/database/dados/banco-de-teste.sql
```

O arquivo cria as tabelas e já marca as migrações como rodadas. Não rode `migrate:fresh --seed` depois, porque ele apaga tudo. Se o seu código tiver migrações mais novas que as deste arquivo, rode só `php artisan migrate`.

O arquivo apaga e recria as tabelas que traz (`DROP TABLE IF EXISTS`), então substitui o que houver nelas.
