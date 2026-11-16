// Formatos de exibição usados nas telas.

// "2026-10-23" ou "2026-10-23T14:00:00-03:00" vira "23/10/2026".
export function formatarData(data: string | null | undefined) {
  if (!data) return '—'
  const [ano, mes, dia] = data.slice(0, 10).split('-')
  return `${dia}/${mes}/${ano}`
}

// Datas sem horário continuam exibindo apenas o dia informado pela API.
export function formatarDataHora(data: string | null | undefined) {
  if (!data || !data.includes('T')) return formatarData(data)
  const instante = new Date(data)
  if (Number.isNaN(instante.getTime())) return '—'
  const opcoes = { timeZone: 'America/Sao_Paulo' }
  const dia = instante.toLocaleDateString('pt-BR', opcoes)
  const hora = instante.toLocaleTimeString('pt-BR', { ...opcoes, hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
  return `${dia} às ${hora}`
}

// Data de hoje no formato da API (ano-mês-dia), no fuso do navegador.
export function hojeIso() {
  const agora = new Date()
  const mes = String(agora.getMonth() + 1).padStart(2, '0')
  const dia = String(agora.getDate()).padStart(2, '0')
  return `${agora.getFullYear()}-${mes}-${dia}`
}

// "1 bem" e "2 bens": a quantidade com a palavra no número certo.
export function plural(quantidade: number, singular: string, plural: string) {
  return `${quantidade} ${quantidade === 1 ? singular : plural}`
}
