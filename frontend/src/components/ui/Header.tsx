import { Landmark, LogOut } from 'lucide-react'
import { NavLink } from 'react-router'
import { Botao } from './Botao'
import { ThemeToggle } from './ThemeToggle'
import { Tooltip } from './Tooltip'
export type ItemMenu = { para: string; rotulo: string }
function iniciais(nome: string) {
  const partes = nome.trim().split(/\s+/)
  return ((partes[0]?.[0] ?? '') + (partes.length > 1 ? partes[partes.length - 1][0] : '')).toUpperCase()
}
export function Header({
  usuario,
  menu,
  aoSair,
}: {
  usuario: { nome: string; perfil_rotulo: string }
  menu: ItemMenu[]
  aoSair: () => void
}) {
  return (
    <header className="nao-imprimir bg-cead-900 text-white">
      <div className="mx-auto flex max-w-7xl flex-wrap items-center gap-x-8 gap-y-2 px-4 py-3 sm:px-6">
        <NavLink to="/bens" className="flex shrink-0 items-center gap-2 text-sm font-bold tracking-wide">
          <span className="rounded-md border border-white/30 p-1">
            <Landmark className="size-4" />
          </span>
          CEAD PATRIMÔNIO
        </NavLink>

        <nav className="order-3 flex w-full flex-wrap gap-1 text-sm lg:order-none lg:w-auto lg:flex-1">
          {menu.map((item) => (
            <NavLink
              key={item.para}
              to={item.para}
              className={({ isActive }) =>
                `rounded-md px-3 py-1.5 transition-colors ${isActive ? 'bg-white/15 font-semibold' : 'text-white/80 hover:text-white'}`
              }
            >
              {item.rotulo}
            </NavLink>
          ))}
        </nav>

        <div className="ml-auto flex items-center gap-3">
          <ThemeToggle />
          <span className="flex size-9 items-center justify-center rounded-lg bg-white/15 text-sm font-semibold">
            {iniciais(usuario.nome)}
          </span>
          <div className="text-sm leading-tight">
            <p className="font-semibold">{usuario.nome}</p>
            <p className="text-xs text-white/70">{usuario.perfil_rotulo}</p>
          </div>
          <Tooltip texto="Sair do sistema">
            <Botao
              type="button"
              onClick={() => aoSair()}
              className="rounded-md p-1.5 text-white/80 hover:bg-white/10 hover:text-white"

              aria-label="Sair do sistema"
            >
              <LogOut className="size-4" />
            </Botao>
          </Tooltip>
        </div>
      </div>
    </header>
  )
}
