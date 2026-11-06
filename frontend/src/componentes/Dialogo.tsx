import { X } from 'lucide-react'
import { useEffect, type ReactNode } from 'react'

// Popup no meio da tela, com fundo escurecido. Esc e o clique fora fecham,
// a não ser enquanto a ação está em andamento (ocupado).
export function Dialogo({
  titulo,
  aoFechar,
  ocupado = false,
  rodape,
  children,
}: {
  titulo: ReactNode
  aoFechar: () => void
  ocupado?: boolean
  rodape: ReactNode
  children: ReactNode
}) {
  useEffect(() => {
    const aoTeclar = (evento: KeyboardEvent) => evento.key === 'Escape' && !ocupado && aoFechar()
    window.addEventListener('keydown', aoTeclar)
    return () => window.removeEventListener('keydown', aoTeclar)
  }, [aoFechar, ocupado])

  return (
    <div className="nao-imprimir fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" onClick={() => !ocupado && aoFechar()}>
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="titulo-dialogo"
        className="cartao w-full max-w-lg p-6 shadow-xl"
        onClick={(evento) => evento.stopPropagation()}
      >
        <div className="mb-4 flex items-start justify-between gap-3">
          <h2 id="titulo-dialogo" className="flex items-center gap-2 text-lg font-bold text-cead-900">
            {titulo}
          </h2>
          <button type="button" className="text-slate-400 hover:text-slate-600" onClick={aoFechar} aria-label="Fechar" disabled={ocupado}>
            <X className="size-5" />
          </button>
        </div>
        <div className="space-y-3 text-sm text-slate-600">{children}</div>
        <div className="mt-6 flex justify-end gap-2">{rodape}</div>
      </div>
    </div>
  )
}
