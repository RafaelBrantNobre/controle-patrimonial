import { Plus, ScanLine, ShieldCheck, Users } from 'lucide-react'
import { Link } from 'react-router'
import { useAutenticacao } from '../../autenticacao/Autenticacao'
import { Cabecalho, Desativado, Indicador, Selo } from '../../componentes/ui'

// Usuários do Sistema (wireframe).
// A API ainda não lista nem gerencia usuários (tarefa 3.3): ela só diz quem
// está conectado (GET /usuario). Por isso a tabela mostra apenas a pessoa
// conectada, e os indicadores ficam em branco até a rota existir.
export function ListaDeUsuarios() {
  const { usuario } = useAutenticacao()
  if (!usuario) return null

  return (
    <>
      <Cabecalho
        titulo="Equipe e Usuários do Sistema"
        subtitulo="Gestão simplificada das permissões de acesso dos operadores internos do CEAD."
        acoes={
          <Link to="/usuarios/novo" className="botao-primario">
            <Plus className="size-4" /> Adicionar Usuário
          </Link>
        }
      />

      <div className="mb-6 grid gap-4 sm:grid-cols-3">
        <Indicador titulo="Total de usuários" valor="—" icone={Users} />
        <Indicador titulo="Acesso total" valor="—" detalhe="administradores" icone={ShieldCheck} />
        <Indicador titulo="Operadores de vistoria" valor="—" detalhe="perfil consulta" icone={ScanLine} />
      </div>

      <div className="mb-6">
        {/* Desativado: A lista de usuários, o cadastro e a edição dependem da gestão de usuários na API (tarefa 3.3). Até lá, os usuários são criados no servidor com <code>php artisan usuario:criar-admin</code>. */}
        <Desativado />
      </div>

      <section className="cartao overflow-hidden">
        <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
          <h2 className="text-sm font-semibold text-slate-700">Usuários autorizados</h2>
          <span className="text-xs text-slate-500">Acesso restrito à equipe interna do CEAD</span>
        </div>
        <div className="overflow-x-auto">
          <table className="tabela">
            <thead>
              <tr>
                <th>Nome / Usuário</th>
                <th>E-mail institucional</th>
                <th>Nível de acesso</th>
                <th>Situação</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>
                  <p className="font-medium text-slate-800">{usuario.nome}</p>
                  <p className="text-xs text-slate-500">você</p>
                </td>
                <td className="font-mono text-xs">{usuario.email}</td>
                <td>
                  <Selo cor="azul">{usuario.pode_alterar ? 'Acesso total / Administrador' : 'Operador / Consulta'}</Selo>
                </td>
                <td>
                  <Selo cor="verde">Ativo</Selo>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p className="px-4 py-3 text-xs text-slate-500">Exibindo só o usuário conectado</p>
      </section>
    </>
  )
}
