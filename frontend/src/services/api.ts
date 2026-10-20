// Ponto único de acesso à API do back-end.
// O endereço vem do arquivo .env (variável VITE_API_URL).
const API_URL = (import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api').replace(/\/+$/, '')

export async function apiGet<T>(caminho: string): Promise<T> {
  const resposta = await fetch(`${API_URL}${caminho}`, {
    headers: { Accept: 'application/json' },
  })

  if (!resposta.ok) {
    throw new Error(`A API respondeu com o código ${resposta.status}`)
  }

  return resposta.json() as Promise<T>
}

export { API_URL }
