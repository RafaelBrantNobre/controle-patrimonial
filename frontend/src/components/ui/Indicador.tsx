import { Cartao } from './Cartao'
import type { ReactNode } from 'react'
import type { LucideIcon } from 'lucide-react'

export function Indicador({
  titulo,
  valor,
  detalhe,
  icone: Icone,
}: {
  titulo: string
  valor: ReactNode
  detalhe?: ReactNode
  icone: LucideIcon
}) {
  return (
    <Cartao className="flex items-start justify-between p-5">
      <div>
        <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">{titulo}</p>
        <p className="mt-2 text-3xl font-bold text-cead-900">{valor}</p>
        {detalhe && <p className="mt-1 text-xs text-slate-500">{detalhe}</p>}
      </div>
      <span className="rounded-lg bg-cead-50 p-2.5 text-cead-700">
        <Icone className="size-5" />
      </span>
    </Cartao>
  )
}
