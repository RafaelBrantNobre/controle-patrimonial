import { Botao } from './Botao'
import { ChevronLeft, ChevronRight } from 'lucide-react'

export function Paginacao({
  pagina,
  ultima,
  total,
  rotulo,
  singular,
  aoMudar,
}: {
  pagina: number
  ultima: number
  total: number
  rotulo: string
  // Para o total 1: "1 bem encontrado" em vez de "1 bens encontrados".
  singular?: string
  aoMudar: (pagina: number) => void
}) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-xs text-slate-500">
      <span>
        {total} {total === 1 && singular ? singular : rotulo}
      </span>
      <div className="flex items-center gap-2">
        <Botao
          type="button"
          variante="secundario"
          className="px-2 py-1"
          disabled={pagina <= 1}
          onClick={() => aoMudar(pagina - 1)}
          aria-label="Página anterior"
        >
          <ChevronLeft className="size-4" />
        </Botao>
        <span>
          Página {pagina} de {Math.max(ultima, 1)}
        </span>
        <Botao
          type="button"
          variante="secundario"
          className="px-2 py-1"
          disabled={pagina >= ultima}
          onClick={() => aoMudar(pagina + 1)}
          aria-label="Próxima página"
        >
          <ChevronRight className="size-4" />
        </Botao>
      </div>
    </div>
  )
}
