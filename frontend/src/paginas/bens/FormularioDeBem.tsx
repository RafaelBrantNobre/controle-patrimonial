import { Camera, CheckCircle2, MapPin, RotateCcw, Save, Tag } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { Aviso, Campo, Carregando, Desativado } from '../../componentes/ui'
import { apiPatch, apiPost, consulta, ErroDaApi } from '../../services/api'
import {
  ROTULOS_CONSERVACAO,
  type Bem,
  type EstadoConservacao,
  type Pagina,
  type Sala,
  type TipoPatrimonio,
} from '../../services/tipos'
import { useApi } from '../../services/useApi'

type Dados = {
  numero_patrimonio: string
  tipo_patrimonio_id: string
  descricao: string
  marca: string
  origem: string
  quantidade: string
  sala_id: string
  status: 'em_uso' | 'sem_uso'
  estado_conservacao: EstadoConservacao
}

const VAZIO: Dados = {
  numero_patrimonio: '',
  tipo_patrimonio_id: '',
  descricao: '',
  marca: '',
  origem: '',
  quantidade: '1',
  sala_id: '',
  status: 'em_uso',
  estado_conservacao: 'indeterminado',
}

function dadosDoBem(bem: Bem): Dados {
  return {
    numero_patrimonio: bem.numero_patrimonio ?? '',
    tipo_patrimonio_id: String(bem.tipo_patrimonio_id),
    descricao: bem.descricao ?? '',
    marca: bem.marca ?? '',
    origem: bem.origem ?? '',
    quantidade: String(bem.quantidade),
    sala_id: String(bem.sala_id ?? ''),
    status: bem.status === 'sem_uso' ? 'sem_uso' : 'em_uso',
    estado_conservacao: bem.estado_conservacao,
  }
}

// Cadastro de Bem Patrimonial (wireframe), também usado para editar.
// Na edição, sala e situação não mudam aqui: são operações próprias, na
// ficha do bem (README, 11.2 e 12).
// Fora do back-end por enquanto: modelo, número de série, foto e impressão
// da etiqueta. As situações "em manutenção" e "danificado" do wireframe
// viram o estado de conservação, que é como o back-end registra (README, 11.2).
export function FormularioDeBem() {
  const { id } = useParams()
  const resposta = useApi<{ data: Bem }>(id !== undefined ? `/bens/${id}` : null)

  if (id === undefined) return <Formulario key="novo" />
  if (resposta.erro) return <Aviso>{resposta.erro}</Aviso>
  if (!resposta.dados) return <Carregando />
  return <Formulario key={id} bem={resposta.dados.data} />
}

function Formulario({ bem }: { bem?: Bem }) {
  const editando = bem !== undefined
  const id = bem?.id
  const navegar = useNavigate()

  const [dados, setDados] = useState<Dados>(bem ? dadosDoBem(bem) : VAZIO)
  const [erro, setErro] = useState<ErroDaApi | null>(null)
  const [salvando, setSalvando] = useState(false)

  // Sala e tipo inativos não recebem bens novos (README, 10.2).
  const tipos = useApi<Pagina<TipoPatrimonio>>(`/tipos-patrimonio${consulta({ ativo: editando ? undefined : true, por_pagina: 100 })}`)
  const salas = useApi<Pagina<Sala>>(editando ? null : '/salas?ativo=1&por_pagina=100')

  function mudar<C extends keyof Dados>(campo: C, valor: Dados[C]) {
    setDados((atual) => ({ ...atual, [campo]: valor }))
  }

  async function salvar(evento: FormEvent) {
    evento.preventDefault()
    setSalvando(true)
    setErro(null)

    const corpo = {
      numero_patrimonio: dados.numero_patrimonio.trim() || null,
      tipo_patrimonio_id: Number(dados.tipo_patrimonio_id) || null,
      descricao: dados.descricao.trim() || null,
      marca: dados.marca.trim() || null,
      origem: dados.origem.trim() || null,
      quantidade: Number(dados.quantidade) || 1,
      estado_conservacao: dados.estado_conservacao,
      // Sem sala escolhida, o bem fica sem localização.
      ...(editando ? {} : { sala_id: Number(dados.sala_id) || null, status: dados.status }),
    }

    try {
      const resposta = editando
        ? await apiPatch<{ data: Bem }>(`/bens/${id}`, corpo)
        : await apiPost<{ data: Bem }>('/bens', corpo)
      navegar(`/bens/${resposta.data.id}`, { state: { mensagem: editando ? 'Bem atualizado.' : 'Bem cadastrado.' } })
    } catch (e) {
      setErro(e instanceof ErroDaApi ? e : new ErroDaApi(0, 'Não foi possível salvar.'))
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } finally {
      setSalvando(false)
    }
  }

  const salaEscolhida = salas.dados?.data.find((sala) => String(sala.id) === dados.sala_id) ?? bem?.sala
  const tipoEscolhido = tipos.dados?.data.find((tipo) => String(tipo.id) === dados.tipo_patrimonio_id)

  return (
    <form onSubmit={salvar}>
      <p className="text-xs text-slate-500">
        <Link to="/bens" className="hover:underline">
          Bens
        </Link>{' '}
        / {editando ? 'Editar bem' : 'Cadastrar bem'}
      </p>
      <h1 className="mt-1 text-2xl font-bold text-cead-900">{editando ? 'Editar Bem' : 'Cadastrar Novo Bem'}</h1>
      <p className="mt-1 mb-6 text-sm text-slate-500">
        Preencha os dados essenciais para {editando ? 'atualizar o' : 'cadastrar o'} item no patrimônio do CEAD.
      </p>

      {erro && (
        <div className="mb-4">
          <Aviso>{erro.message}</Aviso>
        </div>
      )}

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          <section className="cartao space-y-4 p-6">
            <h2 className="flex items-center gap-2 font-semibold text-cead-900">
              <Tag className="size-4" /> Identificação do Bem
            </h2>
            <div className="grid gap-4 sm:grid-cols-2">
              <Campo
                rotulo="Tombamento / Plaqueta"
                erro={erro?.doCampo('numero_patrimonio')}
                ajuda="Em branco quando o bem não tem plaqueta: o sistema gera o código interno."
              >
                <input
                  className="entrada font-mono"
                  maxLength={20}
                  placeholder="ex: 1634456-1"
                  value={dados.numero_patrimonio}
                  onChange={(e) => mudar('numero_patrimonio', e.target.value)}
                />
              </Campo>
              <Campo rotulo="Tipo / Categoria" obrigatorio erro={erro?.doCampo('tipo_patrimonio_id')}>
                <select
                  className="entrada"
                  value={dados.tipo_patrimonio_id}
                  onChange={(e) => mudar('tipo_patrimonio_id', e.target.value)}
                  required
                >
                  <option value="">Selecione o tipo...</option>
                  {tipos.dados?.data.map((tipo) => (
                    <option key={tipo.id} value={tipo.id}>
                      {tipo.nome}
                    </option>
                  ))}
                </select>
              </Campo>
            </div>
            <Campo rotulo="Descrição do bem" erro={erro?.doCampo('descricao')}>
              <input
                className="entrada"
                maxLength={120}
                placeholder="ex: vermelha, giratória com braços"
                value={dados.descricao}
                onChange={(e) => mudar('descricao', e.target.value)}
              />
            </Campo>
            <div className="grid gap-4 sm:grid-cols-3">
              <Campo rotulo="Marca" erro={erro?.doCampo('marca')}>
                <input className="entrada" maxLength={80} value={dados.marca} onChange={(e) => mudar('marca', e.target.value)} />
              </Campo>
              <Campo rotulo="Origem" erro={erro?.doCampo('origem')} ajuda="ex: Convênio, UAB">
                <input className="entrada" maxLength={120} value={dados.origem} onChange={(e) => mudar('origem', e.target.value)} />
              </Campo>
              <Campo rotulo="Quantidade" erro={erro?.doCampo('quantidade')}>
                <input
                  className="entrada"
                  type="number"
                  min={1}
                  max={9999}
                  value={dados.quantidade}
                  onChange={(e) => mudar('quantidade', e.target.value)}
                />
              </Campo>
            </div>
          </section>

          <section className="cartao space-y-4 p-6">
            <h2 className="flex items-center gap-2 font-semibold text-cead-900">
              <MapPin className="size-4" /> Localização e Situação
            </h2>

            {editando ? (
              <p className="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">
                {bem?.sala ? 'Está em ' : 'Está '}
                <strong>{bem?.sala?.nome ?? 'sem localização'}</strong>, situação{' '}
                <strong>{bem?.status_rotulo}</strong>. Para mudar de sala, de situação ou dar baixa, use a{' '}
                <Link to={`/bens/${id}`} className="font-semibold text-cead-700 hover:underline">
                  ficha do bem
                </Link>
                .
              </p>
            ) : (
              <>
                <Campo rotulo="Sala / Ambiente" erro={erro?.doCampo('sala_id')} ajuda="Deixe sem localização quando ainda não se sabe onde o bem está.">
                  <select className="entrada" value={dados.sala_id} onChange={(e) => mudar('sala_id', e.target.value)}>
                    <option value="">Sem localização</option>
                    {salas.dados?.data.map((sala) => (
                      <option key={sala.id} value={sala.id}>
                        {sala.nome}
                      </option>
                    ))}
                  </select>
                </Campo>

                <div>
                  <span className="rotulo">Situação de uso</span>
                  <div className="grid gap-3 sm:grid-cols-2">
                    {(['em_uso', 'sem_uso'] as const).map((status) => (
                      <label
                        key={status}
                        className={`flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2.5 text-sm ${
                          dados.status === status ? 'border-emerald-400 bg-emerald-50' : 'border-slate-200'
                        }`}
                      >
                        <input
                          type="radio"
                          name="status"
                          checked={dados.status === status}
                          onChange={() => mudar('status', status)}
                        />
                        {status === 'em_uso' ? 'Em uso' : 'Sem uso / Reserva'}
                      </label>
                    ))}
                  </div>
                  {erro?.doCampo('status') && <span className="mt-1 block text-xs text-red-600">{erro.doCampo('status')}</span>}
                </div>
              </>
            )}

            <Campo rotulo="Estado de conservação" erro={erro?.doCampo('estado_conservacao')}>
              <select
                className="entrada"
                value={dados.estado_conservacao}
                onChange={(e) => mudar('estado_conservacao', e.target.value as EstadoConservacao)}
              >
                {(Object.keys(ROTULOS_CONSERVACAO) as EstadoConservacao[]).map((estado) => (
                  <option key={estado} value={estado}>
                    {ROTULOS_CONSERVACAO[estado]}
                  </option>
                ))}
              </select>
            </Campo>
          </section>
        </div>

        <aside className="space-y-6">
          <section className="cartao p-5">
            <h2 className="mb-3 text-xs font-semibold tracking-wide text-slate-500 uppercase">Prévia da etiqueta</h2>
            <div className="rounded-lg border-2 border-dashed border-slate-300 p-4">
              <p className="text-[10px] font-semibold tracking-wider text-slate-500">UNIMONTES • CEAD</p>
              <p className="mt-2 text-xs text-slate-500">Tombamento</p>
              <p className="font-mono text-lg font-bold text-cead-900">
                {dados.numero_patrimonio.trim() || bem?.codigo_interno || 'CP-gerado ao salvar'}
              </p>
              <p className="mt-2 text-xs text-slate-500">
                {tipoEscolhido?.nome ?? 'Tipo'} · {salaEscolhida?.nome ?? 'Sem localização'}
              </p>
            </div>
          </section>

          <section className="cartao space-y-3 p-5">
            <h2 className="flex items-center gap-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">
              <Camera className="size-4" /> Foto do equipamento
            </h2>
            {/* Desativado: O cadastro de foto, modelo, número de série e a impressão da etiqueta ainda não existem na API. */}
            <Desativado />
          </section>
        </aside>
      </div>

      {!editando && (
        <div className="mt-6">
          <Aviso tipo="info">
            <strong>Atenção: um bem cadastrado não pode ser excluído.</strong> Confira o tombamento, o tipo e a sala antes de salvar. Se o bem sair do
            patrimônio depois, registre a baixa na ficha dele.
          </Aviso>
        </div>
      )}

      <div className="mt-6 flex flex-wrap items-center justify-between gap-3">
        <button type="button" className="botao text-slate-500 hover:text-slate-700" onClick={() => setDados(bem ? dadosDoBem(bem) : VAZIO)}>
          <RotateCcw className="size-4" /> Limpar formulário
        </button>
        <div className="flex gap-2">
          <Link to={editando ? `/bens/${id}` : '/bens'} className="botao-secundario">
            Cancelar
          </Link>
          <button type="submit" className="botao-primario" disabled={salvando}>
            {editando ? <Save className="size-4" /> : <CheckCircle2 className="size-4" />}
            {salvando ? 'Salvando...' : 'Salvar Bem Patrimonial'}
          </button>
        </div>
      </div>
    </form>
  )
}
