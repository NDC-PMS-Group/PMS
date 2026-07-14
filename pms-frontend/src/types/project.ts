import type { ProponentProfile } from './user';

export interface Project {
  id: number;
  project_code: string;
  title: string;
  description: string;
  process_track?: string | null;
  origin_track?: string | null;
  lifecycle_phase?: 'development' | 'implementation_monitoring' | 'post_investment' | 'divestment' | 'completed' | string;
  lifecycle_phase_started_at?: string | null;
  implementation_started_by?: User | null;
  date_of_application?: string | null;
  project_type_id: number;
  industry_id: number;
  sector_id: number;
  investment_type_id: number | null;
  funding_source_id: number | null;
  estimated_cost: number | null;
  actual_cost: number | null;
  target_amount_to_raise?: number | null;
  ndc_participation?: number | null;
  ndc_investment_criteria?: string[];
  project_rationale?: string | null;
  company_background?: string | null;
  target_beneficiaries?: string | null;
  expected_benefits?: string | null;
  risk_analysis?: string | null;
  financial_metrics?: ProjectFinancialMetrics | null;
  implementation_milestones?: unknown[] | null;
  issues_problems?: string | null;
  next_steps?: string | null;
  post_investment_strategy?: string | null;
  monitoring_status?: 'closed' | 'active' | 'completed' | string;
  monitoring_submission_status?: 'not_requested' | 'draft' | 'submitted' | 'returned' | 'accepted' | string;
  monitoring_draft_saved_at?: string | null;
  monitoring_submitted_at?: string | null;
  monitoring_submitted_by?: User | null;
  monitoring_reviewed_at?: string | null;
  monitoring_reviewed_by?: User | null;
  monitoring_review_notes?: string | null;
  monitoring_activated_at?: string | null;
  monitoring_activated_by?: User | null;
  monitoring_due_date?: string | null;
  monitoring_instructions?: string | null;
  monitoring_proponent_access?: boolean;
  monitoring_closed_at?: string | null;
  currency: string;
  current_stage_id: number;
  status_id: number;
  proposal_date: string | null;
  start_date: string | null;
  target_completion_date: string | null;
  actual_completion_date: string | null;
  location_address: string | null;
  location_region_code: string | null;
  location_region_name: string | null;
  location_province_code: string | null;
  location_province_name: string | null;
  location_city_code: string | null;
  location_city_name: string | null;
  location_barangay_code: string | null;
  location_barangay_name: string | null;
  location_street: string | null;
  location_lat: number | string | null;
  location_lng: number | string | null;
  map_layer: string | null;
  thumbnail_url: string | null;
  logo_url: string | null;
  project_officer_id: number | null;
  workgroup_head_id: number | null;
  proponent_name: string | null;
  proponent_contact: string | null;
  proponent_email: string | null;
  is_svf: boolean;
  is_archived: boolean;
  is_deleted: boolean;
  approval_lock?: {
    is_locked: boolean;
    can_override: boolean;
    approval_status: string | null;
    message: string | null;
  };
  approval_timing?: {
    current_step_started_at: string | null;
    sla_due_at: string | null;
    is_overdue: boolean;
  } | null;
  created_by_id?: number;
  created_by: number | User;
  created_at: string;
  updated_at: string;
  
  // Relationships
  project_type?: ProjectType;
  industry?: Industry;
  sector?: Sector;
  investment_type?: InvestmentType;
  funding_source?: FundingSource;
  current_stage?: ProjectStage;
  status?: ProjectStatus;
  project_officer?: User;
  workgroup_head?: User;
  creator?: User;
  proponent_user?: User | null;
  members?: ProjectMember[];
  tags?: Tag[];
  tasks?: Task[];
  documents?: Document[];
  images?: ProjectImage[];
  requirements?: ProjectRequirement[];
  fund_releases?: ProjectFundRelease[];
  fund_release_summary?: ProjectFundReleaseSummary;
  
  // Computed attributes
  is_overdue?: boolean;
  progress_percentage?: number;
}

export interface ProjectImage {
  id: number;
  project_id: number;
  title?: string | null;
  file_name: string;
  file_path: string;
  url?: string | null;
  file_size?: number | null;
  file_type?: string | null;
  is_thumbnail: boolean;
  sort_order?: number;
  uploaded_by?: User | null;
  uploaded_at?: string | null;
}

export interface ProjectRequirement {
  id: number;
  project_id: number;
  document_id?: number | null;
  group_name: string;
  item_name: string;
  source_document?: string | null;
  track?: string | null;
  owner_type?: 'proponent' | 'internal' | 'shared' | string;
  visibility?: 'proponent_visible' | 'internal_only' | string;
  soi_section?: string | null;
  gate_step?: string | null;
  is_required: boolean;
  is_applicable: boolean;
  svf_only: boolean;
  status: string;
  due_date?: string | null;
  received_at?: string | null;
  remarks?: string | null;
  sort_order: number;
  document?: Document | null;
  received_by?: User | null;
}

export interface ProjectFundReleaseSummary {
  target_amount: number;
  released_amount: number;
  remaining_amount: number;
  release_count: number;
  released_count: number;
  progress: number;
}

export interface ProjectFundRelease {
  id: number;
  project_id: number;
  requirement_id?: number | null;
  task_id?: number | null;
  document_id?: number | null;
  funding_source_id?: number | null;
  soi_section?: string | null;
  gate_step?: string | null;
  release_type: string;
  status: string;
  reference_no?: string | null;
  payee?: string | null;
  approved_amount?: number | string | null;
  amount: number | string;
  release_date?: string | null;
  remarks?: string | null;
  reviewed_at?: string | null;
  released_at?: string | null;
  requirement?: ProjectRequirement | null;
  task?: Task | null;
  document?: Document | null;
  funding_source?: FundingSource | null;
  prepared_by?: User | null;
  reviewed_by?: User | null;
  released_by?: User | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface ProjectType {
  id: number;
  name: string;
  code: string;
}

export interface Industry {
  id: number;
  name: string;
}

export interface Sector {
  id: number;
  name: string;
}

export interface InvestmentType {
  id: number;
  name: string;
}

export interface FundingSource {
  id: number;
  name: string;
}

export interface ProjectStage {
  id: number;
  name: string;
  order: number;
  color: string;
}

export interface ProjectStatus {
  id: number;
  name: string;
  color: string;
}

export interface ProjectMember {
  id: number;
  project_id: number;
  user_id: number;
  role_id: number;
  assignment_type?: string;
  can_view?: boolean;
  can_edit?: boolean;
  can_delete?: boolean;
  can_approve?: boolean;
  can_manage_members?: boolean;
  permissions?: {
    can_view: boolean;
    can_edit: boolean;
    can_delete: boolean;
    can_approve: boolean;
    can_manage_members: boolean;
  };
  assigned_by?: number | User;
  assigned_at?: string;
  removed_at: string | null;
  user?: User;
  role?: Role;
}

export interface User {
  id: number;
  name?: string;
  full_name?: string;
  first_name?: string;
  last_name?: string;
  email: string;
  avatar?: string;
  organization_name?: string | null;
  organization_type?: string | null;
  organization_registration_no?: string | null;
  proponent_profile?: ProponentProfile | null;
  role?: Role | null;
}

export interface Role {
  id: number;
  name: string;
}

export interface Tag {
  id: number;
  name: string;
  color: string;
}

export interface Task {
  id: number;
  title: string;
  description?: string | null;
  task_type?: string | null;
  soi_section?: string | null;
  task_scope?: 'implementation' | 'legacy_soi' | string;
  workstream?: string | null;
  template_source?: string | null;
  archived_at?: string | null;
  parent_task_id?: number | null;
  assigned_to?: User | null;
  assigned_by?: User | null;
  start_date?: string | null;
  status: string;
  progress_percentage?: number | null;
  priority?: string | null;
  due_date: string | null;
  completion_date?: string | null;
  estimated_hours?: number | null;
  actual_hours?: number | null;
  is_milestone?: boolean;
  is_overdue?: boolean;
  subtasks?: Task[];
}

export interface Document {
  id: number;
  title: string;
  description?: string | null;
  file_name?: string;
  file_path?: string;
  download_url?: string;
  file_size?: number | null;
  file_type?: string | null;
  category?: string | null;
  version?: number;
  is_public?: boolean;
  requires_approval?: boolean;
  submission_status?: 'draft' | 'submitted' | 'update_requested' | string;
  uploaded_by?: User;
  uploaded_at?: string;
  submitted_by?: User;
  submitted_at?: string;
  update_requested_by?: User;
  update_requested_at?: string;
  update_request_reason?: string | null;
  task?: Task;
}

export interface ProjectFilters {
  stage_id?: number;
  status_id?: number;
  project_type_id?: number;
  industry_id?: number;
  sector_id?: number;
  process_track?: string;
  report_preset?: 'all' | 'approved' | 'ongoing' | 'completed' | 'categorized' | 'reportable';
  date_from?: string;
  date_to?: string;
  date_field?: string;
  estimated_cost_min?: number | string;
  estimated_cost_max?: number | string;
  actual_cost_min?: number | string;
  actual_cost_max?: number | string;
  progress_min?: number | string;
  progress_max?: number | string;
  is_overdue?: boolean;
  reportable_to_gcg?: boolean;
  is_svf?: boolean;
  is_archived?: boolean;
  my_projects?: boolean;
  editable_projects?: boolean;
  search?: string;
  sort_by?: string;
  sort_order?: 'asc' | 'desc';
  per_page?: number;
  page?: number;
}

export interface ProjectFormData {
  title: string;
  description: string;
  process_track?: string;
  origin_track?: string;
  lifecycle_phase?: 'development' | 'implementation_monitoring' | 'post_investment' | 'divestment' | 'completed';
  date_of_application?: string;
  project_type_id: number;
  industry_id: number;
  sector_id: number;
  investment_type_id?: number;
  funding_source_id?: number;
  estimated_cost?: number;
  actual_cost?: number;
  target_amount_to_raise?: number;
  ndc_participation?: number;
  ndc_investment_criteria?: string[];
  project_rationale?: string;
  company_background?: string;
  target_beneficiaries?: string;
  expected_benefits?: string;
  risk_analysis?: string;
  financial_metrics?: ProjectFinancialMetrics;
  issues_problems?: string;
  next_steps?: string;
  post_investment_strategy?: string;
  currency: string;
  current_stage_id: number;
  status_id: number;
  proposal_date?: string;
  start_date?: string;
  target_completion_date?: string;
  actual_completion_date?: string;
  location_address?: string;
  location_region_code?: string;
  location_region_name?: string;
  location_province_code?: string;
  location_province_name?: string;
  location_city_code?: string;
  location_city_name?: string;
  location_barangay_code?: string;
  location_barangay_name?: string;
  location_street?: string;
  location_lat?: number;
  location_lng?: number;
  thumbnail_url?: string;
  logo_url?: string;
  project_officer_id?: number;
  workgroup_head_id?: number;
  proponent_name?: string;
  proponent_contact?: string;
  proponent_email?: string;
  is_svf?: boolean;
}

export interface ProjectFinancialMetrics {
  jobs_generated_direct?: number | null;
  jobs_generated_indirect?: number | null;
  retained_jobs?: number | null;
  jobs_direct_male?: number | null;
  jobs_direct_female?: number | null;
  jobs_indirect_male?: number | null;
  jobs_indirect_female?: number | null;
  jobs_retained_male?: number | null;
  jobs_retained_female?: number | null;
  projected_revenue?: number | null;
  actual_revenue?: number | null;
  dividend_remittance?: number | null;
  gcg_relevance?: boolean;
  gcg_score?: number | null;
  reportable_to_gcg?: boolean;
  is_reportable?: boolean;
  monitoring_frequency?: string | null;
  reporting_period?: string | null;
  monitoring_indicators?: string | null;
  gcg_metrics?: string | null;
  social_impact_notes?: string | null;
}

export interface ProjectStageHistory {
  id: number;
  project_id: number;
  from_stage_id: number | null;
  to_stage_id: number;
  changed_by: number;
  changed_at: string;
  change_reason: string | null;
  from_stage?: ProjectStage;
  to_stage?: ProjectStage;
  changed_by_user?: User;
}

export interface ProjectStatusHistory {
  id: number;
  project_id: number;
  from_status_id: number | null;
  to_status_id: number;
  changed_by: number;
  changed_at: string;
  change_reason: string | null;
  from_status?: ProjectStatus;
  to_status?: ProjectStatus;
  changed_by_user?: User;
}

export interface ApprovalStep {
  id: number;
  workflow_id: number;
  role_id: number;
  step_order: number;
  step_name: string;
  soi_section?: string | null;
  description: string | null;
  role?: Role;
}

export interface ApprovalWorkflow {
  id: number;
  name: string;
  description: string | null;
  project_type_id: number | null;
  is_active: boolean;
  steps?: ApprovalStep[];
}

export interface ProjectApproval {
  id: number;
  project_id: number;
  workflow_id: number;
  current_step_id: number | null;
  overall_status: string;
  started_at: string;
  completed_at: string | null;
  workflow?: ApprovalWorkflow;
  current_step?: ApprovalStep;
}

export interface ApprovalStepRecord {
  id: number;
  project_approval_id: number;
  step_id: number;
  approver_id: number | null;
  status: string;
  comments: string | null;
  conditions: string | null;
  submitted_at: string | null;
  reviewed_at: string | null;
  approver?: User;
  step?: ApprovalStep;
}
