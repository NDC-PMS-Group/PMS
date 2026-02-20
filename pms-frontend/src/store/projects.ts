import { defineStore } from 'pinia';
import type { 
  Project, 
  ProjectFilters, 
  ProjectFormData, 
  ProjectStageHistory, 
  ProjectStatusHistory,
  ProjectType,
  Industry,
  Sector,
  ProjectStage,
  ProjectStatus,
  InvestmentType,
  FundingSource
} from '@/types/project';
import type { PaginationMeta } from '@/types/paginationMeta';
import axiosInstance from '@/utils/axiosInstance';

const PROJECT_ENDPOINTS = ['/api/v1/admin/projects', '/api/v1/projects', '/api/projects'];
const LOOKUP_ENDPOINTS = ['/api/v1/admin/lookup', '/api/v1/lookup', '/api/lookup'];

const isFallbackCandidateError = (error: any) => {
  const status = error?.response?.status;
  return status === 404 || status === 405;
};

const requestWithFallback = async <T>(requestFn: (basePath: string) => Promise<T>, basePaths: string[]): Promise<T> => {
  let lastError: any = null;

  for (const basePath of basePaths) {
    try {
      return await requestFn(basePath);
    } catch (error: any) {
      lastError = error;
      if (!isFallbackCandidateError(error)) {
        throw error;
      }
    }
  }

  throw lastError;
};

const parsePagination = (source: any): PaginationMeta | null => {
  if (!source || typeof source !== 'object') return null;

  const currentPage = source.current_page ?? source.currentPage;
  const lastPage = source.last_page ?? source.lastPage;
  const perPage = source.per_page ?? source.perPage;
  const total = source.total;

  if (
    typeof currentPage !== 'number' ||
    typeof lastPage !== 'number' ||
    typeof perPage !== 'number' ||
    typeof total !== 'number'
  ) {
    return null;
  }

  return {
    current_page: currentPage,
    last_page: lastPage,
    per_page: perPage,
    total,
    from: source.from ?? null,
    to: source.to ?? null,
  };
};

const parseProjectListResponse = (responseData: any): { projects: Project[]; pagination: PaginationMeta | null } => {
  const payload = responseData?.data ?? responseData;
  const list =
    (Array.isArray(payload?.data) && payload.data) ||
    (Array.isArray(payload?.projects) && payload.projects) ||
    (Array.isArray(payload) && payload) ||
    (Array.isArray(responseData?.data) && responseData.data) ||
    (Array.isArray(responseData?.projects) && responseData.projects) ||
    [];

  const pagination =
    parsePagination(payload?.meta?.pagination) ||
    parsePagination(payload?.meta) ||
    parsePagination(payload?.pagination) ||
    parsePagination(payload) ||
    parsePagination(responseData?.meta?.pagination) ||
    parsePagination(responseData?.meta) ||
    parsePagination(responseData?.pagination) ||
    parsePagination(responseData);

  return {
    projects: list,
    pagination,
  };
};

const parseProjectItemResponse = (responseData: any): Project | null => {
  const payload = responseData?.data ?? responseData;
  if (payload && typeof payload === 'object' && !Array.isArray(payload) && typeof payload.id === 'number') {
    return payload as Project;
  }
  if (responseData?.project && typeof responseData.project === 'object') {
    return responseData.project as Project;
  }
  if (payload?.project && typeof payload.project === 'object') {
    return payload.project as Project;
  }
  return null;
};

const parseLookupItems = <T>(responseData: any): T[] => {
  const payload = responseData?.data ?? responseData;
  if (Array.isArray(payload?.data)) return payload.data as T[];
  if (Array.isArray(payload)) return payload as T[];
  return [];
};

const nullableFields: (keyof ProjectFormData)[] = [
  'investment_type_id',
  'funding_source_id',
  'estimated_cost',
  'actual_cost',
  'proposal_date',
  'start_date',
  'target_completion_date',
  'actual_completion_date',
  'location_address',
  'location_lat',
  'location_lng',
  'thumbnail_url',
  'logo_url',
  'project_officer_id',
  'workgroup_head_id',
  'proponent_name',
  'proponent_contact',
  'proponent_email',
];

const numericFields: (keyof ProjectFormData)[] = [
  'project_type_id',
  'industry_id',
  'sector_id',
  'current_stage_id',
  'status_id',
  'investment_type_id',
  'funding_source_id',
  'estimated_cost',
  'actual_cost',
  'location_lat',
  'location_lng',
  'project_officer_id',
  'workgroup_head_id',
];

const normalizeProjectPayload = (data: Partial<ProjectFormData>): Partial<ProjectFormData> => {
  const payload: Record<string, any> = { ...data };

  if (typeof payload.title === 'string') {
    payload.title = payload.title.trim();
  }
  if (typeof payload.description === 'string') {
    payload.description = payload.description.trim();
  }

  nullableFields.forEach((field) => {
    if (!(field in payload)) return;
    if (payload[field] === '' || payload[field] === undefined) {
      payload[field] = null;
    }
  });

  numericFields.forEach((field) => {
    if (!(field in payload)) return;
    const rawValue = payload[field];
    if (rawValue === null || rawValue === undefined || rawValue === '') return;
    const num = Number(rawValue);
    if (!Number.isNaN(num)) {
      payload[field] = num;
    }
  });

  if ('is_svf' in payload) {
    payload.is_svf = Boolean(payload.is_svf);
  }

  return Object.fromEntries(
    Object.entries(payload).filter(([, value]) => value !== undefined)
  ) as Partial<ProjectFormData>;
};

interface ProjectState {
  projects: Project[];
  currentProject: Project | null;
  projectTypes: ProjectType[];
  industries: Industry[];
  sectors: Sector[];
  stages: ProjectStage[];
  statuses: ProjectStatus[];
  investmentTypes: InvestmentType[];
  fundingSources: FundingSource[];
  pagination: PaginationMeta | null;
  filters: ProjectFilters;
  loading: boolean;
  error: string | null;
}

export const useProjectStore = defineStore('project', {
  state: (): ProjectState => ({
    projects: [],
    currentProject: null,
    projectTypes: [],
    industries: [],
    sectors: [],
    stages: [],
    statuses: [],
    investmentTypes: [],
    fundingSources: [],
    pagination: null,
    filters: {
      search: '',
      sort_by: 'created_at',
      sort_order: 'desc',
      per_page: 15,
      page: 1,
      is_archived: false
    },
    loading: false,
    error: null
  }),

  getters: {
    activeProjects: (state) => state.projects.filter(p => !p.is_archived && !p.is_deleted),
    archivedProjects: (state) => state.projects.filter(p => p.is_archived),
    svfProjects: (state) => state.projects.filter(p => p.is_svf),
    overdueProjects: (state) => state.projects.filter(p => p.is_overdue),
  },

  actions: {
    getApiErrorMessage(error: any, fallback: string): string {
      return (
        error?.response?.data?.error ||
        error?.response?.data?.message ||
        error?.message ||
        fallback
      );
    },

    async fetchProjects(filters?: Partial<ProjectFilters>) {
      this.loading = true;
      this.error = null;
      
      try {
        // Merge filters with existing state
        if (filters) {
          this.filters = { ...this.filters, ...filters };
        }

        const params = new URLSearchParams();
        Object.entries(this.filters).forEach(([key, value]) => {
          if (value !== undefined && value !== null && value !== '') {
            params.append(key, String(value));
          }
        });

        const query = params.toString();
        const response = await requestWithFallback(
          (basePath) => axiosInstance.get(query ? `${basePath}?${query}` : basePath),
          PROJECT_ENDPOINTS
        );
        const parsed = parseProjectListResponse(response.data);
        this.projects = parsed.projects;
        this.pagination = parsed.pagination;
      } catch (error: any) {
        this.error = this.getApiErrorMessage(error, 'Failed to fetch projects');
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async fetchProject(id: number) {
      this.loading = true;
      this.error = null;
      
      try {
        const response = await requestWithFallback(
          (basePath) => axiosInstance.get(`${basePath}/${id}`),
          PROJECT_ENDPOINTS
        );
        const project = parseProjectItemResponse(response.data);
        this.currentProject = project;
        return project;
      } catch (error: any) {
        this.error = this.getApiErrorMessage(error, 'Failed to fetch project');
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async createProject(data: ProjectFormData) {
      this.loading = true;
      this.error = null;
      
      try {
        const payload = normalizeProjectPayload(data) as ProjectFormData;
        const response = await requestWithFallback(
          (basePath) => axiosInstance.post(basePath, payload),
          PROJECT_ENDPOINTS
        );
        const createdProject = parseProjectItemResponse(response.data);
        if (createdProject) {
          this.projects.unshift(createdProject);
        }
        return createdProject;
      } catch (error: any) {
        this.error = this.getApiErrorMessage(error, 'Failed to create project');
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async updateProject(id: number, data: Partial<ProjectFormData>) {
      this.loading = true;
      this.error = null;
      
      try {
        const payload = normalizeProjectPayload(data);
        const response = await requestWithFallback(
          (basePath) => axiosInstance.put(`${basePath}/${id}`, payload),
          PROJECT_ENDPOINTS
        );
        const updatedProject = parseProjectItemResponse(response.data);
        const index = this.projects.findIndex(p => p.id === id);
        if (index !== -1 && updatedProject) {
          this.projects[index] = updatedProject;
        }
        if (this.currentProject?.id === id && updatedProject) {
          this.currentProject = updatedProject;
        }
        return updatedProject;
      } catch (error: any) {
        this.error = this.getApiErrorMessage(error, 'Failed to update project');
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async deleteProject(id: number) {
      this.loading = true;
      this.error = null;
      
      try {
        await requestWithFallback(
          (basePath) => axiosInstance.delete(`${basePath}/${id}`),
          PROJECT_ENDPOINTS
        );
        this.projects = this.projects.filter(p => p.id !== id);
      } catch (error: any) {
        this.error = this.getApiErrorMessage(error, 'Failed to delete project');
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async archiveProject(id: number) {
      this.loading = true;
      this.error = null;
      
      try {
        const response = await requestWithFallback(
          (basePath) => axiosInstance.post(`${basePath}/${id}/archive`),
          PROJECT_ENDPOINTS
        );
        const archivedProject = parseProjectItemResponse(response.data);
        const index = this.projects.findIndex(p => p.id === id);
        if (index !== -1 && archivedProject) {
          this.projects[index] = archivedProject;
        }
        return archivedProject;
      } catch (error: any) {
        this.error = this.getApiErrorMessage(error, 'Failed to archive project');
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async addMember(projectId: number, data: {
      user_id: number;
      role_id: number;
      assignment_type?: 'member' | 'owner' | 'collaborator' | 'observer';
      can_view?: boolean;
      can_edit?: boolean;
      can_delete?: boolean;
      can_approve?: boolean;
      can_manage_members?: boolean;
    }) {
      this.loading = true;
      this.error = null;
      
      try {
        const response = await requestWithFallback(
          (basePath) => axiosInstance.post(`${basePath}/${projectId}/members`, data),
          PROJECT_ENDPOINTS
        );
        return response.data.member;
      } catch (error: any) {
        this.error = this.getApiErrorMessage(error, 'Failed to add member');
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async removeMember(projectId: number, memberId: number) {
      this.loading = true;
      this.error = null;
      
      try {
        await requestWithFallback(
          (basePath) => axiosInstance.delete(`${basePath}/${projectId}/members/${memberId}`),
          PROJECT_ENDPOINTS
        );
      } catch (error: any) {
        this.error = this.getApiErrorMessage(error, 'Failed to remove member');
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async fetchTimeline(projectId: number): Promise<{
      stage_history: ProjectStageHistory[];
      status_history: ProjectStatusHistory[];
    }> {
      this.loading = true;
      this.error = null;
      
      try {
        const response = await requestWithFallback(
          (basePath) => axiosInstance.get(`${basePath}/${projectId}/timeline`),
          PROJECT_ENDPOINTS
        );
        return response.data;
      } catch (error: any) {
        this.error = this.getApiErrorMessage(error, 'Failed to fetch timeline');
        throw error;
      } finally {
        this.loading = false;
      }
    },

    // Lookup data fetchers - Updated to use /api/lookup/ prefix
    async fetchProjectTypes() {
      try {
        const response = await requestWithFallback(
          (basePath) => axiosInstance.get(`${basePath}/project-types`),
          LOOKUP_ENDPOINTS
        );
        this.projectTypes = parseLookupItems(response.data);
      } catch (error: any) {
        console.error('Failed to fetch project types:', error);
      }
    },

    async fetchIndustries() {
      try {
        const response = await requestWithFallback(
          (basePath) => axiosInstance.get(`${basePath}/industries`),
          LOOKUP_ENDPOINTS
        );
        this.industries = parseLookupItems(response.data);
      } catch (error: any) {
        console.error('Failed to fetch industries:', error);
      }
    },

    async fetchSectors() {
      try {
        const response = await requestWithFallback(
          (basePath) => axiosInstance.get(`${basePath}/sectors`),
          LOOKUP_ENDPOINTS
        );
        this.sectors = parseLookupItems(response.data);
      } catch (error: any) {
        console.error('Failed to fetch sectors:', error);
      }
    },

    async fetchStages() {
      try {
        const response = await requestWithFallback(
          (basePath) => axiosInstance.get(`${basePath}/project-stages`),
          LOOKUP_ENDPOINTS
        );
        this.stages = parseLookupItems(response.data);
      } catch (error: any) {
        console.error('Failed to fetch stages:', error);
      }
    },

    async fetchStatuses() {
      try {
        const response = await requestWithFallback(
          (basePath) => axiosInstance.get(`${basePath}/project-statuses`),
          LOOKUP_ENDPOINTS
        );
        this.statuses = parseLookupItems(response.data);
      } catch (error: any) {
        console.error('Failed to fetch statuses:', error);
      }
    },

    async fetchInvestmentTypes() {
      try {
        const response = await requestWithFallback(
          (basePath) => axiosInstance.get(`${basePath}/investment-types`),
          LOOKUP_ENDPOINTS
        );
        this.investmentTypes = parseLookupItems(response.data);
      } catch (error: any) {
        console.error('Failed to fetch investment types:', error);
      }
    },

    async fetchFundingSources() {
      try {
        const response = await requestWithFallback(
          (basePath) => axiosInstance.get(`${basePath}/funding-sources`),
          LOOKUP_ENDPOINTS
        );
        this.fundingSources = parseLookupItems(response.data);
      } catch (error: any) {
        console.error('Failed to fetch funding sources:', error);
      }
    },

    // Utility methods
    setFilters(filters: Partial<ProjectFilters>) {
      this.filters = { ...this.filters, ...filters };
    },

    resetFilters() {
      this.filters = {
        search: '',
        sort_by: 'created_at',
        sort_order: 'desc',
        per_page: 15,
        page: 1,
        is_archived: false
      };
    },

    clearError() {
      this.error = null;
    },

    // Load all lookup data at once
    async loadAllLookupData() {
      await Promise.all([
        this.fetchProjectTypes(),
        this.fetchIndustries(),
        this.fetchSectors(),
        this.fetchStages(),
        this.fetchStatuses(),
        this.fetchInvestmentTypes(),
        this.fetchFundingSources(),
      ]);
    }
  }
});
