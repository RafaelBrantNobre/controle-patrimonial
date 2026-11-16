import type { ComponentProps } from 'react'
import { Link } from 'react-router'
import { classes } from './classes'
const variantes = {
  neutro: '',
  primario: 'botao-primario',
  secundario: 'botao-secundario',
  sucesso: 'botao-verde',
  perigo: 'botao-perigo',
  simples: 'botao',
}
export type VarianteBotao = keyof typeof variantes
export function Botao({
  variante = 'neutro',
  type = 'button',
  className,
  ...props
}: ComponentProps<'button'> & { variante?: VarianteBotao }) {
  return <button type={type} className={classes(variantes[variante], className)} {...props} />
}
export function LinkBotao({ variante = 'secundario', className, ...props }: ComponentProps<typeof Link> & { variante?: VarianteBotao }) {
  return <Link className={classes(variantes[variante], className)} {...props} />
}
