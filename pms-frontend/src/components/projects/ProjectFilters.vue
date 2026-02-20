<template>
  <div class="space-y-4">
    <!-- Search Bar -->
    <div class="flex gap-2">
      <div class="relative flex-1">
        <input
          v-model="localFilters.search"
          type="text"
          placeholder="Search by title, code, or description..."
          class="w-full px-4 py-2 pl-10 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-primary-500 dark:bg-gray-800 dark:text-white"
          @keyup.enter="emitFilter"
        />
        <i class="ri-search-line absolute left-3 top-3 text-gray-400"></i>
      </div>
      <Button variant="primary" @click="emitFilter">
        <i class="ri-search-line mr-1"></i> Search
      </Button>
      <Button variant="plain" @click="toggleAdvanced">
        <i :class="showAdvanced ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line'"></i>
        {{ showAdvanced ? 'Hide' : 'Show' }} Filters
        <span v-if="activeFilterCount > 0" class="ml-2 px-2 py-0.5 bg-primary-600 text-white text-xs rounded-full">
          {{ activeFilterCount }}
        </span>
      </Button>
      <Button variant="plain" @click="emitReset" v-if="activeFilterCount > 0">
        <i class="ri-close-line mr-1"></i> Clear
      </Button>
    </div>

    <!-- Active Filters Tags -->
    <div v-if="activeFilterTags.length > 0" class="flex flex-wrap gap-2">
      <span
        v-for="tag in activeFilterTags"
        :key="tag.key"
        class="inline-flex items-center gap-1 px-3 py-1 bg-primary-100 dark:bg-primary-900 text-primary-800 dark:text-primary-200 rounded-full text-sm"
      >
        {{ tag.label }}: {{ tag.value }}
        <button @click="removeFilter(tag.key)" class="hover:text-primary-600">
          <i class="ri-close-line"></i>
        </button>
      </span>
    </div>

    <!-- Advanced Filters -->
    <Collapse :show="showAdvanced">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4">
        <!-- Project Type -->
        <div>
          <Label>Project Type</Label>
          <Select v-model="localFilters.project_type_id">
            <option :value="undefined">All Types</option>
            <option v-for="type in projectTypes" :key="type.id" :value="type.id">
              {{ type.name }}
            </option>
          </Select>
        </div>

        <!-- Industry -->
        <div>
          <Label>Industry</Label>
          <Select v-model="localFilters.industry_id">
            <option :value="undefined">All Industries</option>
            <option v-for="industry in industries" :key="industry.id" :value="industry.id">
              {{ industry.name }}
            </option>
          </Select>
        </div>

        <!-- Sector -->
        <div>
          <Label>Sector</Label>
          <Select v-model="localFilters.sector_id">
            <option :value="undefined">All Sectors</option>
            <option v-for="sector in sectors" :key="sector.id" :value="sector.id">
              {{ sector.name }}
            </option>
          </Select>
        </div>

        <!-- Stage -->
        <div>
          <Label>Stage</Label>
          <Select v-model="localFilters.stage_id">
            <option :value="undefined">All Stages</option>
            <option v-for="stage in stages" :key="stage.id" :value="stage.id">
              {{ stage.name }}
            </option>
          </Select>
        </div>

        <!-- Status -->
        <div>
          <Label>Status</Label>
          <Select v-model="localFilters.status_id">
            <option :value="undefined">All Statuses</option>
            <option v-for="status in statuses" :key="status.id" :value="status.id">
              {{ status.name }}
            </option>
          </Select>
        </div>

        <!-- SVF Filter -->
        <div>
          <Label>SVF Projects</Label>
          <Select v-model="localFilters.is_svf">
            <option :value="undefined">All Projects</option>
            <option :value="true">SVF Only</option>
            <option :value="false">Non-SVF</option>
          </Select>
        </div>

        <!-- Archive Status -->
        <div>
          <Label>Archive Status</Label>
          <Select v-model="localFilters.is_archived">
            <option :value="false">Active Only</option>
            <option :value="true">Archived Only</option>
            <option :value="undefined">All</option>
          </Select>
        </div>

        <!-- Sort By -->
        <div>
          <Label>Sort By</Label>
          <Select v-model="localFilters.sort_by">
            <option value="created_at">Created Date</option>
            <option value="updated_at">Updated Date</option>
            <option value="title">Title</option>
            <option value="project_code">Project Code</option>
            <option value="start_date">Start Date</option>
            <option value="estimated_cost">Estimated Cost</option>
          </Select>
        </div>

        <!-- Sort Order -->
        <div>
          <Label>Sort Order</Label>
          <Select v-model="localFilters.sort_order">
            <option value="desc">Descending</option>
            <option value="asc">Ascending</option>
          </Select>
        </div>
      </div>

      <div class="flex justify-end gap-2 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
        <Button variant="plain" @click="emitReset">
          <i class="ri-refresh-line mr-1"></i> Reset Filters
        </Button>
        <Button variant="primary" @click="emitFilter">
          <i class="ri-filter-line mr-1"></i> Apply Filters
        </Button>
      </div>
    </Collapse>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useProjectStore } from '@/store/projects';
import type { ProjectFilters as ProjectFiltersType } from '@/types/project';
import Button from '@/app/components/Button.vue';
import Select from '@/app/components/formFields/Select.vue';
import Label from '@/app/components/Label.vue';
import Collapse from '@/app/components/Collapse.vue';

const props = defineProps<{
  modelValue: ProjectFiltersType;
}>();

const emit = defineEmits<{
  filter: [];
  reset: [];
}>();

const projectStore = useProjectStore();
const { projectTypes, industries, sectors, stages, statuses } = storeToRefs(projectStore);

const showAdvanced = ref(false);
const localFilters = ref<ProjectFiltersType>({ ...props.modelValue });

watch(() => props.modelValue, (newVal) => {
  localFilters.value = { ...newVal };
}, { deep: true });

const toggleAdvanced = () => {
  showAdvanced.value = !showAdvanced.value;
};

const activeFilterCount = computed(() => {
  let count = 0;
  if (localFilters.value.search) count++;
  if (localFilters.value.project_type_id) count++;
  if (localFilters.value.industry_id) count++;
  if (localFilters.value.sector_id) count++;
  if (localFilters.value.stage_id) count++;
  if (localFilters.value.status_id) count++;
  if (localFilters.value.is_svf !== undefined) count++;
  if (localFilters.value.is_archived !== false) count++;
  return count;
});

const activeFilterTags = computed(() => {
  const tags = [];
  
  if (localFilters.value.search) {
    tags.push({ key: 'search', label: 'Search', value: localFilters.value.search });
  }
  
  if (localFilters.value.project_type_id) {
    const type = projectTypes.value.find(t => t.id === localFilters.value.project_type_id);
    if (type) tags.push({ key: 'project_type_id', label: 'Type', value: type.name });
  }
  
  if (localFilters.value.industry_id) {
    const industry = industries.value.find(i => i.id === localFilters.value.industry_id);
    if (industry) tags.push({ key: 'industry_id', label: 'Industry', value: industry.name });
  }
  
  if (localFilters.value.sector_id) {
    const sector = sectors.value.find(s => s.id === localFilters.value.sector_id);
    if (sector) tags.push({ key: 'sector_id', label: 'Sector', value: sector.name });
  }
  
  if (localFilters.value.stage_id) {
    const stage = stages.value.find(s => s.id === localFilters.value.stage_id);
    if (stage) tags.push({ key: 'stage_id', label: 'Stage', value: stage.name });
  }
  
  if (localFilters.value.status_id) {
    const status = statuses.value.find(s => s.id === localFilters.value.status_id);
    if (status) tags.push({ key: 'status_id', label: 'Status', value: status.name });
  }
  
  if (localFilters.value.is_svf === true) {
    tags.push({ key: 'is_svf', label: 'SVF', value: 'Yes' });
  }
  
  if (localFilters.value.is_archived === true) {
    tags.push({ key: 'is_archived', label: 'Archived', value: 'Yes' });
  }
  
  return tags;
});

const removeFilter = (key: string) => {
  (localFilters.value as any)[key] = undefined;
  if (key === 'is_archived') {
    localFilters.value.is_archived = false;
  }
  emitFilter();
};

const emitFilter = () => {
  emit('filter');
};

const emitReset = () => {
  emit('reset');
};
</script>