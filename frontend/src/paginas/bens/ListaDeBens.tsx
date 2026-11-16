import { Tooltip, LinkBotao, Cartao, RolagemTabela, Tabela, Aviso, Cabecalho, Carregando, Paginacao } from '../../components/ui'
import { ClipboardList, Eye, FilePlus2, Pencil, Plus } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { useAutenticacao } from '../../autenticacao/Autenticacao'
import { FiltrosDeBens } from '../../componentes/FiltrosDeBens'
import { GerenciarTipos } from '../../componentes/TiposDePatrimonio'
import { SeloConferencia, SeloConservacao, SeloStatus } from '../../componentes/SelosDeBem'
import { useFiltrosDeBens } from '../../componentes/useFiltrosDeBens'
import { consulta } from '../../services/api'
import type { Bem, Pagina } from '../../services/tipos'
import { useApi } from '../../services/useApi'

// Listagem de Bens Patrimoniais (wireframe). Os filtros ficam na URL, para o
// botão voltar do navegador e o link copiado manterem a mesma consulta.
// Fora do back-end por enquanto: foto do bem e exportar esta lista em Excel
// (a planilha existe só para o relatório, na tela Relatórios).
export function ListaDeBens() {
  const { usuario } = useAutenticacao()
  const { filtro, mudarFiltro, filtros, pagina } = useFiltrosDeBens()
  const [versaoTipos, setVersaoTipos] = useState(0)

  const bens = useApi<Pagina<Bem>>(`/bens${consulta({ ...filtros, pagina, por_pagina: 25 })}`)

  return (
    <>
      <Cabecalho
        titulo="Bens Patrimoniais do CEAD"
        subtitulo="Consulte, localize e filtre todos os equipamentos cadastrados no prédio."
        acoes={
          usuario?.pode_alterar && (
            <>
              <GerenciarTipos aoMudar={() => setVersaoTipos((v) => v + 1)} />
              <LinkBotao variante="primario" to="/bens/novo">
                <Plus className="size-4" /> Cadastrar Novo Bem
              </LinkBotao>
            </>
          )
        }
      />

      <FiltrosDeBens filtro={filtro} mudarFiltro={mudarFiltro} versaoTipos={versaoTipos} />

      <Cartao as="section" className="overflow-hidden">
        <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
          <h2 className="flex items-center gap-2 text-sm font-semibold text-slate-700">
            <ClipboardList className="size-4" /> Inventário de equipamentos
          </h2>
          <span className="text-xs text-slate-500">Ordenado por sala e tipo</span>
        </div>

        {bens.erro && (
          <div className="p-4">
            <Aviso>{bens.erro}</Aviso>
          </div>
        )}

        <RolagemTabela>
          <Tabela>
            <thead>
              <tr>
                <th>Tombamento</th>
                <th>Tipo do bem</th>
                <th>Localização física</th>
                <th>Situação</th>
                <th>Conservação</th>
                <th>Conferência</th>
                <th className="text-right">Ações</th>
              </tr>
            </thead>
            <tbody className={bens.carregando ? 'opacity-50' : ''}>
              {bens.dados?.data.map((bem) => (
                <tr key={bem.id} className="hover:bg-slate-50">
                  <td>
                    <span className="rounded-md bg-cead-50 px-2 py-1 font-mono text-xs font-semibold whitespace-nowrap text-cead-800">
                      {bem.numero_patrimonio ?? 'sem patrimônio'}
                    </span>
                    <span className="mt-1 block font-mono text-xs text-slate-400">{bem.codigo_interno}</span>
                  </td>
                  <td className="font-medium text-slate-800">{bem.tipo_patrimonio?.nome}</td>
                  <td className={bem.sala ? 'text-slate-800' : 'text-slate-500 italic'}>{bem.sala?.nome ?? 'Sem localização'}</td>
                  <td>
                    <SeloStatus status={bem.status} rotulo={bem.status_rotulo} />
                  </td>
                  <td>
                    <SeloConservacao estado={bem.estado_conservacao} rotulo={bem.estado_conservacao_rotulo} />
                  </td>
                  <td>
                    <SeloConferencia bem={bem} />
                  </td>
                  <td className="text-right whitespace-nowrap">
                    <Tooltip texto={'Ver ficha'}>
                      <Link to={`/bens/${bem.id}`} className="inline-flex p-1.5 text-slate-500 hover:text-cead-700" aria-label="Ver ficha">
                        <Eye className="size-4" />
                      </Link>
                    </Tooltip>
                    {usuario?.pode_alterar && (
                      <Tooltip texto={'Registrar ocorrência'}>
                        <Link
                          to={`/bens/${bem.id}?ocorrencia=1`}
                          className="inline-flex p-1.5 text-slate-500 hover:text-cead-700"
                          aria-label="Registrar ocorrência"
                        >
                          <FilePlus2 className="size-4" />
                        </Link>
                      </Tooltip>
                    )}
                    {usuario?.pode_alterar && !bem.baixado && (
                      <Tooltip texto={'Editar'}>
                        <Link
                          to={`/bens/${bem.id}/editar`}
                          className="inline-flex p-1.5 text-slate-500 hover:text-cead-700"
                          aria-label="Editar"
                        >
                          <Pencil className="size-4" />
                        </Link>
                      </Tooltip>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </Tabela>
          {bens.carregando && !bens.dados && <Carregando />}
          {bens.dados?.data.length === 0 && <Carregando texto="Nenhum bem encontrado com estes filtros." />}
        </RolagemTabela>

        {bens.dados && (
          <Paginacao
            pagina={bens.dados.meta.current_page}
            ultima={bens.dados.meta.last_page}
            total={bens.dados.meta.total}
            rotulo="bens encontrados"
            singular="bem encontrado"
            aoMudar={(nova) => mudarFiltro('pagina', String(nova))}
          />
        )}
      </Cartao>
    </>
  )
}
