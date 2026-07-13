import type { Project, User } from '@/types/project'
import type { PaginationMeta } from '@/types/paginationMeta'

export const DIVESTMENT_PHASES = [
  'assessment',
  'due_diligence',
  'management_approval',
  'board_approval',
  'execution',
  'closure',
] as const

export type DivestmentPhase = typeof DIVESTMENT_PHASES[number]
export type DivestmentStatus = 'active' | 'closed' | 'cancelled'

export interface DivestmentTransition {
  id: number
  from_phase: DivestmentPhase | null
  to_phase: DivestmentPhase
  notes: string
  transitioned_by: User | null
  transitioned_at: string
}

export interface DivestmentClosureGates {
  board_approved_at: string | null
  transfer_completed_at: string | null
  proceeds_collected_at: string | null
  closing_documents_completed_at: string | null
}

export interface DivestmentCase {
  id: number
  case_number: string
  project_id: number
  project: Project
  phase: DivestmentPhase
  next_phase: DivestmentPhase | null
  status: DivestmentStatus
  exit_strategy: string
  target_exit_date: string | null
  estimated_proceeds: number | string | null
  actual_proceeds: number | string | null
  notes: string | null
  phase_started_at: string | null
  progress_percentage: number
  closure_gates: DivestmentClosureGates
  missing_closure_gates: (keyof DivestmentClosureGates)[]
  closure_notes: string | null
  closed_at: string | null
  transitions: DivestmentTransition[]
  created_at: string
  updated_at: string
}

export interface DivestmentCaseFilters {
  search?: string
  phase?: DivestmentPhase | ''
  status?: DivestmentStatus | ''
  page?: number
  per_page?: number
}

export interface CreateDivestmentCasePayload {
  project_id: number
  exit_strategy: string
  target_exit_date?: string | null
  estimated_proceeds?: number | null
  notes?: string | null
}

export interface CloseDivestmentCasePayload extends DivestmentClosureGates {
  actual_proceeds: number
  closure_notes: string
}

export interface DivestmentState {
  cases: DivestmentCase[]
  selectedCase: DivestmentCase | null
  pagination: PaginationMeta | null
  loading: boolean
  submitting: boolean
}
