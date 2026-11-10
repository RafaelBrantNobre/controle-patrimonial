// Peças visuais repetidas nas telas: selos, indicadores, avisos e paginação.
import { AlertTriangle, ChevronLeft, ChevronRight, Info, type LucideIcon } from 'lucide-react'
import type { ReactNode } from 'react'
import { formatarData } from '../services/formatos'
import type { Bem, EstadoConservacao, StatusBem } from '../services/tipos'

const CORES_SELO = {
  verde: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  azul: 'bg-cead-50 text-cead-700 ring-cead-100',
  amarelo: 'bg-amber-50 text-amber-700 ring-amber-200',
  vermelho: 'bg-red-50 text-red-700 ring-red-200',
  cinza: 'bg-slate-100 text-slate-600 ring-slate-200',
}

export type CorSelo = keyof typeof CORES_SELO

export function Selo({ cor, children }: { cor: CorSelo; children: ReactNode }) {
  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ${CORES_SELO[cor]}`}
    >
      <span className="size-1.5 rounded-full bg-current" />
      {children}
    </span>
  )
}

const COR_STATUS: Record<StatusBem, CorSelo> = { em_uso: 'verde', sem_uso: 'azul', baixado: 'vermelho' }

export function SeloStatus({ status, rotulo }: { status: StatusBem; rotulo: string }) {
  return <Selo cor={COR_STATUS[status]}>{rotulo}</Selo>
}

const COR_CONSERVACAO: Record<EstadoConservacao, CorSelo> = {
  novo: 'verde',
  bom: 'verde',
  regular: 'amarelo',
  pessimo: 'vermelho',
  sucata: 'vermelho',
  indeterminado: 'cinza',
}

export function SeloConservacao({ estado, rotulo }: { estado: EstadoConservacao; rotulo: string }) {
  return <Selo cor={COR_CONSERVACAO[estado]}>{rotulo}</Selo>
}

// Situação de conferência do bem: em conferência aberta, conferido (com a
// data da última conferência), não localizado ou nunca conferido.
export function SeloConferencia({ bem }: { bem: Bem }) {
  if (bem.em_conferencia) return <Selo cor="azul">Em conferência</Selo>
  if (bem.resultado_ultima_conferencia === 'encontrado') {
    return <Selo cor="verde">Conferido em {formatarData(bem.data_ultima_conferencia)}</Selo>
  }
  if (bem.resultado_ultima_conferencia === 'nao_localizado') {
    return <Selo cor="vermelho">Não localizado em {formatarData(bem.data_ultima_conferencia)}</Selo>
  }
  return <Selo cor="cinza">Nunca conferido</Selo>
}

export function SeloAtivo({ ativo }: { ativo: boolean }) {
  return <Selo cor={ativo ? 'verde' : 'cinza'}>{ativo ? 'Ativo' : 'Inativo'}</Selo>
}

export function Indicador({
  titulo,
  valor,
  detalhe,
  icone: Icone,
}: {
  titulo: string
  valor: ReactNode
  detalhe?: ReactNode
  icone: LucideIcon
}) {
  return (
    <div className="cartao flex items-start justify-between p-5">
      <div>
        <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">{titulo}</p>
        <p className="mt-2 text-3xl font-bold text-cead-900">{valor}</p>
        {detalhe && <p className="mt-1 text-xs text-slate-500">{detalhe}</p>}
      </div>
      <span className="rounded-lg bg-cead-50 p-2.5 text-cead-700">
        <Icone className="size-5" />
      </span>
    </div>
  )
}

export function Cabecalho({ titulo, subtitulo, acoes }: { titulo: string; subtitulo?: string; acoes?: ReactNode }) {
  return (
    <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 className="text-2xl font-bold text-cead-900">{titulo}</h1>
        {subtitulo && <p className="mt-1 text-sm text-slate-500">{subtitulo}</p>}
      </div>
      {acoes && <div className="flex flex-wrap gap-2">{acoes}</div>}
    </div>
  )
}

export function Aviso({ tipo = 'erro', children }: { tipo?: 'erro' | 'info'; children: ReactNode }) {
  const Icone = tipo === 'erro' ? AlertTriangle : Info
  const cores = tipo === 'erro' ? 'border-red-200 bg-red-50 text-red-800' : 'border-amber-200 bg-amber-50 text-amber-900'
  return (
    <div className={`flex gap-3 rounded-lg border px-4 py-3 text-sm ${cores}`} role={tipo === 'erro' ? 'alert' : 'note'}>
      <Icone className="mt-0.5 size-4 shrink-0" />
      <div>{children}</div>
    </div>
  )
}

// Caixa no lugar de um recurso que ainda não funciona. O motivo fica em
// comentário no código de quem usa, não na tela.
export function Desativado() {
  return (
    <div className="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-center text-sm font-medium text-slate-500">
      Desativado
    </div>
  )
}

export function Carregando({ texto = 'Carregando...' }: { texto?: string }) {
  return <p className="px-4 py-8 text-center text-sm text-slate-500">{texto}</p>
}

export function Paginacao({
  pagina,
  ultima,
  total,
  rotulo,
  singular,
  aoMudar,
}: {
  pagina: number
  ultima: number
  total: number
  rotulo: string
  // Para o total 1: "1 bem encontrado" em vez de "1 bens encontrados".
  singular?: string
  aoMudar: (pagina: number) => void
}) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-xs text-slate-500">
      <span>
        {total} {total === 1 && singular ? singular : rotulo}
      </span>
      <div className="flex items-center gap-2">
        <button
          type="button"
          className="botao-secundario px-2 py-1"
          disabled={pagina <= 1}
          onClick={() => aoMudar(pagina - 1)}
          aria-label="Página anterior"
        >
          <ChevronLeft className="size-4" />
        </button>
        <span>
          Página {pagina} de {Math.max(ultima, 1)}
        </span>
        <button
          type="button"
          className="botao-secundario px-2 py-1"
          disabled={pagina >= ultima}
          onClick={() => aoMudar(pagina + 1)}
          aria-label="Próxima página"
        >
          <ChevronRight className="size-4" />
        </button>
      </div>
    </div>
  )
}

export function Campo({
  rotulo,
  erro,
  obrigatorio,
  ajuda,
  children,
}: {
  rotulo: string
  erro?: string
  obrigatorio?: boolean
  ajuda?: string
  children: ReactNode
}) {
  return (
    <label className="block">
      <span className="rotulo">
        {rotulo}
        {obrigatorio && <span className="text-red-600"> *</span>}
      </span>
      {children}
      {ajuda && !erro && <span className="mt-1 block text-xs text-slate-400">{ajuda}</span>}
      {erro && <span className="mt-1 block text-xs text-red-600">{erro}</span>}
    </label>
  )
}
