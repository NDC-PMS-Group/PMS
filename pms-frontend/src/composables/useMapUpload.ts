import { ref } from 'vue'
import axiosInstance from '@/utils/axiosInstance'
import { useMapStore } from '@/store/map'
import { resolveImageUrl } from '@/utils/resolveImage';
import type { MapProject } from '@/types/map'

type MediaType = 'thumbnail' | 'logo'

export function useMapUpload(
  refreshMarkerTooltip: (project: MapProject) => void
) {
  const mapStore        = useMapStore()
  const uploadingType   = ref<MediaType | null>(null)
  const uploadError     = ref<string | null>(null)

  const upload = async (projectId: number, file: File, type: MediaType) => {
    uploadingType.value = type
    uploadError.value   = null

    const formData = new FormData()
    formData.append(type, file)

    try {
      const response = await axiosInstance.post(
        `/api/projects/${projectId}/${type}`,
        formData,
        {
            headers: {
            'Content-Type': undefined,   // ← let the browser set multipart + boundary
            },
        }
        )

      const urlKey   = type === 'thumbnail' ? 'thumbnail_url' : 'logo_url'
      const rawPath: string = response.data[urlKey]

      // Resolve relative path → full URL using same helper as avatar composable
      const freshUrl = resolveImageUrl(rawPath) ?? rawPath

      // 1. Update selectedProject in the store in-place
      if (mapStore.selectedProject?.id === projectId) {
        mapStore.selectedProject = {
          ...mapStore.selectedProject,
          [urlKey]: freshUrl,
        }
      }

      // 2. Update the project in mapProjects list in-place
      const idx = mapStore.mapProjects.findIndex((p) => p.id === projectId)
      if (idx !== -1) {
        mapStore.mapProjects[idx] = {
          ...mapStore.mapProjects[idx],
          [urlKey]: freshUrl,
        }
      }

      // 3. Rebuild just this marker's tooltip with the new image — no re-render
      const updatedProject = mapStore.mapProjects[idx] ?? mapStore.selectedProject
      if (updatedProject) {
        refreshMarkerTooltip(updatedProject)
      }

      return freshUrl
    } catch (error: any) {
      console.log('Full error response:', error?.response?.data)
      uploadError.value =
        error?.response?.data?.message ||
        error?.message ||
        `Failed to upload ${type}`
      throw error
    } finally {
      uploadingType.value = null
    }
  }

  const triggerFilePicker = (type: MediaType, onFilePicked: (file: File) => void) => {
    const input = document.createElement('input')
    input.type   = 'file'
    input.accept = 'image/jpeg,image/jpg,image/png,image/webp'
    input.onchange = (e) => {
      const file = (e.target as HTMLInputElement).files?.[0]
      if (file) onFilePicked(file)
    }
    input.click()
  }

  return {
    uploadingType,
    uploadError,
    upload,
    triggerFilePicker,
  }
}