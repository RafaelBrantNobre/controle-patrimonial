import type { ComponentProps } from 'react'
import { classes } from './classes'
import { ChevronDown } from 'lucide-react'
export function Entrada({ className, ...props }: ComponentProps<'input'>) {
  return <input className={classes('entrada', className)} {...props} />
}
export function Selecao({ className, ...props }: ComponentProps<'select'>) {
  if (props.multiple || (props.size ?? 0) > 1) return <select className={classes('entrada', className)} {...props} />
  return (
    <span className="relative block">
      <select className={classes('entrada appearance-none pr-10', className)} {...props} />
      <ChevronDown aria-hidden="true" className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-slate-500" />
    </span>
  )
}
export function AreaDeTexto({ className, ...props }: ComponentProps<'textarea'>) {
  return <textarea className={classes('entrada', className)} {...props} />
}
export function Checkbox({ className, ...props }: Omit<ComponentProps<'input'>, 'type'>) {
  return <input type="checkbox" className={classes('controle-selecao rounded', className)} {...props} />
}
export function Radio({ className, ...props }: Omit<ComponentProps<'input'>, 'type'>) {
  return <input type="radio" className={classes('controle-selecao rounded-full', className)} {...props} />
}
