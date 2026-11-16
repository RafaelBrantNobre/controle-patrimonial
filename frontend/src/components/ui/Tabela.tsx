import type { ComponentProps } from 'react'
import { classes } from './classes'
// O contêiner fica separado para permitir paginação e cabeçalhos fora da rolagem.
export function Tabela({ className, ...props }: ComponentProps<'table'>) {
  return <table className={classes('tabela', className)} {...props} />
}
export function RolagemTabela({ className, ...props }: ComponentProps<'div'>) {
  return (
    <div
      className={classes('overflow-x-auto', className)}
      tabIndex={0}
      role="region"
      aria-label="Tabela com rolagem horizontal"
      {...props}
    />
  )
}
