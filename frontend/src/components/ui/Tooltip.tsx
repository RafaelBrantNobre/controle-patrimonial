import { cloneElement, useId, type ReactElement, type ReactNode } from 'react'
// O filho deve ser um único controle que encaminha aria-describedby ao HTML.
export function Tooltip({ texto, children }: { texto: ReactNode; children: ReactElement<{ 'aria-describedby'?: string }> }) {
  const id = useId()
  return (
    <span className="group/tooltip relative inline-flex">
      {cloneElement(children, { 'aria-describedby': [children.props['aria-describedby'], id].filter(Boolean).join(' ') })}
      <span
        id={id}
        role="tooltip"
        className="pointer-events-none absolute top-full right-0 z-50 mt-2 hidden w-max max-w-64 rounded-md bg-slate-900 px-2 py-1 text-xs font-normal text-white shadow-lg group-hover/tooltip:block group-focus-within/tooltip:block"
      >
        {texto}
      </span>
    </span>
  )
}
