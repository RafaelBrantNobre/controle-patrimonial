import type { ReactNode } from 'react'

const CORES_SELO = {
  verde: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  azul: 'bg-cead-50 text-cead-700 ring-cead-100',
  amarelo: 'bg-amber-50 text-amber-700 ring-amber-200',
  vermelho: 'bg-red-50 text-red-700 ring-red-200',
  cinza: 'bg-slate-100 text-slate-600 ring-slate-200',
}

export type CorSelo = keyof typeof CORES_SELO

export function Selo({ cor, children }: { cor: CorSelo; children: ReactNode }) {
  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ${CORES_SELO[cor]}`}
    >
      <span className="size-1.5 rounded-full bg-current" />
      {children}
    </span>
  )
}
