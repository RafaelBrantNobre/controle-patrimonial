import { Briefcase, Copy, KeyRound, Mail, ShieldCheck, User, UserPlus, Wrench } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { AguardandoApi, Campo } from '../../componentes/ui'

const LETRAS = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789'

// Senha provisória com letras e números, como pede a regra de senha da API
// (pelo menos 10 caracteres, README 9.1.1). Gerada no navegador.
function gerarSenha(): string {
  const valores = crypto.getRandomValues(new Uint32Array(12))
  const senha = Array.from(valores, (valor) => LETRAS[valor % LETRAS.length]).join('')
  return /\d/.test(senha) && /[a-z]/i.test(senha) ? senha : gerarSenha()
}

// Cadastrar Novo Usuário (wireframe).
// O formulário está pronto, mas a API ainda não cadastra usuários (tarefa
// 3.3), então o botão fica desligado. "Operador" do wireframe corresponde
// ao perfil consulta da API (README, 9.1).
// Fora do back-end por enquanto: cargo ou função e a troca obrigatória de
// senha no primeiro acesso.
export function FormularioDeUsuario() {
  const [perfil, setPerfil] = useState<'consulta' | 'administrador'>('consulta')
  const [senha, setSenha] = useState(gerarSenha)
  const [pedirTroca, setPedirTroca] = useState(true)

  return (
    <form onSubmit={(e) => e.preventDefault()}>
      <p className="text-xs text-slate-500">
        <Link to="/usuarios" className="hover:underline">
          Usuários
        </Link>{' '}
        / Cadastrar novo usuário
      </p>

      <section className="cartao mt-3 space-y-6 p-6">
        <div className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
          <div>
            <h1 className="text-2xl font-bold text-cead-900">Cadastrar Novo Usuário</h1>
            <p className="mt-1 text-sm text-slate-500">Preencha os dados da pessoa para liberar o acesso ao sistema de patrimônio do CEAD.</p>
          </div>
          <span className="rounded-full bg-cead-50 px-3 py-1 text-xs font-semibold text-cead-700">CEAD / UNIMONTES</span>
        </div>

        <AguardandoApi>O cadastro de usuários ainda não existe na API (tarefa 3.3). O formulário fica pronto para quando a rota chegar.</AguardandoApi>

        <div className="space-y-4">
          <h2 className="font-semibold text-cead-900">
            <span className="mr-2 inline-flex size-6 items-center justify-center rounded-full bg-cead-800 text-xs text-white">1</span>
            Dados Pessoais e de Contato
          </h2>
          <Campo rotulo="Nome completo" obrigatorio>
            <span className="relative block">
              <User className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
              <input className="entrada pl-9" placeholder="ex: Renata Alencar" required />
            </span>
          </Campo>
          <div className="grid gap-4 sm:grid-cols-2">
            <Campo rotulo="E-mail de acesso" obrigatorio>
              <span className="relative block">
                <Mail className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                <input type="email" className="entrada pl-9" placeholder="usuario@cead.unimontes.br" required />
              </span>
            </Campo>
            <Campo rotulo="Cargo ou função no CEAD" ajuda="Ainda não guardado pela API.">
              <span className="relative block">
                <Briefcase className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                <input className="entrada pl-9" placeholder="ex: Técnico de Audiovisual, Apoio Administrativo" />
              </span>
            </Campo>
          </div>
        </div>

        <div className="grid gap-6 lg:grid-cols-2">
          <div className="space-y-3">
            <h2 className="font-semibold text-cead-900">
              <span className="mr-2 inline-flex size-6 items-center justify-center rounded-full bg-cead-800 text-xs text-white">2</span>
              Nível de Acesso
            </h2>
            {[
              { valor: 'consulta' as const, titulo: 'Operador', texto: 'Consulta e vistorias em campo', icone: Wrench },
              { valor: 'administrador' as const, titulo: 'Administrador', texto: 'Acesso total e gestão do sistema', icone: ShieldCheck },
            ].map((opcao) => (
              <label
                key={opcao.valor}
                className={`flex cursor-pointer items-center gap-3 rounded-lg border p-4 ${perfil === opcao.valor ? 'border-cead-600 bg-cead-50' : 'border-slate-200'}`}
              >
                <opcao.icone className="size-5 text-cead-700" />
                <span className="flex-1">
                  <span className="block font-semibold text-slate-800">{opcao.titulo}</span>
                  <span className="block text-xs text-slate-500">{opcao.texto}</span>
                </span>
                <input type="radio" name="perfil" checked={perfil === opcao.valor} onChange={() => setPerfil(opcao.valor)} />
              </label>
            ))}
          </div>

          <div className="space-y-3">
            <h2 className="font-semibold text-cead-900">
              <span className="mr-2 inline-flex size-6 items-center justify-center rounded-full bg-cead-800 text-xs text-white">3</span>
              Senha de Acesso Inicial
            </h2>
            <div className="rounded-lg border border-slate-200 p-4">
              <div className="mb-2 flex items-center justify-between">
                <span className="text-sm font-medium text-slate-700">Senha provisória</span>
                <button type="button" className="text-xs font-semibold text-cead-600 hover:underline" onClick={() => setSenha(gerarSenha())}>
                  Gerar automaticamente
                </button>
              </div>
              <span className="relative block">
                <KeyRound className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                <input className="entrada px-9 font-mono" value={senha} onChange={(e) => setSenha(e.target.value)} minLength={10} />
                <button
                  type="button"
                  className="absolute top-1/2 right-3 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                  onClick={() => void navigator.clipboard?.writeText(senha)}
                  title="Copiar senha"
                  aria-label="Copiar senha"
                >
                  <Copy className="size-4" />
                </button>
              </span>
              <label className="mt-3 flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" checked={pedirTroca} onChange={(e) => setPedirTroca(e.target.checked)} />
                Solicitar alteração de senha no primeiro acesso
              </label>
            </div>
          </div>
        </div>

        <div className="flex justify-end gap-2 border-t border-slate-100 pt-4">
          <Link to="/usuarios" className="botao-secundario">
            Cancelar
          </Link>
          <button type="submit" className="botao-primario" disabled title="Aguardando a gestão de usuários na API (tarefa 3.3)">
            <UserPlus className="size-4" /> Cadastrar Usuário
          </button>
        </div>
      </section>
    </form>
  )
}
