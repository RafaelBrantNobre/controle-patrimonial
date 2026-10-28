import type { ReactNode } from 'react'
import { BrowserRouter, Navigate, Route, Routes, useLocation } from 'react-router'
import { ProvedorDeAutenticacao, useAutenticacao } from './autenticacao/Autenticacao'
import { Layout } from './componentes/Layout'
import { Carregando } from './componentes/ui'
import { DetalhesDaSala } from './paginas/ambientes/DetalhesDaSala'
import { FormularioDeSala } from './paginas/ambientes/FormularioDeSala'
import { ListaDeSalas } from './paginas/ambientes/ListaDeSalas'
import { FichaDoBem } from './paginas/bens/FichaDoBem'
import { FormularioDeBem } from './paginas/bens/FormularioDeBem'
import { ListaDeBens } from './paginas/bens/ListaDeBens'
import { Login } from './paginas/Login'
import { PainelDeGerencia } from './paginas/PainelDeGerencia'
import { Relatorios } from './paginas/Relatorios'
import { FormularioDeUsuario } from './paginas/usuarios/FormularioDeUsuario'
import { ListaDeUsuarios } from './paginas/usuarios/ListaDeUsuarios'
import { StatusDoAmbiente } from './paginas/StatusDoAmbiente'

// Só deixa passar quem está conectado. Quem não está vai para o login e volta
// para a mesma página depois de entrar.
function Protegida({ children }: { children: ReactNode }) {
  const { usuario, conferindo } = useAutenticacao()
  const location = useLocation()

  if (conferindo) return <Carregando texto="Conferindo o acesso..." />
  if (!usuario) return <Navigate to="/login" replace state={{ de: location.pathname + location.search }} />
  return children
}

// Telas de cadastro e gestão: o perfil consulta volta para a lista de bens.
// Esconder a tela não basta; quem bloqueia de verdade é o back-end (RN10).
function SoAdministrador({ children }: { children: ReactNode }) {
  const { usuario } = useAutenticacao()
  if (!usuario?.pode_alterar) return <Navigate to="/bens" replace />
  return children
}

function App() {
  return (
    <ProvedorDeAutenticacao>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<Login />} />
          {/* Verificação do ambiente de desenvolvimento (README, 2.4). */}
          <Route path="/status" element={<StatusDoAmbiente />} />
          <Route
            element={
              <Protegida>
                <Layout />
              </Protegida>
            }
          >
            <Route path="/" element={<Navigate to="/bens" replace />} />
            <Route path="/bens" element={<ListaDeBens />} />
            <Route path="/bens/novo" element={<SoAdministrador><FormularioDeBem /></SoAdministrador>} />
            <Route path="/bens/:id" element={<FichaDoBem />} />
            <Route path="/bens/:id/editar" element={<SoAdministrador><FormularioDeBem /></SoAdministrador>} />
            <Route path="/ambientes" element={<ListaDeSalas />} />
            <Route path="/ambientes/novo" element={<SoAdministrador><FormularioDeSala /></SoAdministrador>} />
            <Route path="/ambientes/:id" element={<DetalhesDaSala />} />
            <Route path="/ambientes/:id/editar" element={<SoAdministrador><FormularioDeSala /></SoAdministrador>} />
            <Route path="/relatorios" element={<Relatorios />} />
            <Route path="/gerencia" element={<SoAdministrador><PainelDeGerencia /></SoAdministrador>} />
            <Route path="/usuarios" element={<SoAdministrador><ListaDeUsuarios /></SoAdministrador>} />
            <Route path="/usuarios/novo" element={<SoAdministrador><FormularioDeUsuario /></SoAdministrador>} />
            <Route path="*" element={<Navigate to="/bens" replace />} />
          </Route>
        </Routes>
      </BrowserRouter>
    </ProvedorDeAutenticacao>
  )
}

export default App
