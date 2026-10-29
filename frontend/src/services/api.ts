// Ponto único de acesso à API do back-end.
// O endereço vem do arquivo .env (variável VITE_API_URL).
const API_URL = (import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api').replace(/\/+$/, '')

// O token do login fica no localStorage quando a pessoa marca "Lembrar meu
// acesso" e no sessionStorage quando não marca (some ao fechar o navegador).
const CHAVE_TOKEN = 'controle-patrimonial:token'

export function lerToken(): string | null {
  return localStorage.getItem(CHAVE_TOKEN) ?? sessionStorage.getItem(CHAVE_TOKEN)
}

export function guardarToken(token: string, lembrar: boolean) {
  apagarToken()
  ;(lembrar ? localStorage : sessionStorage).setItem(CHAVE_TOKEN, token)
}

export function apagarToken() {
  localStorage.removeItem(CHAVE_TOKEN)
  sessionStorage.removeItem(CHAVE_TOKEN)
}

// Chamado quando a API responde 401 (token vencido ou apagado). O contexto de
// login troca esta função para limpar o usuário e voltar à tela de login.
let aoPerderSessao = () => {}

export function definirAoPerderSessao(funcao: () => void) {
  aoPerderSessao = funcao
}

// Erro com a resposta da API: a mensagem já vem em português (README, 9.2).
export class ErroDaApi extends Error {
  status: number
  erros: Record<string, string[]>

  constructor(status: number, mensagem: string, erros: Record<string, string[]> = {}) {
    super(mensagem)
    this.status = status
    this.erros = erros
  }

  // Primeira mensagem de um campo, para mostrar ao lado dele.
  doCampo(campo: string): string | undefined {
    return this.erros[campo]?.[0]
  }
}

async function requisitar(metodo: string, caminho: string, corpo?: unknown): Promise<Response> {
  const token = lerToken()
  const cabecalhos: Record<string, string> = { Accept: 'application/json' }

  if (token) cabecalhos.Authorization = `Bearer ${token}`
  if (corpo !== undefined) cabecalhos['Content-Type'] = 'application/json'

  let resposta: Response
  try {
    resposta = await fetch(`${API_URL}${caminho}`, {
      method: metodo,
      headers: cabecalhos,
      body: corpo === undefined ? undefined : JSON.stringify(corpo),
    })
  } catch {
    throw new ErroDaApi(0, 'Não foi possível falar com o servidor. Confira a conexão e tente de novo.')
  }

  if (resposta.ok) return resposta

  const dados = await resposta.json().catch(() => ({}))

  if (resposta.status === 401 && token) {
    apagarToken()
    aoPerderSessao()
  }

  const erros = (dados.errors ?? {}) as Record<string, string[]>
  const mensagem =
    Object.values(erros)[0]?.[0] ?? dados.message ?? `A API respondeu com o código ${resposta.status}`

  throw new ErroDaApi(resposta.status, mensagem, erros)
}

export async function apiGet<T>(caminho: string): Promise<T> {
  return (await requisitar('GET', caminho)).json() as Promise<T>
}

export async function apiPost<T>(caminho: string, corpo?: unknown): Promise<T> {
  return (await requisitar('POST', caminho, corpo ?? {})).json() as Promise<T>
}

export async function apiPatch<T>(caminho: string, corpo: unknown): Promise<T> {
  return (await requisitar('PATCH', caminho, corpo)).json() as Promise<T>
}

export async function apiDelete<T>(caminho: string): Promise<T> {
  return (await requisitar('DELETE', caminho)).json() as Promise<T>
}

// Baixa um arquivo (a planilha do relatório) com o token e entrega ao
// navegador com o nome que vem em Content-Disposition.
export async function apiBaixar(caminho: string, nomePadrao: string) {
  const resposta = await requisitar('GET', caminho)
  const disposicao = resposta.headers.get('Content-Disposition') ?? ''
  const nome = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(disposicao)?.[1] ?? nomePadrao

  const url = URL.createObjectURL(await resposta.blob())
  const link = document.createElement('a')
  link.href = url
  link.download = decodeURIComponent(nome)
  link.click()
  URL.revokeObjectURL(url)
}

// Monta a query string, deixando de fora os filtros vazios.
export function consulta(parametros: Record<string, string | number | boolean | undefined | null>) {
  const busca = new URLSearchParams()
  for (const [chave, valor] of Object.entries(parametros)) {
    if (valor === undefined || valor === null || valor === '') continue
    busca.set(chave, typeof valor === 'boolean' ? (valor ? '1' : '0') : String(valor))
  }
  const texto = busca.toString()
  return texto ? `?${texto}` : ''
}

export { API_URL }
