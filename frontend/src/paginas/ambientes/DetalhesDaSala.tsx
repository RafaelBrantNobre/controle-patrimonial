import { ClipboardCheck, DoorOpen, History, Info, Layers, Package, Pencil } from 'lucide-react'
import { Link, useParams } from 'react-router'
import { useAutenticacao } from '../../autenticacao/Autenticacao'
import { AguardandoApi, Aviso, Carregando, SeloAtivo, SeloConservacao, SeloStatus } from '../../componentes/ui'
import type { Bem, Sala } from '../../services/tipos'
import { useApi } from '../../services/useApi'

// Detalhes da sala (wireframe): dados da sala e todos os bens dela, na ordem
// da conferência (por tipo e número de patrimônio).
// Fora do back-end por enquanto: capacidade, responsável, o registro da
// conferência da sala e o histórico de mudanças da sala.
// "Iniciar conferência" abre a folha de conferência da sala em Relatórios.
export function DetalhesDaSala() {
  const { id } = useParams()
  const { usuario } = useAutenticacao()
  const sala = useApi<{ data: Sala }>(`/salas/${id}`)
  const bens = useApi<{ data: Bem[]; total: number }>(`/salas/${id}/bens`)

  if (sala.erro) return <Aviso>{sala.erro}</Aviso>
  if (!sala.dados) return <Carregando />

  const dados = sala.dados.data
  const emUso = bens.dados?.data.filter((bem) => bem.status === 'em_uso').length ?? 0

  return (
    <>
      <p className="text-xs text-slate-500">
        <Link to="/ambientes" className="hover:underline">
          Ambientes
        </Link>{' '}
        / {dados.nome}
      </p>

      <section className="cartao mt-3 mb-6 p-6">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p className="flex items-center gap-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">
              <DoorOpen className="size-4" /> Detalhes do ambiente <SeloAtivo ativo={dados.ativo} />
            </p>
            <h1 className="mt-2 text-2xl font-bold text-cead-900">{dados.nome}</h1>
            <p className="mt-1 text-sm text-slate-500">Prédio Sede CEAD · {dados.andar?.nome}</p>
          </div>
          <div className="flex flex-wrap gap-2">
            {usuario?.pode_alterar && (
              <Link to={`/ambientes/${dados.id}/editar`} className="botao-secundario">
                <Pencil className="size-4" /> Editar sala
              </Link>
            )}
            <Link to={`/relatorios?sala_id=${dados.id}`} className="botao-primario">
              <ClipboardCheck className="size-4" /> Iniciar conferência
            </Link>
          </div>
        </div>
        <p className="mt-4 flex flex-wrap gap-4 border-t border-slate-100 pt-4 text-xs text-slate-500">
          <span>● {dados.bens_count ?? 0} bens alocados</span>
          <span>● {emUso} em uso</span>
        </p>
      </section>

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          <section className="cartao p-6">
            <h2 className="mb-4 flex items-center gap-2 font-semibold text-cead-900">
              <Info className="size-4" /> Informações da sala
            </h2>
            <dl className="grid gap-4 sm:grid-cols-3">
              <div>
                <dt className="rotulo">Andar</dt>
                <dd className="flex items-center gap-1.5 text-sm">
                  <Layers className="size-4 text-cead-700" /> {dados.andar?.nome}
                </dd>
              </div>
              <div>
                <dt className="rotulo">Bens alocados</dt>
                <dd className="flex items-center gap-1.5 text-sm">
                  <Package className="size-4 text-cead-700" /> {dados.bens_count ?? 0}
                </dd>
              </div>
              <div>
                <dt className="rotulo">Situação</dt>
                <dd>
                  <SeloAtivo ativo={dados.ativo} />
                </dd>
              </div>
            </dl>
            <div className="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
              <p className="rotulo">Observações técnicas</p>
              <p className="text-sm text-slate-700">{dados.descricao || 'Nenhuma observação cadastrada.'}</p>
            </div>
          </section>

          <section className="cartao overflow-hidden">
            <div className="flex items-center justify-between border-b border-slate-200 px-6 py-4">
              <h2 className="flex items-center gap-2 font-semibold text-cead-900">
                <Package className="size-4" /> Bens alocados
              </h2>
              <Link to={`/bens?sala_id=${dados.id}`} className="text-xs font-semibold text-cead-700 hover:underline">
                Ver na lista de bens
              </Link>
            </div>
            {bens.erro && (
              <div className="p-4">
                <Aviso>{bens.erro}</Aviso>
              </div>
            )}
            <div className="overflow-x-auto">
              <table className="tabela">
                <thead>
                  <tr>
                    <th>Tipo do bem</th>
                    <th>Tombamento</th>
                    <th>Situação</th>
                    <th>Conservação</th>
                  </tr>
                </thead>
                <tbody>
                  {bens.dados?.data.map((bem) => (
                    <tr key={bem.id} className="hover:bg-slate-50">
                      <td>
                        <Link to={`/bens/${bem.id}`} className="font-medium text-slate-800 hover:text-cead-700">
                          {bem.tipo_patrimonio?.nome}
                        </Link>
                      </td>
                      <td className="font-mono text-xs whitespace-nowrap">
                        {bem.numero_patrimonio ?? 'sem patrimônio'}
                        <span className="block text-slate-400">{bem.codigo_interno}</span>
                      </td>
                      <td>
                        <SeloStatus status={bem.status} rotulo={bem.status_rotulo} />
                      </td>
                      <td>
                        <SeloConservacao estado={bem.estado_conservacao} rotulo={bem.estado_conservacao_rotulo} />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
              {bens.carregando && <Carregando />}
              {bens.dados?.data.length === 0 && <Carregando texto="Nenhum bem nesta sala." />}
            </div>
            {bens.dados && <p className="px-6 py-3 text-xs text-slate-500">Exibindo {bens.dados.total} bens alocados</p>}
          </section>
        </div>

        <aside className="space-y-6">
          <section className="cartao space-y-3 p-5">
            <h2 className="flex items-center gap-2 font-semibold text-cead-900">
              <ClipboardCheck className="size-4" /> Conferência patrimonial
            </h2>
            <AguardandoApi>O registro da conferência (data, quem conferiu e itens conferidos) ainda não existe na API.</AguardandoApi>
          </section>
          <section className="cartao space-y-3 p-5">
            <h2 className="flex items-center gap-2 font-semibold text-cead-900">
              <History className="size-4" /> Histórico recente
            </h2>
            <AguardandoApi>O histórico da sala ainda não tem rota. As mudanças de sala de cada bem aparecem na ficha do bem.</AguardandoApi>
          </section>
        </aside>
      </div>
    </>
  )
}
