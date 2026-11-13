import { Selo, type CorSelo } from '../components/ui/Selo'
import { formatarData } from '../services/formatos'
import type { Bem, EstadoConservacao, StatusBem } from '../services/tipos'

const COR_STATUS: Record<StatusBem, CorSelo> = { em_uso: 'verde', sem_uso: 'azul', baixado: 'vermelho' }

export function SeloStatus({ status, rotulo }: { status: StatusBem; rotulo: string }) {
  return <Selo cor={COR_STATUS[status]}>{rotulo}</Selo>
}

const COR_CONSERVACAO: Record<EstadoConservacao, CorSelo> = {
  novo: 'verde',
  bom: 'verde',
  regular: 'amarelo',
  pessimo: 'vermelho',
  sucata: 'vermelho',
  indeterminado: 'cinza',
}

export function SeloConservacao({ estado, rotulo }: { estado: EstadoConservacao; rotulo: string }) {
  return <Selo cor={COR_CONSERVACAO[estado]}>{rotulo}</Selo>
}

export function SeloConferencia({ bem }: { bem: Bem }) {
  if (bem.em_conferencia) return <Selo cor="azul">Em conferência</Selo>
  if (bem.resultado_ultima_conferencia === 'encontrado') {
    return <Selo cor="verde">Conferido em {formatarData(bem.data_ultima_conferencia)}</Selo>
  }
  if (bem.resultado_ultima_conferencia === 'nao_localizado') {
    return <Selo cor="vermelho">Não localizado em {formatarData(bem.data_ultima_conferencia)}</Selo>
  }
  return <Selo cor="cinza">Nunca conferido</Selo>
}

export function SeloAtivo({ ativo }: { ativo: boolean }) {
  return <Selo cor={ativo ? 'verde' : 'cinza'}>{ativo ? 'Ativo' : 'Inativo'}</Selo>
}
