import { AlertTriangle, ArrowRight, ClipboardCheck, FileText, MapPin, Play } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { useAutenticacao } from '../../autenticacao/Autenticacao'
import { Aviso, Cabecalho, Carregando, Paginacao, Selo, type CorSelo } from '../../componentes/ui'
import { consulta } from '../../services/api'
import { formatarData, plural } from '../../services/formatos'
import type { Bem, Conferencia, Pagina } from '../../services/tipos'
import { useApi } from '../../services/useApi'
import { IniciarConferencia } from './IniciarConferencia'
import { ResolverPendencia } from './ResolverPendencia'

const COR_SITUACAO: Record<Conferencia['situacao'], CorSelo> = { aberta: 'azul', concluida: 'verde', cancelada: 'cinza' }

// Gerenciamento das conferências.
// Só existe uma conferência aberta por vez: se há uma, a tela convida a
// continuar; se não há, oferece iniciar. Depois vêm os bens não localizados
// ainda sem resolução (a gestora responde por eles) e o histórico.
export function Conferencias() {
  const { usuario } = useAutenticacao()
  const [pagina, setPagina] = useState(1)
  const [versaoPendencias, setVersaoPendencias] = useState(0)
  const conferencias = useApi<Pagina<Conferencia>>(`/conferencias${consulta({ pagina, por_pagina: 20 })}`)
  const pendencias = useApi<{ data: Bem[] }>('/pendencias', versaoPendencias)
  const abertas = useApi<Pagina<Conferencia>>('/conferencias?situacao=aberta&por_pagina=1')
  const aberta = abertas.dados?.data[0]
  const [iniciando, setIniciando] = useState(false)

  return (
    <>
      <Cabecalho
        titulo="Conferências"
        subtitulo="Inicie a conferência dos bens, confira sala por sala e acompanhe os bens não localizados."
        acoes={
          usuario?.pode_alterar &&
          abertas.dados &&
          !aberta && (
            <button type="button" className="botao-primario" onClick={() => setIniciando(true)}>
              <Play className="size-4" /> Iniciar conferência
            </button>
          )
        }
      />

      {aberta && (
        <section className="cartao mb-6 flex flex-wrap items-center justify-between gap-4 border-cead-100 bg-cead-50 p-5">
          <div>
            <p className="text-xs font-semibold tracking-wide text-cead-700 uppercase">Conferência em andamento</p>
            <p className="mt-1 text-lg font-bold text-cead-900">{aberta.nome}</p>
            {aberta.totais && (
              <p className="text-sm text-slate-600">
                {aberta.totais.itens - aberta.totais.pendentes} de {plural(aberta.totais.itens, 'bem verificado', 'bens verificados')}. Só pode haver uma
                conferência aberta por vez.
              </p>
            )}
          </div>
          <Link to={`/conferencias/${aberta.id}`} className="botao-primario">
            {usuario?.pode_alterar ? 'Continuar conferência' : 'Acompanhar'} <ArrowRight className="size-4" />
          </Link>
        </section>
      )}

      {iniciando && <IniciarConferencia aoFechar={() => setIniciando(false)} />}

      <Pendencias bens={pendencias.dados?.data} erro={pendencias.erro} podeResolver={Boolean(usuario?.pode_alterar)} aoResolver={() => setVersaoPendencias((v) => v + 1)} />

      <section className="cartao overflow-hidden">
        <div className="border-b border-slate-200 px-4 py-3">
          <h2 className="flex items-center gap-2 text-sm font-semibold text-slate-700">
            <ClipboardCheck className="size-4" /> Histórico de conferências
          </h2>
        </div>
        {conferencias.erro && (
          <div className="p-4">
            <Aviso>{conferencias.erro}</Aviso>
          </div>
        )}
        <div className="overflow-x-auto">
          <table className="tabela">
            <thead>
              <tr>
                <th>Conferência</th>
                <th>Situação</th>
                <th>Andamento</th>
                <th>Realizada em</th>
                <th>Iniciada por</th>
              </tr>
            </thead>
            <tbody className={conferencias.carregando ? 'opacity-50' : ''}>
              {conferencias.dados?.data.map((conferencia) => (
                <tr key={conferencia.id} className="hover:bg-slate-50">
                  <td>
                    <Link to={`/conferencias/${conferencia.id}`} className="font-medium text-cead-700 hover:underline">
                      {conferencia.nome}
                    </Link>
                    <span className="block text-xs text-slate-500">iniciada em {formatarData(conferencia.criada_em)}</span>
                  </td>
                  <td>
                    <Selo cor={COR_SITUACAO[conferencia.situacao]}>{conferencia.situacao_rotulo}</Selo>
                  </td>
                  <td className="text-xs text-slate-600">
                    {conferencia.totais && (
                      <>
                        {conferencia.totais.itens - conferencia.totais.pendentes} de {conferencia.totais.itens} verificados
                        {conferencia.totais.nao_localizados > 0 && (
                          <span className="block text-red-700">{plural(conferencia.totais.nao_localizados, 'não localizado', 'não localizados')}</span>
                        )}
                      </>
                    )}
                  </td>
                  <td>{formatarData(conferencia.realizada_em)}</td>
                  <td className="text-slate-600">{conferencia.criada_por?.nome}</td>
                </tr>
              ))}
            </tbody>
          </table>
          {conferencias.carregando && !conferencias.dados && <Carregando />}
          {conferencias.dados?.data.length === 0 && <Carregando texto="Nenhuma conferência iniciada ainda." />}
        </div>
        {conferencias.dados && conferencias.dados.meta.total > 0 && (
          <Paginacao
            pagina={conferencias.dados.meta.current_page}
            ultima={conferencias.dados.meta.last_page}
            total={conferencias.dados.meta.total}
            rotulo="conferências"
            singular="conferência"
            aoMudar={setPagina}
          />
        )}
      </section>
    </>
  )
}

function Pendencias({ bens, erro, podeResolver, aoResolver }: { bens?: Bem[]; erro: string | null; podeResolver: boolean; aoResolver: () => void }) {
  const [resolvendo, setResolvendo] = useState<{ bem: Bem; jeito: 'localizado' | 'processo_administrativo' } | null>(null)

  if (erro) return <Aviso>{erro}</Aviso>
  if (!bens || bens.length === 0) return null

  return (
    <section className="cartao mb-6 overflow-hidden border-red-200">
      <div className="flex items-center justify-between border-b border-red-100 bg-red-50 px-4 py-3">
        <h2 className="flex items-center gap-2 text-sm font-semibold text-red-800">
          <AlertTriangle className="size-4" /> Bens não localizados ({bens.length})
        </h2>
        <span className="text-xs text-red-700">A pendência fecha quando o bem aparece ou com o número do processo administrativo.</span>
      </div>
      <div className="overflow-x-auto">
        <table className="tabela">
          <thead>
            <tr>
              <th>Tombamento</th>
              <th>Tipo do bem</th>
              <th>Sala registrada</th>
              <th>Não localizado desde</th>
              {podeResolver && <th className="text-right">Resolver</th>}
            </tr>
          </thead>
          <tbody>
            {bens.map((bem) => (
              <tr key={bem.id}>
                <td className="font-mono text-xs whitespace-nowrap">
                  <Link to={`/bens/${bem.id}`} className="font-semibold text-cead-800 hover:underline">
                    {bem.numero_patrimonio ?? 'sem patrimônio'}
                  </Link>
                  <span className="block text-slate-400">{bem.codigo_interno}</span>
                </td>
                <td className="font-medium">{bem.tipo_patrimonio?.nome}</td>
                <td>{bem.sala?.nome ?? 'Sem localização'}</td>
                <td>
                  {formatarData(bem.pendencia?.desde)}
                  <span className="block text-xs text-slate-500">{bem.pendencia?.conferencia}</span>
                </td>
                {podeResolver && (
                  <td className="text-right whitespace-nowrap">
                    <button type="button" className="botao-secundario px-3 py-1 text-xs" onClick={() => setResolvendo({ bem, jeito: 'localizado' })}>
                      <MapPin className="size-3.5" /> Localizado
                    </button>{' '}
                    <button
                      type="button"
                      className="botao-secundario px-3 py-1 text-xs"
                      onClick={() => setResolvendo({ bem, jeito: 'processo_administrativo' })}
                    >
                      <FileText className="size-3.5" /> Processo
                    </button>
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {resolvendo && (
        <ResolverPendencia
          bem={resolvendo.bem}
          jeito={resolvendo.jeito}
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
