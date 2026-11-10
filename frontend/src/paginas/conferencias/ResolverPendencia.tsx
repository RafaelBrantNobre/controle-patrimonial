import { useState } from 'react'
import { Dialogo } from '../../componentes/Dialogo'
import { Aviso, Campo } from '../../componentes/ui'
import { apiPost, ErroDaApi } from '../../services/api'
import { hojeIso } from '../../services/formatos'
import type { Bem, Pagina, Sala } from '../../services/tipos'
import { useApi } from '../../services/useApi'

// Resolver a pendência de um bem: ele apareceu (sala e dia) ou foi aberto o
// processo administrativo (número). Usado aqui e na ficha do bem.
export function ResolverPendencia({
  bem,
  jeito,
  aoFechar,
  aoResolver,
}: {
  bem: Bem
  jeito: 'localizado' | 'processo_administrativo'
  aoFechar: () => void
  aoResolver: () => void
}) {
  const [salaId, setSalaId] = useState(String(bem.sala_id))
  const [data, setData] = useState(hojeIso())
  const [processo, setProcesso] = useState('')
  const [erro, setErro] = useState<ErroDaApi | null>(null)
  const [enviando, setEnviando] = useState(false)
  const salas = useApi<Pagina<Sala>>(jeito === 'localizado' ? '/salas?ativo=1&por_pagina=100' : null)

  async function resolver() {
    setEnviando(true)
    setErro(null)
    try {
      await apiPost(
        `/bens/${bem.id}/pendencia`,
        jeito === 'localizado'
          ? { resolucao: 'localizado', sala_id: Number(salaId), localizado_em: data }
          : { resolucao: 'processo_administrativo', processo_numero: processo.trim() },
      )
      aoResolver()
    } catch (e) {
      setErro(e instanceof ErroDaApi ? e : new ErroDaApi(0, 'Não foi possível resolver a pendência.'))
      setEnviando(false)
    }
  }

  const identificacao = bem.numero_patrimonio ?? bem.codigo_interno

  return (
    <Dialogo
      titulo={jeito === 'localizado' ? `Bem ${identificacao} localizado` : `Processo administrativo do bem ${identificacao}`}
      aoFechar={aoFechar}
      ocupado={enviando}
      rodape={
        <>
          <button type="button" className="botao-secundario" onClick={aoFechar} disabled={enviando}>
            Voltar
          </button>
          <button type="button" className="botao-primario" onClick={() => void resolver()} disabled={enviando || (jeito === 'processo_administrativo' && !processo.trim())}>
            {enviando ? 'Salvando...' : 'Fechar pendência'}
          </button>
        </>
      }
    >
      {jeito === 'localizado' ? (
        <>
          <p>Informe onde e quando o bem apareceu. Se for outra sala, a mudança entra no histórico.</p>
          <Campo rotulo="Sala onde estava" erro={erro?.doCampo('sala_id')}>
            <select className="entrada" value={salaId} onChange={(e) => setSalaId(e.target.value)}>
              {!salas.dados?.data.some((sala) => sala.id === bem.sala_id) && (
                <option value={bem.sala_id}>
                  {bem.sala?.nome}
                  {bem.sala?.andar && ` · ${bem.sala.andar.nome}`}
                </option>
              )}
              {salas.dados?.data.map((sala) => (
                <option key={sala.id} value={sala.id}>
                  {sala.nome}
                  {sala.andar && ` · ${sala.andar.nome}`}
                </option>
              ))}
            </select>
          </Campo>
          <Campo rotulo="Dia em que foi localizado" erro={erro?.doCampo('localizado_em')}>
            <input type="date" className="entrada" max={hojeIso()} value={data} onChange={(e) => setData(e.target.value)} />
          </Campo>
        </>
      ) : (
        <>
          <p>O bem continua como não localizado até a baixa. O número do processo fica registrado no histórico de conferências do bem.</p>
          <Campo rotulo="Número do processo" obrigatorio erro={erro?.doCampo('processo_numero')}>
            <input className="entrada" maxLength={60} value={processo} onChange={(e) => setProcesso(e.target.value)} />
          </Campo>
        </>
      )}
      {erro && !erro.doCampo('sala_id') && !erro.doCampo('localizado_em') && !erro.doCampo('processo_numero') && <Aviso>{erro.message}</Aviso>}
    </Dialogo>
  )
}
