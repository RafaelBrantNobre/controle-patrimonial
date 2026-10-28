import { ArrowRightLeft, CircleSlash, History, Pencil, RefreshCw } from 'lucide-react'
import { useState, type FormEvent, type ReactNode } from 'react'
import { Link, useLocation, useParams } from 'react-router'
import { useAutenticacao } from '../../autenticacao/Autenticacao'
import { Aviso, Campo, Carregando, SeloConservacao, SeloStatus } from '../../componentes/ui'
import { apiPatch, consulta, ErroDaApi } from '../../services/api'
import { formatarData, hojeIso } from '../../services/formatos'
import type { Andar, Bem, Pagina, Sala } from '../../services/tipos'
import { useApi } from '../../services/useApi'

// Ficha do bem (RF15). Não há wireframe próprio: segue o padrão das outras
// telas e reúne as operações da tarefa 2.8 (mudar de sala, de situação e
// dar baixa), que só o administrador faz.
export function FichaDoBem() {
  const { id } = useParams()
  const { usuario } = useAutenticacao()
  const location = useLocation()
  const [versao, setVersao] = useState(0)
  const [mensagem, setMensagem] = useState<string | null>((location.state as { mensagem?: string } | null)?.mensagem ?? null)
  const [acao, setAcao] = useState<'sala' | 'status' | 'baixa' | null>(null)

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
          <h1 className="text-2xl font-bold text-cead-900">
            {bem.tipo_patrimonio?.nome}
            {bem.descricao && <span className="font-normal text-slate-600"> · {bem.descricao}</span>}
          </h1>
          <p className="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500">
            <span className="font-mono font-semibold text-cead-800">{bem.numero_patrimonio ?? 'sem patrimônio'}</span>·
            <span className="font-mono">{bem.codigo_interno}</span>
            <SeloStatus status={bem.status} rotulo={bem.status_rotulo} />
          </p>
        </div>
        {usuario?.pode_alterar && (
          <div className="flex flex-wrap gap-2">
            {!bem.baixado && (
              <>
                <Link to={`/bens/${bem.id}/editar`} className="botao-secundario">
                  <Pencil className="size-4" /> Editar
                </Link>
                <button type="button" className="botao-secundario" onClick={() => setAcao('sala')}>
                  <ArrowRightLeft className="size-4" /> Mudar de sala
                </button>
              </>
            )}
            <button type="button" className="botao-secundario" onClick={() => setAcao('status')}>
              <RefreshCw className="size-4" /> Mudar situação
            </button>
            {!bem.baixado && (
              <button type="button" className="botao border border-red-200 bg-white text-red-700 hover:bg-red-50" onClick={() => setAcao('baixa')}>
                <CircleSlash className="size-4" /> Dar baixa
              </button>
            )}
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
          {bem.observacao && (
            <div className="mt-5 rounded-lg bg-slate-50 p-4">
              <p className="rotulo">Observação</p>
              <p className="text-sm whitespace-pre-line text-slate-700">{bem.observacao}</p>
            </div>
          )}
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
                ? `${bem.ultima_movimentacao.sala_origem?.nome} → ${bem.ultima_movimentacao.sala_destino?.nome}, em ${formatarData(bem.ultima_movimentacao.data)}`
                : 'nunca mudou de sala'}
            </Dado>
            <Dado rotulo="Cadastrado em">{formatarData(bem.cadastrado_em)}</Dado>
          </dl>
        </section>
      </div>
    </>
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

function MudarStatus({ bem, aoCancelar, aoConcluir }: PropsDaAcao) {
  const opcoes = (['em_uso', 'sem_uso'] as const).filter((status) => status !== bem.status)
  const [status, setStatus] = useState<string>(opcoes[0])

  return (
    <PainelDeAcao
      titulo={bem.baixado ? 'Desfazer a baixa' : 'Mudar a situação de uso'}
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
