import { Titulo, Cartao, LinkBotao, RolagemTabela, Tabela, Aviso, Carregando, Desativado } from '../../components/ui'
import { ClipboardCheck, DoorOpen, History, Info, Package, Pencil } from 'lucide-react'
import { Link, useNavigate, useParams } from 'react-router'
import { useAutenticacao } from '../../autenticacao/Autenticacao'
import { BotaoExcluir } from '../../componentes/Exclusao'
import { SeloAtivo, SeloConferencia, SeloConservacao, SeloStatus } from '../../componentes/SelosDeBem'
import { apiDelete, apiGet } from '../../services/api'
import { formatarData } from '../../services/formatos'
import type { Bem, Sala } from '../../services/tipos'
import { useApi } from '../../services/useApi'

// Detalhes da sala (wireframe): dados da sala e todos os bens dela, na ordem
// da conferência (por tipo e número de patrimônio).
// O cartão de conferência resume os bens da sala pela última conferência.
// O botão "Conferências" leva à tela onde a conferência é iniciada ou continuada.
// Fora do back-end por enquanto: capacidade, responsável e o histórico de
// mudanças da sala.
export function DetalhesDaSala() {
  const { id } = useParams()
  const { usuario } = useAutenticacao()
  const navegar = useNavigate()
  const sala = useApi<{ data: Sala }>(`/salas/${id}`)
  const bens = useApi<{ data: Bem[]; total: number }>(`/salas/${id}/bens`)

  if (sala.erro) return <Aviso>{sala.erro}</Aviso>
  if (!sala.dados) return <Carregando />

  const dados = sala.dados.data
  const lista = bens.dados?.data ?? []
  const emUso = lista.filter((bem) => bem.status === 'em_uso').length
  const conferidos = lista.filter((bem) => bem.resultado_ultima_conferencia === 'encontrado').length
  const naoLocalizados = lista.filter((bem) => bem.resultado_ultima_conferencia === 'nao_localizado').length
  const nuncaConferidos = lista.filter((bem) => !bem.data_ultima_conferencia).length
  const emConferencia = lista.filter((bem) => bem.em_conferencia).length
  const ultimaConferencia = lista.reduce<string | null>(
    (maior, bem) => (bem.data_ultima_conferencia && (!maior || bem.data_ultima_conferencia > maior) ? bem.data_ultima_conferencia : maior),
    null,
  )

  return (
    <>
      <p className="text-xs text-slate-500">
        <Link to="/ambientes" className="hover:underline">
          Ambientes
        </Link>{' '}
        / {dados.nome}
      </p>

      <Cartao as="section" className="mt-3 mb-6 p-6">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p className="flex items-center gap-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">
              <DoorOpen className="size-4" /> Detalhes do ambiente <SeloAtivo ativo={dados.ativo} />
            </p>
            <Titulo className="mt-2">{dados.nome}</Titulo>
            <p className="mt-1 text-sm text-slate-500">Prédio Sede CEAD</p>
          </div>
          <div className="flex flex-wrap gap-2">
            {usuario?.pode_alterar && (
              <LinkBotao variante="secundario" to={`/ambientes/${dados.id}/editar`}>
                <Pencil className="size-4" /> Editar sala
              </LinkBotao>
            )}
            {usuario?.pode_alterar && (
              <BotaoExcluir
                rotulo="a sala"
                nome={dados.nome}
                nomeDoVinculo={{ singular: 'bem', plural: 'bens' }}
                buscarVinculos={async () => {
                  const resposta = await apiGet<{ data: Bem[]; total: number }>(`/salas/${dados.id}/bens`)
                  return {
                    total: resposta.total,
                    itens: resposta.data.map((bem) => ({
                      id: bem.id,
                      titulo: `${bem.numero_patrimonio ?? 'sem patrimônio'} · ${bem.tipo_patrimonio?.nome ?? ''}`,
                      detalhe: bem.codigo_interno,
                      para: `/bens/${bem.id}`,
                    })),
                  }
                }}
                excluir={() => apiDelete(`/salas/${dados.id}`)}
                aoExcluir={() => navegar('/ambientes', { replace: true })}
              />
            )}
            <LinkBotao variante="primario" to="/conferencias">
              <ClipboardCheck className="size-4" /> Conferências
            </LinkBotao>
          </div>
        </div>
        <p className="mt-4 flex flex-wrap gap-4 border-t border-slate-100 pt-4 text-xs text-slate-500">
          <span>● {dados.bens_count ?? 0} bens alocados</span>
          <span>● {emUso} em uso</span>
        </p>
      </Cartao>

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          <Cartao as="section" className="p-6">
            <h2 className="mb-4 flex items-center gap-2 font-semibold text-cead-900">
              <Info className="size-4" /> Informações da sala
            </h2>
            <dl className="grid gap-4 sm:grid-cols-3">
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
          </Cartao>

          <Cartao as="section" className="overflow-hidden">
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
            <RolagemTabela>
              <Tabela>
                <thead>
                  <tr>
                    <th>Tipo do bem</th>
                    <th>Tombamento</th>
                    <th>Situação</th>
                    <th>Conservação</th>
                    <th>Conferência</th>
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
                      <td>
                        <SeloConferencia bem={bem} />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </Tabela>
              {bens.carregando && <Carregando />}
              {bens.dados?.data.length === 0 && <Carregando texto="Nenhum bem nesta sala." />}
            </RolagemTabela>
            {bens.dados && <p className="px-6 py-3 text-xs text-slate-500">Exibindo {bens.dados.total} bens alocados</p>}
          </Cartao>
        </div>

        <aside className="space-y-6">
          <Cartao as="section" className="space-y-3 p-5">
            <h2 className="flex items-center gap-2 font-semibold text-cead-900">
              <ClipboardCheck className="size-4" /> Conferência patrimonial
            </h2>
            {bens.dados && (
              <dl className="space-y-2 text-sm">
                <div className="flex justify-between">
                  <dt className="text-slate-500">Última conferência</dt>
                  <dd className="font-medium text-slate-800">{ultimaConferencia ? formatarData(ultimaConferencia) : 'nenhuma'}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-slate-500">Encontrados</dt>
                  <dd className="font-medium text-emerald-700">
                    {conferidos} de {lista.length}
                  </dd>
                </div>
                {naoLocalizados > 0 && (
                  <div className="flex justify-between">
                    <dt className="text-slate-500">Não localizados</dt>
                    <dd className="font-medium text-red-700">{naoLocalizados}</dd>
                  </div>
                )}
                <div className="flex justify-between">
                  <dt className="text-slate-500">Nunca conferidos</dt>
                  <dd className="font-medium text-slate-800">{nuncaConferidos}</dd>
                </div>
                {emConferencia > 0 && (
                  <div className="flex justify-between">
                    <dt className="text-slate-500">Em conferência aberta</dt>
                    <dd className="font-medium text-cead-700">{emConferencia}</dd>
                  </div>
                )}
              </dl>
            )}
          </Cartao>
          <Cartao as="section" className="space-y-3 p-5">
            <h2 className="flex items-center gap-2 font-semibold text-cead-900">
              <History className="size-4" /> Histórico recente
            </h2>
            {/* Desativado: O histórico da sala ainda não tem rota. As mudanças de sala de cada bem aparecem na ficha do bem. */}
            <Desativado />
          </Cartao>
        </aside>
      </div>
    </>
  )
}
