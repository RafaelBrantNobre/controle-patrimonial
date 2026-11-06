// Formato dos dados que a API devolve (README, seções 9 a 13).

export type Usuario = {
  id: number
  nome: string
  email: string
  perfil: 'administrador' | 'consulta'
  perfil_rotulo: string
  pode_alterar: boolean
}

export type Andar = {
  id: number
  nome: string
  ordem: number
  ativo: boolean
  salas_count?: number
}

export type Sala = {
  id: number
  andar_id: number
  andar?: Andar
  nome: string
  descricao: string | null
  ativo: boolean
  bens_count?: number
}

export type TipoPatrimonio = {
  id: number
  nome: string
  ativo: boolean
  bens_count?: number
}

export type StatusBem = 'em_uso' | 'sem_uso' | 'baixado'

export type EstadoConservacao = 'novo' | 'bom' | 'regular' | 'pessimo' | 'sucata' | 'indeterminado'

export type Movimentacao = {
  id: number
  sala_origem?: Sala
  sala_destino?: Sala
  data: string
  usuario?: { id: number; nome: string }
}

export type Bem = {
  id: number
  numero_patrimonio: string | null
  codigo_interno: string
  tipo_patrimonio_id: number
  tipo_patrimonio?: TipoPatrimonio
  sala_id: number
  sala?: Sala
  ultima_movimentacao?: Movimentacao | null
  descricao: string | null
  marca: string | null
  origem: string | null
  quantidade: number
  status: StatusBem
  status_rotulo: string
  estado_conservacao: EstadoConservacao
  estado_conservacao_rotulo: string
  baixado: boolean
  data_ultima_mudanca: string | null
  usuario_ultima_mudanca?: { id: number; nome: string } | null
  baixa_data: string | null
  baixa_observacao: string | null
  data_ultima_conferencia: string | null
  resultado_ultima_conferencia: ResultadoConferencia | null
  resultado_ultima_conferencia_rotulo: string | null
  // Só quando a resposta carrega: a conferência aberta em que o bem está,
  // a pendência (se não foi localizado) e, na ficha, o histórico.
  em_conferencia?: { conferencia_id: number; nome: string } | null
  pendencia?: { desde: string | null; conferencia_id: number; conferencia: string } | null
  historico_conferencias?: {
    conferencia_id: number
    conferencia: string
    realizada_em: string | null
    resultado: ResultadoConferencia | null
    resultado_rotulo: string | null
    resolucao_rotulo: string | null
    processo_numero: string | null
  }[]
  cadastrado_em: string | null
  atualizado_em: string | null
}

export type ResultadoConferencia = 'encontrado' | 'nao_localizado'

export type SituacaoConferencia = 'aberta' | 'concluida' | 'cancelada'

export type Conferencia = {
  id: number
  nome: string
  situacao: SituacaoConferencia
  situacao_rotulo: string
  realizada_em: string | null
  criada_por?: { id: number; nome: string }
  criada_em: string | null
  concluida_em: string | null
  concluida_por?: { id: number; nome: string } | null
  totais?: { itens: number; pendentes: number; encontrados: number; nao_localizados: number }
}

export type ItemDeConferencia = {
  id: number
  conferencia_id: number
  bem: Bem
  resultado: ResultadoConferencia | null
  resultado_rotulo: string | null
  sala_encontrada_id: number | null
  sala_encontrada?: Sala | null
  estado_conservacao: EstadoConservacao | null
  estado_conservacao_rotulo: string | null
  conferido_em: string | null
  conferido_por?: { id: number; nome: string } | null
}

// GET /conferencias/{id}/itens: os itens, a conferência e as salas dela.
export type ItensDaConferencia = {
  data: ItemDeConferencia[]
  conferencia: Conferencia
  salas: { id: number; nome: string; andar: string; total: number; pendentes: number }[]
}

export const SITUACOES_DE_CONFERENCIA = {
  nunca: 'Nunca conferido',
  encontrado: 'Conferido e encontrado',
  nao_localizado: 'Não localizado',
  em_aberto: 'Em conferência aberta',
} as const

// Listagens paginadas do Laravel.
export type Pagina<T> = {
  data: T[]
  meta: { total: number; current_page: number; last_page: number; per_page: number }
}

export const ROTULOS_STATUS: Record<StatusBem, string> = {
  em_uso: 'Em uso',
  sem_uso: 'Sem uso',
  baixado: 'Baixado',
}

export const ROTULOS_CONSERVACAO: Record<EstadoConservacao, string> = {
  novo: 'Novo',
  bom: 'Bom',
  regular: 'Regular',
  pessimo: 'Péssimo',
  sucata: 'Sucata',
  indeterminado: 'Indeterminado',
}

export type BemDoRelatorio = {
  id: number
  numero_patrimonio: string | null
  codigo_interno: string
  tipo: string
  descricao: string | null
  marca: string | null
  quantidade: number
  status: StatusBem
  status_rotulo: string
  estado_conservacao: EstadoConservacao
  estado_conservacao_rotulo: string
  baixado: boolean
  baixa_data: string | null
  data_ultima_mudanca: string | null
  mudou_no_periodo: boolean
  mudou_de_sala_no_periodo: boolean
  veio_da_sala: { id: number; nome: string; andar: string } | null
}

export type Relatorio = {
  periodo: { inicio: string; fim: string }
  totais: {
    bens: number
    mudaram_no_periodo: number
    mudaram_de_sala_no_periodo: number
    por_status: Record<StatusBem, number>
    por_estado_conservacao: Record<EstadoConservacao, number>
  }
  andares: {
    id: number
    nome: string
    total: number
    salas: { id: number; nome: string; total: number; bens: BemDoRelatorio[] }[]
  }[]
}
