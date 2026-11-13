import type { ReactNode } from 'react'
import type { LucideIcon } from 'lucide-react'
import { Cartao } from './Cartao'
import { Titulo } from './Titulo'

export function BannerPagina({
  titulo,
  subtitulo,
  contexto,
  icone: Icone,
  acoes,
}: {
  titulo: string
  subtitulo?: string
  contexto?: string
  icone?: LucideIcon
  acoes?: ReactNode
}) {
  return (
    <Cartao as="section" className="banner-pagina mb-7">
      <div className="grafismo-marca" aria-hidden="true">
        <span />
        <span />
        <span />
      </div>
      <div className="relative flex min-w-0 flex-1 basis-full items-center gap-5 sm:basis-0">
        {Icone && (
          <span className="icone-banner">
            <Icone className="size-8 sm:size-6" strokeWidth={1.7} />
          </span>
        )}
        <div className="min-w-0">
          {contexto && <p className="mb-1 text-xs font-semibold tracking-wide text-slate-500 uppercase">{contexto}</p>}
          <Titulo className="tracking-tight">{titulo}</Titulo>
          {subtitulo && <p className="mt-1.5 max-w-3xl text-base leading-relaxed text-slate-600 sm:text-sm">{subtitulo}</p>}
        </div>
      </div>
      {acoes && <div className="relative flex flex-wrap items-center gap-2">{acoes}</div>}
    </Cartao>
  )
}
