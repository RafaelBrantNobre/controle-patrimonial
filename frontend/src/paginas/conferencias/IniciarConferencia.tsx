import { Database, FileUp, Play } from 'lucide-react'
import { useState } from 'react'
import { useNavigate } from 'react-router'
import { Dialogo } from '../../componentes/Dialogo'
import { Aviso, Campo } from '../../componentes/ui'
import { apiPost, ErroDaApi } from '../../services/api'
import { formatarData, hojeIso } from '../../services/formatos'
import type { Conferencia } from '../../services/tipos'

// Iniciar conferência: dá o nome e escolhe de onde vêm os bens.
// Importar um arquivo (planilha ou SIAD) ainda não está ligado: a leitura
// depende do formato do arquivo, que não está definido. Por enquanto a
// conferência começa com os bens já cadastrados, e a pessoa vai direto para
// a primeira sala.
export function IniciarConferencia({ aoFechar }: { aoFechar: () => void }) {
  const navegar = useNavigate()
  const [nome, setNome] = useState(`Conferência de ${formatarData(hojeIso())}`)
  const [erro, setErro] = useState<ErroDaApi | null>(null)
  const [iniciando, setIniciando] = useState(false)

  async function iniciar() {
    setIniciando(true)
    setErro(null)
    try {
      const resposta = await apiPost<{ data: Conferencia }>('/conferencias', { nome: nome.trim(), modo: 'filtro' })
      navegar(`/conferencias/${resposta.data.id}`)
    } catch (e) {
      setErro(e instanceof ErroDaApi ? e : new ErroDaApi(0, 'Não foi possível iniciar a conferência.'))
      setIniciando(false)
    }
  }

  return (
    <Dialogo
      titulo={
        <>
          <Play className="size-5 text-cead-700" /> Iniciar conferência
        </>
      }
      aoFechar={aoFechar}
      ocupado={iniciando}
      rodape={
        <>
          <button type="button" className="botao-secundario" onClick={aoFechar} disabled={iniciando}>
            Voltar
          </button>
          <button type="button" className="botao-primario" onClick={() => void iniciar()} disabled={iniciando || !nome.trim()}>
            {iniciando ? 'Iniciando...' : 'Iniciar e ir para a primeira sala'}
          </button>
        </>
      }
    >
      <Campo rotulo="Nome da conferência" obrigatorio erro={erro?.doCampo('nome')}>
        <input className="entrada" maxLength={120} value={nome} onChange={(e) => setNome(e.target.value)} />
      </Campo>

      <div>
        <span className="rotulo">Deseja importar um arquivo?</span>
        <div className="space-y-2">
          <label className="flex cursor-not-allowed gap-3 rounded-lg border border-slate-200 p-3 opacity-60">
            <input type="radio" name="fonte" disabled />
            <span>
              <span className="flex items-center gap-1.5 font-semibold text-slate-800">
                <FileUp className="size-4" /> Importar um arquivo (planilha ou SIAD)
              </span>
              <span className="block text-xs text-slate-500">Ainda não disponível: a leitura será ligada quando o formato do arquivo estiver definido.</span>
            </span>
          </label>
          <label className="flex cursor-pointer gap-3 rounded-lg border border-cead-600 bg-cead-50 p-3">
            <input type="radio" name="fonte" checked readOnly />
            <span>
              <span className="flex items-center gap-1.5 font-semibold text-slate-800">
                <Database className="size-4" /> Pular e usar os bens cadastrados
              </span>
              <span className="block text-xs text-slate-500">Entram todos os bens que não estão baixados, organizados por sala.</span>
            </span>
          </label>
        </div>
      </div>

      {erro && !erro.doCampo('nome') && <Aviso>{erro.message}</Aviso>}
    </Dialogo>
  )
}
