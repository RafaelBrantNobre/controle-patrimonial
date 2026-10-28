import type { ReactNode } from 'react'
import { BrowserRouter, Navigate, Route, Routes, useLocation } from 'react-router'
import { ProvedorDeAutenticacao, useAutenticacao } from './autenticacao/Autenticacao'
import { Layout } from './componentes/Layout'
import { Carregando } from './componentes/ui'
import { Login } from './paginas/Login'
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
            <Route path="*" element={<Navigate to="/bens" replace />} />
          </Route>
        </Routes>
      </BrowserRouter>
    </ProvedorDeAutenticacao>
  )
}

export default App
