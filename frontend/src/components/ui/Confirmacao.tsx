import type { ReactNode } from 'react'
import { Botao, type VarianteBotao } from './Botao'
import { Dialogo } from './Dialogo'
export type ConfirmacaoProps = {
  titulo: ReactNode
  children: ReactNode
  aoFechar: () => void
  aoConfirmar: () => void
  ocultarConfirmacao?: boolean
  ocupado?: boolean
  desabilitado?: boolean
  confirmar?: string
  processando?: string
  cancelar?: string
  variante?: VarianteBotao
}
export function ConfirmarEnvio({
  titulo,
  children,
  aoFechar,
  aoConfirmar,
  ocupado = false,
  ocultarConfirmacao = false,
  desabilitado = false,
  confirmar = 'Confirmar e salvar',
  processando = 'Salvando...',
  cancelar = 'Voltar',
  variante = 'primario',
}: ConfirmacaoProps) {
  return (
    <Dialogo
      titulo={titulo}
      aoFechar={aoFechar}
      ocupado={ocupado}
      rodape={
        <>
          <Botao variante="secundario" onClick={aoFechar} disabled={ocupado}>
            {cancelar}
          </Botao>
          {!ocultarConfirmacao && (
            <Botao variante={variante} onClick={aoConfirmar} disabled={ocupado || desabilitado}>
              {ocupado ? processando : confirmar}
            </Botao>
          )}
        </>
      }
    >
      {children}
    </Dialogo>
  )
}
export function ConfirmarExclusao(props: Omit<ConfirmacaoProps, 'variante'>) {
  return <ConfirmarEnvio confirmar="Excluir permanentemente" processando="Excluindo..." cancelar="Cancelar" {...props} variante="perigo" />
}
