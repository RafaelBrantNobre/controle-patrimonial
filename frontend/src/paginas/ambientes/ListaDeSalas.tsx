import { Building2, ClipboardCheck, DoorOpen, Eye, Layers, Package, Pencil, Plus, Search } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { useAutenticacao } from '../../autenticacao/Autenticacao'
import { Aviso, Cabecalho, Carregando, Indicador, Paginacao, SeloAtivo } from '../../componentes/ui'
import { consulta } from '../../services/api'
import type { Andar, Bem, Pagina, Sala } from '../../services/tipos'
import { useApi } from '../../services/useApi'

const ABAS = [
  { valor: '', rotulo: 'Todas' },
  { valor: '1', rotulo: 'Ativas' },
  { valor: '0', rotulo: 'Inativas' },
]

// Gestão de Ambientes e Salas (wireframe).
// Fora do back-end por enquanto: a situação de conferência de cada sala
// (conferida ou pendente) e o indicador de salas conferidas. As abas filtram
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
      andar_id: filtro('andar_id'),
      ativo: filtro('ativo'),
      pagina: filtro('pagina') || 1,
      por_pagina: 20,
    })}`,
  )
  const andares = useApi<Pagina<Andar>>('/andares?por_pagina=100')
  const todasAtivas = useApi<Pagina<Sala>>('/salas?ativo=1&por_pagina=1')
  const bens = useApi<Pagina<Bem>>('/bens?por_pagina=1')

  return (
    <>
      <Cabecalho
        titulo="Locais e Ambientes"
        subtitulo="Gestão das salas, pavimentos e conferência física dos bens."
        acoes={
          usuario?.pode_alterar && (
            <Link to="/ambientes/novo" className="botao-primario">
              <Plus className="size-4" /> Nova Sala / Ambiente
            </Link>
          )
        }
      />

      <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Indicador titulo="Total de ambientes" valor={todasAtivas.dados?.meta.total ?? '…'} detalhe="salas ativas" icone={Building2} />
        <Indicador titulo="Pavimentos" valor={andares.dados?.meta.total ?? '…'} detalhe="andares cadastrados" icone={Layers} />
        <Indicador titulo="Bens alocados" valor={bens.dados?.meta.total.toLocaleString('pt-BR') ?? '…'} detalhe="itens cadastrados" icone={Package} />
        <Indicador titulo="Salas conferidas" valor="—" detalhe="aguardando o registro da conferência na API" icone={ClipboardCheck} />
      </div>

      <section className="cartao mb-6 flex flex-wrap items-center gap-3 p-4">
        <select
          className="entrada w-auto min-w-48"
          value={filtro('andar_id')}
          onChange={(e) => mudarFiltro('andar_id', e.target.value)}
          aria-label="Filtrar por pavimento"
        >
          <option value="">Todos os pavimentos</option>
          {andares.dados?.data.map((andar) => (
            <option key={andar.id} value={andar.id}>
              {andar.nome}
            </option>
          ))}
        </select>
        <div className="flex rounded-lg bg-slate-100 p-1 text-xs">
          {ABAS.map((aba) => (
            <button
              key={aba.valor}
              type="button"
              onClick={() => mudarFiltro('ativo', aba.valor)}
              className={`rounded-md px-3 py-1.5 font-medium ${filtro('ativo') === aba.valor ? 'bg-white text-cead-900 shadow-sm' : 'text-slate-500'}`}
            >
              {aba.rotulo}
            </button>
          ))}
        </div>
        <label className="relative ml-auto block w-full sm:w-72">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
          <input className="entrada pl-9" placeholder="Buscar sala pelo nome..." value={busca} onChange={(e) => setBusca(e.target.value)} />
        </label>
      </section>

      <section className="cartao overflow-hidden">
        {salas.erro && (
          <div className="p-4">
            <Aviso>{salas.erro}</Aviso>
          </div>
        )}
        <div className="overflow-x-auto">
          <table className="tabela">
            <thead>
              <tr>
                <th>Identificação da sala</th>
                <th>Pavimento</th>
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
                  <td className="text-slate-600">{sala.andar?.nome}</td>
                  <td className="text-center">
                    <span className="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{sala.bens_count ?? 0} bens</span>
                  </td>
                  <td>
                    <SeloAtivo ativo={sala.ativo} />
                  </td>
                  <td className="text-right whitespace-nowrap">
                    <Link to={`/ambientes/${sala.id}`} className="inline-flex items-center gap-1 p-1.5 text-xs font-medium text-cead-700 hover:underline">
                      <Eye className="size-4" /> Ver bens
                    </Link>
                    {usuario?.pode_alterar && (
                      <Link to={`/ambientes/${sala.id}/editar`} className="inline-flex p-1.5 text-slate-500 hover:text-cead-700" title="Editar sala">
                        <Pencil className="size-4" />
                      </Link>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {salas.carregando && !salas.dados && <Carregando />}
          {salas.dados?.data.length === 0 && <Carregando texto="Nenhuma sala encontrada com estes filtros." />}
        </div>
        {salas.dados && (
          <Paginacao
            pagina={salas.dados.meta.current_page}
            ultima={salas.dados.meta.last_page}
            total={salas.dados.meta.total}
            rotulo="ambientes"
            aoMudar={(nova) => mudarFiltro('pagina', String(nova))}
          />
        )}
      </section>
    </>
  )
}
