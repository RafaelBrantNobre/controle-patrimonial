export function Desativado() {
  return (
    <div className="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-center text-sm font-medium text-slate-500">
      Desativado
    </div>
  )
}

export function Carregando({ texto = 'Carregando...' }: { texto?: string }) {
  return <p className="px-4 py-8 text-center text-sm text-slate-500">{texto}</p>
}
