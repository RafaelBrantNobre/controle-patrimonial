import { Aviso, Botao, Carregando, Dialogo, Entrada } from '../components/ui'
import { Plus, Tags } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { BotaoExcluir } from './Exclusao'
import { SeloAtivo } from './SelosDeBem'
import { apiDelete, apiGet, apiPatch, apiPost, ErroDaApi } from '../services/api'
import type { Bem, Pagina, TipoPatrimonio } from '../services/tipos'
import { useApi } from '../services/useApi'

// Tipos de patrimônio, sem tela própria: o cadastro rápido fica ao lado do
// campo Tipo no cadastro do bem, e a lista (inativar, reativar e excluir o
// tipo vazio) abre por "Gerenciar tipos" na tela de Bens. Só administrador:
// a API recusa as alterações para o perfil de consulta.

function useCriarTipo(aoCriar: (tipo: TipoPatrimonio) => void) {
  const [nome, setNome] = useState('')
  const [erro, setErro] = useState<string | null>(null)
  const [ocupado, setOcupado] = useState(false)

  async function criar(evento: FormEvent) {
    evento.preventDefault()
    // O popup é um portal: sem isto, o envio subiria até o formulário do bem.
    evento.stopPropagation()
    setOcupado(true)
    setErro(null)
    try {
      const resposta = await apiPost<{ data: TipoPatrimonio }>('/tipos-patrimonio', { nome: nome.trim() })
      setNome('')
      aoCriar(resposta.data)
    } catch (e) {
      setErro(e instanceof ErroDaApi ? e.message : 'Não foi possível cadastrar o tipo.')
    } finally {
      setOcupado(false)
    }
  }

  return { nome, setNome, erro, ocupado, criar }
}

// Botão "Novo tipo" com o popup de cadastro. Quem chama recebe o tipo criado,
// para já deixá-lo escolhido no formulário.
export function NovoTipo({ aoCriar }: { aoCriar: (tipo: TipoPatrimonio) => void }) {
  const [aberto, setAberto] = useState(false)
  const { nome, setNome, erro, ocupado, criar } = useCriarTipo((tipo) => {
    setAberto(false)
    aoCriar(tipo)
  })

  return (
    <>
      <Botao type="button" className="mt-1.5 inline-flex items-center gap-1 text-xs font-semibold text-cead-700 hover:underline" onClick={() => setAberto(true)}>
        <Plus className="size-3.5" /> Novo tipo
      </Botao>
      {aberto && (
        <Dialogo
          titulo={
            <>
              <Tags className="size-5" /> Novo tipo de patrimônio
            </>
          }
          aoFechar={() => setAberto(false)}
          ocupado={ocupado}
        >
          <form onSubmit={criar} className="space-y-3">
            <Entrada placeholder="ex: Projetor" value={nome} onChange={(e) => setNome(e.target.value)} required maxLength={120} aria-label="Nome do tipo" />
            {erro && <Aviso>{erro}</Aviso>}
            <div className="flex justify-end gap-2">
              <Botao variante="secundario" type="button" onClick={() => setAberto(false)} disabled={ocupado}>
                Cancelar
              </Botao>
              <Botao variante="primario" type="submit" disabled={ocupado}>
                <Plus className="size-4" /> Cadastrar tipo
              </Botao>
            </div>
          </form>
        </Dialogo>
      )}
    </>
  )
}

// Botão "Gerenciar tipos" com o popup da lista. aoMudar avisa quem chama
// para recarregar o que depende dos tipos (o filtro da lista de bens).
export function GerenciarTipos({ aoMudar }: { aoMudar: () => void }) {
  const [aberto, setAberto] = useState(false)

  return (
    <>
      <Botao variante="secundario" type="button" onClick={() => setAberto(true)}>
        <Tags className="size-4" /> Gerenciar tipos
      </Botao>
      {aberto && <ListaDeTipos aoFechar={() => setAberto(false)} aoMudar={aoMudar} />}
    </>
  )
}

function ListaDeTipos({ aoFechar, aoMudar }: { aoFechar: () => void; aoMudar: () => void }) {
  const [versao, setVersao] = useState(0)
  const [erro, setErro] = useState<string | null>(null)
  const [ocupado, setOcupado] = useState(false)
  const lista = useApi<Pagina<TipoPatrimonio>>('/tipos-patrimonio?por_pagina=100', versao)

  function recarregar() {
    setVersao((v) => v + 1)
    aoMudar()
  }

  const novo = useCriarTipo(recarregar)

  async function alternarAtivo(tipo: TipoPatrimonio) {
    setOcupado(true)
    setErro(null)
    try {
      await apiPatch(`/tipos-patrimonio/${tipo.id}`, { ativo: !tipo.ativo })
      recarregar()
    } catch (e) {
      setErro(e instanceof ErroDaApi ? e.message : 'Não foi possível salvar.')
    } finally {
      setOcupado(false)
    }
  }

  return (
    <Dialogo
      titulo={
        <>
          <Tags className="size-5" /> Tipos de patrimônio
        </>
      }
      aoFechar={aoFechar}
      ocupado={ocupado || novo.ocupado}
    >
      <p>Tipo inativo não aparece no cadastro de bens novos. Só o tipo sem bens pode ser excluído.</p>
      <form onSubmit={novo.criar} className="flex gap-2">
        <Entrada placeholder="Nome do novo tipo" value={novo.nome} onChange={(e) => novo.setNome(e.target.value)} required maxLength={120} aria-label="Nome do novo tipo" />
        <Botao variante="primario" type="submit" className="shrink-0" disabled={novo.ocupado}>
          <Plus className="size-4" /> Adicionar
        </Botao>
      </form>
      {(erro || novo.erro || lista.erro) && <Aviso>{erro ?? novo.erro ?? lista.erro}</Aviso>}
      <ul className="max-h-80 divide-y divide-slate-100 overflow-y-auto rounded-lg border border-slate-200">
        {lista.dados?.data.map((tipo) => (
          <li key={tipo.id} className="flex items-center justify-between gap-3 px-3 py-2.5">
            <span>
              <span className="block font-medium text-slate-800">{tipo.nome}</span>
              <span className="block text-xs text-slate-500">{tipo.bens_count ?? 0} bens</span>
            </span>
            <span className="flex items-center gap-3">
              <SeloAtivo ativo={tipo.ativo} />
              <Botao
                type="button"
                className="text-xs font-semibold text-cead-700 hover:underline disabled:opacity-50"
                disabled={ocupado}
                onClick={() => void alternarAtivo(tipo)}
              >
                {tipo.ativo ? 'Inativar' : 'Reativar'}
              </Botao>
              <BotaoExcluir
                rotulo="o tipo"
                nome={tipo.nome}
                nomeDoVinculo={{ singular: 'bem', plural: 'bens' }}
                buscarVinculos={async () => {
                  const bens = await apiGet<Pagina<Bem>>(`/bens?tipo_patrimonio_id=${tipo.id}&por_pagina=100`)
                  return {
                    total: bens.meta.total,
                    itens: bens.data.map((bem) => ({
                      id: bem.id,
                      titulo: `${bem.numero_patrimonio ?? 'sem patrimônio'} · ${bem.codigo_interno}`,
                      detalhe: bem.sala?.nome ?? 'Sem localização',
                      para: `/bens/${bem.id}`,
                    })),
                  }
                }}
                excluir={() => apiDelete(`/tipos-patrimonio/${tipo.id}`)}
                aoExcluir={recarregar}
                className="text-xs font-semibold text-red-700 hover:underline"
              >
                Excluir
              </BotaoExcluir>
            </span>
          </li>
        ))}
      </ul>
      {lista.carregando && !lista.dados && <Carregando />}
    </Dialogo>
  )
}
