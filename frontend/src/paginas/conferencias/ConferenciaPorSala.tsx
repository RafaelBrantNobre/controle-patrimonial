import {
  Titulo,
  ConfirmarEnvio,
  Cartao,
  Botao,
  RolagemTabela,
  Tabela,
  Selecao,
  Entrada,
  Dialogo,
  Aviso,
  Carregando,
  Selo,
  type CorSelo,
} from '../../components/ui'
import { ArrowLeft, ArrowRight, Ban, Check, CheckCheck, CheckCircle2, CircleSlash, Save } from 'lucide-react'
import { useState } from 'react'
import { Link, useParams } from 'react-router'
import { useAutenticacao } from '../../autenticacao/Autenticacao'
import { apiPost, apiPut, ErroDaApi } from '../../services/api'
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

type Uso = 'em_uso' | 'sem_uso'

// O que está na tela para um bem, antes de salvar a sala.
type Linha = { resultado: ResultadoConferencia | null; sala: string; estado: EstadoConservacao; uso: Uso }

// Ponto de partida da linha: o que já foi salvo nesta conferência ou, se
// ainda nada, a situação registrada do bem, como sugestão para confirmar.
function linhaDoItem(item: ItemDeConferencia): Linha {
  return {
    resultado: item.resultado,
    sala: String(item.sala_encontrada_id ?? item.sala_registrada_id ?? ''),
    estado: item.estado_conservacao ?? item.bem.estado_conservacao,
    uso: item.condicao_uso ?? (item.bem.status === 'sem_uso' ? 'sem_uso' : 'em_uso'),
  }
}

// Conferência sala a sala. Cada sala é uma etapa (os bens sem sala ficam na
// etapa "Sem localização") e a última etapa é a conclusão.
// Em cada sala a pessoa diz, para cada bem, se foi encontrado, em que sala
// estava, o estado de conservação e a condição de uso, e clica em "Salvar
// sala e ir para a próxima". O que foi salvo fica guardado: dá para parar e
// voltar depois, e a tela reabre na primeira sala que ainda tem pendentes.
// Os bens só mudam quando a conferência é concluída.
export function ConferenciaPorSala() {
  const { id } = useParams()
  const { usuario } = useAutenticacao()
  const [versao, setVersao] = useState(0)
  const [etapaEscolhida, setEtapaEscolhida] = useState<number | null>(null)
  const [alteradas, setAlteradas] = useState<Map<number, Linha>>(new Map())
  const [erros, setErros] = useState<string[]>([])
  const [mensagem, setMensagem] = useState<string | null>(null)
  const [salvando, setSalvando] = useState(false)
  const [confirmando, setConfirmando] = useState(false)
  const [cancelando, setCancelando] = useState(false)

  const resumo = useApi<ItensDaConferencia>(`/conferencias/${id}/itens?resumo=1`, versao)
  const salas = resumo.dados?.salas ?? []
  const conferencia = resumo.dados?.conferencia

  // Etapa de agora: a escolhida ou, ao abrir, a primeira sala com pendentes.
  // A etapa depois da última sala é a conclusão.
  const primeiraPendente = salas.findIndex((sala) => sala.pendentes > 0)
  const etapa = Math.min(etapaEscolhida ?? (primeiraPendente === -1 ? salas.length : primeiraPendente), salas.length)
  const sala = salas[etapa]

  const lista = useApi<ItensDaConferencia>(
    sala ? `/conferencias/${id}/itens${sala.id === null ? '?sem_sala=1' : `?sala_id=${sala.id}`}` : null,
    versao,
  )
  const salasAtivas = useApi<Pagina<Sala>>('/salas?ativo=1&por_pagina=100')

  if (resumo.erro && !resumo.dados) return <Aviso>{resumo.erro}</Aviso>
  if (!conferencia) return <Carregando />

  const podeLancar = Boolean(usuario?.pode_alterar && conferencia.situacao === 'aberta')
  const itens = lista.carregando ? [] : (lista.dados?.data ?? [])
  const linha = (item: ItemDeConferencia) => alteradas.get(item.id) ?? linhaDoItem(item)
  const temAlteracoes = alteradas.size > 0
  const totais = conferencia.totais

  function mudar(item: ItemDeConferencia, parte: Partial<Linha>) {
    setAlteradas((atual) => new Map(atual).set(item.id, { ...(atual.get(item.id) ?? linhaDoItem(item)), ...parte }))
  }

  function irPara(nova: number) {
    setAlteradas(new Map())
    setErros([])
    setEtapaEscolhida(nova)
    window.scrollTo({ top: 0 })
  }

  function marcarPendentes() {
    setAlteradas((atual) => {
      const novo = new Map(atual)
      for (const item of itens) {
        const agora = novo.get(item.id) ?? linhaDoItem(item)
        if (agora.resultado === null) novo.set(item.id, { ...agora, resultado: 'encontrado' })
      }
      return novo
    })
  }

  const novosNaoLocalizados = itens.filter((item) => linha(item).resultado === 'nao_localizado' && item.resultado !== 'nao_localizado')

  async function salvar() {
    setConfirmando(false)
    setSalvando(true)
    setErros([])
    setMensagem(null)
    try {
      await apiPut(`/conferencias/${id}/itens`, {
        itens: itens.map((item) => {
          const atual = linha(item)
          return atual.resultado === 'encontrado'
            ? {
                id: item.id,
                resultado: atual.resultado,
                sala_encontrada_id: atual.sala ? Number(atual.sala) : null,
                estado_conservacao: atual.estado,
                condicao_uso: atual.uso,
              }
            : { id: item.id, resultado: atual.resultado }
        }),
      })
      const semResultado = itens.filter((item) => linha(item).resultado === null).length
      setMensagem(
        `${sala?.nome}: sala salva.${semResultado ? ` ${plural(semResultado, 'bem ficou', 'bens ficaram')} sem resultado; volte a esta sala antes de concluir.` : ''}`,
      )
      setVersao((v) => v + 1)
      irPara(etapa + 1)
    } catch (e) {
      const mensagens = e instanceof ErroDaApi ? Object.values(e.erros).flat() : []
      setErros(mensagens.length ? [...new Set(mensagens)] : [e instanceof Error ? e.message : 'Não foi possível salvar a sala.'])
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } finally {
      setSalvando(false)
    }
  }

  return (
    <>
      <p className="text-xs text-slate-500">
        <Link to="/conferencias" className="hover:underline">
          Conferências
        </Link>{' '}
        / {conferencia.nome}
      </p>

      <Cartao as="section" className="mt-3 mb-4 p-5">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <Titulo className="flex flex-wrap items-center gap-2">
              {conferencia.nome} <Selo cor={COR_SITUACAO[conferencia.situacao]}>{conferencia.situacao_rotulo}</Selo>
            </Titulo>
            <p className="mt-1 text-sm text-slate-500">
              Iniciada por {conferencia.criada_por?.nome} em {formatarData(conferencia.criada_em)}
              {conferencia.realizada_em && <> · realizada em {formatarData(conferencia.realizada_em)}</>}
              {conferencia.concluida_por && <> · concluída por {conferencia.concluida_por.nome}</>}
            </p>
          </div>
          {podeLancar && (
            <Botao
              type="button"
              className="border border-red-200 bg-white text-red-700 hover:bg-red-50"
              onClick={() => setCancelando(true)}
            >
              <Ban className="size-4" /> Cancelar conferência
            </Botao>
          )}
        </div>
        {totais && (
          <div className="mt-4">
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
      </Cartao>

      <nav className="mb-4 flex flex-wrap gap-2" aria-label="Etapas da conferência">
        {salas.map((item, posicao) => (
          <Botao
            key={item.id ?? 'sem-sala'}
            type="button"
            onClick={() => irPara(posicao)}
            disabled={temAlteracoes || salvando}
            title={temAlteracoes ? 'Salve ou descarte as alterações desta sala antes de mudar de etapa.' : undefined}
            aria-current={posicao === etapa ? 'step' : undefined}
            className={`flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium disabled:cursor-not-allowed disabled:opacity-60 ${
              posicao === etapa
                ? 'border-cead-700 bg-cead-800 text-white'
                : 'border-slate-200 bg-white text-slate-700 hover:border-cead-600'
            }`}
          >
            <span className="opacity-70">{posicao + 1}.</span> {item.nome}
            {item.pendentes === 0 ? (
              <Check className={`size-3.5 ${posicao === etapa ? '' : 'text-emerald-600'}`} aria-label="completa" />
            ) : (
              <span className={`rounded-full px-1.5 ${posicao === etapa ? 'bg-white/20' : 'bg-amber-100 text-amber-800'}`}>
                {item.pendentes}
              </span>
            )}
          </Botao>
        ))}
        <Botao
          type="button"
          onClick={() => irPara(salas.length)}
          disabled={temAlteracoes || salvando}
          aria-current={etapa === salas.length ? 'step' : undefined}
          className={`flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium disabled:cursor-not-allowed disabled:opacity-60 ${
            etapa === salas.length
              ? 'border-cead-700 bg-cead-800 text-white'
              : 'border-slate-200 bg-white text-slate-700 hover:border-cead-600'
          }`}
        >
          <CheckCircle2 className="size-3.5" /> Conclusão
        </Botao>
      </nav>

      {mensagem && (
        <p className="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
          {mensagem}
        </p>
      )}
      {erros.length > 0 && (
        <div className="mb-4">
          <Aviso>
            {erros.length === 1 ? (
              erros[0]
            ) : (
              <ul className="list-disc pl-4">
                {erros.map((erro) => (
                  <li key={erro}>{erro}</li>
                ))}
              </ul>
            )}
          </Aviso>
        </div>
      )}

      {sala ? (
        <Cartao as="section" className="overflow-hidden">
          <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
            <div>
              <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                Etapa {etapa + 1} de {salas.length + 1}
              </p>
              <h2 className="text-lg font-bold text-cead-900">{sala.nome}</h2>
              <p className="text-xs text-slate-500">
                {plural(sala.total, 'bem', 'bens')} ·{' '}
                {sala.pendentes ? plural(sala.pendentes, 'pendente', 'pendentes') : 'todos com resultado'}
                {sala.id === null && ' · informe a sala onde cada bem foi encontrado'}
              </p>
            </div>
            {podeLancar && (
              <Botao
                variante="sucesso"
                type="button"
                onClick={marcarPendentes}
                disabled={salvando || itens.every((item) => linha(item).resultado !== null)}
              >
                <CheckCheck className="size-4" /> Marcar os pendentes como encontrados
              </Botao>
            )}
          </div>

          {lista.erro && (
            <div className="p-4">
              <Aviso>{lista.erro}</Aviso>
            </div>
          )}
          {lista.carregando && <Carregando texto="Carregando os bens da sala..." />}

          {itens.length > 0 && (
            <RolagemTabela>
              <Tabela>
                <thead>
                  <tr>
                    <th>Tombamento</th>
                    <th>Bem</th>
                    <th>Resultado</th>
                    <th>Localização</th>
                    <th>Conservação</th>
                    <th>Uso</th>
                  </tr>
                </thead>
                <tbody>
                  {itens.map((item) => {
                    const atual = linha(item)
                    const encontrado = atual.resultado === 'encontrado'
                    const mudouDeSala = encontrado && item.sala_registrada_id !== null && atual.sala !== String(item.sala_registrada_id)
                    return (
                      <tr
                        key={item.id}
                        className={atual.resultado === 'nao_localizado' ? 'bg-red-50/60' : atual.resultado === null ? 'bg-amber-50/40' : ''}
                      >
                        <td className="font-mono text-xs whitespace-nowrap">
                          <Link to={`/bens/${item.bem.id}`} className="font-semibold text-cead-800 hover:underline">
                            {item.bem.numero_patrimonio ?? 'sem patrimônio'}
                          </Link>
                          <span className="block text-slate-400">{item.bem.codigo_interno}</span>
                        </td>
                        <td>
                          <span className="font-medium text-slate-800">{item.bem.tipo_patrimonio?.nome}</span>
                          {item.bem.descricao && <span className="block text-xs text-slate-500">{item.bem.descricao}</span>}
                        </td>
                        <td className="whitespace-nowrap">
                          {podeLancar ? (
                            <div className="inline-flex overflow-hidden rounded-lg border border-slate-200 text-xs font-medium">
                              <Botao
                                type="button"
                                aria-pressed={encontrado}
                                onClick={() => mudar(item, { resultado: encontrado ? null : 'encontrado' })}
                                className={`px-2.5 py-1.5 ${encontrado ? 'bg-emerald-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'}`}
                              >
                                Encontrado
                              </Botao>
                              <Botao
                                type="button"
                                aria-pressed={atual.resultado === 'nao_localizado'}
                                onClick={() => mudar(item, { resultado: atual.resultado === 'nao_localizado' ? null : 'nao_localizado' })}
                                className={`border-l border-slate-200 px-2.5 py-1.5 ${
                                  atual.resultado === 'nao_localizado'
                                    ? 'bg-red-600 text-white'
                                    : 'bg-white text-slate-600 hover:bg-slate-50'
                                }`}
                              >
                                Não localizado
                              </Botao>
                            </div>
                          ) : (
                            <Selo cor={encontrado ? 'verde' : atual.resultado === 'nao_localizado' ? 'vermelho' : 'amarelo'}>
                              {encontrado ? 'Encontrado' : atual.resultado === 'nao_localizado' ? 'Não localizado' : 'Pendente'}
                            </Selo>
                          )}
                        </td>
                        <td>
                          {podeLancar ? (
                            <Selecao
                              className={`entrada py-1 text-xs ${mudouDeSala ? 'border-amber-400 bg-amber-50' : ''}`}
                              value={atual.sala}
                              disabled={!encontrado}
                              onChange={(e) => mudar(item, { sala: e.target.value })}
                              aria-label="Sala onde o bem está"
                            >
                              {item.sala_registrada_id === null && <option value="">Escolha a sala...</option>}
                              {/* A sala lançada ou registrada aparece mesmo se foi inativada depois. */}
                              {[item.sala_registrada, item.sala_encontrada]
                                .filter((opcao): opcao is Sala => Boolean(opcao))
                                .filter((opcao, posicao, todas) => todas.findIndex((outra) => outra.id === opcao.id) === posicao)
                                .filter((opcao) => !salasAtivas.dados?.data.some((ativa) => ativa.id === opcao.id))
                                .map((opcao) => (
                                  <option key={opcao.id} value={opcao.id}>
                                    {opcao.nome} (inativa)
                                  </option>
                                ))}
                              {salasAtivas.dados?.data.map((opcao) => (
                                <option key={opcao.id} value={opcao.id}>
                                  {opcao.nome}
                                </option>
                              ))}
                            </Selecao>
                          ) : (
                            <span className={mudouDeSala ? 'text-amber-700' : 'text-slate-600'}>
                              {encontrado ? (item.sala_encontrada?.nome ?? '—') : '—'}
                            </span>
                          )}
                        </td>
                        <td>
                          {podeLancar ? (
                            <Selecao
                              className="py-1 text-xs"
                              value={atual.estado}
                              disabled={!encontrado}
                              onChange={(e) => mudar(item, { estado: e.target.value as EstadoConservacao })}
                              aria-label="Estado de conservação"
                            >
                              {(Object.keys(ROTULOS_CONSERVACAO) as EstadoConservacao[]).map((estado) => (
                                <option key={estado} value={estado}>
                                  {ROTULOS_CONSERVACAO[estado]}
                                </option>
                              ))}
                            </Selecao>
                          ) : (
                            <span className="text-slate-600">{item.estado_conservacao_rotulo ?? '—'}</span>
                          )}
                        </td>
                        <td>
                          {podeLancar ? (
                            <Selecao
                              className="py-1 text-xs"
                              value={atual.uso}
                              disabled={!encontrado}
                              onChange={(e) => mudar(item, { uso: e.target.value as Uso })}
                              aria-label="Condição de uso"
                            >
                              <option value="em_uso">Em uso</option>
                              <option value="sem_uso">Sem uso</option>
                            </Selecao>
                          ) : (
                            <span className="text-slate-600">{item.condicao_uso_rotulo ?? '—'}</span>
                          )}
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </Tabela>
            </RolagemTabela>
          )}

          <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3">
            <Botao
              variante="secundario"
              type="button"
              onClick={() => irPara(etapa - 1)}
              disabled={etapa === 0 || temAlteracoes || salvando}
            >
              <ArrowLeft className="size-4" /> Sala anterior
            </Botao>
            <div className="flex flex-wrap gap-2">
              {podeLancar && temAlteracoes && (
                <Botao
                  variante="simples"
                  type="button"
                  className="text-slate-500 hover:text-slate-700"
                  onClick={() => setAlteradas(new Map())}
                  disabled={salvando}
                >
                  Descartar alterações
                </Botao>
              )}
              {podeLancar ? (
                <Botao
                  variante="primario"
                  type="button"

                  onClick={() => (novosNaoLocalizados.length ? setConfirmando(true) : void salvar())}
                  disabled={salvando || lista.carregando}
                >
                  <Save className="size-4" /> {salvando ? 'Salvando...' : 'Salvar sala e ir para a próxima'}{' '}
                  <ArrowRight className="size-4" />
                </Botao>
              ) : (
                <Botao variante="secundario" type="button" onClick={() => irPara(etapa + 1)}>
                  Próxima <ArrowRight className="size-4" />
                </Botao>
              )}
            </div>
          </div>
        </Cartao>
      ) : (
        <Conclusao
          conferencia={conferencia}
          salas={salas}
          podeLancar={podeLancar}
          aoIrParaSala={irPara}
          aoConcluir={() => {
            setMensagem('Conferência concluída. Os resultados foram aplicados aos bens.')
            setVersao((v) => v + 1)
          }}
        />
      )}

      {confirmando && (
        <ConfirmarEnvio
          titulo={
            <>
              <CircleSlash className="size-5 text-red-600" /> Bens não localizados
            </>
          }
          aoFechar={() => setConfirmando(false)}
          aoConfirmar={() => void salvar()}
          confirmar="Confirmar e salvar a sala"
          variante="perigo"
        >
          <p>
            Você marcou {plural(novosNaoLocalizados.length, 'bem como não localizado', 'bens como não localizados')} em{' '}
            <strong className="text-slate-800">{sala?.nome}</strong>:
          </p>
          <ul className="max-h-40 list-disc overflow-y-auto pl-5">
            {novosNaoLocalizados.map((item) => (
              <li key={item.id}>
                {item.bem.numero_patrimonio ?? item.bem.codigo_interno} · {item.bem.tipo_patrimonio?.nome}
              </li>
            ))}
          </ul>
          <p>
            Ao concluir a conferência, cada um vira uma <strong className="text-red-700">pendência da gestora</strong>, que só fecha quando
            o bem aparecer ou com o número do processo administrativo.
          </p>
        </ConfirmarEnvio>
      )}

      {cancelando && (
        <CancelarConferencia
          conferencia={conferencia}
          aoFechar={() => setCancelando(false)}
          aoCancelar={() => {
            setCancelando(false)
            setMensagem('Conferência cancelada. Nada mudou nos bens.')
            setAlteradas(new Map())
            setVersao((v) => v + 1)
          }}
        />
      )}
    </>
  )
}

function Conclusao({
  conferencia,
  salas,
  podeLancar,
  aoIrParaSala,
  aoConcluir,
}: {
  conferencia: Conferencia
  salas: ItensDaConferencia['salas']
  podeLancar: boolean
  aoIrParaSala: (posicao: number) => void
  aoConcluir: () => void
}) {
  const [data, setData] = useState(hojeIso())
  const [erros, setErros] = useState<string[]>([])
  const [enviando, setEnviando] = useState(false)
  const comPendentes = salas.map((sala, posicao) => ({ sala, posicao })).filter(({ sala }) => sala.pendentes > 0)

  async function concluir() {
    setEnviando(true)
    setErros([])
    try {
      await apiPost(`/conferencias/${conferencia.id}/concluir`, { realizada_em: data })
      aoConcluir()
    } catch (e) {
      const mensagens = e instanceof ErroDaApi ? Object.values(e.erros).flat() : []
      setErros(mensagens.length ? mensagens : [e instanceof Error ? e.message : 'Não foi possível concluir.'])
    } finally {
      setEnviando(false)
    }
  }

  return (
    <Cartao as="section" className="space-y-4 p-6">
      <h2 className="flex items-center gap-2 text-lg font-bold text-cead-900">
        <CheckCircle2 className="size-5 text-emerald-600" /> Conclusão
      </h2>

      {conferencia.situacao === 'concluida' && (
        <p className="text-sm text-slate-600">
          Conferência concluída em {formatarData(conferencia.concluida_em)}. Os resultados já foram aplicados aos bens, e as salas acima
          mostram o que foi registrado.
        </p>
      )}
      {conferencia.situacao === 'cancelada' && <p className="text-sm text-slate-600">Conferência cancelada. Nada mudou nos bens.</p>}

      {conferencia.situacao === 'aberta' && (
        <>
          {comPendentes.length > 0 ? (
            <Aviso tipo="info">
              Ainda há salas com bens sem resultado. Volte a cada uma antes de concluir:
              <span className="mt-2 flex flex-wrap gap-2">
                {comPendentes.map(({ sala, posicao }) => (
                  <Botao
                    key={sala.id ?? 'sem-sala'}
                    type="button"
                    className="rounded-md border border-amber-300 bg-white px-2 py-1 text-xs font-medium text-amber-900 hover:bg-amber-100"
                    onClick={() => aoIrParaSala(posicao)}
                  >
                    {sala.nome} ({sala.pendentes})
                  </Botao>
                ))}
              </span>
            </Aviso>
          ) : (
            <p className="text-sm text-slate-600">Todas as salas foram conferidas. Ao concluir:</p>
          )}
          <ul className="list-disc space-y-1 pl-5 text-sm text-slate-600">
            <li>o bem achado em outra sala muda de sala, e a mudança entra no histórico dele;</li>
            <li>o estado de conservação e a condição de uso informados passam a valer;</li>
            <li>os não localizados viram pendências da gestora;</li>
            <li>a conferência não recebe mais alterações.</li>
          </ul>
          {podeLancar && (
            <div className="flex flex-wrap items-end gap-3">
              <label>
                <span className="rotulo">Dia em que a conferência foi feita</span>
                <Entrada type="date" max={hojeIso()} value={data} onChange={(e) => setData(e.target.value)} />
              </label>
              <Botao
                variante="primario"
                type="button"
                onClick={() => void concluir()}
                disabled={enviando || !data || comPendentes.length > 0}
              >
                <CheckCircle2 className="size-4" /> {enviando ? 'Concluindo...' : 'Concluir conferência'}
              </Botao>
            </div>
          )}
          {erros.length > 0 && (
            <Aviso>
              <ul className="list-disc pl-4">
                {erros.map((erro) => (
                  <li key={erro}>{erro}</li>
                ))}
              </ul>
            </Aviso>
          )}
        </>
      )}
    </Cartao>
  )
}

function CancelarConferencia({
  conferencia,
  aoFechar,
  aoCancelar,
}: {
  conferencia: Conferencia
  aoFechar: () => void
  aoCancelar: () => void
}) {
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
          <Botao variante="secundario" type="button" onClick={aoFechar} disabled={enviando}>
            Voltar
          </Botao>
          <Botao variante="perigo" type="button" onClick={() => void cancelar()} disabled={enviando}>
            {enviando ? 'Cancelando...' : 'Cancelar conferência'}
          </Botao>
        </>
      }
    >
      <p>
        Cancelar <strong className="text-slate-800">{conferencia.nome}</strong>? Nada muda nos bens, e o que foi registrado até agora fica
        só no histórico da conferência. Depois disso, dá para iniciar uma conferência nova.
      </p>
      {erro && <Aviso>{erro}</Aviso>}
    </Dialogo>
  )
}
