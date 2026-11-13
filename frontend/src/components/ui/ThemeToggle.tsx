import { Moon, Sun } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Botao } from './Botao'
import { Tooltip } from './Tooltip'
export function ThemeToggle() {
  const [escuro, setEscuro] = useState(() => {
    try {
      const salvo = localStorage.getItem('patrimonio-tema')
      if (salvo) return salvo === 'escuro'
    } catch {
      /* Navegação sem armazenamento. */
    }
    return window.matchMedia('(prefers-color-scheme: dark)').matches
  })
  useEffect(() => {
    document.documentElement.dataset.tema = escuro ? 'escuro' : 'claro'
    document.documentElement.style.colorScheme = escuro ? 'dark' : 'light'
    try {
      localStorage.setItem('patrimonio-tema', escuro ? 'escuro' : 'claro')
    } catch {
      /* Tema continua funcionando na sessão. */
    }
  }, [escuro])
  const rotulo = escuro ? 'Ativar tema claro' : 'Ativar tema escuro'
  return (
    <Tooltip texto={rotulo}>
      <Botao aria-label={rotulo} aria-pressed={escuro} onClick={() => setEscuro(!escuro)} className="rounded-md p-1.5 hover:bg-white/10">
        {escuro ? <Sun className="size-4" /> : <Moon className="size-4" />}
      </Botao>
    </Tooltip>
  )
}
