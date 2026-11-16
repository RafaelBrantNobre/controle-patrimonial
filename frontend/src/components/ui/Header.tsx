import { ChartNoAxesCombined, ClipboardCheck, House, LayoutGrid, MapPin, Menu, Users, X, type LucideIcon } from 'lucide-react'
import { useId, useState } from 'react'
import { NavLink } from 'react-router'
import { Botao } from './Botao'
import { Logo } from './Logo'
import { PerfilUsuario } from './PerfilUsuario'
import { ThemeToggle } from './ThemeToggle'
export type ItemMenu = { para: string; rotulo: string }
const icones: Record<string, LucideIcon> = {
  '/bens': House,
  '/ambientes': MapPin,
  '/conferencias': ClipboardCheck,
  '/gerencia': LayoutGrid,
  '/relatorios': ChartNoAxesCombined,
  '/usuarios': Users,
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
  const [aberto, setAberto] = useState(false)
  const idMenu = useId()
  return (
    <header className="header-marca nao-imprimir">
      <a href="#conteudo" className="pular-conteudo">
        Pular para o conteúdo
      </a>
      <div className="mx-auto flex max-w-[1640px] flex-wrap items-center gap-x-5 gap-y-3 px-4 py-3 sm:px-6 xl:px-8">
        <NavLink to="/bens" className="shrink-0 rounded-lg" onClick={() => setAberto(false)}>
          <Logo className="w-36 sm:w-44" />
        </NavLink>
        <nav
          id={idMenu}
          aria-label="Navegação principal"
          className={`${aberto ? 'flex' : 'hidden'} order-last w-full flex-col gap-1 border-t border-slate-200 pt-3 md:order-none md:flex md:w-auto md:flex-1 md:flex-row md:justify-center md:border-0 md:pt-0 xl:gap-1`}
        >
          {menu.map((item) => {
            const Icone = icones[item.para] ?? LayoutGrid
            return (
              <NavLink
                key={item.para}
                to={item.para}
                onClick={() => setAberto(false)}
                title={item.rotulo}
                className={({ isActive }) => `item-menu ${isActive ? 'item-menu-ativo' : ''}`}
              >
                <Icone className="size-5 shrink-0" strokeWidth={1.8} />
                {/* Entre 768 e 1279px o menu mostra só os ícones; o nome fica no title e para leitor de tela. */}
                <span className="md:sr-only xl:not-sr-only">{item.rotulo}</span>
              </NavLink>
            )
          })}
        </nav>
        <div className="ml-auto flex items-center gap-3 border-l border-slate-200 pl-3 sm:gap-5 sm:pl-5">
          <ThemeToggle />
          <PerfilUsuario usuario={usuario} aoSair={aoSair} />
          <Botao
            className="controle-cabecalho md:hidden"
            aria-label={aberto ? 'Fechar menu' : 'Abrir menu'}
            aria-controls={idMenu}
            aria-expanded={aberto}
            onClick={() => setAberto(!aberto)}
          >
            {aberto ? <X className="size-5" /> : <Menu className="size-5" />}
          </Botao>
        </div>
      </div>
      <div className="faixa-marca" aria-hidden="true" />
    </header>
  )
}
