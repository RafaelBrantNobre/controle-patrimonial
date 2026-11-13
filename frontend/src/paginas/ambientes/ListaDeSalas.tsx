import {
  Tooltip,
  LinkBotao,
  Cartao,
  Botao,
  Entrada,
  RolagemTabela,
  Tabela,
  Aviso,
  Cabecalho,
  Carregando,
  Indicador,
  Paginacao,
} from '../../components/ui'
import { Building2, DoorOpen, Eye, Package, Pencil, Plus, Search } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { useAutenticacao } from '../../autenticacao/Autenticacao'
import { SeloAtivo } from '../../componentes/SelosDeBem'
import { consulta } from '../../services/api'
import type { Bem, Pagina, Sala } from '../../services/tipos'
import { useApi } from '../../services/useApi'

const ABAS = [
  { valor: '', rotulo: 'Todas' },
  { valor: '1', rotulo: 'Ativas' },
  { valor: '0', rotulo: 'Inativas' },
]

// Gestão de Ambientes e Salas (wireframe).
// O wireframe mostra a situação de conferência de cada sala, que o back-end
// ainda não registra; até lá a tela não mostra conferência. As abas filtram
// por sala ativa e inativa, que o back-end já tem.
export function ListaDeSalas() {
  const { usuario } = useAutenticacao()
  const [parametros, setParametros] = useSearchParams()
  const [busca, setBusca] = useState(parametros.get('busca') ?? '')
  const filtro = (nome: string) => parametros.get(nome) ?? ''

  function mudarFiltro(nome: string, valor: string) {
    const novos = new URLSearchParams(parametros)
    if (valor) novos.set(nome, valor)
    else novos.delete(nome)
    if (nome !== 'pagina') novos.delete('pagina')
    setParametros(novos)
  }

  useEffect(() => {
    const espera = setTimeout(() => {
      if (busca !== (parametros.get('busca') ?? '')) mudarFiltro('busca', busca.trim())
    }, 400)
    return () => clearTimeout(espera)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [busca])

  const salas = useApi<Pagina<Sala>>(
    `/salas${consulta({
      busca: filtro('busca'),
      ativo: filtro('ativo'),
      pagina: filtro('pagina') || 1,
      por_pagina: 20,
    })}`,
  )
  const todasAtivas = useApi<Pagina<Sala>>('/salas?ativo=1&por_pagina=1')
  const bens = useApi<Pagina<Bem>>('/bens?por_pagina=1')

  return (
    <>
      <Cabecalho
        titulo="Locais e Ambientes"
        subtitulo="Gestão das salas e ambientes onde ficam os bens."
        acoes={
          usuario?.pode_alterar && (
            <LinkBotao variante="primario" to="/ambientes/novo">
              <Plus className="size-4" /> Nova Sala / Ambiente
            </LinkBotao>
          )
        }
      />

      <div className="mb-6 grid gap-4 sm:grid-cols-2">
        <Indicador titulo="Total de ambientes" valor={todasAtivas.dados?.meta.total ?? '…'} detalhe="salas ativas" icone={Building2} />
        <Indicador
          titulo="Bens alocados"
          valor={bens.dados?.meta.total.toLocaleString('pt-BR') ?? '…'}
          detalhe="itens cadastrados"
          icone={Package}
        />
      </div>

      <Cartao as="section" className="mb-6 flex flex-wrap items-center gap-3 p-4">
        <div className="flex rounded-lg bg-slate-100 p-1 text-xs">
          {ABAS.map((aba) => (
            <Botao
              key={aba.valor}
              type="button"
              onClick={() => mudarFiltro('ativo', aba.valor)}
              className={`rounded-md px-3 py-1.5 font-medium ${filtro('ativo') === aba.valor ? 'bg-white text-cead-900 shadow-sm' : 'text-slate-500'}`}
            >
              {aba.rotulo}
            </Botao>
          ))}
        </div>
        <label className="relative ml-auto block w-full sm:w-72">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
          <Entrada className="pl-9" placeholder="Buscar sala pelo nome..." value={busca} onChange={(e) => setBusca(e.target.value)} />
        </label>
      </Cartao>

      <Cartao as="section" className="overflow-hidden">
        {salas.erro && (
          <div className="p-4">
            <Aviso>{salas.erro}</Aviso>
          </div>
        )}
        <RolagemTabela>
          <Tabela>
            <thead>
              <tr>
                <th>Identificação da sala</th>
                <th className="text-center">Total de bens alocados</th>
                <th>Situação</th>
                <th className="text-right">Ações</th>
              </tr>
            </thead>
            <tbody className={salas.carregando ? 'opacity-50' : ''}>
              {salas.dados?.data.map((sala) => (
                <tr key={sala.id} className="hover:bg-slate-50">
                  <td>
                    <Link to={`/ambientes/${sala.id}`} className="flex items-center gap-3">
                      <span className="rounded-lg bg-cead-50 p-2 text-cead-700">
                        <DoorOpen className="size-4" />
                      </span>
                      <span>
                        <span className="block font-medium text-slate-800">{sala.nome}</span>
                        {sala.descricao && <span className="block text-xs text-slate-500">{sala.descricao}</span>}
                      </span>
                    </Link>
                  </td>
                  <td className="text-center">
                    <span className="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">
                      {sala.bens_count ?? 0} bens
                    </span>
                  </td>
                  <td>
                    <SeloAtivo ativo={sala.ativo} />
                  </td>
                  <td className="text-right whitespace-nowrap">
                    <Link
                      to={`/ambientes/${sala.id}`}
                      className="inline-flex items-center gap-1 p-1.5 text-xs font-medium text-cead-700 hover:underline"
                    >
                      <Eye className="size-4" /> Ver bens
                    </Link>
                    {usuario?.pode_alterar && (
                      <Tooltip texto={'Editar sala'}>
                        <Link
                          to={`/ambientes/${sala.id}/editar`}
                          className="inline-flex p-1.5 text-slate-500 hover:text-cead-700"
                          aria-label="Editar sala"
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
          {salas.carregando && !salas.dados && <Carregando />}
          {salas.dados?.data.length === 0 && <Carregando texto="Nenhuma sala encontrada com estes filtros." />}
        </RolagemTabela>
        {salas.dados && (
          <Paginacao
            pagina={salas.dados.meta.current_page}
            ultima={salas.dados.meta.last_page}
            total={salas.dados.meta.total}
            rotulo="ambientes"
            singular="ambiente"
            aoMudar={(nova) => mudarFiltro('pagina', String(nova))}
          />
        )}
      </Cartao>
    </>
  )
}
