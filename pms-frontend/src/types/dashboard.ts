export type DashboardScope = 'mine' | 'portfolio';
export type DueWindow = 'all' | 'overdue' | '7' | '14' | '30';
export type Priority = 'normal' | 'high' | 'critical';

export interface DashboardRoute {
  path: string;
  query?: Record<string, string | number>;
}

export interface DashboardFilters {
  year: number | null;
  due_window: DueWindow;
  scope: DashboardScope;
  sector_id: number | null;
  stage_id: number | null;
  origin_track: string | null;
  lifecycle_phase: string | null;
  officer_id: number | null;
}

export interface FilterOption<T = string | number> {
  value: T;
  label: string;
}

export interface NamedOption {
  id: number;
  name: string;
}

export interface DecisionQueueItem {
  approval_id: number | null;
  project_id: number;
  project_code: string | null;
  title: string;
  overall_status: string;
  current_step: string;
  role: string;
  stage: string;
  status: string;
  started_at: string | null;
  type: 'approval' | 'revision' | 'monitoring';
  priority: Priority;
  due_date: string | null;
  action_label: string;
  route: DashboardRoute;
}

export interface RiskReason {
  code: string;
  label: string;
}

export interface RiskProject {
  project_id: number;
  project_code: string;
  title: string;
  stage: string;
  officer: string;
  risk_score: number;
  risk_level: 'watch' | 'high' | 'critical';
  reasons: RiskReason[];
  target_completion_date: string | null;
  monitoring_due_date: string | null;
  route: DashboardRoute;
}

export interface WorkloadOfficer {
  user_id: number;
  name: string;
  active_projects: number;
  open_tasks: number;
  overdue_tasks: number;
  load_level: 'balanced' | 'moderate' | 'high';
}

export interface Workload {
  mode: 'team' | 'personal';
  totals: {
    officers: number;
    active_projects: number;
    open_tasks: number;
    overdue_tasks: number;
    unassigned_projects: number;
  };
  officers: WorkloadOfficer[];
}

export interface MonitoringComplianceProject {
  project_id: number;
  project_code: string;
  title: string;
  due_date: string | null;
  submission_status: string;
  is_overdue: boolean;
}

export interface MonitoringCompliance {
  active: number;
  due_in_window: number;
  overdue: number;
  submitted: number;
  accepted: number;
  missing_due_date: number;
  compliance_rate: number;
  projects: MonitoringComplianceProject[];
}

export interface DataQualityRecord {
  project_id: number;
  project_code: string;
  title: string;
  missing_fields: string[];
  completeness: number;
}

export interface DataQuality {
  total_projects: number;
  complete_projects: number;
  projects_with_issues: number;
  completeness_rate: number;
  records: DataQualityRecord[];
}

export interface DashboardFilterPayload {
  applied: DashboardFilters;
  available_years: number[];
  due_windows: FilterOption<DueWindow>[];
  scopes: FilterOption<DashboardScope>[];
  sectors: NamedOption[];
  stages: NamedOption[];
  origin_tracks: FilterOption[];
  lifecycle_phases: FilterOption[];
  officers: NamedOption[];
  role: {
    name: string;
    mode: 'portfolio' | 'officer';
    can_view_portfolio: boolean;
  };
}

export interface DashboardStats {
  total_projects: number;
  my_projects: number;
  pending_approvals: number;
  overdue_tasks: number;
  my_tasks: number;
  completed_this_month: number;
  approved_with_conditions: number;
  revision_requests_count: number;
  active_workflows: number;
  pending_actions: unknown[];
  revision_requests: unknown[];
  workflow_summary: Array<{ overall_status: string; count: number }>;
  projects_by_stage: Array<{ count: number; total_investment?: number; current_stage?: NamedOption }>;
  projects_by_status: Array<{ count: number; status?: NamedOption }>;
  projects_by_sector: Array<{ count: number; total_investment?: number; sector?: NamedOption }>;
  monitoring_summary: Record<string, number>;
  lifecycle_pipeline: Array<{ label: string; count: number }>;
  attention_summary: Record<string, number>;
  decision_queue: DecisionQueueItem[];
  risk_projects: RiskProject[];
  workload: Workload;
  monitoring_compliance: MonitoringCompliance;
  data_quality: DataQuality;
  filters: DashboardFilterPayload;
}
