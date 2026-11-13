# Frontend — componentes e estilos

O frontend usa **React + TypeScript + Tailwind CSS**. Não é necessário trocar de framework: componentes são funções que retornam HTML (JSX), recebem parâmetros (`props`) e encaminham os eventos JavaScript da tela.

## Onde editar

| Elemento | Arquivo em `src/components/ui` |
| --- | --- |
| Cabeçalho, navegação e identificação do usuário | `Header.tsx` |
| Logo do projeto | `Logo.tsx` e `src/assets/cead-patrimonio.svg` |
| Menu do usuário e saída | `PerfilUsuario.tsx` |
| Banner de página com grafismo | `BannerPagina.tsx` |
| Cards de atalhos com cores de destaque | `CartaoDeAcao.tsx` |
| Formas decorativas do fundo | `FundoMarca.tsx` |
| Título, subtítulo e ações de uma página | `Cabecalho.tsx`, `Titulo.tsx` |
| Botões e links com aparência de botão | `Botao.tsx` |
| Input, select, textarea, checkbox e radio | `Entrada.tsx` |
| Rótulo, ajuda e erro de um campo | `Campo.tsx` |
| Cards e indicadores numéricos | `Cartao.tsx`, `Indicador.tsx` |
| Tabelas e contêiner de rolagem | `Tabela.tsx` |
| Modal, foco e fechamento | `Dialogo.tsx` |
| Confirmação de envio e exclusão | `Confirmacao.tsx` |
| Dicas no mouse e no foco do teclado | `Tooltip.tsx` |
| Alternância claro/escuro e preferência salva | `ThemeToggle.tsx` |
| Selos, avisos, carregamento e paginação | `Selo.tsx`, `Aviso.tsx`, `Estados.tsx`, `Paginacao.tsx` |
| Aparência compartilhada e paleta escura | `ui.css` |
| Identidade visual, faixa colorida, navegação e destaques | `marca.css` |

`src/index.css` mantém a importação do Tailwind, as cores CEAD, fonte, base da página e regras de impressão. Modifique as classes em `ui.css` para alterar o mesmo elemento em todas as telas. As classes Tailwind de disposição e espaçamento específicas de cada tela continuam na página.

Importe os componentes por `components/ui` (o `index.ts` reúne as exportações). Não copie a estrutura HTML de um controle para outra tela.

## Identidade visual

O rebranding usa superfícies claras, azul institucional e as seis cores da logo na faixa do cabeçalho. `marca.css` concentra os tokens de fundo, superfície, borda e sombra, além das variantes de tema escuro. Os grafismos são formas CSS decorativas, sem imagens adicionais.

`BannerPagina` recebe título, subtítulo, contexto opcional, ícone e ações. `Cabecalho` usa esse banner para manter a identidade nas listagens. `CartaoDeAcao` recebe a prop `cor` (`azul`, `roxo`, `ciano`, `verde` ou `laranja`) e centraliza ícone, selo, descrição e link de ação. O Painel de Gerência combina esses componentes sem alterar as operações da API.

O cabeçalho mostra navegação com ícones em telas grandes e um menu expansível abaixo de 1280 px. O menu do perfil contém a ação de sair; fecha por Escape ou clique fora. A logo usa SVG inline, com enquadramento definido em `.logo-marca`. O grupo de contornos `.texto-marca` mantém o azul original no tema claro e recebe branco no tema escuro; os símbolos coloridos permanecem iguais.

## Exemplo de formulário

```tsx
import { Botao, Campo, Cartao, Entrada, Selecao } from '../../components/ui'

<Cartao as="section" className="space-y-4 p-6">
  <Campo rotulo="Nome" obrigatorio erro={erro?.doCampo('nome')}>
    <Entrada required value={nome} onChange={(e) => setNome(e.target.value)} />
  </Campo>
  <Campo rotulo="Situação">
    <Selecao value={situacao} onChange={(e) => setSituacao(e.target.value)}>
      <option value="ativo">Ativo</option>
      <option value="inativo">Inativo</option>
    </Selecao>
  </Campo>
  <Botao variante="primario" type="submit" disabled={salvando}>Salvar</Botao>
</Cartao>
```

Os controles aceitam os atributos e eventos do HTML, inclusive `ref`, `required`, `disabled`, `aria-*` e `autoComplete`. Botões usam `type="button"` por padrão; para enviar um formulário, informe `type="submit"`. As variantes são `primario`, `secundario`, `sucesso`, `perigo`, `simples` (base com espaçamento) e `neutro` (sem aparência adicional, padrão). Para navegação, use `LinkBotao`, com a prop `to` do React Router.

`Tabela` centraliza o estilo dos `th` e `td`, mantendo `thead`, `tbody`, linhas e células em HTML. Envolva-a em `RolagemTabela` para tabelas largas.

## Modais

Renderize o modal apenas quando estiver aberto. `Dialogo` usa o elemento nativo `dialog`: mantém o foco dentro do modal, devolve o foco ao acionador e bloqueia a interação com o fundo. Escape e clique no fundo fecham quando `ocupado` é falso. O conteúdo pode rolar em telas pequenas.

```tsx
{confirmando && (
  <ConfirmarEnvio
    titulo="Salvar alterações?"
    aoFechar={() => setConfirmando(false)}
    aoConfirmar={() => void salvar()}
    ocupado={salvando}
    desabilitado={!valido}
  >
    <p>Confira os dados antes de continuar.</p>
  </ConfirmarEnvio>
)}
```

`ConfirmarExclusao` compartilha o comportamento e usa o botão de perigo. `componentes/Exclusao.tsx` continua responsável pela regra de negócio: consulta os vínculos, bloqueia exclusão de itens vinculados, exige digitar o nome e trata os erros da API. Os selos que interpretam o estado dos bens ficam em `componentes/SelosDeBem.tsx`; a UI genérica não depende dos serviços patrimoniais.

## Tema e tooltip

O cabeçalho inclui `ThemeToggle`. A primeira visita usa a preferência do sistema; a escolha fica em `localStorage` na chave `patrimonio-tema`. A paleta escura fica centralizada em `ui.css` e as regras de impressão mantêm o fundo claro.

`Tooltip` recebe `texto` e um único controle como filho. O controle deve encaminhar `aria-describedby`; para ações com apenas ícone, informe também `aria-label`.

## Verificação

```sh
npm ci
npm run build
npm run lint
```

As páginas ficam em `src/paginas`, a composição e as regras compartilhadas em `src/componentes`, e os controles visuais em `src/components/ui`. Chamadas à API, autenticação e permissões continuam fora dos componentes visuais.
