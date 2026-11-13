import type { ReactNode } from 'react'

export function Campo({
  rotulo,
  erro,
  obrigatorio,
  ajuda,
  children,
}: {
  rotulo: string
  erro?: string
  obrigatorio?: boolean
  ajuda?: string
  children: ReactNode
}) {
  return (
    <label className="block">
      <span className="rotulo">
        {rotulo}
        {obrigatorio && <span className="text-red-600"> *</span>}
      </span>
      {children}
      {ajuda && !erro && <span className="mt-1 block text-xs text-slate-400">{ajuda}</span>}
      {erro && <span className="mt-1 block text-xs text-red-600">{erro}</span>}
    </label>
  )
}
