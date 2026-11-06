import { useSearchParams } from 'react-router'

// Filtros da lista de bens, guardados na URL para o botão voltar e o link
// copiado manterem a mesma consulta. A lista de bens e a tela de gerar
// conferência usam os mesmos, para "todos os bens do filtro" ser exatamente
// o que a lista mostra.
const CAMPOS = ['busca', 'andar_id', 'sala_id', 'tipo_patrimonio_id', 'status', 'conferencia'] as const

export function useFiltrosDeBens() {
  const [parametros, setParametros] = useSearchParams()
  const filtro = (nome: string) => parametros.get(nome) ?? ''

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

  // Só os filtros preenchidos, no formato da API.
  const filtros = Object.fromEntries(CAMPOS.map((campo) => [campo, filtro(campo)]).filter(([, valor]) => valor !== ''))

  return { filtro, mudarFiltro, filtros, pagina: Number(filtro('pagina') || 1) }
}
