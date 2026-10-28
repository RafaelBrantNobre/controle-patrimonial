import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'
import { apagarToken, apiGet, apiPost, definirAoPerderSessao, guardarToken, lerToken } from '../services/api'
import type { Usuario } from '../services/tipos'

type RespostaLogin = { token: string; expira_em: string; usuario: Usuario }

type ContextoAutenticacao = {
  usuario: Usuario | null
  // true enquanto confere, ao abrir, se o token guardado ainda vale.
  conferindo: boolean
  entrar: (email: string, senha: string, lembrar: boolean) => Promise<void>
  sair: () => Promise<void>
}

const Contexto = createContext<ContextoAutenticacao | null>(null)

export function ProvedorDeAutenticacao({ children }: { children: ReactNode }) {
  const [usuario, setUsuario] = useState<Usuario | null>(null)
  const [conferindo, setConferindo] = useState(() => lerToken() !== null)

  useEffect(() => {
    definirAoPerderSessao(() => setUsuario(null))

    if (!lerToken()) return

    apiGet<{ usuario: Usuario }>('/usuario')
      .then((resposta) => setUsuario(resposta.usuario))
      .catch(() => {
        apagarToken()
        setUsuario(null)
      })
      .finally(() => setConferindo(false))
  }, [])

  const entrar = useCallback(async (email: string, senha: string, lembrar: boolean) => {
    const resposta = await apiPost<RespostaLogin>('/login', { email, senha })
    guardarToken(resposta.token, lembrar)
    setUsuario(resposta.usuario)
  }, [])

  const sair = useCallback(async () => {
    // Mesmo se a API falhar, a pessoa sai: o token local é apagado.
    await apiPost('/logout').catch(() => {})
    apagarToken()
    setUsuario(null)
  }, [])

  const valor = useMemo(() => ({ usuario, conferindo, entrar, sair }), [usuario, conferindo, entrar, sair])

  return <Contexto.Provider value={valor}>{children}</Contexto.Provider>
}

// eslint-disable-next-line react/only-export-components
export function useAutenticacao() {
  const contexto = useContext(Contexto)
  if (!contexto) throw new Error('useAutenticacao precisa estar dentro de ProvedorDeAutenticacao')
  return contexto
}
