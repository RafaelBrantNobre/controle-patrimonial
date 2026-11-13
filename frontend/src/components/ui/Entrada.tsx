import type { ComponentProps } from 'react'
import { classes } from './classes'
export function Entrada({ className, ...props }: ComponentProps<'input'>) {
  return <input className={classes('entrada', className)} {...props} />
}
export function Selecao({ className, ...props }: ComponentProps<'select'>) {
  return <select className={classes('entrada', className)} {...props} />
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
