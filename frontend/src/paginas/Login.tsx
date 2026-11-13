import { Titulo, Entrada, Botao, Checkbox, LoginApresentacao, ThemeToggle } from '../components/ui'
import { Eye, EyeOff, Lock, LogIn, Mail, ShieldCheck } from 'lucide-react'
import { useId, useState, type FormEvent } from 'react'
import { Navigate, useLocation } from 'react-router'
import { useAutenticacao } from '../autenticacao/Autenticacao'
import { ErroDaApi } from '../services/api'

// Acesso institucional com a identidade visual compartilhada do projeto.
// Login por CPF e "Esqueci minha senha" não existem no back-end: o campo
// aceita só e-mail e o link avisa para procurar a gestora.
export function Login() {
  const { usuario, entrar } = useAutenticacao()
  const location = useLocation()
  const idSenha = useId()
  const [email, setEmail] = useState('')
  const [senha, setSenha] = useState('')
  const [lembrar, setLembrar] = useState(false)
  const [mostrarSenha, setMostrarSenha] = useState(false)
  const [enviando, setEnviando] = useState(false)
  const [erro, setErro] = useState<string | null>(null)
  const [aviso, setAviso] = useState<string | null>(null)

  if (usuario) {
    const destino = (location.state as { de?: string } | null)?.de ?? '/bens'
    return <Navigate to={destino} replace />
  }

  async function enviar(evento: FormEvent) {
    evento.preventDefault()
    setEnviando(true)
    setErro(null)
    try {
      await entrar(email.trim(), senha, lembrar)
    } catch (e) {
      setErro(e instanceof ErroDaApi ? e.message : 'Não foi possível entrar. Tente de novo.')
    } finally {
      setEnviando(false)
    }
  }

  return (
    <div className="login-pagina">
      <div className="login-atmosfera" aria-hidden="true" />
      <header className="login-topo">
        <span className="text-[11px] font-semibold tracking-[0.18em] uppercase">
          Unimontes <span className="mx-2 text-white/30">/</span> CEAD
        </span>
        <ThemeToggle />
      </header>
      <main className="login-conteudo">
        <div className="login-cartao">
          <LoginApresentacao />
          <form onSubmit={enviar} className="login-formulario">
            <div>
              <p className="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-cead-600 uppercase">
                <ShieldCheck className="size-4" /> Acesso institucional
              </p>
              <Titulo className="mt-2">Bem-vindo de volta</Titulo>
              <p className="mt-2 text-sm leading-relaxed text-slate-500">Entre com sua conta para acessar o patrimônio do CEAD.</p>
            </div>

            {erro && (
              <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
                {erro}
              </p>
            )}

            <label className="block">
              <span className="mb-1 block text-sm font-medium text-slate-700">E-mail Institucional</span>
              <span className="relative block">
                <Mail className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                <Entrada
                  type="email"
                  className="pl-9"
                  placeholder="seu.email@cead.unimontes.br"
                  autoComplete="username"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  required
                />
              </span>
            </label>

            <div className="block">
              <span className="mb-1 flex items-center justify-between text-sm font-medium text-slate-700">
                <label htmlFor={idSenha}>Senha de Acesso</label>
                <Botao
                  type="button"
                  className="text-xs font-semibold text-cead-600 hover:underline"
                  onClick={() => setAviso('Para trocar a senha, procure a gestora do patrimônio ou o suporte de TI.')}
                >
                  Esqueci minha senha
                </Botao>
              </span>
              <span className="relative block">
                <Lock className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                <Entrada
                  id={idSenha}
                  type={mostrarSenha ? 'text' : 'password'}
                  className="px-9"
                  placeholder="Insira sua senha"
                  autoComplete="current-password"
                  value={senha}
                  onChange={(e) => setSenha(e.target.value)}
                  required
                />
                <Botao
                  type="button"
                  className="absolute top-1/2 right-3 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                  onClick={() => setMostrarSenha((v) => !v)}
                  aria-label={mostrarSenha ? 'Esconder senha' : 'Mostrar senha'}
                >
                  {mostrarSenha ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                </Botao>
              </span>
            </div>

            {aviso && <p className="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">{aviso}</p>}

            <label className="flex items-center gap-2 text-sm text-slate-600">
              <Checkbox checked={lembrar} onChange={(e) => setLembrar(e.target.checked)} />
              Lembrar meu acesso
            </label>

            <Botao variante="primario" type="submit" className="py-3" disabled={enviando}>
              <LogIn className="size-4" />
              {enviando ? 'Entrando...' : 'Entrar no Sistema'}
            </Botao>
            <p className="login-acesso-restrito">
              <ShieldCheck className="size-3.5 shrink-0" />
              Acesso exclusivo a pessoas autorizadas.
            </p>
          </form>
        </div>
        <footer className="login-rodape">
          Universidade Estadual de Montes Claros <span aria-hidden="true">·</span> CEAD Patrimônio
        </footer>
      </main>
    </div>
  )
}
