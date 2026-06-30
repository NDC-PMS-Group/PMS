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
          v-if="activeImageUrl"
          :src="activeImageUrl"
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
          <span class="text-xs text-gray-400 dark:text-gray-500 font-medium">No project photo</span>
        </div>

        <!-- Gradient overlay -->
        <div class="absolute inset-0 bg-gradient-to-t from-black/65 via-black/10 to-transparent" />

        <template v-if="projectImages.length > 1">
          <button
            class="absolute left-3 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full
                   bg-black/40 hover:bg-black/60 flex items-center justify-center
                   text-white transition-colors"
            aria-label="Previous project photo"
            @click="previousImage"
          >
            <ChevronLeft :size="16" />
          </button>
          <button
            class="absolute right-3 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full
                   bg-black/40 hover:bg-black/60 flex items-center justify-center
                   text-white transition-colors"
            aria-label="Next project photo"
            @click="nextImage"
          >
            <ChevronRight :size="16" />
          </button>
          <div class="absolute top-3 left-3 flex items-center gap-1.5 rounded-full bg-black/45 px-2.5 py-1 text-[11px] font-semibold text-white">
            {{ activeImageIndex + 1 }} / {{ projectImages.length }}
          </div>
          <div class="absolute bottom-3 right-3 flex items-center gap-1.5">
            <button
              v-for="(image, index) in projectImages"
              :key="image.id || index"
              class="h-1.5 rounded-full transition-all"
              :class="index === activeImageIndex ? 'w-5 bg-white' : 'w-1.5 bg-white/50 hover:bg-white/80'"
              :aria-label="`Show project photo ${index + 1}`"
              @click="activeImageIndex = index"
            />
          </div>
        </template>

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

        <!-- Logo + status -->
        <div class="absolute bottom-3 left-3 flex items-end gap-2">
          <div class="relative">
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
          <InfoRow icon="User"         label="Project Officer"    :value="project.project_officer?.name" />
          <InfoRow icon="Layers"       label="Stage"              :value="project.current_stage?.name" />
          <InfoRow icon="Layers"       label="SOI Track"          :value="formatTrack(project.process_track)" />
          <InfoRow icon="CalendarCheck" label="Next Due Task"      :value="nextDueTaskLabel" />
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
      <div class="px-5 py-4 border-t border-gray-100 dark:border-gray-800 flex-shrink-0 space-y-2">
        <button
          @click="emit('view-project', project.id)"
          class="flex items-center justify-center gap-2 w-full
                 px-4 py-2.5 rounded-xl
                 bg-blue-600 hover:bg-blue-700
                 text-white text-sm font-semibold
                 transition-colors duration-150 cursor-pointer"
        >
          View Full Project
          <ArrowRight :size="15" />
        </button>
        <div class="grid grid-cols-2 gap-2">
          <button
            @click="emit('open-tasks', project.id)"
            class="flex items-center justify-center rounded-xl border border-gray-200 px-3 py-2 text-xs font-bold text-gray-700 transition hover:border-blue-400 hover:text-blue-600 dark:border-gray-700 dark:text-gray-200 cursor-pointer"
          >
            Open Tasks
          </button>
          <button
            @click="emit('open-soi-flow', project.id)"
            class="flex items-center justify-center rounded-xl border border-gray-200 px-3 py-2 text-xs font-bold text-gray-700 transition hover:border-blue-400 hover:text-blue-600 dark:border-gray-700 dark:text-gray-200 cursor-pointer"
          >
            Open SOI Flow
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import {
  X, Folder, AlertTriangle, ArrowRight,
  Image as ImageIcon, ChevronLeft, ChevronRight,
} from 'lucide-vue-next'
import type { MapProject } from '@/types/map'
import InfoRow from './InfoRow.vue'

const props = defineProps<{
  project:               MapProject | null
}>()

const emit = defineEmits<{
  close: []
  'view-project': [id: number]
  'open-tasks': [id: number]
  'open-soi-flow': [id: number]
}>()

// ── Display helpers ───────────────────────────────────────────────────────────

const activeImageIndex = ref(0)

const projectImages = computed(() => {
  const gallery = props.project?.images?.filter((image) => image.url) || []
  if (gallery.length) return gallery
  return props.project?.thumbnail_url
    ? [{ id: 0, url: props.project.thumbnail_url, title: props.project.title, file_name: props.project.title, is_thumbnail: true }]
    : []
})

const activeImageUrl = computed(() => projectImages.value[activeImageIndex.value]?.url || null)

watch(
  () => props.project?.id,
  () => {
    activeImageIndex.value = 0
  }
)

const previousImage = () => {
  if (!projectImages.value.length) return
  activeImageIndex.value = (activeImageIndex.value - 1 + projectImages.value.length) % projectImages.value.length
}

const nextImage = () => {
  if (!projectImages.value.length) return
  activeImageIndex.value = (activeImageIndex.value + 1) % projectImages.value.length
}

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

const nextDueTaskLabel = computed(() => {
  const task = props.project?.next_due_task
  if (!task) return null
  const due = task.due_date ? ` due ${formatDate(task.due_date)}` : ''
  return `${task.title}${due}`
})

function formatTrack(track?: string | null) {
  if (!track) return null
  return track.split('_').map((part) => part.charAt(0).toUpperCase() + part.slice(1)).join(' ')
}

const formatDate = (date: string | null | undefined) => {
  if (!date) return null
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric', month: 'short', day: 'numeric',
  })
}
</script>

<style scoped>
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
