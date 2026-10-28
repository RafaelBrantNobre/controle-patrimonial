import { Landmark, LogOut } from 'lucide-react'
import { NavLink, Outlet } from 'react-router'
import { useAutenticacao } from '../autenticacao/Autenticacao'

// Menu do cabeçalho, como no wireframe. Painel de Gerência e Usuários só
// aparecem para o administrador (RF03); a regra que vale é a do back-end.
const MENU = [
  { para: '/bens', rotulo: 'Bens' },
  { para: '/ambientes', rotulo: 'Ambientes' },
  { para: '/gerencia', rotulo: 'Painel de Gerência', soAdministrador: true },
  { para: '/relatorios', rotulo: 'Relatórios' },
  { para: '/usuarios', rotulo: 'Usuários', soAdministrador: true },
]

function iniciais(nome: string) {
  const partes = nome.trim().split(/\s+/)
  return ((partes[0]?.[0] ?? '') + (partes.length > 1 ? partes[partes.length - 1][0] : '')).toUpperCase()
}

export function Layout() {
  const { usuario, sair } = useAutenticacao()
  if (!usuario) return null

  return (
    <div className="min-h-screen">
      <header className="nao-imprimir bg-cead-900 text-white">
        <div className="mx-auto flex max-w-7xl flex-wrap items-center gap-x-8 gap-y-2 px-4 py-3 sm:px-6">
          <NavLink to="/bens" className="flex items-center gap-2 text-sm font-bold tracking-wide">
            <span className="rounded-md border border-white/30 p-1">
              <Landmark className="size-4" />
            </span>
            CEAD PATRIMÔNIO
          </NavLink>

          <nav className="flex flex-1 flex-wrap gap-1 text-sm">
            {MENU.filter((item) => !item.soAdministrador || usuario.pode_alterar).map((item) => (
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

          <div className="flex items-center gap-3">
            <span className="flex size-9 items-center justify-center rounded-lg bg-white/15 text-sm font-semibold">
              {iniciais(usuario.nome)}
            </span>
            <div className="text-sm leading-tight">
              <p className="font-semibold">{usuario.nome}</p>
              <p className="text-xs text-white/70">{usuario.perfil_rotulo}</p>
            </div>
            <button
              type="button"
              onClick={() => void sair()}
              className="rounded-md p-1.5 text-white/80 hover:bg-white/10 hover:text-white"
              title="Sair do sistema"
              aria-label="Sair do sistema"
            >
              <LogOut className="size-4" />
            </button>
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        <Outlet />
      </main>
    </div>
  )
}
