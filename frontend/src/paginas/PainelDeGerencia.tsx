import { Building2, DatabaseBackup, KeyRound, Plus, Settings2, ShieldCheck, Tags, UserPlus, Users, type LucideIcon } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { useAutenticacao } from '../autenticacao/Autenticacao'
import { Aviso, Carregando, Desativado, SeloAtivo } from '../componentes/ui'
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
      <section className="cartao mb-6 flex flex-wrap items-center justify-between gap-4 p-6">
        <div className="flex items-center gap-4">
          <span className="rounded-xl bg-cead-900 p-3 text-white">
            <Settings2 className="size-6" />
          </span>
          <div>
            <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">Módulo restrito · Sessão segura ativa</p>
            <h1 className="text-2xl font-bold text-cead-900">Painel de Gerência</h1>
            <p className="text-sm text-slate-500">Ferramentas de administração exclusivas da gestão do patrimônio e do suporte de TI.</p>
          </div>
        </div>
        <span className="flex items-center gap-2 rounded-lg bg-cead-50 px-4 py-2 text-sm text-cead-800">
          <ShieldCheck className="size-4" /> {usuario?.perfil_rotulo}
        </span>
      </section>

      <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Atalho icone={UserPlus} selo="Equipe" titulo="Cadastrar Novo Usuário" texto="Adicione servidores à equipe e defina o nível de acesso." para="/usuarios/novo" acao="Cadastrar membro" />
        <Atalho icone={Building2} selo="Espaços" titulo="Cadastrar Ambiente / Sala" texto="Ao criar uma sala, ela fica disponível para receber bens." para="/ambientes/novo" acao="Novo local físico" />
        <Atalho icone={KeyRound} selo="Segurança" titulo="Controle de Acessos" texto="Redefina senhas e gerencie os acessos da equipe." para="/usuarios" acao="Gerenciar credenciais" />
        <Atalho icone={DatabaseBackup} selo="Exportar" titulo="Relatórios" texto="Consulte o resultado de cada conferência realizada." para="/relatorios" acao="Ver relatórios" />
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

      <div className="grid gap-6 lg:grid-cols-3">
        <section className="cartao space-y-3 p-6 lg:col-span-2">
          <h2 className="flex items-center gap-2 font-semibold text-cead-900">
            <Users className="size-4" /> Membros da Equipe com Acesso
          </h2>
          {/* Desativado: A lista de membros, o último acesso e a ativação de cada pessoa dependem da gestão de usuários na API (tarefa 3.3). */}
          <Desativado />
        </section>
        <section className="cartao space-y-3 p-6">
          <h2 className="flex items-center gap-2 font-semibold text-cead-900">
            <Settings2 className="size-4" /> Parâmetros Operacionais
          </h2>
          <p className="text-sm text-slate-500">
            Hoje o sistema gera o código interno no formato <span className="font-mono font-semibold">CP-000123</span> para todo bem.
          </p>
          {/* Desativado: Prefixo da plaqueta e próximo número de tombamento configuráveis ainda não existem na API. */}
          <Desativado />
        </section>
      </div>
    </>
  )
}

function Atalho({ icone: Icone, selo, titulo, texto, para, acao }: { icone: LucideIcon; selo: string; titulo: string; texto: string; para: string; acao: string }) {
  return (
    <Link to={para} className="cartao flex flex-col p-5 transition hover:border-cead-600">
      <span className="flex items-center justify-between">
        <span className="rounded-lg bg-cead-50 p-2 text-cead-700">
          <Icone className="size-4" />
        </span>
        <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">{selo}</span>
      </span>
      <span className="mt-3 font-semibold text-cead-900">{titulo}</span>
      <span className="mt-1 flex-1 text-xs text-slate-500">{texto}</span>
      <span className="mt-4 text-xs font-semibold text-cead-700">{acao} →</span>
    </Link>
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
    <section className="cartao overflow-hidden">
      <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4">
        <h2 className="flex items-center gap-2 font-semibold text-cead-900">
          <Icone className="size-4" /> {titulo}
        </h2>
        <span className="text-xs text-slate-500">{lista.dados?.meta.total ?? '…'} cadastrados</span>
      </div>
      <form onSubmit={adicionar} className="flex gap-2 border-b border-slate-100 p-4">
        <input className="entrada" placeholder="Nome do novo cadastro" value={nome} onChange={(e) => setNome(e.target.value)} required maxLength={120} />
        <button type="submit" className="botao-primario shrink-0" disabled={ocupado}>
          <Plus className="size-4" /> Adicionar
        </button>
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
              <button
                type="button"
                className="text-xs font-semibold text-cead-700 hover:underline disabled:opacity-50"
                disabled={ocupado}
                onClick={() => void executar(() => apiPatch(`${caminho}/${item.id}`, { ativo: !item.ativo }))}
              >
                {item.ativo ? 'Inativar' : 'Reativar'}
              </button>
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
    </section>
  )
}
