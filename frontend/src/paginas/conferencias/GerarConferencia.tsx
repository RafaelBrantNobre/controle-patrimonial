import { ClipboardCheck } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { FiltrosDeBens } from '../../componentes/FiltrosDeBens'
import { Aviso, Campo, Carregando, Paginacao, SeloConferencia, SeloStatus } from '../../componentes/ui'
import { useFiltrosDeBens } from '../../componentes/useFiltrosDeBens'
import { apiPost, consulta, ErroDaApi } from '../../services/api'
import { formatarData, hojeIso, plural } from '../../services/formatos'
import type { Bem, Conferencia, Pagina } from '../../services/tipos'
import { useApi } from '../../services/useApi'

type RespostaGerar = {
  data: Conferencia
  incluidos: number
  ignorados: { baixados: number; em_outra_conferencia: number }
}

// Bem baixado e bem que já está numa conferência aberta não entram (a API
// tira os dois). A caixinha deles fica desligada.
function podeEntrar(bem: Bem) {
  return !bem.baixado && !bem.em_conferencia
}

// Gerar conferência: a lista de bens com caixinhas e os mesmos filtros.
// Dá para marcar bem a bem, a página inteira ou todos os bens do filtro
// (sem precisar passar página por página).
export function GerarConferencia() {
  const navegar = useNavigate()
  const { filtro, mudarFiltro, filtros, pagina } = useFiltrosDeBens()
  const [nome, setNome] = useState(`Conferência de ${formatarData(hojeIso())}`)
  const [selecionados, setSelecionados] = useState<Set<number>>(new Set())
  // "Todos do filtro" vale só para o filtro em que foi escolhido: guarda a
  // chave dele, e mudar o filtro desfaz a escolha sozinho.
  const [filtroDosTodos, setFiltroDosTodos] = useState<string | null>(null)
  const [erro, setErro] = useState<ErroDaApi | null>(null)
  const [gerando, setGerando] = useState(false)

  const chaveDoFiltro = JSON.stringify(filtros)
  const todosDoFiltro = filtroDosTodos === chaveDoFiltro
  const bens = useApi<Pagina<Bem>>(`/bens${consulta({ ...filtros, pagina, por_pagina: 50 })}`)
  const daPagina = bens.dados?.data.filter(podeEntrar) ?? []
  const paginaToda = daPagina.length > 0 && daPagina.every((bem) => selecionados.has(bem.id))
  const total = bens.dados?.meta.total ?? 0

  function alternar(bem: Bem) {
    setFiltroDosTodos(null)
    setSelecionados((atual) => {
      const novo = new Set(atual)
      if (novo.has(bem.id)) novo.delete(bem.id)
      else novo.add(bem.id)
      return novo
    })
  }

  function alternarPagina() {
    setFiltroDosTodos(null)
    setSelecionados((atual) => {
      const novo = new Set(atual)
      daPagina.forEach((bem) => (paginaToda ? novo.delete(bem.id) : novo.add(bem.id)))
      return novo
    })
  }

  async function gerar() {
    setGerando(true)
    setErro(null)
    try {
      const corpo = todosDoFiltro
        ? {
            nome: nome.trim(),
            modo: 'filtro',
            filtros: {
              ...filtros,
              andar_id: filtros.andar_id ? Number(filtros.andar_id) : undefined,
              sala_id: filtros.sala_id ? Number(filtros.sala_id) : undefined,
              tipo_patrimonio_id: filtros.tipo_patrimonio_id ? Number(filtros.tipo_patrimonio_id) : undefined,
              status: filtros.status ? [filtros.status] : undefined,
            },
          }
        : { nome: nome.trim(), modo: 'selecionados', bem_ids: [...selecionados] }
      const resposta = await apiPost<RespostaGerar>('/conferencias', corpo)

      const foraBaixados = resposta.ignorados.baixados
      const foraEmOutra = resposta.ignorados.em_outra_conferencia
      const fora = [
        foraBaixados > 0 && `${foraBaixados} baixado${foraBaixados > 1 ? 's' : ''}`,
        foraEmOutra > 0 && `${foraEmOutra} que já ${foraEmOutra > 1 ? 'estão' : 'está'} em outra conferência aberta`,
      ].filter(Boolean)
      navegar(`/conferencias/${resposta.data.id}`, {
        state: {
          mensagem: `Conferência gerada com ${plural(resposta.incluidos, 'bem', 'bens')}.${fora.length ? ` Ficaram de fora: ${fora.join(' e ')}.` : ''}`,
        },
      })
    } catch (e) {
      setErro(e instanceof ErroDaApi ? e : new ErroDaApi(0, 'Não foi possível gerar a conferência.'))
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } finally {
      setGerando(false)
    }
  }

  const quantos = todosDoFiltro ? total : selecionados.size

  return (
    <>
      <p className="text-xs text-slate-500">
        <Link to="/conferencias" className="hover:underline">
          Conferências
        </Link>{' '}
        / Gerar conferência
      </p>
      <h1 className="mt-1 text-2xl font-bold text-cead-900">Gerar conferência</h1>
      <p className="mt-1 mb-6 text-sm text-slate-500">
        Escolha os bens que serão conferidos. Use os filtros para chegar a uma sala, a um andar ou ao prédio inteiro.
      </p>

      {erro && (
        <div className="mb-4">
          <Aviso>{erro.message}</Aviso>
        </div>
      )}

      <section className="cartao mb-6 p-4">
        <Campo rotulo="Nome da conferência" obrigatorio erro={erro?.doCampo('nome')} ajuda='Ex.: "2º semestre de 2026" ou "Sala 05 - Suporte TI".'>
          <input className="entrada" maxLength={120} value={nome} onChange={(e) => setNome(e.target.value)} required />
        </Campo>
      </section>

      <FiltrosDeBens filtro={filtro} mudarFiltro={mudarFiltro} />

      <section className="cartao overflow-hidden">
        {total > 0 && (paginaToda || todosDoFiltro) && total > daPagina.length && (
          <div className="border-b border-cead-100 bg-cead-50 px-4 py-2 text-center text-sm text-cead-800">
            {todosDoFiltro ? (
              <>
                Todos os <strong>{total}</strong> bens deste filtro estão selecionados.{' '}
                <button
                  type="button"
                  className="font-semibold underline"
                  onClick={() => {
                    setFiltroDosTodos(null)
                    setSelecionados(new Set())
                  }}
                >
                  Limpar seleção
                </button>
              </>
            ) : (
              <>
                Os bens desta página estão selecionados.{' '}
                <button type="button" className="font-semibold underline" onClick={() => setFiltroDosTodos(chaveDoFiltro)}>
                  Selecionar todos os {total} bens do filtro
                </button>
              </>
            )}
          </div>
        )}

        {bens.erro && (
          <div className="p-4">
            <Aviso>{bens.erro}</Aviso>
          </div>
        )}

        <div className="overflow-x-auto">
          <table className="tabela">
            <thead>
              <tr>
                <th className="w-10">
                  <input
                    type="checkbox"
                    checked={todosDoFiltro || paginaToda}
                    onChange={alternarPagina}
                    disabled={todosDoFiltro || daPagina.length === 0}
                    aria-label="Selecionar os bens desta página"
                  />
                </th>
                <th>Tombamento</th>
                <th>Tipo do bem</th>
                <th>Localização física</th>
                <th>Situação</th>
                <th>Conferência</th>
              </tr>
            </thead>
            <tbody className={bens.carregando ? 'opacity-50' : ''}>
              {bens.dados?.data.map((bem) => {
                const pode = podeEntrar(bem)
                return (
                  <tr key={bem.id} className={pode ? 'hover:bg-slate-50' : 'text-slate-400'}>
                    <td>
                      <input
                        type="checkbox"
                        checked={pode && (todosDoFiltro || selecionados.has(bem.id))}
                        onChange={() => alternar(bem)}
                        disabled={!pode || todosDoFiltro}
                        title={pode ? undefined : bem.baixado ? 'Bem baixado não entra na conferência.' : 'Este bem já está numa conferência aberta.'}
                        aria-label={`Selecionar ${bem.numero_patrimonio ?? bem.codigo_interno}`}
                      />
                    </td>
                    <td className="font-mono text-xs whitespace-nowrap">
                      {bem.numero_patrimonio ?? 'sem patrimônio'}
                      <span className="block text-slate-400">{bem.codigo_interno}</span>
                    </td>
                    <td className="font-medium">{bem.tipo_patrimonio?.nome}</td>
                    <td>
                      {bem.sala?.nome}
                      <span className="block text-xs text-slate-500">{bem.sala?.andar?.nome}</span>
                    </td>
                    <td>
                      <SeloStatus status={bem.status} rotulo={bem.status_rotulo} />
                    </td>
                    <td>
                      <SeloConferencia bem={bem} />
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
          {bens.carregando && !bens.dados && <Carregando />}
          {bens.dados?.data.length === 0 && <Carregando texto="Nenhum bem encontrado com estes filtros." />}
        </div>

        {bens.dados && (
          <Paginacao
            pagina={bens.dados.meta.current_page}
            ultima={bens.dados.meta.last_page}
            total={bens.dados.meta.total}
            rotulo="bens encontrados"
            singular="bem encontrado"
            aoMudar={(nova) => mudarFiltro('pagina', String(nova))}
          />
        )}
      </section>

      <div className="sticky bottom-0 mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white/95 p-4 shadow-sm backdrop-blur">
        <p className="text-sm text-slate-600">
          <strong className="text-slate-800">{quantos}</strong> {quantos === 1 ? 'bem selecionado' : 'bens selecionados'}
          {todosDoFiltro && ' (todos do filtro)'}. Bens baixados e bens em outra conferência aberta ficam de fora.
        </p>
        <div className="flex gap-2">
          <Link to="/conferencias" className="botao-secundario">
            Cancelar
          </Link>
          <button type="button" className="botao-primario" onClick={() => void gerar()} disabled={gerando || quantos === 0 || !nome.trim()}>
            <ClipboardCheck className="size-4" /> {gerando ? 'Gerando...' : 'Gerar conferência'}
          </button>
        </div>
      </div>
    </>
  )
}
