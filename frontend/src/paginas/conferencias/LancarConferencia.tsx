import { Ban, CheckCheck, CheckCircle2, CircleSlash, Printer, RotateCcw, SearchX } from 'lucide-react'
import { useState } from 'react'
import { Link, useLocation, useParams } from 'react-router'
import { useAutenticacao } from '../../autenticacao/Autenticacao'
import { Dialogo } from '../../componentes/Dialogo'
import { Aviso, Carregando, Selo, type CorSelo } from '../../componentes/ui'
import { apiPatch, apiPost, consulta, ErroDaApi } from '../../services/api'
import { formatarData, hojeIso, plural } from '../../services/formatos'
import {
  ROTULOS_CONSERVACAO,
  type Conferencia,
  type EstadoConservacao,
  type ItemDeConferencia,
  type ItensDaConferencia,
  type Pagina,
  type ResultadoConferencia,
  type Sala,
} from '../../services/tipos'
import { useApi } from '../../services/useApi'

const COR_SITUACAO: Record<Conferencia['situacao'], CorSelo> = { aberta: 'azul', concluida: 'verde', cancelada: 'cinza' }

function SeloResultado({ item }: { item: ItemDeConferencia }) {
  if (item.resultado === 'encontrado') return <Selo cor="verde">Encontrado</Selo>
  if (item.resultado === 'nao_localizado') return <Selo cor="vermelho">Não localizado</Selo>
  return <Selo cor="amarelo">Pendente</Selo>
}

// Lançamento da conferência: passar a folha a limpo. Os bens começam
// pendentes; a pessoa marca em lote os que estavam no lugar e lança só as
// exceções (não localizado, outra sala, estado diferente). Os bens só mudam
// ao concluir. Quem não é administrador só acompanha.
export function LancarConferencia() {
  const { id } = useParams()
  const { usuario } = useAutenticacao()
  const location = useLocation()
  const [salaId, setSalaId] = useState('')
  const [resultado, setResultado] = useState('')
  const [versao, setVersao] = useState(0)
  const [alterados, setAlterados] = useState<Map<number, ItemDeConferencia>>(new Map())
  const [selecionados, setSelecionados] = useState<Set<number>>(new Set())
  const [mensagem, setMensagem] = useState<string | null>((location.state as { mensagem?: string } | null)?.mensagem ?? null)
  const [erro, setErro] = useState<string | null>(null)
  const [ocupado, setOcupado] = useState(false)
  const [confirmarNaoLocalizado, setConfirmarNaoLocalizado] = useState<ItemDeConferencia | null>(null)
  const [concluindo, setConcluindo] = useState(false)
  const [cancelando, setCancelando] = useState(false)

  const lista = useApi<ItensDaConferencia>(`/conferencias/${id}/itens${consulta({ sala_id: salaId, resultado })}`, versao)
  // A conferência é buscada à parte, para os totais do topo se atualizarem a cada lançamento.
  const [versaoDoTopo, setVersaoDoTopo] = useState(0)
  const topo = useApi<{ data: Conferencia }>(`/conferencias/${id}`, versaoDoTopo)
  const salasAtivas = useApi<Pagina<Sala>>('/salas?ativo=1&por_pagina=100')

  const conferencia = topo.dados?.data ?? lista.dados?.conferencia
  const podeLancar = Boolean(usuario?.pode_alterar && conferencia?.situacao === 'aberta')
  const itens = (lista.dados?.data ?? []).map((item) => alterados.get(item.id) ?? item)
  const todosVisiveis = itens.length > 0 && itens.every((item) => selecionados.has(item.id))

  function recarregarTudo() {
    setAlterados(new Map())
    setSelecionados(new Set())
    setVersao((v) => v + 1)
    setVersaoDoTopo((v) => v + 1)
  }

  async function lancar(item: ItemDeConferencia, corpo: { resultado: ResultadoConferencia | null; sala_encontrada_id?: number | null; estado_conservacao?: EstadoConservacao | null }) {
    setErro(null)
    try {
      const resposta = await apiPatch<{ data: ItemDeConferencia }>(`/conferencias/${id}/itens/${item.id}`, corpo)
      setAlterados((atual) => new Map(atual).set(item.id, resposta.data))
      setVersaoDoTopo((v) => v + 1)
    } catch (e) {
      setErro(e instanceof ErroDaApi ? e.message : 'Não foi possível lançar o resultado.')
    }
  }

  async function marcarEncontrados() {
    setOcupado(true)
    setErro(null)
    try {
      await apiPost(`/conferencias/${id}/encontrados`, { itens: [...selecionados] })
      setMensagem(`${plural(selecionados.size, 'bem marcado como encontrado', 'bens marcados como encontrados')}.`)
      recarregarTudo()
    } catch (e) {
      setErro(e instanceof ErroDaApi ? e.message : 'Não foi possível marcar os bens.')
    } finally {
      setOcupado(false)
    }
  }

  function alternarTodos() {
    setSelecionados(todosVisiveis ? new Set() : new Set(itens.map((item) => item.id)))
  }

  if (lista.erro && !lista.dados) return <Aviso>{lista.erro}</Aviso>
  if (!conferencia) return <Carregando />

  const totais = conferencia.totais

  return (
    <>
      <p className="nao-imprimir text-xs text-slate-500">
        <Link to="/conferencias" className="hover:underline">
          Conferências
        </Link>{' '}
        / {conferencia.nome}
      </p>

      <section className="cartao mt-3 mb-6 p-6 print:border-0 print:p-0 print:shadow-none">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p className="flex items-center gap-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">
              Conferência <Selo cor={COR_SITUACAO[conferencia.situacao]}>{conferencia.situacao_rotulo}</Selo>
            </p>
            <h1 className="mt-2 text-2xl font-bold text-cead-900">{conferencia.nome}</h1>
            <p className="mt-1 text-sm text-slate-500">
              Gerada por {conferencia.criada_por?.nome} em {formatarData(conferencia.criada_em)}
              {conferencia.realizada_em && <> · realizada em {formatarData(conferencia.realizada_em)}</>}
              {conferencia.concluida_por && <> · concluída por {conferencia.concluida_por.nome}</>}
            </p>
            <p className="mt-2 hidden text-sm print:block">Data da conferência: ____/____/______</p>
          </div>
          <div className="nao-imprimir flex flex-wrap gap-2">
            <button type="button" className="botao-secundario" onClick={() => window.print()}>
              <Printer className="size-4" /> Imprimir folha
            </button>
            {podeLancar && (
              <>
                <button type="button" className="botao border border-red-200 bg-white text-red-700 hover:bg-red-50" onClick={() => setCancelando(true)}>
                  <Ban className="size-4" /> Cancelar conferência
                </button>
                <button type="button" className="botao-primario" onClick={() => setConcluindo(true)}>
                  <CheckCircle2 className="size-4" /> Concluir conferência
                </button>
              </>
            )}
          </div>
        </div>

        {totais && (
          <div className="nao-imprimir mt-5 border-t border-slate-100 pt-4">
            <div className="mb-2 flex flex-wrap gap-4 text-sm">
              <strong className="text-emerald-700">{plural(totais.encontrados, 'encontrado', 'encontrados')}</strong>
              <strong className="text-red-700">{plural(totais.nao_localizados, 'não localizado', 'não localizados')}</strong>
              <strong className="text-amber-700">{plural(totais.pendentes, 'pendente', 'pendentes')}</strong>
              <span className="text-slate-500">de {plural(totais.itens, 'bem', 'bens')}</span>
            </div>
            <div className="flex h-2 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
              <div className="bg-emerald-500" style={{ width: `${(totais.encontrados / Math.max(totais.itens, 1)) * 100}%` }} />
              <div className="bg-red-500" style={{ width: `${(totais.nao_localizados / Math.max(totais.itens, 1)) * 100}%` }} />
            </div>
          </div>
        )}
      </section>

      {mensagem && (
        <p className="nao-imprimir mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
          {mensagem}
        </p>
      )}
      {erro && (
        <div className="nao-imprimir mb-4">
          <Aviso>{erro}</Aviso>
        </div>
      )}

      <section className="cartao nao-imprimir mb-4 flex flex-wrap items-end gap-3 p-4">
        <label className="min-w-56">
          <span className="rotulo">Sala</span>
          <select
            className="entrada"
            value={salaId}
            onChange={(e) => {
              setSalaId(e.target.value)
              setSelecionados(new Set())
            }}
          >
            <option value="">Todas as salas</option>
            {lista.dados?.salas.map((sala) => (
              <option key={sala.id} value={sala.id}>
                {sala.nome} ({sala.andar}) · {sala.pendentes ? plural(sala.pendentes, 'pendente', 'pendentes') : 'completa'}
              </option>
            ))}
          </select>
        </label>
        <label className="min-w-48">
          <span className="rotulo">Resultado</span>
          <select
            className="entrada"
            value={resultado}
            onChange={(e) => {
              setResultado(e.target.value)
              setSelecionados(new Set())
            }}
          >
            <option value="">Todos</option>
            <option value="pendente">Pendentes</option>
            <option value="encontrado">Encontrados</option>
            <option value="nao_localizado">Não localizados</option>
          </select>
        </label>
        {podeLancar && (
          <button type="button" className="botao-verde ml-auto" onClick={() => void marcarEncontrados()} disabled={ocupado || selecionados.size === 0}>
            <CheckCheck className="size-4" /> Marcar {selecionados.size || ''} selecionados como encontrados
          </button>
        )}
      </section>

      <section className="cartao overflow-hidden print:border-0 print:shadow-none">
        <div className="overflow-x-auto">
          <table className="tabela">
            <thead>
              <tr>
                <th className="w-10">
                  {podeLancar ? (
                    <input type="checkbox" className="nao-imprimir" checked={todosVisiveis} onChange={alternarTodos} aria-label="Selecionar os bens listados" />
                  ) : (
                    '(ok)'
                  )}
                </th>
                <th>Tombamento</th>
                <th>Tipo do bem</th>
                <th>Sala registrada</th>
                <th>Resultado</th>
                <th>Onde estava</th>
                <th>Estado visto</th>
                {podeLancar && <th className="nao-imprimir text-right">Lançar</th>}
              </tr>
            </thead>
            <tbody className={lista.carregando ? 'opacity-50' : ''}>
              {itens.map((item) => (
                <tr key={item.id} className="hover:bg-slate-50">
                  <td>
                    {podeLancar ? (
                      <input
                        type="checkbox"
                        className="nao-imprimir"
                        checked={selecionados.has(item.id)}
                        onChange={() =>
                          setSelecionados((atual) => {
                            const novo = new Set(atual)
                            if (novo.has(item.id)) novo.delete(item.id)
                            else novo.add(item.id)
                            return novo
                          })
                        }
                        aria-label={`Selecionar ${item.bem.numero_patrimonio ?? item.bem.codigo_interno}`}
                      />
                    ) : null}
                    <span className="hidden size-4 rounded border border-slate-400 print:inline-block" />
                  </td>
                  <td className="font-mono text-xs whitespace-nowrap">
                    <Link to={`/bens/${item.bem.id}`} className="font-semibold text-cead-800 hover:underline">
                      {item.bem.numero_patrimonio ?? 'sem patrimônio'}
                    </Link>
                    <span className="block text-slate-400">{item.bem.codigo_interno}</span>
                  </td>
                  <td className="font-medium">{item.bem.tipo_patrimonio?.nome}</td>
                  <td>
                    {item.bem.sala?.nome}
                    <span className="block text-xs text-slate-500">{item.bem.sala?.andar?.nome}</span>
                  </td>
                  <td>
                    <SeloResultado item={item} />
                  </td>
                  <td>
                    {podeLancar && item.resultado === 'encontrado' ? (
                      <select
                        className="entrada py-1 text-xs"
                        value={item.sala_encontrada_id ?? item.bem.sala_id}
                        onChange={(e) =>
                          void lancar(item, { resultado: 'encontrado', sala_encontrada_id: Number(e.target.value), estado_conservacao: item.estado_conservacao })
                        }
                        aria-label="Sala onde o bem estava"
                      >
                        {!salasAtivas.dados?.data.some((sala) => sala.id === item.bem.sala_id) && (
                          <option value={item.bem.sala_id}>{item.bem.sala?.nome}</option>
                        )}
                        {salasAtivas.dados?.data.map((sala) => (
                          <option key={sala.id} value={sala.id}>
                            {sala.nome}
                          </option>
                        ))}
                      </select>
                    ) : item.resultado === 'encontrado' && item.sala_encontrada_id !== item.bem.sala_id ? (
                      <span className="text-amber-700">{item.sala_encontrada?.nome}</span>
                    ) : (
                      <span className="text-slate-400">{item.resultado === 'encontrado' ? 'no lugar' : '—'}</span>
                    )}
                  </td>
                  <td>
                    {podeLancar && item.resultado === 'encontrado' ? (
                      <select
                        className="entrada py-1 text-xs"
                        value={item.estado_conservacao ?? ''}
                        onChange={(e) =>
                          void lancar(item, {
                            resultado: 'encontrado',
                            sala_encontrada_id: item.sala_encontrada_id,
                            estado_conservacao: (e.target.value || null) as EstadoConservacao | null,
                          })
                        }
                        aria-label="Estado de conservação visto"
                      >
                        <option value="">Sem mudança ({item.bem.estado_conservacao_rotulo})</option>
                        {(Object.keys(ROTULOS_CONSERVACAO) as EstadoConservacao[]).map((estado) => (
                          <option key={estado} value={estado}>
                            {ROTULOS_CONSERVACAO[estado]}
                          </option>
                        ))}
                      </select>
                    ) : (
                      <span className="text-slate-600">{item.estado_conservacao_rotulo ?? '—'}</span>
                    )}
                  </td>
                  {podeLancar && (
                    <td className="nao-imprimir text-right whitespace-nowrap">
                      {item.resultado !== 'encontrado' && (
                        <button
                          type="button"
                          className="inline-flex p-1.5 text-emerald-700 hover:text-emerald-900"
                          title="Encontrado na sala registrada"
                          onClick={() => void lancar(item, { resultado: 'encontrado' })}
                        >
                          <CheckCircle2 className="size-4" />
                        </button>
                      )}
                      {item.resultado !== 'nao_localizado' && (
                        <button
                          type="button"
                          className="inline-flex p-1.5 text-red-700 hover:text-red-900"
                          title="Não localizado"
                          onClick={() => setConfirmarNaoLocalizado(item)}
                        >
                          <SearchX className="size-4" />
                        </button>
                      )}
                      {item.resultado !== null && (
                        <button
                          type="button"
                          className="inline-flex p-1.5 text-slate-500 hover:text-slate-700"
                          title="Voltar a pendente"
                          onClick={() => void lancar(item, { resultado: null })}
                        >
                          <RotateCcw className="size-4" />
                        </button>
                      )}
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
          {lista.carregando && !lista.dados && <Carregando />}
          {lista.dados?.data.length === 0 && <Carregando texto="Nenhum bem com estes filtros." />}
        </div>
      </section>

      <footer className="mt-10 hidden grid-cols-2 gap-8 text-xs text-slate-500 print:grid">
        <p className="border-t border-slate-400 pt-2">Aplicador / Conferente</p>
        <p className="border-t border-slate-400 pt-2">Assinatura do responsável</p>
      </footer>

      {confirmarNaoLocalizado && (
        <Dialogo
          titulo={
            <>
              <CircleSlash className="size-5 text-red-600" /> Bem não localizado
            </>
          }
          aoFechar={() => setConfirmarNaoLocalizado(null)}
          rodape={
            <>
              <button type="button" className="botao-secundario" onClick={() => setConfirmarNaoLocalizado(null)}>
                Voltar
              </button>
              <button
                type="button"
                className="botao bg-red-700 text-white hover:bg-red-600"
                onClick={() => {
                  void lancar(confirmarNaoLocalizado, { resultado: 'nao_localizado' })
                  setConfirmarNaoLocalizado(null)
                }}
              >
                Confirmar: não localizado
              </button>
            </>
          }
        >
          <p>
            Confirma que o bem <strong className="text-slate-800">{confirmarNaoLocalizado.bem.numero_patrimonio ?? confirmarNaoLocalizado.bem.codigo_interno}</strong>{' '}
            ({confirmarNaoLocalizado.bem.tipo_patrimonio?.nome}, registrado em {confirmarNaoLocalizado.bem.sala?.nome}) não foi encontrado?
          </p>
          <p>
            Ao concluir a conferência, ele vira uma <strong className="text-red-700">pendência da gestora</strong>, que só fecha quando o bem
            aparecer ou com o número do processo administrativo.
          </p>
        </Dialogo>
      )}

      {concluindo && (
        <ConcluirConferencia
          conferencia={conferencia}
          aoFechar={() => setConcluindo(false)}
          aoConcluir={() => {
            setConcluindo(false)
            setMensagem('Conferência concluída. Os resultados foram aplicados aos bens.')
            recarregarTudo()
          }}
        />
      )}

      {cancelando && (
        <CancelarConferencia
          conferencia={conferencia}
          aoFechar={() => setCancelando(false)}
          aoCancelar={() => {
            setCancelando(false)
            setMensagem('Conferência cancelada. Nada mudou nos bens.')
            recarregarTudo()
          }}
        />
      )}
    </>
  )
}

function ConcluirConferencia({ conferencia, aoFechar, aoConcluir }: { conferencia: Conferencia; aoFechar: () => void; aoConcluir: () => void }) {
  const [data, setData] = useState(hojeIso())
  const [erro, setErro] = useState<ErroDaApi | null>(null)
  const [enviando, setEnviando] = useState(false)
  const totais = conferencia.totais

  async function concluir() {
    setEnviando(true)
    setErro(null)
    try {
      await apiPost(`/conferencias/${conferencia.id}/concluir`, { realizada_em: data })
      aoConcluir()
    } catch (e) {
      setErro(e instanceof ErroDaApi ? e : new ErroDaApi(0, 'Não foi possível concluir.'))
      setEnviando(false)
    }
  }

  return (
    <Dialogo
      titulo={
        <>
          <CheckCircle2 className="size-5 text-emerald-600" /> Concluir conferência
        </>
      }
      aoFechar={aoFechar}
      ocupado={enviando}
      rodape={
        <>
          <button type="button" className="botao-secundario" onClick={aoFechar} disabled={enviando}>
            Voltar
          </button>
          <button type="button" className="botao-primario" onClick={() => void concluir()} disabled={enviando || !data}>
            {enviando ? 'Concluindo...' : 'Concluir'}
          </button>
        </>
      }
    >
      {totais && (
        <p>
          {plural(totais.encontrados, 'encontrado', 'encontrados')}, {plural(totais.nao_localizados, 'não localizado', 'não localizados')} e{' '}
          {plural(totais.pendentes, 'pendente', 'pendentes')}.
        </p>
      )}
      <p>Ao concluir:</p>
      <ul className="list-disc space-y-1 pl-5">
        <li>o bem achado em outra sala muda de sala, e a mudança entra no histórico;</li>
        <li>o estado visto atualiza a conservação do bem;</li>
        <li>os não localizados viram pendências da gestora;</li>
        <li>a conferência não recebe mais resultados.</li>
      </ul>
      <label className="block">
        <span className="rotulo">Dia em que a conferência foi feita</span>
        <input type="date" className="entrada" max={hojeIso()} value={data} onChange={(e) => setData(e.target.value)} />
      </label>
      {erro && (
        <Aviso>
          {Object.values(erro.erros).flat().length > 1 ? (
            <ul className="list-disc pl-4">
              {Object.values(erro.erros)
                .flat()
                .map((mensagem) => (
                  <li key={mensagem}>{mensagem}</li>
                ))}
            </ul>
          ) : (
            erro.message
          )}
        </Aviso>
      )}
    </Dialogo>
  )
}

function CancelarConferencia({ conferencia, aoFechar, aoCancelar }: { conferencia: Conferencia; aoFechar: () => void; aoCancelar: () => void }) {
  const [erro, setErro] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)

  async function cancelar() {
    setEnviando(true)
    setErro(null)
    try {
      await apiPost(`/conferencias/${conferencia.id}/cancelar`)
      aoCancelar()
    } catch (e) {
      setErro(e instanceof ErroDaApi ? e.message : 'Não foi possível cancelar.')
      setEnviando(false)
    }
  }

  return (
    <Dialogo
      titulo={
        <>
          <Ban className="size-5 text-red-600" /> Cancelar conferência
        </>
      }
      aoFechar={aoFechar}
      ocupado={enviando}
      rodape={
        <>
          <button type="button" className="botao-secundario" onClick={aoFechar} disabled={enviando}>
            Voltar
          </button>
          <button type="button" className="botao bg-red-700 text-white hover:bg-red-600" onClick={() => void cancelar()} disabled={enviando}>
            {enviando ? 'Cancelando...' : 'Cancelar conferência'}
          </button>
        </>
      }
    >
      <p>
        Cancelar <strong className="text-slate-800">{conferencia.nome}</strong>? Nada muda nos bens, e os resultados lançados até agora ficam só no
        registro da conferência. Os bens poderão entrar numa conferência nova.
      </p>
      {erro && <Aviso>{erro}</Aviso>}
    </Dialogo>
  )
}
