import { useEffect, useState } from 'react'
import { API_URL, apiGet } from './services/api'

type StatusDaApi = {
  aplicacao: string
  ambiente: string
  banco: {
    conectado: boolean
    conexao: string
    nome: string | null
  }
  horario: string
}

type Estado =
  | { fase: 'carregando' }
  | { fase: 'ok'; status: StatusDaApi }
  | { fase: 'erro'; mensagem: string }

function App() {
  const [estado, setEstado] = useState<Estado>({ fase: 'carregando' })

  useEffect(() => {
    apiGet<StatusDaApi>('/status')
      .then((status) => setEstado({ fase: 'ok', status }))
      .catch((erro: unknown) =>
        setEstado({
          fase: 'erro',
          mensagem: erro instanceof Error ? erro.message : 'erro desconhecido',
        }),
      )
  }, [])

  return (
    <main className="pagina">
      <h1>Controle Patrimonial</h1>
      <p className="subtitulo">Verificação do ambiente de desenvolvimento</p>

      <ul className="lista">
        <li>
          <span>Front-end</span>
          <strong className="ok">no ar</strong>
        </li>
        <li>
          <span>Back-end ({API_URL})</span>
          {estado.fase === 'carregando' && <strong>consultando...</strong>}
          {estado.fase === 'ok' && <strong className="ok">respondendo</strong>}
          {estado.fase === 'erro' && <strong className="erro">sem resposta</strong>}
        </li>
        <li>
          <span>Banco de dados</span>
          {estado.fase === 'carregando' && <strong>consultando...</strong>}
          {estado.fase === 'ok' && estado.status.banco.conectado && (
            <strong className="ok">
              conectado ({estado.status.banco.conexao}, {estado.status.banco.nome})
            </strong>
          )}
          {estado.fase === 'ok' && !estado.status.banco.conectado && (
            <strong className="erro">sem conexão</strong>
          )}
          {estado.fase === 'erro' && <strong className="erro">não foi possível verificar</strong>}
        </li>
      </ul>

      {estado.fase === 'erro' && (
        <p className="aviso">
          Não foi possível falar com o back-end ({estado.mensagem}). Confira se o comando{' '}
          <code>php artisan serve</code> está rodando na pasta <code>backend</code> e se o endereço
          em <code>frontend/.env</code> está correto.
        </p>
      )}

      {estado.fase === 'ok' && !estado.status.banco.conectado && (
        <p className="aviso">
          O back-end respondeu, mas não conseguiu conectar ao banco. Confira se o contêiner do MySQL
          está ligado (<code>docker compose ps</code> na pasta <code>backend</code>) e os dados de
          banco em <code>backend/.env</code>.
        </p>
      )}
    </main>
  )
}

export default App
