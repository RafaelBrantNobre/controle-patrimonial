import { AlertTriangle, Building, ClipboardCheck, FileSpreadsheet, History, Printer, RotateCcw, Table2, type LucideIcon } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { AguardandoApi, Aviso, Carregando, SeloConservacao, SeloStatus } from '../componentes/ui'
import { apiBaixar, consulta } from '../services/api'
import { formatarData, hojeIso } from '../services/formatos'
import {
  ROTULOS_STATUS,
  type Andar,
  type Bem,
  type Pagina,
  type Relatorio,
  type Sala,
  type StatusBem,
  type TipoPatrimonio,
} from '../services/tipos'
import { useApi } from '../services/useApi'

type Modelo = 'por_sala' | 'inventario' | 'pendencias' | 'semestral'

// Os quatro modelos do wireframe, montados com os filtros que o relatório da
// API já aceita (README, 13). "Pendência / avarias" são os bens em estado
// péssimo ou sucata, que é como o back-end registra o bem danificado.
const MODELOS: { id: Modelo; categoria: string; titulo: string; texto: string; icone: LucideIcon; destaque?: boolean }[] = [
  {
    id: 'por_sala',
    categoria: 'Conferência física',
    titulo: 'Lista Geral de Bens por Sala',
    texto: 'Folha prática para conferência física rápida de cada sala, com caixa para marcar se o item está no local.',
    icone: Building,
  },
  {
    id: 'inventario',
    categoria: 'Visão predial',
    titulo: 'Inventário Completo do Prédio',
    texto: 'Listagem geral de todos os equipamentos cadastrados no CEAD, agrupados por pavimento e sala.',
    icone: Table2,
  },
  {
    id: 'pendencias',
    categoria: 'Atenção necessária',
    titulo: 'Itens com Pendência / Avarias',
    texto: 'Bens em estado péssimo ou sucata, que precisam de conserto, manutenção ou baixa.',
    icone: AlertTriangle,
    destaque: true,
  },
  {
    id: 'semestral',
    categoria: 'Checagem de campo',
    titulo: 'Folha de Conferência Semestral',
    texto: 'Só os bens que mudaram de sala ou de situação no período, para conferir as mudanças do semestre.',
    icone: ClipboardCheck,
  },
]

// Semestre corrente: 1º de janeiro a 30 de junho ou 1º de julho a 31 de dezembro.
function semestreAtual() {
  const ano = new Date().getFullYear()
  return new Date().getMonth() < 6 ? { inicio: `${ano}-01-01`, fim: `${ano}-06-30` } : { inicio: `${ano}-07-01`, fim: `${ano}-12-31` }
}

// Relatórios e Folhas de Conferência (wireframe).
// "Gerar PDF para impressão" usa a impressão do navegador sobre a prévia; o
// PDF no modelo da Unimontes gerado pela API é a tarefa 3.4.
// Fora do back-end por enquanto: o histórico de relatórios gerados.
export function Relatorios() {
  const [parametros] = useSearchParams()
  const salaInicial = parametros.get('sala_id') ?? ''
  const [modelo, setModelo] = useState<Modelo>('por_sala')
  const [periodo, setPeriodo] = useState(semestreAtual)
  const [andarId, setAndarId] = useState('')
  const [salaId, setSalaId] = useState(salaInicial)
  const [tipoId, setTipoId] = useState('')
  const [status, setStatus] = useState('')
  const [comCaixas, setComCaixas] = useState(true)
  const [comAssinatura, setComAssinatura] = useState(true)
  const [baixando, setBaixando] = useState(false)
  const [erroDownload, setErroDownload] = useState<string | null>(null)

  const andares = useApi<Pagina<Andar>>('/andares?por_pagina=100')
  const salas = useApi<Pagina<Sala>>(`/salas${consulta({ andar_id: andarId, por_pagina: 100 })}`)
  const tipos = useApi<Pagina<TipoPatrimonio>>('/tipos-patrimonio?por_pagina=100')
  const totalBens = useApi<Pagina<Bem>>('/bens?por_pagina=1')
  const totalSalas = useApi<Pagina<Sala>>('/salas?por_pagina=1')

  const filtros = consulta({
    inicio: periodo.inicio,
    fim: periodo.fim,
    andar_id: andarId,
    sala_id: salaId,
    tipo_patrimonio_id: tipoId,
    status,
    estado_conservacao: modelo === 'pendencias' ? 'pessimo,sucata' : undefined,
    apenas_mudancas: modelo === 'semestral' ? true : undefined,
  })
  const periodoValido = periodo.inicio !== '' && periodo.fim !== '' && periodo.inicio <= periodo.fim
  const relatorio = useApi<Relatorio>(periodoValido ? `/relatorio${filtros}` : null)

  function limpar() {
    setModelo('por_sala')
    setPeriodo(semestreAtual())
    setAndarId('')
    setSalaId('')
    setTipoId('')
    setStatus('')
    setComCaixas(true)
    setComAssinatura(true)
  }

  async function baixarPlanilha() {
    setBaixando(true)
    setErroDownload(null)
    try {
      await apiBaixar(`/relatorio/planilha${filtros}`, `levantamento_${periodo.inicio}_a_${periodo.fim}.xlsx`)
    } catch (e) {
      setErroDownload(e instanceof Error ? e.message : 'Não foi possível gerar a planilha.')
    } finally {
      setBaixando(false)
    }
  }

  const modeloAtual = MODELOS.find((item) => item.id === modelo)!

  return (
    <>
      <div className="nao-imprimir">
        <section className="cartao mb-6 flex flex-wrap items-center justify-between gap-4 p-6">
          <div>
            <p className="text-xs text-slate-500">Controle interno / Relatórios práticos</p>
            <h1 className="mt-1 text-2xl font-bold text-cead-900">Emissão e Listagens Operacionais</h1>
            <p className="mt-1 text-sm text-slate-500">
              Gere rapidamente folhas de conferência física, listagens por sala e inventários para uso no dia a dia do prédio.
            </p>
          </div>
          <div className="flex gap-6 rounded-lg bg-cead-50 px-5 py-3">
            <div>
              <p className="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">Total no CEAD</p>
              <p className="text-xl font-bold text-cead-900">{totalBens.dados?.meta.total.toLocaleString('pt-BR') ?? '…'} bens</p>
            </div>
            <div>
              <p className="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">Salas mapeadas</p>
              <p className="text-xl font-bold text-cead-900">{totalSalas.dados?.meta.total ?? '…'} ambientes</p>
            </div>
          </div>
        </section>

        <section className="mb-6">
          <div className="mb-3 flex items-center justify-between">
            <h2 className="font-semibold text-cead-900">
              <span className="mr-2 inline-flex size-6 items-center justify-center rounded-full bg-cead-800 text-xs text-white">1</span>
              Selecione o Modelo de Relatório Desejado
            </h2>
            <span className="text-xs text-slate-500">{MODELOS.length} modelos práticos disponíveis</span>
          </div>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {MODELOS.map((item) => {
              const Icone = item.icone
              const selecionado = item.id === modelo
              return (
                <button
                  key={item.id}
                  type="button"
                  onClick={() => setModelo(item.id)}
                  className={`cartao flex flex-col p-5 text-left transition ${selecionado ? 'border-cead-600 ring-2 ring-cead-100' : 'hover:border-slate-300'}`}
                >
                  <span className={`w-fit rounded-lg p-2 ${selecionado ? 'bg-cead-800 text-white' : 'bg-cead-50 text-cead-700'}`}>
                    <Icone className="size-4" />
                  </span>
                  <span className={`mt-3 text-[10px] font-semibold tracking-wide uppercase ${item.destaque ? 'text-amber-600' : 'text-slate-500'}`}>
                    {item.categoria}
                  </span>
                  <span className="mt-1 font-semibold text-cead-900">{item.titulo}</span>
                  <span className="mt-2 flex-1 text-xs leading-relaxed text-slate-500">{item.texto}</span>
                  <span className="mt-4 border-t border-slate-100 pt-3 text-xs font-medium text-cead-700">
                    {selecionado ? '✓ Selecionado' : 'Clique para selecionar'}
                  </span>
                </button>
              )
            })}
          </div>
        </section>

        <section className="cartao mb-6 space-y-4 p-6">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div>
              <h2 className="font-semibold text-cead-900">
                <span className="mr-2 inline-flex size-6 items-center justify-center rounded-full bg-cead-800 text-xs text-white">2</span>
                {modeloAtual.titulo}
              </h2>
              <p className="mt-1 text-xs text-slate-500">
                Configure os filtros abaixo para limitar a listagem, ou deixe em "Todos" para imprimir o lote completo.
              </p>
            </div>
            <span className="rounded-full bg-cead-50 px-3 py-1 text-xs font-medium text-cead-700">Impressão A4 / Exportação rápida</span>
          </div>

          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <label>
              <span className="rotulo">Período: início</span>
              <input type="date" className="entrada" value={periodo.inicio} onChange={(e) => setPeriodo((p) => ({ ...p, inicio: e.target.value }))} />
            </label>
            <label>
              <span className="rotulo">Período: fim</span>
              <input type="date" className="entrada" value={periodo.fim} onChange={(e) => setPeriodo((p) => ({ ...p, fim: e.target.value }))} />
            </label>
            <label>
              <span className="rotulo">Pavimento</span>
              <select
                className="entrada"
                value={andarId}
                onChange={(e) => {
                  setAndarId(e.target.value)
                  setSalaId('')
                }}
              >
                <option value="">Todos os pavimentos</option>
                {andares.dados?.data.map((andar) => (
                  <option key={andar.id} value={andar.id}>
                    {andar.nome}
                  </option>
                ))}
              </select>
            </label>
            <label>
              <span className="rotulo">Sala / Ambiente</span>
              <select className="entrada" value={salaId} onChange={(e) => setSalaId(e.target.value)}>
                <option value="">Todas as salas</option>
                {salas.dados?.data.map((sala) => (
                  <option key={sala.id} value={sala.id}>
                    {sala.nome}
                  </option>
                ))}
              </select>
            </label>
            <label>
              <span className="rotulo">Categoria do bem</span>
              <select className="entrada" value={tipoId} onChange={(e) => setTipoId(e.target.value)}>
                <option value="">Todas as categorias</option>
                {tipos.dados?.data.map((tipo) => (
                  <option key={tipo.id} value={tipo.id}>
                    {tipo.nome}
                  </option>
                ))}
              </select>
            </label>
            <label>
              <span className="rotulo">Situação do item</span>
              <select className="entrada" value={status} onChange={(e) => setStatus(e.target.value)}>
                <option value="">Todos os itens</option>
                {(Object.keys(ROTULOS_STATUS) as StatusBem[]).map((opcao) => (
                  <option key={opcao} value={opcao}>
                    {ROTULOS_STATUS[opcao]}
                  </option>
                ))}
              </select>
            </label>
          </div>

          {!periodoValido && <Aviso>Informe o início e o fim do período, com o fim depois do início.</Aviso>}

          <div className="flex flex-wrap gap-x-6 gap-y-2 text-sm text-slate-600">
            <label className="flex items-center gap-2">
              <input type="checkbox" checked={comCaixas} onChange={(e) => setComCaixas(e.target.checked)} />
              Incluir caixas de marcação para conferência física
            </label>
            <label className="flex items-center gap-2">
              <input type="checkbox" checked={comAssinatura} onChange={(e) => setComAssinatura(e.target.checked)} />
              Incluir campo para assinatura do responsável pela checagem
            </label>
          </div>

          {erroDownload && <Aviso>{erroDownload}</Aviso>}

          <div className="flex flex-wrap gap-2">
            <button type="button" className="botao-secundario" onClick={limpar}>
              <RotateCcw className="size-4" /> Limpar
            </button>
            <button type="button" className="botao-verde" onClick={() => void baixarPlanilha()} disabled={!periodoValido || baixando}>
              <FileSpreadsheet className="size-4" /> {baixando ? 'Gerando planilha...' : 'Planilha Excel'}
            </button>
            <button type="button" className="botao-primario" onClick={() => window.print()} disabled={!relatorio.dados}>
              <Printer className="size-4" /> Gerar PDF para impressão
            </button>
          </div>
        </section>
      </div>

      <section className="cartao mb-6 overflow-hidden print:border-0 print:shadow-none">
        <div className="nao-imprimir flex items-center justify-between border-b border-cead-100 bg-cead-50 px-6 py-3">
          <h2 className="text-xs font-semibold tracking-wide text-cead-800 uppercase">Prévia do modelo para impressão</h2>
          {relatorio.dados && (
            <span className="rounded-full bg-white px-3 py-1 text-xs text-slate-600">
              {relatorio.dados.totais.bens} itens · {relatorio.dados.totais.mudaram_no_periodo} mudaram no período
            </span>
          )}
        </div>

        {relatorio.erro && (
          <div className="p-6">
            <Aviso>{relatorio.erro}</Aviso>
          </div>
        )}
        {relatorio.carregando && <Carregando texto="Montando o relatório..." />}
        {relatorio.dados && !relatorio.carregando && (
          <FolhaDeConferencia relatorio={relatorio.dados} titulo={modeloAtual.titulo} comCaixas={comCaixas} comAssinatura={comAssinatura} />
        )}
      </section>

      <section className="cartao nao-imprimir space-y-3 p-6">
        <h2 className="flex items-center gap-2 font-semibold text-cead-900">
          <History className="size-4" /> Histórico Recente de Relatórios
        </h2>
        <AguardandoApi>A API ainda não guarda os relatórios gerados, então não há histórico para mostrar.</AguardandoApi>
      </section>
    </>
  )
}

function FolhaDeConferencia({
  relatorio,
  titulo,
  comCaixas,
  comAssinatura,
}: {
  relatorio: Relatorio
  titulo: string
  comCaixas: boolean
  comAssinatura: boolean
}) {
  const salas = relatorio.andares.flatMap((andar) => andar.salas.map((sala) => ({ ...sala, andar: andar.nome })))

  if (salas.length === 0) return <Carregando texto="Nenhum bem com estes filtros." />

  return (
    <div className="space-y-6 p-6 print:p-0">
      {salas.map((sala) => (
        <article key={sala.id} className="rounded-lg border border-slate-200 p-5 break-inside-avoid-page">
          <header className="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div className="flex items-center gap-3">
              <span className="rounded bg-cead-900 px-2 py-1.5 text-[10px] font-black text-white">CEAD</span>
              <div>
                <p className="font-semibold text-cead-900">CEAD - {titulo.toUpperCase()}</p>
                <p className="text-xs text-slate-500">
                  Ambiente: {sala.nome} · Pavimento: {sala.andar}
                </p>
              </div>
            </div>
            <div className="text-right text-xs text-slate-500">
              <p>Período: {formatarData(relatorio.periodo.inicio)} a {formatarData(relatorio.periodo.fim)}</p>
              <p>
                Data: ____/____/______ · Total: {sala.total} bens · Emitido em {formatarData(hojeIso())}
              </p>
            </div>
          </header>

          <div className="overflow-x-auto">
          <table className="tabela">
            <thead>
              <tr>
                {comCaixas && <th className="w-10">(ok)</th>}
                <th>Nº tombamento</th>
                <th>Descrição do bem</th>
                <th>Categoria</th>
                <th>Situação atual</th>
                <th>Conservação</th>
                <th>Observações de campo</th>
              </tr>
            </thead>
            <tbody>
              {sala.bens.map((bem) => (
                <tr key={bem.id} className={bem.mudou_no_periodo ? 'bg-yellow-50' : ''}>
                  {comCaixas && (
                    <td>
                      <span className="inline-block size-4 rounded border border-slate-400" />
                    </td>
                  )}
                  <td className="font-mono text-xs font-semibold whitespace-nowrap text-cead-800">
                    {bem.numero_patrimonio ?? 'sem patrimônio'}
                    <span className="block font-normal text-slate-400">{bem.codigo_interno}</span>
                  </td>
                  <td>
                    {[bem.descricao, bem.marca].filter(Boolean).join(' · ') || '—'}
                    {bem.quantidade > 1 && <span className="text-xs text-slate-500"> ({bem.quantidade} un.)</span>}
                    {bem.veio_da_sala && <span className="block text-xs text-amber-700">veio de {bem.veio_da_sala.nome}</span>}
                  </td>
                  <td className="text-slate-600">{bem.tipo}</td>
                  <td>
                    <SeloStatus status={bem.status} rotulo={bem.status_rotulo} />
                  </td>
                  <td>
                    <SeloConservacao estado={bem.estado_conservacao} rotulo={bem.estado_conservacao_rotulo} />
                  </td>
                  <td className="min-w-32 border-b border-dashed text-xs text-slate-300">&nbsp;</td>
                </tr>
              ))}
            </tbody>
          </table>
          </div>

          {comAssinatura && (
            <footer className="mt-8 grid gap-8 text-xs text-slate-500 sm:grid-cols-2">
              <p className="border-t border-slate-400 pt-2">Aplicador / Conferente</p>
              <p className="border-t border-slate-400 pt-2">Assinatura do responsável pela sala</p>
            </footer>
          )}
        </article>
      ))}
    </div>
  )
}
