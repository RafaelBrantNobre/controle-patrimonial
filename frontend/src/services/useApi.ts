import { useEffect, useState } from 'react'
import { apiGet } from './api'

type Resultado<T> = { chave: string; dados: T | null; erro: string | null }

// Busca um caminho da API e busca de novo quando o caminho (ou a versão) muda.
// caminho null não busca nada (por exemplo, enquanto falta um filtro).
// Enquanto a busca nova não chega, os dados anteriores continuam na tela.
export function useApi<T>(caminho: string | null, versao = 0) {
  const chave = `${versao}|${caminho}`
  const [resultado, setResultado] = useState<Resultado<T>>({ chave: '', dados: null, erro: null })

  useEffect(() => {
    if (caminho === null) return
    let cancelado = false

    apiGet<T>(caminho)
      .then((dados) => !cancelado && setResultado({ chave, dados, erro: null }))
      .catch(
        (erro: unknown) =>
          !cancelado &&
          setResultado({ chave, dados: null, erro: erro instanceof Error ? erro.message : 'erro desconhecido' }),
      )

    return () => {
      cancelado = true
    }
  }, [caminho, chave])

  return {
    dados: caminho === null ? null : resultado.dados,
    erro: resultado.chave === chave ? resultado.erro : null,
    carregando: caminho !== null && resultado.chave !== chave,
  }
}
