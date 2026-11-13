import logo from '../../assets/cead-patrimonio.svg?raw'
import { classes } from './classes'

// SVG local estático: os contornos do texto recebem a cor do tema pelo CSS.
export function Logo({ className }: { className?: string }) {
  return <span role="img" aria-label="CEAD Patrimônio" className={classes('logo-marca', className)} dangerouslySetInnerHTML={{ __html: logo }} />
}
