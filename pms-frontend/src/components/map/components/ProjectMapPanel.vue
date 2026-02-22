<template>
  <!-- Backdrop (mobile only) -->
  <Transition name="fade">
    <div
      v-if="project"
      class="fixed inset-0 bg-black/30 z-[400] md:hidden"
      @click="emit('close')"
    />
  </Transition>

  <!-- Side Panel -->
  <Transition name="slide">
    <div
      v-if="project"
      class="fixed top-0 right-0 h-full w-full max-w-sm z-[500]
             flex flex-col
             bg-white dark:bg-gray-900
             border-l border-gray-200 dark:border-gray-700
             shadow-2xl"
    >
      <!-- ── Thumbnail banner ─────────────────────────────────────────────── -->
      <div class="relative h-44 flex-shrink-0 overflow-hidden bg-gray-100 dark:bg-gray-800">

        <!-- Image or placeholder -->
        <img
          v-if="project.thumbnail_url"
          :src="project.thumbnail_url"
          :alt="project.title"
          class="w-full h-full object-cover"
        />
        <div
          v-else
          class="w-full h-full flex flex-col items-center justify-center gap-2
                 bg-gradient-to-br from-gray-100 to-gray-200
                 dark:from-gray-800 dark:to-gray-900"
        >
          <ImageIcon :size="36" class="text-gray-300 dark:text-gray-600" />
          <span class="text-xs text-gray-400 dark:text-gray-500 font-medium">No thumbnail</span>
        </div>

        <!-- Gradient overlay -->
        <div class="absolute inset-0 bg-gradient-to-t from-black/65 via-black/10 to-transparent" />

        <!-- Upload thumbnail button -->
        <button
          class="upload-btn absolute top-3 left-3"
          :class="{ 'opacity-60 cursor-wait': uploadingType === 'thumbnail' }"
          :disabled="uploadingType === 'thumbnail'"
          title="Upload thumbnail"
          @click="onUploadThumbnail"
        >
          <template v-if="uploadingType === 'thumbnail'">
            <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
            Uploading...
          </template>
          <template v-else>
            <Upload :size="11" />
            {{ project.thumbnail_url ? 'Replace' : 'Add' }} Thumbnail
          </template>
        </button>

        <!-- Close button -->
        <button
          class="absolute top-3 right-3 w-8 h-8 rounded-full
                 bg-black/40 hover:bg-black/60
                 flex items-center justify-center
                 text-white transition-colors"
          @click="emit('close')"
        >
          <X :size="16" />
        </button>

        <!-- Logo + upload -->
        <div class="absolute bottom-3 left-3 flex items-end gap-2">
          <div class="relative group">
            <!-- Logo display -->
            <div
              class="w-13 h-13 rounded-xl overflow-hidden
                     border-2 border-white/80
                     bg-white dark:bg-gray-800
                     flex items-center justify-center
                     shadow-lg"
              style="width: 52px; height: 52px;"
            >
              <img
                v-if="project.logo_url"
                :src="project.logo_url"
                :alt="project.title + ' logo'"
                class="w-full h-full object-contain p-1"
              />
              <Folder :size="20" v-else class="text-gray-400" />
            </div>

            <!-- Logo upload overlay on hover -->
            <button
              class="absolute inset-0 rounded-xl flex items-center justify-center
                     bg-black/55 opacity-0 group-hover:opacity-100
                     transition-opacity duration-150"
              :class="{ 'opacity-100 cursor-wait': uploadingType === 'logo' }"
              :disabled="uploadingType === 'logo'"
              title="Upload logo"
              @click="onUploadLogo"
            >
              <template v-if="uploadingType === 'logo'">
                <svg class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
              </template>
              <Upload v-else :size="14" class="text-white" />
            </button>
          </div>

          <!-- Status badge -->
          <span
            v-if="project.status"
            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full
                   text-xs font-bold text-white shadow mb-0.5"
            :style="{ backgroundColor: project.status.color_code }"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-white/70 animate-pulse" />
            {{ project.status.name }}
          </span>
        </div>
      </div>

      <!-- ── Upload error ──────────────────────────────────────────────────── -->
      <Transition name="fade">
        <div
          v-if="uploadError"
          class="mx-4 mt-3 flex items-center gap-2 px-3 py-2 rounded-lg
                 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800"
        >
          <AlertTriangle :size="13" class="text-red-500 flex-shrink-0" />
          <span class="text-xs text-red-600 dark:text-red-400 flex-1">{{ uploadError }}</span>
          <button @click="uploadError = null">
            <X :size="12" class="text-red-400 hover:text-red-600" />
          </button>
        </div>
      </Transition>

      <!-- ── Scrollable content ─────────────────────────────────────────────── -->
      <div class="flex-1 overflow-y-auto">

        <!-- Title block -->
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
          <p class="text-xs font-semibold text-blue-500 dark:text-blue-400 mb-1 tracking-wide uppercase">
            {{ project.project_code }}
          </p>
          <h3 class="text-base font-bold text-gray-900 dark:text-white leading-snug">
            {{ project.title }}
          </h3>
          <p v-if="project.project_type" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            {{ project.project_type.name }}
          </p>
        </div>

        <!-- Progress bar -->
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
          <div class="flex items-center justify-between mb-1.5">
            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
              Progress
            </span>
            <span class="text-xs font-bold" :class="progressColor">
              {{ project.progress_percentage }}%
            </span>
          </div>
          <div class="h-1.5 w-full bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
            <div
              class="h-full rounded-full transition-all duration-500"
              :style="{
                width: project.progress_percentage + '%',
                backgroundColor: project.status?.color_code ?? '#3B82F6',
              }"
            />
          </div>
        </div>

        <!-- Info rows -->
        <div class="px-5 py-4 space-y-3.5">
          <InfoRow icon="MapPin"       label="Location"           :value="project.location.address" />
          <InfoRow icon="User"         label="Proponent"          :value="project.proponent.name" />
          <InfoRow icon="Layers"       label="Stage"              :value="project.current_stage?.name" />
          <InfoRow icon="DollarSign"   label="Estimated Cost"     :value="formattedCost" />
          <InfoRow icon="Calendar"     label="Start Date"         :value="formatDate(project.start_date)" />
          <InfoRow icon="CalendarCheck" label="Target Completion" :value="formatDate(project.target_completion_date)" />

          <!-- Overdue badge -->
          <div
            v-if="project.is_overdue"
            class="flex items-center gap-2 px-3 py-2 rounded-lg
                   bg-red-50 dark:bg-red-900/20
                   border border-red-200 dark:border-red-800"
          >
            <AlertTriangle :size="14" class="text-red-500 flex-shrink-0" />
            <span class="text-xs font-semibold text-red-600 dark:text-red-400">
              This project is overdue
            </span>
          </div>
        </div>
      </div>

      <!-- ── Footer CTA ─────────────────────────────────────────────────────── -->
      <div class="px-5 py-4 border-t border-gray-100 dark:border-gray-800 flex-shrink-0">
        <RouterLink
          :to="`/projects/${project.id}`"
          class="flex items-center justify-center gap-2 w-full
                 px-4 py-2.5 rounded-xl
                 bg-blue-600 hover:bg-blue-700
                 text-white text-sm font-semibold
                 transition-colors duration-150"
        >
          View Full Project
          <ArrowRight :size="15" />
        </RouterLink>
      </div>
    </div>
  </Transition>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import {
  X, Folder, AlertTriangle, ArrowRight,
  Upload, Image as ImageIcon,
} from 'lucide-vue-next'
import type { MapProject } from '@/types/map'
import { useMapUpload } from '@/composables/useMapUpload'
import InfoRow from './InfoRow.vue'

const props = defineProps<{
  project:               MapProject | null
  refreshMarkerTooltip:  (project: MapProject) => void
}>()

const emit = defineEmits<{
  close: []
}>()

// ── Upload ────────────────────────────────────────────────────────────────────

const { uploadingType, uploadError, upload, triggerFilePicker } =
  useMapUpload(props.refreshMarkerTooltip)

// Expose uploadError as a local ref so the dismiss button can clear it
// (useMapUpload's ref is already reactive — we just alias it for the template)
const onUploadThumbnail = () => {
  if (!props.project) return
  triggerFilePicker('thumbnail', async (file) => {
    await upload(props.project!.id, file, 'thumbnail').catch(() => {})
  })
}

const onUploadLogo = () => {
  if (!props.project) return
  triggerFilePicker('logo', async (file) => {
    await upload(props.project!.id, file, 'logo').catch(() => {})
  })
}

// ── Display helpers ───────────────────────────────────────────────────────────

const progressColor = computed(() => {
  const p = props.project?.progress_percentage ?? 0
  if (p >= 75) return 'text-green-600 dark:text-green-400'
  if (p >= 40) return 'text-yellow-600 dark:text-yellow-400'
  return 'text-red-500 dark:text-red-400'
})

const formattedCost = computed(() => {
  if (!props.project?.estimated_cost) return null
  return new Intl.NumberFormat('en-PH', {
    style:                 'currency',
    currency:              props.project.currency ?? 'PHP',
    notation:              'compact',
    maximumFractionDigits: 2,
  }).format(props.project.estimated_cost)
})

const formatDate = (date: string | null | undefined) => {
  if (!date) return null
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric', month: 'short', day: 'numeric',
  })
}
</script>

<style scoped>
.upload-btn {
  @apply inline-flex items-center gap-1.5
         px-2.5 py-1.5 rounded-lg
         bg-black/40 hover:bg-black/60
         text-white text-[11px] font-semibold
         border border-white/20
         transition-all duration-150
         backdrop-blur-sm;
}

.slide-enter-active,
.slide-leave-active {
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.slide-enter-from,
.slide-leave-to {
  transform: translateX(100%);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>