// Formato dos dados que a API devolve (README, seções 9 a 13).

export type Usuario = {
  id: number
  nome: string
  email: string
  perfil: 'administrador' | 'consulta'
  perfil_rotulo: string
  pode_alterar: boolean
}

export type Sala = {
  id: number
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
  // Nulo quando o bem está sem localização.
  sala_id: number | null
  sala?: Sala | null
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
  // Só na ficha: o histórico de ocorrências do bem.
  ocorrencias?: Ocorrencia[]
  cadastrado_em: string | null
  atualizado_em: string | null
}

export type Ocorrencia = {
  id: number
  tipo: 'sala' | 'conservacao' | 'uso' | 'baixa'
  tipo_rotulo: string
  valor_anterior: string | null
  valor_novo: string | null
  observacao: string | null
  data: string
  usuario: string
  conferencia: { id: number; nome: string } | null
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
  // Sala do bem quando a conferência foi gerada: a da folha impressa.
  sala_registrada_id: number | null
  sala_registrada?: Sala | null
  resultado: ResultadoConferencia | null
  resultado_rotulo: string | null
  sala_encontrada_id: number | null
  sala_encontrada?: Sala | null
  estado_conservacao: EstadoConservacao | null
  estado_conservacao_rotulo: string | null
  condicao_uso: 'em_uso' | 'sem_uso' | null
  condicao_uso_rotulo: string | null
  conferido_em: string | null
  conferido_por?: { id: number; nome: string } | null
}

// GET /conferencias/{id}/itens: os itens, a conferência e as salas dela.
export type ItensDaConferencia = {
  data: ItemDeConferencia[]
  conferencia: Conferencia
  // id nulo é o grupo "Sem localização": bens sem sala registrada.
  salas: { id: number | null; nome: string; total: number; pendentes: number }[]
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
