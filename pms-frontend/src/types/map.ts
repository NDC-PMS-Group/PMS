import type { ProjectType, ProjectStage } from '@/types/project'
import { resolveImageUrl } from '@/utils/resolveImage';

export interface MapProjectStatus {
  id: number;
  name: string;
  color_code: string;
}

export interface MapProjectLocation {
  address: string | null;
  latitude: number | null;
  longitude: number | null;
}

export interface MapProject {
  id: number;
  project_code: string;
  title: string;
  thumbnail_url: string | null;
  logo_url: string | null;
  location: MapProjectLocation;
  status: MapProjectStatus | null;
  current_stage: Pick<ProjectStage, 'id' | 'name'> | null;
  project_type: Pick<ProjectType, 'id' | 'name'> | null;
  estimated_cost: number | null;
  currency: string;
  proponent: {
    name: string | null;
    contact: string | null;
    email: string | null;
  };
  start_date: string | null;
  target_completion_date: string | null;
  is_overdue: boolean;
  progress_percentage: number;
}

export interface MapFilters {
  status_id?: number | null;
  project_type_id?: number | null;
  stage_id?: number | null;
  bounds?: string | null;
}

export interface MapState {
  mapProjects: MapProject[];
  selectedProject: MapProject | null;
  filters: MapFilters;
  loading: boolean;
  error: string | null;
}

export const parseMapProject = (raw: any): MapProject => {
  return {
    id: raw.id,
    project_code: raw.project_code,
    title: raw.title,
    thumbnail_url: resolveImageUrl(raw.thumbnail_url) ?? null,
    logo_url:      resolveImageUrl(raw.logo_url) ?? null,
    location: {
      address: raw.location?.address ?? null,
      latitude: raw.location?.latitude ?? null,
      longitude: raw.location?.longitude ?? null,
    },
    status: raw.status
      ? {
          id: raw.status.id,
          name: raw.status.name,
          color_code: raw.status.color_code ?? '#6B7280',
        }
      : null,
    current_stage: raw.current_stage
      ? { id: raw.current_stage.id, name: raw.current_stage.name }
      : null,
    project_type: raw.project_type
      ? { id: raw.project_type.id, name: raw.project_type.name }
      : null,
    estimated_cost: raw.estimated_cost ?? null,
    currency: raw.currency ?? 'PHP',
    proponent: {
      name: raw.proponent?.name ?? null,
      contact: raw.proponent?.contact ?? null,
      email: raw.proponent?.email ?? null,
    },
    start_date: raw.start_date ?? null,
    target_completion_date: raw.target_completion_date ?? null,
    is_overdue: raw.is_overdue ?? false,
    progress_percentage: raw.progress_percentage ?? 0,
  };
};

export const parseMapProjectList = (responseData: any): MapProject[] => {
  const payload = responseData?.data ?? responseData;
  const list: any[] = Array.isArray(payload?.data)
    ? payload.data
    : Array.isArray(payload)
    ? payload
    : [];

  return list.map(parseMapProject);
};