import { createElement, type ComponentProps, type ElementType } from 'react'
import { classes } from './classes'
export function Cartao<T extends ElementType = 'div'>({
  as,
  className,
  ...props
}: { as?: T; className?: string } & Omit<ComponentProps<T>, 'as' | 'className'>) {
  return createElement(as ?? 'div', { ...props, className: classes('cartao', className) })
}
