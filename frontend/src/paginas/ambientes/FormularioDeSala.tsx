import { Building2, ClipboardList, Save, X } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { Aviso, Campo, Carregando, Desativado, SeloAtivo } from '../../componentes/ui'
import { apiPatch, apiPost, ErroDaApi } from '../../services/api'
import type { Pagina, Sala } from '../../services/tipos'
import { useApi } from '../../services/useApi'

type Dados = { nome: string; descricao: string; ativo: boolean }

// Cadastrar Ambiente / Sala (wireframe), também usado para editar.
// Fora do back-end por enquanto: código ou sigla, tipo de espaço e
// responsável pela sala. A descrição guarda a finalidade e as observações.
export function FormularioDeSala() {
  const { id } = useParams()
  const resposta = useApi<{ data: Sala }>(id !== undefined ? `/salas/${id}` : null)

  if (id === undefined) return <Formulario key="nova" />
  if (resposta.erro) return <Aviso>{resposta.erro}</Aviso>
  if (!resposta.dados) return <Carregando />
  return <Formulario key={id} sala={resposta.dados.data} />
}

function Formulario({ sala }: { sala?: Sala }) {
  const editando = sala !== undefined
  const navegar = useNavigate()
  const [dados, setDados] = useState<Dados>({
    nome: sala?.nome ?? '',
    descricao: sala?.descricao ?? '',
    ativo: sala?.ativo ?? true,
  })
  const [erro, setErro] = useState<ErroDaApi | null>(null)
  const [salvando, setSalvando] = useState(false)

  const recentes = useApi<Pagina<Sala>>('/salas?por_pagina=5')

  function mudar<C extends keyof Dados>(campo: C, valor: Dados[C]) {
    setDados((atual) => ({ ...atual, [campo]: valor }))
  }

  async function salvar(evento: FormEvent) {
    evento.preventDefault()
    setSalvando(true)
    setErro(null)
    const corpo = {
      nome: dados.nome.trim(),
      descricao: dados.descricao.trim() || null,
      ativo: dados.ativo,
    }
    try {
      const salva = editando
        ? await apiPatch<{ data: Sala }>(`/salas/${sala.id}`, corpo)
        : await apiPost<{ data: Sala }>('/salas', corpo)
      navegar(`/ambientes/${salva.data.id}`)
    } catch (e) {
      setErro(e instanceof ErroDaApi ? e : new ErroDaApi(0, 'Não foi possível salvar.'))
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } finally {
      setSalvando(false)
    }
  }

  return (
    <form onSubmit={salvar}>
      <p className="text-xs text-slate-500">
        <Link to="/ambientes" className="hover:underline">
          Ambientes
        </Link>{' '}
        / {editando ? 'Editar sala' : 'Nova sala ou espaço'}
      </p>
      <h1 className="mt-1 text-2xl font-bold text-cead-900">{editando ? 'Editar Ambiente / Sala' : 'Cadastrar Ambiente / Sala'}</h1>
      <p className="mt-1 mb-6 text-sm text-slate-500">
        {editando ? 'Atualize' : 'Adicione'} uma dependência do prédio do CEAD para organizar a alocação de bens patrimoniais e as
        conferências periódicas.
      </p>

      {erro && (
        <div className="mb-4">
          <Aviso>{erro.message}</Aviso>
        </div>
      )}

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          <section className="cartao space-y-4 p-6">
            <h2 className="font-semibold text-cead-900">
              <span className="mr-2 inline-flex size-6 items-center justify-center rounded-full bg-cead-800 text-xs text-white">1</span>
              Identificação do Espaço
            </h2>
            <Campo rotulo="Nome da sala / descrição" obrigatorio erro={erro?.doCampo('nome')} ajuda="Informe o nome como é reconhecido pela equipe.">
              <input className="entrada" maxLength={120} placeholder="ex: Estúdio 03 - Gravação de Podcasts" value={dados.nome} onChange={(e) => mudar('nome', e.target.value)} required />
            </Campo>
          </section>

          <section className="cartao space-y-4 p-6">
            <h2 className="font-semibold text-cead-900">
              <span className="mr-2 inline-flex size-6 items-center justify-center rounded-full bg-cead-800 text-xs text-white">2</span>
              Informações de Uso e Observações
            </h2>
            <Campo rotulo="Finalidade principal ou observações técnicas" erro={erro?.doCampo('descricao')} ajuda="Até 255 caracteres.">
              <textarea
                className="entrada min-h-24"
                maxLength={255}
                placeholder="ex: Espaço climatizado com isolamento acústico; recebe preferencialmente equipamentos de áudio, gravação e edição de vídeo."
                value={dados.descricao}
                onChange={(e) => mudar('descricao', e.target.value)}
              />
            </Campo>

            <div>
              <span className="rotulo">Status operacional do ambiente</span>
              <div className="grid gap-3 sm:grid-cols-2">
                {[
                  { ativo: true, titulo: 'Ativo para uso', texto: 'Pronto para receber bens, rotações e auditorias.' },
                  { ativo: false, titulo: 'Em reforma / Desativado', texto: 'Não recebe bens novos. Os bens que estão nela continuam.' },
                ].map((opcao) => (
                  <label
                    key={String(opcao.ativo)}
                    className={`flex cursor-pointer gap-2 rounded-lg border p-3 text-sm ${
                      dados.ativo === opcao.ativo ? (opcao.ativo ? 'border-emerald-400 bg-emerald-50' : 'border-amber-400 bg-amber-50') : 'border-slate-200'
                    }`}
                  >
                    <input type="radio" name="ativo" checked={dados.ativo === opcao.ativo} onChange={() => mudar('ativo', opcao.ativo)} />
                    <span>
                      <span className="block font-semibold">{opcao.titulo}</span>
                      <span className="block text-xs text-slate-500">{opcao.texto}</span>
                    </span>
                  </label>
                ))}
              </div>
              {erro?.doCampo('ativo') && <span className="mt-1 block text-xs text-red-600">{erro.doCampo('ativo')}</span>}
            </div>

            {/* Desativado: Código ou sigla, tipo de espaço e responsável pela sala ainda não existem na API. */}
            <Desativado />
          </section>
        </div>

        <aside className="space-y-6">
          <section className="cartao p-5">
            <h2 className="mb-3 flex items-center gap-2 font-semibold text-cead-900">
              <ClipboardList className="size-4" /> Salas cadastradas
            </h2>
            <ul className="space-y-2">
              {recentes.dados?.data.map((item) => (
                <li key={item.id} className="flex items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2 text-sm">
                  <span className="font-medium text-slate-800">{item.nome}</span>
                  <SeloAtivo ativo={item.ativo} />
                </li>
              ))}
            </ul>
            {recentes.dados && (
              <Link to="/ambientes" className="mt-3 block text-xs font-semibold text-cead-700 hover:underline">
                Ver todas as {recentes.dados.meta.total} salas
              </Link>
            )}
          </section>

          <section className="cartao p-5">
            <h2 className="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">Prévia do registro</h2>
            <p className="flex items-center gap-2 font-semibold text-slate-800">
              <Building2 className="size-4 text-cead-700" />
              {dados.nome.trim() || 'Nome do espaço a preencher...'}
            </p>
          </section>
        </aside>
      </div>

      <div className="mt-6 flex flex-wrap items-center justify-between gap-3">
        <Link to={editando ? `/ambientes/${sala.id}` : '/ambientes'} className="botao-secundario">
          <X className="size-4" /> Cancelar
        </Link>
        <button type="submit" className="botao-primario" disabled={salvando}>
          <Save className="size-4" /> {salvando ? 'Salvando...' : editando ? 'Salvar alterações' : 'Salvar e Criar Sala'}
        </button>
      </div>
    </form>
  )
}
