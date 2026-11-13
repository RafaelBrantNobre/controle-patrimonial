import { Cartao } from './Cartao'
import { X } from 'lucide-react'
import { useLayoutEffect, useId, useRef, type ReactNode } from 'react'
import { createPortal } from 'react-dom'
import { Botao } from './Botao'
// O dialog nativo cuida de foco, Tab, isolamento do fundo e retorno ao acionador.
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
  rodape?: ReactNode
  children: ReactNode
}) {
  const id = useId()
  const ref = useRef<HTMLDialogElement>(null)
  const iniciouNoFundo = useRef(false)
  useLayoutEffect(() => {
    const acionador = document.activeElement as HTMLElement | null
    const elemento = ref.current!
    elemento.showModal()
    elemento.querySelector<HTMLElement>('input:not([disabled]), select:not([disabled]), textarea:not([disabled])')?.focus()
    const anterior = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return () => {
      elemento.close()
      queueMicrotask(() => {
        if (!elemento.open && acionador?.isConnected) acionador.focus()
      })
      document.body.style.overflow = anterior
    }
  }, [])
  return createPortal(
    <dialog
      ref={ref}
      aria-labelledby={id}
      aria-busy={ocupado}
      className="dialogo nao-imprimir"
      onCancel={(e) => {
        e.preventDefault()
        if (!ocupado) aoFechar()
      }}
      onMouseDown={(e) => {
        iniciouNoFundo.current = e.target === e.currentTarget
      }}
      onClick={(e) => {
        if (iniciouNoFundo.current && e.target === e.currentTarget && !ocupado) aoFechar()
      }}
    >
      <Cartao className="w-full p-6 shadow-xl">
        <div className="mb-4 flex items-start justify-between gap-3">
          <h2 id={id} className="flex items-center gap-2 text-lg font-bold text-cead-900">
            {titulo}
          </h2>
          <Botao className="text-slate-400 hover:text-slate-600" onClick={aoFechar} aria-label="Fechar" disabled={ocupado}>
            <X className="size-5" />
          </Botao>
        </div>
        <div className="space-y-3 text-sm text-slate-600">{children}</div>
        {rodape && <div className="mt-6 flex flex-wrap justify-end gap-2">{rodape}</div>}
      </Cartao>
    </dialog>,
    document.body,
  )
}
