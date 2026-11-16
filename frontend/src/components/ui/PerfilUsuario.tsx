import { ChevronDown, LogOut } from 'lucide-react'
import { useEffect, useRef } from 'react'
import { Botao } from './Botao'
function iniciais(nome: string) {
  const partes = nome.trim().split(/\s+/)
  return ((partes[0]?.[0] ?? '') + (partes.length > 1 ? partes[partes.length - 1][0] : '')).toUpperCase()
}
export function PerfilUsuario({ usuario, aoSair }: { usuario: { nome: string; perfil_rotulo: string }; aoSair: () => void }) {
  const ref = useRef<HTMLDetailsElement>(null)
  useEffect(() => {
    function fecharFora(evento: PointerEvent) {
      if (ref.current && !ref.current.contains(evento.target as Node)) ref.current.open = false
    }
    document.addEventListener('pointerdown', fecharFora)
    return () => document.removeEventListener('pointerdown', fecharFora)
  }, [])
  return (
    <details
      ref={ref}
      className="perfil-usuario"
      onKeyDown={(e) => {
        if (e.key === 'Escape') {
          e.currentTarget.open = false
          e.currentTarget.querySelector('summary')?.focus()
        }
      }}
    >
      <summary aria-label={`Opções de ${usuario.nome}`}>
        <span className="avatar-usuario">{iniciais(usuario.nome)}</span>
        <span className="hidden min-w-0 text-left text-sm leading-tight 2xl:block">
          <span className="block max-w-44 truncate font-bold">{usuario.nome}</span>
          <span className="mt-1 block text-xs text-slate-500">{usuario.perfil_rotulo}</span>
        </span>
        <ChevronDown className="size-4" />
      </summary>
      <div className="menu-usuario">
        <p className="truncate text-sm font-bold">{usuario.nome}</p>
        <p className="mt-1 mb-3 text-xs text-slate-500">{usuario.perfil_rotulo}</p>
        <Botao className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium hover:bg-slate-50" onClick={aoSair}>
          <LogOut className="size-4" /> Sair do sistema
        </Botao>
      </div>
    </details>
  )
}
