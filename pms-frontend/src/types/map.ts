import type { ProjectType, ProjectStage } from '@/types/project'
import type { TaskItem, TaskUserRef } from '@/types/task'
import type { LocationFilter } from '@/types/psgc'
import { resolveImageUrl } from '@/utils/resolveImage';

export interface MapProjectStatus {
  id: number;
  name: string;
  color_code: string;
}

export interface MapProjectLocation {
  address: string | null;
  region_code: string | null;
  region_name: string | null;
  province_code: string | null;
  province_name: string | null;
  city_code: string | null;
  city_name: string | null;
  barangay_code: string | null;
  barangay_name: string | null;
  latitude: number | null;
  longitude: number | null;
}

export interface MapProject {
  id: number;
  project_code: string;
  title: string;
  thumbnail_url: string | null;
  logo_url: string | null;
  images: MapProjectImage[];
  location: MapProjectLocation;
  status: MapProjectStatus | null;
  current_stage: Pick<ProjectStage, 'id' | 'name'> | null;
  project_type: Pick<ProjectType, 'id' | 'name'> | null;
  process_track: string | null;
  project_officer: TaskUserRef | null;
  next_due_task: TaskItem | null;
  tasks: TaskItem[];
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

export interface MapProjectImage {
  id: number;
  title: string | null;
  file_name: string | null;
  url: string | null;
  is_thumbnail: boolean;
}

export interface MapFilters {
  status_id?: number | null;
  project_type_id?: number | null;
  stage_id?: number | null;
  search?: string | null;
  bounds?: string | null;
  region_code?: string | null;
  province_code?: string | null;
  city_code?: string | null;
  barangay_code?: string | null;
}

export interface MapState {
  mapProjects: MapProject[];
  selectedProject: MapProject | null;
  filters: MapFilters;
  location: LocationFilter;
  filtersVisible: boolean;
  mapZoom: number;
  loading: boolean;
  error: string | null;
}

export const parseMapProject = (raw: any): MapProject => {
  const images: MapProjectImage[] = Array.isArray(raw.images)
    ? raw.images.map((image: any) => ({
        id: image.id,
        title: image.title ?? null,
        file_name: image.file_name ?? null,
        url: resolveImageUrl(image.url ?? image.file_path) ?? null,
        is_thumbnail: Boolean(image.is_thumbnail),
      })).filter((image: MapProjectImage) => Boolean(image.url))
    : [];
  const fallbackThumbnail = images.find((image) => image.is_thumbnail)?.url ?? images[0]?.url ?? null;

  return {
    id: raw.id,
    project_code: raw.project_code,
    title: raw.title,
    thumbnail_url: resolveImageUrl(raw.thumbnail_url) ?? fallbackThumbnail,
    logo_url:      resolveImageUrl(raw.logo_url) ?? null,
    images,
    location: {
      address: raw.location?.address ?? null,
      region_code: raw.location?.region_code ?? raw.location_region_code ?? null,
      region_name: raw.location?.region_name ?? raw.location_region_name ?? null,
      province_code: raw.location?.province_code ?? raw.location_province_code ?? null,
      province_name: raw.location?.province_name ?? raw.location_province_name ?? null,
      city_code: raw.location?.city_code ?? raw.location_city_code ?? null,
      city_name: raw.location?.city_name ?? raw.location_city_name ?? null,
      barangay_code: raw.location?.barangay_code ?? raw.location_barangay_code ?? null,
      barangay_name: raw.location?.barangay_name ?? raw.location_barangay_name ?? null,
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
    process_track: raw.process_track ?? null,
    project_officer: raw.project_officer
      ? {
          id: raw.project_officer.id,
          name: raw.project_officer.full_name ?? raw.project_officer.name,
          email: raw.project_officer.email,
        }
      : null,
    tasks: Array.isArray(raw.tasks) ? raw.tasks : [],
    next_due_task: Array.isArray(raw.tasks)
      ? [...raw.tasks]
          .filter((task: any) => task.due_date && task.status !== 'completed' && task.status !== 'cancelled')
          .sort((a: any, b: any) => new Date(a.due_date).getTime() - new Date(b.due_date).getTime())[0] ?? null
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
