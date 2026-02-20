export interface Region {
  id: number
  office_name: string
  region: string
  invoicing_scc_officer: string | null
  invoicing_acc_officer?: string
  invoicing_acc_officer_pos?: string
  receiving_acc_officer?: string
  receiving_acc_officer_pos?: string
  divisions_count?: number
  created_at?: string
  updated_at?: string
}

export interface CreateRegionPayload {
  office_name: string
  region: string
  invoicing_acc_officer?: string
  invoicing_acc_officer_pos?: string
  receiving_acc_officer?: string
  receiving_acc_officer_pos?: string
}

export interface UpdateRegionPayload {
  office_name: string
  region: string
  invoicing_acc_officer?: string
  invoicing_acc_officer_pos?: string
  receiving_acc_officer?: string
  receiving_acc_officer_pos?: string
}