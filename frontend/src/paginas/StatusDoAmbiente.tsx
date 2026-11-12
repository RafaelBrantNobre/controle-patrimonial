import { useEffect, useState } from 'react'
import { API_URL, apiGet } from '../services/api'

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

export function StatusDoAmbiente() {
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
    <main className="mx-auto max-w-xl px-4 py-12">
      <h1 className="text-2xl font-bold">Controle Patrimonial</h1>
      <p className="mt-1 mb-6 text-slate-500">Verificação do ambiente de desenvolvimento</p>

      <ul className="cartao divide-y divide-slate-200">
        <li className="flex flex-wrap justify-between gap-2 px-4 py-3">
          <span>Front-end</span>
          <strong className="text-emerald-700">no ar</strong>
        </li>
        <li className="flex flex-wrap justify-between gap-2 px-4 py-3">
          <span>Back-end ({API_URL})</span>
          {estado.fase === 'carregando' && <strong>consultando...</strong>}
          {estado.fase === 'ok' && <strong className="text-emerald-700">respondendo</strong>}
          {estado.fase === 'erro' && <strong className="text-red-700">sem resposta</strong>}
        </li>
        <li className="flex flex-wrap justify-between gap-2 px-4 py-3">
          <span>Banco de dados</span>
          {estado.fase === 'carregando' && <strong>consultando...</strong>}
          {estado.fase === 'ok' && estado.status.banco.conectado && (
            <strong className="text-emerald-700">
              conectado ({estado.status.banco.conexao}, {estado.status.banco.nome})
            </strong>
          )}
          {estado.fase === 'ok' && !estado.status.banco.conectado && (
            <strong className="text-red-700">sem conexão</strong>
          )}
          {estado.fase === 'erro' && <strong className="text-red-700">não foi possível verificar</strong>}
        </li>
      </ul>

      {estado.fase === 'erro' && (
        <p className="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-relaxed">
          Não foi possível falar com o back-end ({estado.mensagem}). Confira se o comando{' '}
          <code>php artisan serve</code> está rodando na pasta <code>backend</code> e se o endereço
          em <code>frontend/.env</code> está correto.
        </p>
      )}

      {estado.fase === 'ok' && !estado.status.banco.conectado && (
        <p className="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-relaxed">
          O back-end respondeu, mas não conseguiu conectar ao banco. Confira se o contêiner do MySQL
          está ligado (<code>docker compose ps</code> na pasta <code>backend</code>) e os dados de
          banco em <code>backend/.env</code>.
        </p>
      )}
    </main>
  )
}

