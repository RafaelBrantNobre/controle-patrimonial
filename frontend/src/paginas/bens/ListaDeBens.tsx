import { ClipboardList, Eye, Pencil, Plus, Search } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { useAutenticacao } from '../../autenticacao/Autenticacao'
import { Aviso, Cabecalho, Carregando, Paginacao, SeloConservacao, SeloStatus } from '../../componentes/ui'
import { consulta } from '../../services/api'
import { ROTULOS_STATUS, type Andar, type Bem, type Pagina, type Sala, type StatusBem, type TipoPatrimonio } from '../../services/tipos'
import { useApi } from '../../services/useApi'

// Listagem de Bens Patrimoniais (wireframe). Os filtros ficam na URL, para o
// botão voltar do navegador e o link copiado manterem a mesma consulta.
// Fora do back-end por enquanto: foto do bem e exportar esta lista em Excel
// (a planilha existe só para o relatório, na tela Relatórios).
export function ListaDeBens() {
  const { usuario } = useAutenticacao()
  const [parametros, setParametros] = useSearchParams()
  const [busca, setBusca] = useState(parametros.get('busca') ?? '')

  const filtro = (nome: string) => parametros.get(nome) ?? ''
  const pagina = Number(filtro('pagina') || 1)

  function mudarFiltro(nome: string, valor: string) {
    const novos = new URLSearchParams(parametros)
    if (valor) novos.set(nome, valor)
    else novos.delete(nome)
    // Trocar um filtro volta para a primeira página.
    if (nome !== 'pagina') novos.delete('pagina')
    // A sala depende do andar.
    if (nome === 'andar_id') novos.delete('sala_id')
    setParametros(novos)
  }

  // Busca enquanto a pessoa digita, com uma pequena espera.
  useEffect(() => {
    const espera = setTimeout(() => {
      if (busca !== (parametros.get('busca') ?? '')) mudarFiltro('busca', busca.trim())
    }, 400)
    return () => clearTimeout(espera)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [busca])

  const bens = useApi<Pagina<Bem>>(
    `/bens${consulta({
      busca: filtro('busca'),
      andar_id: filtro('andar_id'),
      sala_id: filtro('sala_id'),
      tipo_patrimonio_id: filtro('tipo_patrimonio_id'),
      status: filtro('status'),
      pagina,
      por_pagina: 25,
    })}`,
  )
  const andares = useApi<Pagina<Andar>>('/andares?por_pagina=100')
  const salas = useApi<Pagina<Sala>>(`/salas${consulta({ andar_id: filtro('andar_id'), por_pagina: 100 })}`)
  const tipos = useApi<Pagina<TipoPatrimonio>>('/tipos-patrimonio?por_pagina=100')

  return (
    <>
      <Cabecalho
        titulo="Bens Patrimoniais do CEAD"
        subtitulo="Consulte, localize e filtre todos os equipamentos cadastrados no prédio."
        acoes={
          usuario?.pode_alterar && (
            <Link to="/bens/novo" className="botao-primario">
              <Plus className="size-4" /> Cadastrar Novo Bem
            </Link>
          )
        }
      />

      <section className="cartao mb-6 space-y-4 p-4">
        <label className="relative block">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
          <input
            className="entrada pl-9"
            placeholder="Buscar por tombamento (ex: 1634456-1), código interno (CP-000123), descrição ou marca"
            value={busca}
            onChange={(e) => setBusca(e.target.value)}
            aria-label="Buscar bens"
          />
        </label>

        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <label>
            <span className="rotulo">Andar</span>
            <select className="entrada" value={filtro('andar_id')} onChange={(e) => mudarFiltro('andar_id', e.target.value)}>
              <option value="">Todos os andares</option>
              {andares.dados?.data.map((andar) => (
                <option key={andar.id} value={andar.id}>
                  {andar.nome}
                </option>
              ))}
            </select>
          </label>
          <label>
            <span className="rotulo">Sala / Ambiente</span>
            <select className="entrada" value={filtro('sala_id')} onChange={(e) => mudarFiltro('sala_id', e.target.value)}>
              <option value="">Todas as salas</option>
              {salas.dados?.data.map((sala) => (
                <option key={sala.id} value={sala.id}>
                  {sala.nome}
                </option>
              ))}
            </select>
          </label>
          <label>
            <span className="rotulo">Tipo do item</span>
            <select
              className="entrada"
              value={filtro('tipo_patrimonio_id')}
              onChange={(e) => mudarFiltro('tipo_patrimonio_id', e.target.value)}
            >
              <option value="">Todos os tipos</option>
              {tipos.dados?.data.map((tipo) => (
                <option key={tipo.id} value={tipo.id}>
                  {tipo.nome}
                </option>
              ))}
            </select>
          </label>
          <label>
            <span className="rotulo">Situação de uso</span>
            <select className="entrada" value={filtro('status')} onChange={(e) => mudarFiltro('status', e.target.value)}>
              <option value="">Todas as situações</option>
              {(Object.keys(ROTULOS_STATUS) as StatusBem[]).map((status) => (
                <option key={status} value={status}>
                  {ROTULOS_STATUS[status]}
                </option>
              ))}
            </select>
          </label>
        </div>
      </section>

      <section className="cartao overflow-hidden">
        <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
          <h2 className="flex items-center gap-2 text-sm font-semibold text-slate-700">
            <ClipboardList className="size-4" /> Inventário de equipamentos
          </h2>
          <span className="text-xs text-slate-500">Ordenado por andar, sala e tipo</span>
        </div>

        {bens.erro && (
          <div className="p-4">
            <Aviso>{bens.erro}</Aviso>
          </div>
        )}

        <div className="overflow-x-auto">
          <table className="tabela">
            <thead>
              <tr>
                <th>Tombamento</th>
                <th>Tipo do bem</th>
                <th>Localização física</th>
                <th>Situação</th>
                <th>Conservação</th>
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
                  <td>
                    <p className="text-slate-800">{bem.sala?.nome}</p>
                    <p className="text-xs text-slate-500">{bem.sala?.andar?.nome}</p>
                  </td>
                  <td>
                    <SeloStatus status={bem.status} rotulo={bem.status_rotulo} />
                  </td>
                  <td>
                    <SeloConservacao estado={bem.estado_conservacao} rotulo={bem.estado_conservacao_rotulo} />
                  </td>
                  <td className="text-right whitespace-nowrap">
                    <Link to={`/bens/${bem.id}`} className="inline-flex p-1.5 text-slate-500 hover:text-cead-700" title="Ver ficha">
                      <Eye className="size-4" />
                    </Link>
                    {usuario?.pode_alterar && !bem.baixado && (
                      <Link
                        to={`/bens/${bem.id}/editar`}
                        className="inline-flex p-1.5 text-slate-500 hover:text-cead-700"
                        title="Editar"
                      >
                        <Pencil className="size-4" />
                      </Link>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {bens.carregando && !bens.dados && <Carregando />}
          {bens.dados?.data.length === 0 && <Carregando texto="Nenhum bem encontrado com estes filtros." />}
        </div>

        {bens.dados && (
          <Paginacao
            pagina={bens.dados.meta.current_page}
            ultima={bens.dados.meta.last_page}
            total={bens.dados.meta.total}
            rotulo="bens encontrados"
            aoMudar={(nova) => mudarFiltro('pagina', String(nova))}
          />
        )}
      </section>

    </>
  )
}
