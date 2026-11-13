import type { LucideIcon } from 'lucide-react'
export function Desativado({ icone: Icone }: { icone?: LucideIcon }) {
  return <div className="estado-desativado">{Icone && <Icone className="size-5" aria-hidden="true" />}Desativado</div>
}
export function Carregando({ texto = 'Carregando...' }: { texto?: string }) {
  return (
    <p className="px-4 py-8 text-center text-sm text-slate-500" role="status">
      {texto}
    </p>
  )
}
