import type { ComponentProps } from 'react'
import { classes } from './classes'
export function Titulo({ className, ...props }: ComponentProps<'h1'>) {
  return <h1 className={classes('text-2xl font-bold text-cead-900', className)} {...props} />
}
