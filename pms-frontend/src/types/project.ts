export interface Project {
  id: number;
  project_code: string;
  title: string;
  description: string;
  project_type_id: number;
  industry_id: number;
  sector_id: number;
  investment_type_id: number | null;
  funding_source_id: number | null;
  estimated_cost: number | null;
  actual_cost: number | null;
  currency: string;
  current_stage_id: number;
  status_id: number;
  proposal_date: string | null;
  start_date: string | null;
  target_completion_date: string | null;
  actual_completion_date: string | null;
  location_address: string | null;
  location_lat: number | null;
  location_lng: number | null;
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
  created_by: number;
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
  members?: ProjectMember[];
  tags?: Tag[];
  tasks?: Task[];
  documents?: Document[];
  
  // Computed attributes
  is_overdue?: boolean;
  progress_percentage?: number;
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
  status: string;
  due_date: string | null;
}

export interface Document {
  id: number;
  title: string;
  file_url: string;
  uploaded_at: string;
}

export interface ProjectFilters {
  stage_id?: number;
  status_id?: number;
  project_type_id?: number;
  industry_id?: number;
  sector_id?: number;
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
  project_type_id: number;
  industry_id: number;
  sector_id: number;
  investment_type_id?: number;
  funding_source_id?: number;
  estimated_cost?: number;
  actual_cost?: number;
  currency: string;
  current_stage_id: number;
  status_id: number;
  proposal_date?: string;
  start_date?: string;
  target_completion_date?: string;
  actual_completion_date?: string;
  location_address?: string;
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
