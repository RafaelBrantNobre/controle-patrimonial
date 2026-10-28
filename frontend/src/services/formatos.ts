// Formatos de exibição usados nas telas.

// "2026-10-23" ou "2026-10-23T14:00:00-03:00" vira "23/10/2026".
export function formatarData(data: string | null | undefined) {
  if (!data) return '—'
  const [ano, mes, dia] = data.slice(0, 10).split('-')
  return `${dia}/${mes}/${ano}`
}

// Data de hoje no formato da API (ano-mês-dia), no fuso do navegador.
export function hojeIso() {
  const agora = new Date()
  const mes = String(agora.getMonth() + 1).padStart(2, '0')
  const dia = String(agora.getDate()).padStart(2, '0')
  return `${agora.getFullYear()}-${mes}-${dia}`
}
