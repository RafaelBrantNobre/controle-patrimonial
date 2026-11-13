import { BannerPagina, CartaoDeAcao, Cartao, Entrada, Botao, Aviso, Carregando, Desativado } from '../components/ui'
import {
  Building2,
  ChartNoAxesCombined,
  KeyRound,
  Plus,
  Settings2,
  ShieldCheck,
  Tags,
  UserPlus,
  Users,
  type LucideIcon,
} from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { useAutenticacao } from '../autenticacao/Autenticacao'
import { SeloAtivo } from '../componentes/SelosDeBem'
import { BotaoExcluir, type Vinculos } from '../componentes/Exclusao'
import { apiDelete, apiGet, apiPatch, apiPost, ErroDaApi } from '../services/api'
import type { Bem, Pagina, TipoPatrimonio } from '../services/tipos'
import { useApi } from '../services/useApi'

// Painel de Gerência (wireframe), só para o administrador.
// Funcionam: atalho para cadastrar sala, exportação (pela tela Relatórios) e
// o cadastro de tipos de patrimônio, que o wireframe não desenha mas a API
// já tem (README, 10).
// Fora do back-end por enquanto: gestão de usuários e de acessos (tarefa 3.3)
// e os parâmetros da plaqueta (o código interno CP-000123 é fixo na API).
export function PainelDeGerencia() {
  const { usuario } = useAutenticacao()

  return (
    <>
      <BannerPagina
        titulo="Painel de Gerência"
        contexto="Módulo restrito · Sessão segura ativa"
        subtitulo="Ferramentas de administração exclusivas da gestão do patrimônio e do suporte de TI."
        icone={Settings2}
        acoes={
          <span className="selo-perfil">
            <ShieldCheck className="size-5" />
            {usuario?.perfil_rotulo}
          </span>
        }
      />

      <div className="mb-7 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <CartaoDeAcao
          cor="roxo"
          icone={UserPlus}
          selo="Equipe"
          titulo="Cadastrar Novo Usuário"
          texto="Adicione servidores à equipe e defina o nível de acesso."
          para="/usuarios/novo"
          acao="Cadastrar membro"
        />
        <CartaoDeAcao
          cor="ciano"
          icone={Building2}
          selo="Espaços"
          titulo="Cadastrar Ambiente / Sala"
          texto="Ao criar uma sala, ela fica disponível para receber bens."
          para="/ambientes/novo"
          acao="Novo local físico"
        />
        <CartaoDeAcao
          cor="verde"
          icone={KeyRound}
          selo="Segurança"
          titulo="Controle de Acessos"
          texto="Redefina senhas e gerencie os acessos da equipe."
          para="/usuarios"
          acao="Gerenciar credenciais"
        />
        <CartaoDeAcao
          cor="laranja"
          icone={ChartNoAxesCombined}
          selo="Exportar"
          titulo="Relatórios"
          texto="Consulte o resultado de cada conferência realizada."
          para="/relatorios"
          acao="Ver relatórios"
        />
      </div>

      <div className="mb-6">
        <CadastroSimples<TipoPatrimonio>
          titulo="Tipos de patrimônio"
          icone={Tags}
          caminho="/tipos-patrimonio"
          detalhe={(tipo) => `${tipo.bens_count ?? 0} bens`}
          rotulo="o tipo"
          nomeDoVinculo={{ singular: 'bem', plural: 'bens' }}
          buscarVinculos={async (tipo) => {
            const bens = await apiGet<Pagina<Bem>>(`/bens?tipo_patrimonio_id=${tipo.id}&por_pagina=100`)
            return {
              total: bens.meta.total,
              itens: bens.data.map((bem) => ({
                id: bem.id,
                titulo: `${bem.numero_patrimonio ?? 'sem patrimônio'} · ${bem.codigo_interno}`,
                detalhe: bem.sala?.nome ?? 'Sem localização',
                para: `/bens/${bem.id}`,
              })),
            }
          }}
        />
      </div>

      <div>
        <Cartao as="section" className="secao-destaque tom-roxo space-y-4 p-5 sm:p-6">
          <h2 className="flex items-center gap-3 text-xl font-bold tracking-tight text-cead-900 sm:text-base sm:font-semibold">
            <Users className="acao-destaque size-6" /> Membros da Equipe com Acesso
          </h2>
          {/* Desativado: A lista de membros, o último acesso e a ativação de cada pessoa dependem da gestão de usuários na API (tarefa 3.3). */}
          <Desativado icone={Users} />
        </Cartao>
      </div>
    </>
  )
}

type ItemSimples = { id: number; nome: string; ativo: boolean }

// Lista com cadastro rápido, botão de ativar e inativar e botão de excluir.
// Excluir só funciona com o item vazio (RN08): o popup mostra o que está
// ligado e, se não houver nada, pede o nome do item para confirmar.
function CadastroSimples<T extends ItemSimples>({
  titulo,
  icone: Icone,
  caminho,
  detalhe,
  rotulo,
  nomeDoVinculo,
  buscarVinculos,
}: {
  titulo: string
  icone: LucideIcon
  caminho: string
  detalhe: (item: T) => string
  rotulo: string
  nomeDoVinculo: { singular: string; plural: string }
  buscarVinculos: (item: T) => Promise<Vinculos>
}) {
  const [versao, setVersao] = useState(0)
  const [nome, setNome] = useState('')
  const [erro, setErro] = useState<string | null>(null)
  const [ocupado, setOcupado] = useState(false)
  const lista = useApi<Pagina<T>>(`${caminho}?por_pagina=100`, versao)

  async function executar(acao: () => Promise<unknown>) {
    setOcupado(true)
    setErro(null)
    try {
      await acao()
      setVersao((v) => v + 1)
      return true
    } catch (e) {
      setErro(e instanceof ErroDaApi ? e.message : 'Não foi possível salvar.')
      return false
    } finally {
      setOcupado(false)
    }
  }

  async function adicionar(evento: FormEvent) {
    evento.preventDefault()
    if (await executar(() => apiPost(caminho, { nome: nome.trim() }))) setNome('')
  }

  return (
    <Cartao as="section" className="secao-destaque tom-azul overflow-hidden">
      <div className="flex items-center justify-between gap-3 px-5 pt-5 pb-2">
        <h2 className="flex items-center gap-3 text-xl font-bold tracking-tight text-cead-900 sm:text-base sm:font-semibold">
          <Icone className="size-6" /> {titulo}
        </h2>
        <span className="text-xs text-slate-500">{lista.dados?.meta.total ?? '…'} cadastrados</span>
      </div>
      <form onSubmit={adicionar} className="flex flex-col gap-3 px-5 pt-3 pb-5 sm:flex-row sm:gap-5">
        <Entrada
          aria-label="Nome do novo tipo de patrimônio"
          placeholder="Nome do novo cadastro"
          value={nome}
          onChange={(e) => setNome(e.target.value)}
          required
          maxLength={120}
        />
        <Botao variante="primario" type="submit" className="shrink-0" disabled={ocupado}>
          <Plus className="size-4" /> Adicionar
        </Botao>
      </form>
      {erro && (
        <div className="px-4 pt-4">
          <Aviso>{erro}</Aviso>
        </div>
      )}
      {lista.erro && (
        <div className="p-4">
          <Aviso>{lista.erro}</Aviso>
        </div>
      )}
      <ul className="max-h-72 divide-y divide-slate-100 overflow-y-auto">
        {lista.dados?.data.map((item) => (
          <li key={item.id} className="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
            <span>
              <span className="block font-medium text-slate-800">{item.nome}</span>
              <span className="block text-xs text-slate-500">{detalhe(item)}</span>
            </span>
            <span className="flex items-center gap-3">
              <SeloAtivo ativo={item.ativo} />
              <Botao
                type="button"
                className="text-xs font-semibold text-cead-700 hover:underline disabled:opacity-50"
                disabled={ocupado}
                onClick={() => void executar(() => apiPatch(`${caminho}/${item.id}`, { ativo: !item.ativo }))}
              >
                {item.ativo ? 'Inativar' : 'Reativar'}
              </Botao>
              <BotaoExcluir
                rotulo={rotulo}
                nome={item.nome}
                nomeDoVinculo={nomeDoVinculo}
                buscarVinculos={() => buscarVinculos(item)}
                excluir={() => apiDelete(`${caminho}/${item.id}`)}
                aoExcluir={() => setVersao((v) => v + 1)}
                className="text-xs font-semibold text-red-700 hover:underline"
              >
                Excluir
              </BotaoExcluir>
            </span>
          </li>
        ))}
      </ul>
      {lista.carregando && !lista.dados && <Carregando />}
    </Cartao>
  )
}
