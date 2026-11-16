import { Cartao, RolagemTabela, Tabela, Aviso, Cabecalho, Carregando, Paginacao, Entrada, Selecao } from '../../components/ui'
import { History } from 'lucide-react'
import { Link } from 'react-router'
import { FiltrosDeBens } from '../../componentes/FiltrosDeBens'
import { useFiltrosDeBens } from '../../componentes/useFiltrosDeBens'
import { consulta } from '../../services/api'
import { formatarData } from '../../services/formatos'
import type { Ocorrencia, Pagina } from '../../services/tipos'
import { useApi } from '../../services/useApi'

// Ocorrência na lista geral: a mesma da ficha do bem, com o bem a que pertence.
type OcorrenciaDaLista = Ocorrencia & {
  bem: { id: number; numero_patrimonio: string | null; codigo_interno: string; tipo: string; sala: string | null }
}

const TIPOS_DE_OCORRENCIA: Record<Ocorrencia['tipo'], string> = {
  sala: 'Mudança de sala',
  conservacao: 'Estado de conservação',
  uso: 'Condição de uso',
  baixa: 'Baixa',
}

// Ocorrências de todos os bens (GET /ocorrencias), mais recentes primeiro.
// Os filtros de cima são os da lista de bens e valem para o bem de cada
// ocorrência (a sala é a atual do bem). Os de baixo são da ocorrência.
// Tudo fica na URL, como na lista de bens.
export function ListaDeOcorrencias() {
  const { filtro, mudarFiltro, filtros, pagina } = useFiltrosDeBens()
  const daOcorrencia = { tipo: filtro('tipo'), de: filtro('de'), ate: filtro('ate') }

  const ocorrencias = useApi<Pagina<OcorrenciaDaLista>>(`/ocorrencias${consulta({ ...filtros, ...daOcorrencia, pagina, por_pagina: 25 })}`)

  return (
    <>
      <Cabecalho titulo="Ocorrências" subtitulo="Mudanças de sala, de conservação e de uso e baixas de todos os bens." />

      <FiltrosDeBens filtro={filtro} mudarFiltro={mudarFiltro} />

      <Cartao as="section" className="mb-6 grid gap-3 p-4 sm:grid-cols-3">
        <label>
          <span className="rotulo">Tipo de ocorrência</span>
          <Selecao value={daOcorrencia.tipo} onChange={(e) => mudarFiltro('tipo', e.target.value)}>
            <option value="">Todos os tipos</option>
            {(Object.keys(TIPOS_DE_OCORRENCIA) as Ocorrencia['tipo'][]).map((tipo) => (
              <option key={tipo} value={tipo}>
                {TIPOS_DE_OCORRENCIA[tipo]}
              </option>
            ))}
          </Selecao>
        </label>
        <label>
          <span className="rotulo">De</span>
          <Entrada type="date" value={daOcorrencia.de} max={daOcorrencia.ate || undefined} onChange={(e) => mudarFiltro('de', e.target.value)} />
        </label>
        <label>
          <span className="rotulo">Até</span>
          <Entrada type="date" value={daOcorrencia.ate} min={daOcorrencia.de || undefined} onChange={(e) => mudarFiltro('ate', e.target.value)} />
        </label>
      </Cartao>

      <Cartao as="section" className="overflow-hidden">
        <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
          <h2 className="flex items-center gap-2 text-sm font-semibold text-slate-700">
            <History className="size-4" /> Histórico de ocorrências
          </h2>
          <span className="text-xs text-slate-500">Mais recentes primeiro</span>
        </div>

        {ocorrencias.erro && (
          <div className="p-4">
            <Aviso>{ocorrencias.erro}</Aviso>
          </div>
        )}

        <RolagemTabela>
          <Tabela>
            <thead>
              <tr>
                <th>Data</th>
                <th>Bem</th>
                <th>Ocorrência</th>
                <th>Antes</th>
                <th>Depois</th>
                <th>Registrada por</th>
                <th>Origem</th>
              </tr>
            </thead>
            <tbody className={ocorrencias.carregando ? 'opacity-50' : ''}>
              {ocorrencias.dados?.data.map((ocorrencia) => (
                <tr key={ocorrencia.id} className="hover:bg-slate-50">
                  <td className="whitespace-nowrap text-slate-600">{formatarData(ocorrencia.data)}</td>
                  <td>
                    <Link to={`/bens/${ocorrencia.bem.id}`} className="font-medium text-cead-700 hover:underline">
                      {ocorrencia.bem.numero_patrimonio ?? ocorrencia.bem.codigo_interno}
                    </Link>
                    <span className="block text-xs text-slate-500">
                      {ocorrencia.bem.tipo} · {ocorrencia.bem.sala ?? 'Sem localização'}
                    </span>
                  </td>
                  <td className="font-medium text-slate-800">
                    {ocorrencia.tipo_rotulo}
                    {ocorrencia.observacao && <span className="block text-xs font-normal text-slate-500">{ocorrencia.observacao}</span>}
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
          </Tabela>
          {ocorrencias.carregando && !ocorrencias.dados && <Carregando />}
          {ocorrencias.dados?.data.length === 0 && <Carregando texto="Nenhuma ocorrência encontrada com estes filtros." />}
        </RolagemTabela>

        {ocorrencias.dados && (
          <Paginacao
            pagina={ocorrencias.dados.meta.current_page}
            ultima={ocorrencias.dados.meta.last_page}
            total={ocorrencias.dados.meta.total}
            rotulo="ocorrências encontradas"
            singular="ocorrência encontrada"
            aoMudar={(nova) => mudarFiltro('pagina', String(nova))}
          />
        )}
      </Cartao>
    </>
  )
}
