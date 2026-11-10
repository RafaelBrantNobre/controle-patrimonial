import { FileSpreadsheet } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { AguardandoApi, Aviso, Cabecalho, Carregando, Paginacao } from '../componentes/ui'
import { consulta } from '../services/api'
import { formatarData, plural } from '../services/formatos'
import type { Conferencia, Pagina } from '../services/tipos'
import { useApi } from '../services/useApi'

// Relatórios: um por conferência concluída, e não mais por período.
// Cada linha abre a conferência, com o que foi registrado sala a sala.
// Fora do back-end por enquanto: baixar a conferência em planilha (XLSX),
// no formato da planilha de 2024.
export function Relatorios() {
  const [pagina, setPagina] = useState(1)
  const conferencias = useApi<Pagina<Conferencia>>(`/conferencias${consulta({ situacao: 'concluida', pagina, por_pagina: 20 })}`)

  return (
    <>
      <Cabecalho titulo="Relatórios" subtitulo="O resultado de cada conferência concluída." />

      <div className="mb-6">
        <AguardandoApi>
          A exportação de cada conferência em planilha (XLSX), no formato da planilha de 2024, ainda não existe na API. Por enquanto o resultado é
          consultado na própria conferência.
        </AguardandoApi>
      </div>

      <section className="cartao overflow-hidden">
        <div className="border-b border-slate-200 px-4 py-3">
          <h2 className="flex items-center gap-2 text-sm font-semibold text-slate-700">
            <FileSpreadsheet className="size-4" /> Conferências concluídas
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
                <th>Realizada em</th>
                <th>Bens</th>
                <th>Encontrados</th>
                <th>Não localizados</th>
                <th>Concluída por</th>
              </tr>
            </thead>
            <tbody className={conferencias.carregando ? 'opacity-50' : ''}>
              {conferencias.dados?.data.map((conferencia) => (
                <tr key={conferencia.id} className="hover:bg-slate-50">
                  <td>
                    <Link to={`/conferencias/${conferencia.id}`} className="font-medium text-cead-700 hover:underline">
                      {conferencia.nome}
                    </Link>
                  </td>
                  <td>{formatarData(conferencia.realizada_em)}</td>
                  <td>{conferencia.totais ? plural(conferencia.totais.itens, 'bem', 'bens') : '—'}</td>
                  <td className="text-emerald-700">{conferencia.totais?.encontrados ?? '—'}</td>
                  <td className={conferencia.totais?.nao_localizados ? 'font-semibold text-red-700' : 'text-slate-600'}>
                    {conferencia.totais?.nao_localizados ?? '—'}
                  </td>
                  <td className="text-slate-600">{conferencia.concluida_por?.nome ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
          {conferencias.carregando && !conferencias.dados && <Carregando />}
          {conferencias.dados?.data.length === 0 && <Carregando texto="Nenhuma conferência concluída ainda." />}
        </div>
        {conferencias.dados && conferencias.dados.meta.total > 0 && (
          <Paginacao
            pagina={conferencias.dados.meta.current_page}
            ultima={conferencias.dados.meta.last_page}
            total={conferencias.dados.meta.total}
            rotulo="conferências concluídas"
            singular="conferência concluída"
            aoMudar={setPagina}
          />
        )}
      </section>
    </>
  )
}
