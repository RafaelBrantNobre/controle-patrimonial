import type { ReactNode } from 'react'
import { AlertTriangle, Info } from 'lucide-react'

export function Aviso({ tipo = 'erro', children }: { tipo?: 'erro' | 'info'; children: ReactNode }) {
  const Icone = tipo === 'erro' ? AlertTriangle : Info
  const cores = tipo === 'erro' ? 'border-red-200 bg-red-50 text-red-800' : 'border-amber-200 bg-amber-50 text-amber-900'
  return (
    <div className={`flex gap-3 rounded-lg border px-4 py-3 text-sm ${cores}`} role={tipo === 'erro' ? 'alert' : 'note'}>
      <Icone className="mt-0.5 size-4 shrink-0" />
      <div>{children}</div>
    </div>
  )
}
