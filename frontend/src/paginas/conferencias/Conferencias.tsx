import { AlertTriangle, ClipboardCheck, FileText, MapPin, Plus } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { useAutenticacao } from '../../autenticacao/Autenticacao'
import { Aviso, Cabecalho, Carregando, Paginacao, Selo, type CorSelo } from '../../componentes/ui'
import { consulta } from '../../services/api'
import { formatarData, plural } from '../../services/formatos'
import type { Bem, Conferencia, Pagina } from '../../services/tipos'
import { useApi } from '../../services/useApi'
import { ResolverPendencia } from './ResolverPendencia'

const COR_SITUACAO: Record<Conferencia['situacao'], CorSelo> = { aberta: 'azul', concluida: 'verde', cancelada: 'cinza' }

// Conferências dos bens e pendências da gestora.
// Em cima, os bens não localizados ainda sem resolução: é o que mais pede
// atenção, porque a gestora responde por eles. Embaixo, as conferências.
export function Conferencias() {
  const { usuario } = useAutenticacao()
  const [pagina, setPagina] = useState(1)
  const [versaoPendencias, setVersaoPendencias] = useState(0)
  const conferencias = useApi<Pagina<Conferencia>>(`/conferencias${consulta({ pagina, por_pagina: 20 })}`)
  const pendencias = useApi<{ data: Bem[] }>('/pendencias', versaoPendencias)

  return (
    <>
      <Cabecalho
        titulo="Conferências"
        subtitulo="Gere a conferência dos bens, passe a folha a limpo e acompanhe os bens não localizados."
        acoes={
          usuario?.pode_alterar && (
            <Link to="/conferencias/nova" className="botao-primario">
              <Plus className="size-4" /> Gerar conferência
            </Link>
          )
        }
      />

      <Pendencias bens={pendencias.dados?.data} erro={pendencias.erro} podeResolver={Boolean(usuario?.pode_alterar)} aoResolver={() => setVersaoPendencias((v) => v + 1)} />

      <section className="cartao overflow-hidden">
        <div className="border-b border-slate-200 px-4 py-3">
          <h2 className="flex items-center gap-2 text-sm font-semibold text-slate-700">
            <ClipboardCheck className="size-4" /> Conferências geradas
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
                <th>Gerada por</th>
              </tr>
            </thead>
            <tbody className={conferencias.carregando ? 'opacity-50' : ''}>
              {conferencias.dados?.data.map((conferencia) => (
                <tr key={conferencia.id} className="hover:bg-slate-50">
                  <td>
                    <Link to={`/conferencias/${conferencia.id}`} className="font-medium text-cead-700 hover:underline">
                      {conferencia.nome}
                    </Link>
                    <span className="block text-xs text-slate-500">gerada em {formatarData(conferencia.criada_em)}</span>
                  </td>
                  <td>
                    <Selo cor={COR_SITUACAO[conferencia.situacao]}>{conferencia.situacao_rotulo}</Selo>
                  </td>
                  <td className="text-xs text-slate-600">
                    {conferencia.totais && (
                      <>
                        {conferencia.totais.itens - conferencia.totais.pendentes} de {conferencia.totais.itens} lançados
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
          {conferencias.dados?.data.length === 0 && <Carregando texto="Nenhuma conferência gerada ainda." />}
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
                <td>
                  {bem.sala?.nome}
                  <span className="block text-xs text-slate-500">{bem.sala?.andar?.nome}</span>
                </td>
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
