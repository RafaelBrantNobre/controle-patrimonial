import { Eye, EyeOff, Lock, LogIn, Mail, ShieldCheck } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Navigate, useLocation } from 'react-router'
import { useAutenticacao } from '../autenticacao/Autenticacao'
import { ErroDaApi } from '../services/api'

// Tela Login do wireframe (página "Definitivo" do Figma).
// Login por CPF e "Esqueci minha senha" não existem no back-end: o campo
// aceita só e-mail e o link avisa para procurar a gestora.
export function Login() {
  const { usuario, entrar } = useAutenticacao()
  const location = useLocation()
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
    <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-slate-800 via-cead-900 to-slate-900 p-4">
      <div className="grid w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl md:grid-cols-2">
        <section className="flex flex-col justify-center gap-5 bg-cead-900 p-10 text-white">
          <div>
            <p className="text-5xl font-black tracking-tight">CEAD</p>
            <p className="text-xs tracking-widest text-white/70 uppercase">Centro de Educação a Distância</p>
          </div>
          <span className="inline-flex w-fit items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs">
            <span className="size-1.5 rounded-full bg-emerald-400" />
            Módulo Integrado Patrimonial
          </span>
          <h2 className="text-2xl font-semibold">Gestão de Ativos &amp; Tombamento</h2>
          <p className="text-sm leading-relaxed text-white/75">
            Ambiente exclusivo para registro, conciliação, transferências e auditoria de bens permanentes e
            equipamentos do Centro de Educação a Distância.
          </p>
        </section>

        <form onSubmit={enviar} className="flex flex-col gap-5 p-10">
          <div>
            <p className="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-slate-500 uppercase">
              <ShieldCheck className="size-4" /> Faça seu login
            </p>
            <h1 className="mt-1 text-2xl font-bold text-cead-900">Acesso Restrito - Controle Patrimonial</h1>
          </div>

          {erro && <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{erro}</p>}

          <label className="block">
            <span className="mb-1 block text-sm font-medium text-slate-700">E-mail Institucional</span>
            <span className="relative block">
              <Mail className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
              <input
                type="email"
                className="entrada pl-9"
                placeholder="seu.email@cead.unimontes.br"
                autoComplete="username"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
              />
            </span>
          </label>

          <label className="block">
            <span className="mb-1 flex items-center justify-between text-sm font-medium text-slate-700">
              Senha de Acesso
              <button
                type="button"
                className="text-xs font-semibold text-cead-600 hover:underline"
                onClick={() => setAviso('Para trocar a senha, procure a gestora do patrimônio ou o suporte de TI.')}
              >
                Esqueci minha senha
              </button>
            </span>
            <span className="relative block">
              <Lock className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
              <input
                type={mostrarSenha ? 'text' : 'password'}
                className="entrada px-9"
                placeholder="Insira sua senha"
                autoComplete="current-password"
                value={senha}
                onChange={(e) => setSenha(e.target.value)}
                required
              />
              <button
                type="button"
                className="absolute top-1/2 right-3 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                onClick={() => setMostrarSenha((v) => !v)}
                aria-label={mostrarSenha ? 'Esconder senha' : 'Mostrar senha'}
              >
                {mostrarSenha ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
              </button>
            </span>
          </label>

          {aviso && <p className="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">{aviso}</p>}

          <label className="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" checked={lembrar} onChange={(e) => setLembrar(e.target.checked)} />
            Lembrar meu acesso
          </label>

          <button type="submit" className="botao-primario py-3" disabled={enviando}>
            <LogIn className="size-4" />
            {enviando ? 'Entrando...' : 'Entrar no Sistema'}
          </button>
        </form>
      </div>
    </div>
  )
}
