import { ArrowRight, type LucideIcon } from 'lucide-react'
import { Link } from 'react-router'
import { Cartao } from './Cartao'
export type CorDestaque = 'azul' | 'roxo' | 'ciano' | 'verde' | 'laranja'
export function CartaoDeAcao({
  icone: Icone,
  selo,
  titulo,
  texto,
  para,
  acao,
  cor = 'azul',
}: {
  icone: LucideIcon
  selo: string
  titulo: string
  texto: string
  para: string
  acao: string
  cor?: CorDestaque
}) {
  return (
    <Cartao as={Link} to={para} className={`cartao-acao tom-${cor}`}>
      <span className="flex items-start justify-between gap-3">
        <span className="icone-acao">
          <Icone className="size-7 sm:size-5" strokeWidth={1.7} />
        </span>
        <span className="selo-acao">{selo}</span>
      </span>
      <span className="mt-4 text-xl leading-snug font-bold tracking-tight text-cead-900 sm:mt-3 sm:text-base sm:font-semibold">{titulo}</span>
      <span className="mt-1.5 flex-1 text-base leading-relaxed text-slate-600 sm:mt-1 sm:text-xs">{texto}</span>
      <span className="acao-destaque mt-5 inline-flex items-center gap-2 text-base font-bold sm:mt-4 sm:text-xs sm:font-semibold">
        {acao}
        <ArrowRight className="size-4" />
      </span>
    </Cartao>
  )
}
