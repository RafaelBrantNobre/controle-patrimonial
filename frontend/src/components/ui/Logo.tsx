import logo from '../../assets/cead-patrimonio.svg?raw'
import { classes } from './classes'

// SVG local estático: os contornos do texto recebem a cor do tema pelo CSS.
export function Logo({ className, sobreEscuro = false }: { className?: string; sobreEscuro?: boolean }) {
  return (
    <span
      role="img"
      aria-label="CEAD Patrimônio"
      className={classes('logo-marca', sobreEscuro && 'logo-inversa', className)}
      dangerouslySetInnerHTML={{ __html: logo }}
    />
  )
}
