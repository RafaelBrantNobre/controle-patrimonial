import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { apiGet } from './services/api'

type StatusDaApi = { banco: { conectado: boolean } }

function App() {
  const [mostrarSenha, setMostrarSenha] = useState(false)
  const [mensagem, setMensagem] = useState('')
  const [conexao, setConexao] = useState('Verificando conexão com o Laravel…')

  useEffect(() => {
    let ativo = true
    apiGet<StatusDaApi>('/status')
      .then((status) => {
        if (ativo) setConexao(status.banco.conectado
          ? 'Laravel e banco de dados conectados'
          : 'Laravel disponível · banco de dados sem conexão')
      })
      .catch(() => {
        if (ativo) setConexao('API indisponível · confira se o Laravel está rodando')
      })
    return () => { ativo = false }
  }, [])

  function entrar(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    // A API ainda não possui uma rota de autenticação.
    // Nunca valide credenciais ou guarde senhas no front-end.
    setMensagem('Formulário validado! Este exemplo ainda não autentica usuários. O próximo passo é conectar o envio a uma rota de login no Laravel.')
  }

  const campo = 'mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-teal-700 focus:ring-4 focus:ring-teal-700/10'

  return (
    <main className="min-h-dvh bg-[#f6f8f7] text-slate-900 lg:grid lg:grid-cols-2">
      <section className="relative flex flex-col justify-between overflow-hidden bg-[#103e39] px-7 py-10 text-white sm:px-12 lg:min-h-dvh lg:px-16 lg:py-12">
        <div aria-hidden="true" className="pointer-events-none absolute -right-36 top-1/4 size-[520px] rounded-full border border-white/10 shadow-[0_0_0_65px_#ffffff04,0_0_0_130px_#ffffff04]" />
        <a href="/" className="relative flex w-fit items-center gap-3 rounded-md focus-visible:outline-2 focus-visible:outline-offset-8 focus-visible:outline-white">
          <span aria-hidden="true" className="grid size-11 place-items-center rounded-xl bg-white/10 ring-1 ring-white/20">
            <svg width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5"><path d="m12 3 9 5-9 5-9-5 9-5Z" /><path d="M3 8v9l9 5 9-5V8M12 13v9" /></svg>
          </span>
          <span className="text-sm font-semibold tracking-wide">CEAD <span className="font-normal text-teal-100/60">/ Unimontes</span></span>
        </a>
        <div className="relative max-w-lg py-16 lg:py-24">
          <p className="mb-5 text-xs font-semibold uppercase tracking-[0.22em] text-teal-200">Controle patrimonial</p>
          <h1 className="text-4xl font-semibold leading-[1.15] tracking-tight sm:text-5xl xl:text-6xl">Cada bem tem<br />seu lugar.<br /><span className="text-teal-200">Tudo sob controle.</span></h1>
          <p className="mt-7 max-w-sm text-base leading-7 text-teal-50/70">Organize os bens, acompanhe as movimentações e cuide do patrimônio da nossa instituição.</p>
          <div className="mt-10 flex items-center gap-3 text-sm text-teal-50/80"><span aria-hidden="true" className="h-px w-9 bg-teal-200/60" />Gestão que faz parte do cuidado.</div>
        </div>
        <p className="relative text-xs leading-5 text-teal-100/50">Centro de Educação a Distância<br />Universidade Estadual de Montes Claros</p>
      </section>
      <section className="flex flex-col justify-between px-6 py-10 sm:px-12 lg:px-16 lg:py-12">
        <p className="text-right text-xs text-slate-500">Sistema de gestão patrimonial</p>
        <div className="mx-auto w-full max-w-sm py-14 lg:py-16">
          <span className="mb-6 inline-flex rounded-full border border-teal-800/15 bg-teal-800/5 px-3 py-1 text-xs font-medium text-teal-800">Acesso institucional</span>
          <h2 className="text-3xl font-semibold tracking-tight">Boas-vindas de volta</h2>
          <p className="mt-3 text-sm leading-6 text-slate-500">Entre com suas credenciais para acessar o sistema.</p>
          <form onSubmit={entrar} className="mt-9 space-y-6">
            <div>
              <label htmlFor="email" className="text-sm font-medium">E-mail institucional</label>
              <input id="email" name="email" type="email" autoComplete="username" placeholder="seu.nome@unimontes.br" required className={campo} />
            </div>
            <div>
              <label htmlFor="senha" className="text-sm font-medium">Senha</label>
              <div className="relative">
                <input id="senha" name="senha" type={mostrarSenha ? 'text' : 'password'} autoComplete="current-password" placeholder="Digite sua senha" required className={`${campo} pr-24`} />
                <button type="button" aria-controls="senha" aria-pressed={mostrarSenha} onClick={() => setMostrarSenha(!mostrarSenha)} className="absolute inset-y-0 right-1 my-1 rounded-lg px-3 text-xs font-medium text-teal-800 hover:bg-teal-50 focus-visible:outline-2 focus-visible:outline-teal-700">{mostrarSenha ? 'Ocultar' : 'Mostrar'}</button>
              </div>
            </div>
            <button type="submit" className="flex w-full items-center justify-center gap-3 rounded-xl bg-teal-800 px-4 py-3.5 text-sm font-semibold text-white transition hover:bg-teal-900 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700">Entrar no sistema <span aria-hidden="true">→</span></button>
            {mensagem && <p role="status" className="rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm leading-6 text-teal-900">{mensagem}</p>}
          </form>
          <div className="mt-8 border-t border-slate-200 pt-6">
            <p className="text-xs leading-5 text-slate-500">Página de exemplo. A autenticação ainda não está habilitada.</p>
            <details className="mt-4 text-xs text-slate-500">
              <summary className="w-fit cursor-pointer rounded focus-visible:outline-2 focus-visible:outline-teal-700">Conexão com o back-end</summary>
              <p role="status" className="mt-3 leading-5">{conexao}</p>
            </details>
          </div>
        </div>
        <p className="text-center text-xs text-slate-400">CEAD / Unimontes · Controle Patrimonial</p>
      </section>
    </main>
  )
}

export default App
