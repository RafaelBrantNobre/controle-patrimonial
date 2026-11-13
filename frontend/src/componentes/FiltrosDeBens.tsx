import { Cartao, Entrada, Selecao } from '../components/ui'
import { Search } from 'lucide-react'
import { useEffect, useState } from 'react'
import { ROTULOS_STATUS, SITUACOES_DE_CONFERENCIA, type Pagina, type Sala, type StatusBem, type TipoPatrimonio } from '../services/tipos'
import { useApi } from '../services/useApi'

// Valor da opção "Sem localização" no seletor de sala (vira ?sem_sala=1).
const SEM_SALA = 'sem'

// Campos de filtro da lista de bens. O estado fica na URL (useFiltrosDeBens).
export function FiltrosDeBens({
  filtro,
  mudarFiltro,
}: {
  filtro: (nome: string) => string
  mudarFiltro: (nome: string, valor: string) => void
}) {
  const [busca, setBusca] = useState(filtro('busca'))

  // Busca enquanto a pessoa digita, com uma pequena espera.
  useEffect(() => {
    const espera = setTimeout(() => {
      if (busca.trim() !== filtro('busca')) mudarFiltro('busca', busca.trim())
    }, 400)
    return () => clearTimeout(espera)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [busca])

  const salas = useApi<Pagina<Sala>>('/salas?por_pagina=100')
  const tipos = useApi<Pagina<TipoPatrimonio>>('/tipos-patrimonio?por_pagina=100')

  return (
    <Cartao as="section" className="mb-6 space-y-4 p-4">
      <label className="relative block">
        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
        <Entrada
          className="pl-9"
          placeholder="Buscar por tombamento (ex: 1634456-1), código interno (CP-000123), descrição ou marca"
          value={busca}
          onChange={(e) => setBusca(e.target.value)}
          aria-label="Buscar bens"
        />
      </label>

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <label>
          <span className="rotulo">Sala / Ambiente</span>
          <Selecao
            value={filtro('sem_sala') ? SEM_SALA : filtro('sala_id')}
            onChange={(e) => (e.target.value === SEM_SALA ? mudarFiltro('sem_sala', '1') : mudarFiltro('sala_id', e.target.value))}
          >
            <option value="">Todas as salas</option>
            <option value={SEM_SALA}>Sem localização</option>
            {salas.dados?.data.map((sala) => (
              <option key={sala.id} value={sala.id}>
                {sala.nome}
              </option>
            ))}
          </Selecao>
        </label>
        <label>
          <span className="rotulo">Tipo do item</span>
          <Selecao value={filtro('tipo_patrimonio_id')} onChange={(e) => mudarFiltro('tipo_patrimonio_id', e.target.value)}>
            <option value="">Todos os tipos</option>
            {tipos.dados?.data.map((tipo) => (
              <option key={tipo.id} value={tipo.id}>
                {tipo.nome}
              </option>
            ))}
          </Selecao>
        </label>
        <label>
          <span className="rotulo">Situação de uso</span>
          <Selecao value={filtro('status')} onChange={(e) => mudarFiltro('status', e.target.value)}>
            <option value="">Todas as situações</option>
            {(Object.keys(ROTULOS_STATUS) as StatusBem[]).map((status) => (
              <option key={status} value={status}>
                {ROTULOS_STATUS[status]}
              </option>
            ))}
          </Selecao>
        </label>
        <label>
          <span className="rotulo">Conferência</span>
          <Selecao value={filtro('conferencia')} onChange={(e) => mudarFiltro('conferencia', e.target.value)}>
            <option value="">Todas</option>
            {(Object.keys(SITUACOES_DE_CONFERENCIA) as (keyof typeof SITUACOES_DE_CONFERENCIA)[]).map((situacao) => (
              <option key={situacao} value={situacao}>
                {SITUACOES_DE_CONFERENCIA[situacao]}
              </option>
            ))}
          </Selecao>
        </label>
      </div>
    </Cartao>
  )
}
