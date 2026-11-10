import { ArrowRightLeft, CircleSlash, ClipboardCheck, FilePlus2, FileText, History, MapPin, Pencil, RefreshCw, Wrench } from 'lucide-react'
import { useState, type FormEvent, type ReactNode } from 'react'
import { Link, useLocation, useParams, useSearchParams } from 'react-router'
import { useAutenticacao } from '../../autenticacao/Autenticacao'
import { Aviso, Campo, Carregando, SeloConferencia, SeloConservacao, SeloStatus } from '../../componentes/ui'
import { ResolverPendencia } from '../conferencias/ResolverPendencia'
import { apiPatch, consulta, ErroDaApi } from '../../services/api'
import { formatarData, hojeIso } from '../../services/formatos'
import { ROTULOS_CONSERVACAO, type Andar, type Bem, type EstadoConservacao, type Pagina, type Sala } from '../../services/tipos'
import { useApi } from '../../services/useApi'

// Ficha do bem (RF15). Não há wireframe próprio: segue o padrão das outras
// telas. "Registrar ocorrência" reúne o que pode acontecer com o bem (mudou
// de sala, de estado de conservação, de condição de uso, ou foi baixado),
// e só o administrador registra. Cada registro entra no histórico de
// ocorrências, com a data e quem registrou. Com ?ocorrencia=1 na URL, a
// ficha já abre perguntando o tipo da ocorrência.
export function FichaDoBem() {
  const { id } = useParams()
  const { usuario } = useAutenticacao()
  const location = useLocation()
  const [versao, setVersao] = useState(0)
  const [mensagem, setMensagem] = useState<string | null>((location.state as { mensagem?: string } | null)?.mensagem ?? null)
  const [parametros] = useSearchParams()
  const [acao, setAcao] = useState<'escolher' | 'sala' | 'conservacao' | 'status' | 'baixa' | null>(parametros.get('ocorrencia') ? 'escolher' : null)

  const resposta = useApi<{ data: Bem }>(`/bens/${id}`, versao)

  if (resposta.carregando && !resposta.dados) return <Carregando />
  if (resposta.erro) return <Aviso>{resposta.erro}</Aviso>
  if (!resposta.dados) return null

  const bem = resposta.dados.data

  function concluir(texto: string) {
    setAcao(null)
    setMensagem(texto)
    setVersao((v) => v + 1)
  }

  return (
    <>
      <p className="text-xs text-slate-500">
        <Link to="/bens" className="hover:underline">
          Bens
        </Link>{' '}
        / Ficha do bem
      </p>
      <div className="mt-1 mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-cead-900">{bem.tipo_patrimonio?.nome}</h1>
          <p className="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500">
            <span className="font-mono font-semibold text-cead-800">{bem.numero_patrimonio ?? 'sem patrimônio'}</span>·
            <span className="font-mono">{bem.codigo_interno}</span>
            <SeloStatus status={bem.status} rotulo={bem.status_rotulo} />
          </p>
        </div>
        {usuario?.pode_alterar && (
          <div className="flex flex-wrap gap-2">
            {!bem.baixado && (
              <Link to={`/bens/${bem.id}/editar`} className="botao-secundario">
                <Pencil className="size-4" /> Editar
              </Link>
            )}
            <button type="button" className="botao-primario" onClick={() => setAcao('escolher')}>
              <FilePlus2 className="size-4" /> Registrar ocorrência
            </button>
          </div>
        )}
      </div>

      {mensagem && (
        <p className="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
          {mensagem}
        </p>
      )}
      {bem.baixado && (
        <div className="mb-4">
          <Aviso tipo="info">
            Bem baixado em {formatarData(bem.baixa_data)}
            {bem.baixa_observacao && <> ({bem.baixa_observacao})</>}. Ele continua aparecendo nas listas (RN05).
          </Aviso>
        </div>
      )}

      {usuario?.pode_alterar && acao === 'escolher' && (
        <section className="cartao mb-6 space-y-4 border-cead-100 p-6">
          <div>
            <h2 className="font-semibold text-cead-900">Registrar ocorrência</h2>
            <p className="text-sm text-slate-500">O que aconteceu com este bem?</p>
          </div>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {!bem.baixado && (
              <button type="button" className="botao-secundario justify-start" onClick={() => setAcao('sala')}>
                <ArrowRightLeft className="size-4" /> Mudou de sala
              </button>
            )}
            {!bem.baixado && (
              <button type="button" className="botao-secundario justify-start" onClick={() => setAcao('conservacao')}>
                <Wrench className="size-4" /> Mudou a conservação
              </button>
            )}
            <button type="button" className="botao-secundario justify-start" onClick={() => setAcao('status')}>
              <RefreshCw className="size-4" /> {bem.baixado ? 'Desfazer a baixa' : 'Mudou a condição de uso'}
            </button>
            {!bem.baixado && (
              <button type="button" className="botao justify-start border border-red-200 bg-white text-red-700 hover:bg-red-50" onClick={() => setAcao('baixa')}>
                <CircleSlash className="size-4" /> Baixa
              </button>
            )}
          </div>
          <div className="flex justify-end">
            <button type="button" className="botao-secundario" onClick={() => setAcao(null)}>
              Cancelar
            </button>
          </div>
        </section>
      )}
      {acao === 'conservacao' && <MudarConservacao bem={bem} aoCancelar={() => setAcao(null)} aoConcluir={concluir} />}
      {acao === 'sala' && <MudarSala bem={bem} aoCancelar={() => setAcao(null)} aoConcluir={concluir} />}
      {acao === 'status' && <MudarStatus bem={bem} aoCancelar={() => setAcao(null)} aoConcluir={concluir} />}
      {acao === 'baixa' && <DarBaixa bem={bem} aoCancelar={() => setAcao(null)} aoConcluir={concluir} />}

      <div className="grid gap-6 lg:grid-cols-3">
        <section className="cartao p-6 lg:col-span-2">
          <h2 className="mb-4 font-semibold text-cead-900">Dados do bem</h2>
          <dl className="grid gap-x-6 gap-y-4 sm:grid-cols-3">
            <Dado rotulo="Tombamento">{bem.numero_patrimonio ?? 'sem patrimônio'}</Dado>
            <Dado rotulo="Código interno">{bem.codigo_interno}</Dado>
            <Dado rotulo="Tipo">{bem.tipo_patrimonio?.nome}</Dado>
            <Dado rotulo="Descrição">{bem.descricao}</Dado>
            <Dado rotulo="Marca">{bem.marca}</Dado>
            <Dado rotulo="Origem">{bem.origem}</Dado>
            <Dado rotulo="Quantidade">{bem.quantidade}</Dado>
            <Dado rotulo="Sala">
              <Link to={`/ambientes/${bem.sala_id}`} className="text-cead-700 hover:underline">
                {bem.sala?.nome}
              </Link>
            </Dado>
            <Dado rotulo="Andar">{bem.sala?.andar?.nome}</Dado>
            <Dado rotulo="Conservação">
              <SeloConservacao estado={bem.estado_conservacao} rotulo={bem.estado_conservacao_rotulo} />
            </Dado>
          </dl>
        </section>

        <section className="cartao p-6">
          <h2 className="mb-4 flex items-center gap-2 font-semibold text-cead-900">
            <History className="size-4" /> Última mudança
          </h2>
          <dl className="space-y-3">
            <Dado rotulo="Data">{formatarData(bem.data_ultima_mudanca)}</Dado>
            <Dado rotulo="Registrada por">{bem.usuario_ultima_mudanca?.nome}</Dado>
            <Dado rotulo="Última mudança de sala">
              {bem.ultima_movimentacao
                ? `${bem.ultima_movimentacao.sala_origem?.nome ?? 'sem sala de origem'} → ${bem.ultima_movimentacao.sala_destino?.nome}, em ${formatarData(bem.ultima_movimentacao.data)}`
                : 'nunca mudou de sala'}
            </Dado>
            <Dado rotulo="Cadastrado em">{formatarData(bem.cadastrado_em)}</Dado>
          </dl>
        </section>
      </div>

      <ConferenciaDoBem bem={bem} podeResolver={Boolean(usuario?.pode_alterar)} aoResolver={() => concluir('Pendência resolvida.')} />

      <section className="cartao mt-6 p-6">
        <h2 className="mb-4 flex items-center gap-2 font-semibold text-cead-900">
          <History className="size-4" /> Histórico de ocorrências
        </h2>
        {(bem.ocorrencias ?? []).length === 0 ? (
          <p className="text-sm text-slate-500">Nenhuma ocorrência registrada para este bem.</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="tabela">
              <thead>
                <tr>
                  <th>Data</th>
                  <th>Ocorrência</th>
                  <th>De</th>
                  <th>Para</th>
                  <th>Registrada por</th>
                  <th>Origem</th>
                </tr>
              </thead>
              <tbody>
                {bem.ocorrencias?.map((ocorrencia) => (
                  <tr key={ocorrencia.id}>
                    <td className="whitespace-nowrap">{formatarData(ocorrencia.data)}</td>
                    <td>
                      {ocorrencia.tipo_rotulo}
                      {ocorrencia.observacao && <span className="block text-xs text-slate-500">{ocorrencia.observacao}</span>}
                    </td>
                    <td className="text-slate-600">{ocorrencia.valor_anterior ?? '—'}</td>
                    <td className="font-medium text-slate-800">{ocorrencia.valor_novo ?? '—'}</td>
                    <td className="text-slate-600">{ocorrencia.usuario}</td>
                    <td>
                      {ocorrencia.conferencia ? (
                        <Link to={`/conferencias/${ocorrencia.conferencia.id}`} className="text-cead-700 hover:underline">
                          {ocorrencia.conferencia.nome}
                        </Link>
                      ) : (
                        <span className="text-slate-500">Registro manual</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </>
  )
}

// Conferência do bem: a situação de agora, a pendência (se ele não foi
// localizado) e o histórico das conferências concluídas.
function ConferenciaDoBem({ bem, podeResolver, aoResolver }: { bem: Bem; podeResolver: boolean; aoResolver: () => void }) {
  const [resolvendo, setResolvendo] = useState<'localizado' | 'processo_administrativo' | null>(null)
  const historico = bem.historico_conferencias ?? []

  return (
    <section className="cartao mt-6 p-6">
      <h2 className="mb-4 flex flex-wrap items-center gap-2 font-semibold text-cead-900">
        <ClipboardCheck className="size-4" /> Conferência <SeloConferencia bem={bem} />
      </h2>

      {bem.em_conferencia && (
        <p className="mb-4 text-sm text-slate-600">
          Está na conferência aberta{' '}
          <Link to={`/conferencias/${bem.em_conferencia.conferencia_id}`} className="font-semibold text-cead-700 hover:underline">
            {bem.em_conferencia.nome}
          </Link>
          .
        </p>
      )}

      {bem.pendencia && (
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          <p>
            <strong>Não localizado desde {formatarData(bem.pendencia.desde)}</strong>, na conferência {bem.pendencia.conferencia}. A pendência fecha quando
            o bem aparecer ou com o número do processo administrativo.
          </p>
          {podeResolver && (
            <div className="flex gap-2">
              <button type="button" className="botao-secundario px-3 py-1 text-xs" onClick={() => setResolvendo('localizado')}>
                <MapPin className="size-3.5" /> Localizado
              </button>
              <button type="button" className="botao-secundario px-3 py-1 text-xs" onClick={() => setResolvendo('processo_administrativo')}>
                <FileText className="size-3.5" /> Processo
              </button>
            </div>
          )}
        </div>
      )}

      {historico.length === 0 ? (
        <p className="text-sm text-slate-500">Este bem ainda não entrou numa conferência concluída.</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="tabela">
            <thead>
              <tr>
                <th>Conferência</th>
                <th>Realizada em</th>
                <th>Resultado</th>
                <th>Pendência</th>
              </tr>
            </thead>
            <tbody>
              {historico.map((item) => (
                <tr key={item.conferencia_id}>
                  <td>
                    <Link to={`/conferencias/${item.conferencia_id}`} className="text-cead-700 hover:underline">
                      {item.conferencia}
                    </Link>
                  </td>
                  <td>{formatarData(item.realizada_em)}</td>
                  <td>{item.resultado_rotulo}</td>
                  <td className="text-slate-600">
                    {item.resolucao_rotulo
                      ? `${item.resolucao_rotulo}${item.processo_numero ? ` nº ${item.processo_numero}` : ''}`
                      : item.resultado === 'nao_localizado'
                        ? 'aberta'
                        : '—'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {resolvendo && (
        <ResolverPendencia
          bem={bem}
          jeito={resolvendo}
          aoFechar={() => setResolvendo(null)}
          aoResolver={() => {
            setResolvendo(null)
            aoResolver()
          }}
        />
      )}
    </section>
  )
}

function Dado({ rotulo, children }: { rotulo: string; children: ReactNode }) {
  return (
    <div>
      <dt className="text-xs font-semibold tracking-wide text-slate-500 uppercase">{rotulo}</dt>
      <dd className="mt-0.5 text-sm text-slate-800">{children || '—'}</dd>
    </div>
  )
}

type PropsDaAcao = { bem: Bem; aoCancelar: () => void; aoConcluir: (mensagem: string) => void }

// Moldura comum das três operações: envia, mostra o erro da API e conclui.
function PainelDeAcao({
  titulo,
  textoBotao,
  enviar,
  aoCancelar,
  children,
}: {
  titulo: string
  textoBotao: string
  enviar: () => Promise<void>
  aoCancelar: () => void
  children: (erro: ErroDaApi | null) => ReactNode
}) {
  const [erro, setErro] = useState<ErroDaApi | null>(null)
  const [enviando, setEnviando] = useState(false)

  async function aoEnviar(evento: FormEvent) {
    evento.preventDefault()
    setEnviando(true)
    setErro(null)
    try {
      await enviar()
    } catch (e) {
      setErro(e instanceof ErroDaApi ? e : new ErroDaApi(0, 'Não foi possível salvar.'))
      setEnviando(false)
    }
  }

  return (
    <form onSubmit={aoEnviar} className="cartao mb-6 space-y-4 border-cead-100 p-6">
      <h2 className="font-semibold text-cead-900">{titulo}</h2>
      {erro && <Aviso>{erro.message}</Aviso>}
      {children(erro)}
      <div className="flex justify-end gap-2">
        <button type="button" className="botao-secundario" onClick={aoCancelar}>
          Cancelar
        </button>
        <button type="submit" className="botao-primario" disabled={enviando}>
          {enviando ? 'Salvando...' : textoBotao}
        </button>
      </div>
    </form>
  )
}

function MudarSala({ bem, aoCancelar, aoConcluir }: PropsDaAcao) {
  const [andarId, setAndarId] = useState(String(bem.sala?.andar_id ?? ''))
  const [salaId, setSalaId] = useState('')
  const andares = useApi<Pagina<Andar>>('/andares?ativo=1&por_pagina=100')
  const salas = useApi<Pagina<Sala>>(andarId ? `/salas${consulta({ andar_id: andarId, ativo: true, por_pagina: 100 })}` : null)

  return (
    <PainelDeAcao
      titulo="Mudar de sala"
      textoBotao="Confirmar mudança"
      aoCancelar={aoCancelar}
      enviar={async () => {
        const resposta = await apiPatch<{ data: Bem }>(`/bens/${bem.id}/sala`, { sala_id: Number(salaId) || null })
        aoConcluir(`Bem movido para ${resposta.data.sala?.nome}.`)
      }}
    >
      {(erro) => (
        <div className="grid gap-4 sm:grid-cols-2">
          <Campo rotulo="Andar de destino">
            <select
              className="entrada"
              value={andarId}
              onChange={(e) => {
                setAndarId(e.target.value)
                setSalaId('')
              }}
            >
              <option value="">Selecione o andar...</option>
              {andares.dados?.data.map((andar) => (
                <option key={andar.id} value={andar.id}>
                  {andar.nome}
                </option>
              ))}
            </select>
          </Campo>
          <Campo rotulo="Sala de destino" obrigatorio erro={erro?.doCampo('sala_id')}>
            <select className="entrada" value={salaId} onChange={(e) => setSalaId(e.target.value)} required>
              <option value="">Selecione a sala...</option>
              {salas.dados?.data
                .filter((sala) => sala.id !== bem.sala_id)
                .map((sala) => (
                  <option key={sala.id} value={sala.id}>
                    {sala.nome}
                  </option>
                ))}
            </select>
          </Campo>
        </div>
      )}
    </PainelDeAcao>
  )
}

function MudarConservacao({ bem, aoCancelar, aoConcluir }: PropsDaAcao) {
  const opcoes = (Object.keys(ROTULOS_CONSERVACAO) as EstadoConservacao[]).filter((estado) => estado !== bem.estado_conservacao)
  const [estado, setEstado] = useState<EstadoConservacao>(opcoes[0])

  return (
    <PainelDeAcao
      titulo="Mudar o estado de conservação"
      textoBotao="Confirmar"
      aoCancelar={aoCancelar}
      enviar={async () => {
        const resposta = await apiPatch<{ data: Bem }>(`/bens/${bem.id}`, { estado_conservacao: estado })
        aoConcluir(`Conservação alterada para ${resposta.data.estado_conservacao_rotulo.toLowerCase()}.`)
      }}
    >
      {(erro) => (
        <Campo rotulo={`Novo estado (hoje: ${bem.estado_conservacao_rotulo})`} erro={erro?.doCampo('estado_conservacao')}>
          <select className="entrada" value={estado} onChange={(e) => setEstado(e.target.value as EstadoConservacao)}>
            {opcoes.map((opcao) => (
              <option key={opcao} value={opcao}>
                {ROTULOS_CONSERVACAO[opcao]}
              </option>
            ))}
          </select>
        </Campo>
      )}
    </PainelDeAcao>
  )
}

function MudarStatus({ bem, aoCancelar, aoConcluir }: PropsDaAcao) {
  const opcoes = (['em_uso', 'sem_uso'] as const).filter((status) => status !== bem.status)
  const [status, setStatus] = useState<string>(opcoes[0])

  return (
    <PainelDeAcao
      titulo={bem.baixado ? 'Desfazer a baixa' : 'Mudar a condição de uso'}
      textoBotao="Confirmar"
      aoCancelar={aoCancelar}
      enviar={async () => {
        const resposta = await apiPatch<{ data: Bem }>(`/bens/${bem.id}/status`, { status })
        aoConcluir(`Situação alterada para ${resposta.data.status_rotulo.toLowerCase()}.`)
      }}
    >
      {(erro) => (
        <>
          {bem.baixado && (
            <p className="text-sm text-slate-600">Os dados da baixa serão apagados, e a mudança fica registrada com data e usuário.</p>
          )}
          <Campo rotulo="Nova situação" erro={erro?.doCampo('status')}>
            <select className="entrada" value={status} onChange={(e) => setStatus(e.target.value)}>
              {opcoes.map((opcao) => (
                <option key={opcao} value={opcao}>
                  {opcao === 'em_uso' ? 'Em uso' : 'Sem uso'}
                </option>
              ))}
            </select>
          </Campo>
        </>
      )}
    </PainelDeAcao>
  )
}

function DarBaixa({ bem, aoCancelar, aoConcluir }: PropsDaAcao) {
  const [data, setData] = useState(hojeIso())
  const [observacao, setObservacao] = useState('')

  return (
    <PainelDeAcao
      titulo="Dar baixa no bem"
      textoBotao="Registrar baixa"
      aoCancelar={aoCancelar}
      enviar={async () => {
        await apiPatch(`/bens/${bem.id}/status`, {
          status: 'baixado',
          baixa_data: data,
          baixa_observacao: observacao.trim() || null,
        })
        aoConcluir('Baixa registrada.')
      }}
    >
      {(erro) => (
        <div className="grid gap-4 sm:grid-cols-2">
          <Campo rotulo="Data da baixa" obrigatorio erro={erro?.doCampo('baixa_data')}>
            <input type="date" className="entrada" max={hojeIso()} value={data} onChange={(e) => setData(e.target.value)} required />
          </Campo>
          <Campo rotulo="Observação" erro={erro?.doCampo('baixa_observacao')} ajuda="ex: número do processo">
            <input className="entrada" value={observacao} onChange={(e) => setObservacao(e.target.value)} />
          </Campo>
        </div>
      )}
    </PainelDeAcao>
  )
}
