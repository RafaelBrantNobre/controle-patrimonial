import { Building2, ClipboardCheck, History } from 'lucide-react'
import { Logo } from './Logo'

const recursos = [
  { icone: Building2, rotulo: 'Bens e ambientes', cor: 'ciano' },
  { icone: ClipboardCheck, rotulo: 'Conferências', cor: 'verde' },
  { icone: History, rotulo: 'Histórico e rastreabilidade', cor: 'roxo' },
]

export function LoginApresentacao() {
  return (
    <section className="login-apresentacao">
      <div className="login-orbitas" aria-hidden="true">
        <span />
        <span />
        <span />
      </div>
      <div className="relative">
        <Logo sobreEscuro className="mx-auto w-56 sm:w-64" />
      </div>
      <div className="relative mt-8 sm:mt-10">
        <span className="login-etiqueta">
          <span />
          Gestão patrimonial integrada
        </span>
        <h2 className="mt-5 text-2xl leading-tight font-semibold tracking-tight sm:text-[28px]">
          Cuidar do patrimônio é cuidar da universidade.
        </h2>
        <p className="mt-4 max-w-sm text-sm leading-relaxed text-white/70">
          Bens, espaços e conferências em um só lugar. Mais organização para o dia a dia do CEAD.
        </p>
      </div>
      <ul className="relative mt-8 hidden space-y-3 md:block">
        {recursos.map(({ icone: Icone, rotulo, cor }) => (
          <li key={rotulo} className={`login-recurso tom-${cor}`}>
            <span>
              <Icone className="size-4" strokeWidth={1.7} />
            </span>
            {rotulo}
          </li>
        ))}
      </ul>
      <div className="login-paleta" aria-hidden="true">
        <i />
        <i />
        <i />
        <i />
        <i />
        <i />
      </div>
    </section>
  )
}
