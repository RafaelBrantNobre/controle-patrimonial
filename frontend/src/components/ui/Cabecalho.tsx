import type { ReactNode } from 'react'
import { BannerPagina } from './BannerPagina'
export function Cabecalho({ titulo, subtitulo, acoes }: { titulo: string; subtitulo?: string; acoes?: ReactNode }) {
  return <BannerPagina titulo={titulo} subtitulo={subtitulo} acoes={acoes} />
}
