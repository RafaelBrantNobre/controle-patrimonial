import { Header } from '../components/ui'
import { Outlet } from 'react-router'
import { useAutenticacao } from '../autenticacao/Autenticacao'

// Menu do cabeçalho, como no wireframe. Painel de Gerência e Usuários só
// aparecem para o administrador (RF03); a regra que vale é a do back-end.
const MENU = [
  { para: '/bens', rotulo: 'Bens' },
  { para: '/ambientes', rotulo: 'Ambientes' },
  { para: '/conferencias', rotulo: 'Conferências' },
  { para: '/gerencia', rotulo: 'Painel de Gerência', soAdministrador: true },
  { para: '/relatorios', rotulo: 'Relatórios' },
  { para: '/usuarios', rotulo: 'Usuários', soAdministrador: true },
]

export function Layout() {
  const { usuario, sair } = useAutenticacao()
  if (!usuario) return null

  return (
    <div className="min-h-screen">
      <Header usuario={usuario} menu={MENU.filter((item) => !item.soAdministrador || usuario.pode_alterar)} aoSair={() => void sair()} />

      <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        <Outlet />
      </main>
    </div>
  )
}
