<template>
  <form class="command-bar" aria-label="Dashboard filters" @submit.prevent="store.fetchDashboard">
    <div class="command-row">
      <fieldset v-if="options.scopes.length > 1" class="scope-control">
        <legend>Dashboard scope</legend>
        <button
          v-for="option in options.scopes"
          :key="option.value"
          type="button"
          :class="{ active: store.filters.scope === option.value }"
          :aria-pressed="store.filters.scope === option.value"
          :disabled="loading"
          @click="setScope(option.value)"
        >
          {{ option.label }}
        </button>
      </fieldset>

      <label class="compact-field">
        <span>Year</span>
        <select v-model="store.filters.year">
          <option :value="null">All years</option>
          <option v-for="year in options.available_years" :key="year" :value="year">{{ year }}</option>
        </select>
      </label>

      <label class="compact-field due-field">
        <span>Due window</span>
        <select v-model="store.filters.due_window">
          <option v-for="option in options.due_windows" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </label>

      <button v-if="!isProponent" class="filter-toggle" type="button" :aria-expanded="showMore" aria-controls="dashboard-more-filters" @click="showMore = !showMore">
        <SlidersHorizontal aria-hidden="true" />
        More filters
        <span v-if="advancedFilterCount">{{ advancedFilterCount }}</span>
      </button>

      <button class="apply-button" type="submit" :disabled="loading">
        <Loader2 v-if="loading" class="spin" aria-hidden="true" />
        <Check v-else aria-hidden="true" />
        Apply
      </button>
    </div>

    <div v-if="!isProponent" v-show="showMore" id="dashboard-more-filters" class="more-filters">
      <label>
        <span>Sector</span>
        <select v-model="store.filters.sector_id">
          <option :value="null">All sectors</option>
          <option v-for="sector in options.sectors" :key="sector.id" :value="sector.id">{{ sector.name }}</option>
        </select>
      </label>
      <label>
        <span>Stage</span>
        <select v-model="store.filters.stage_id">
          <option :value="null">All stages</option>
          <option v-for="stage in options.stages" :key="stage.id" :value="stage.id">{{ stage.name }}</option>
        </select>
      </label>
      <label>
        <span>Project category</span>
        <select v-model="store.filters.origin_track">
          <option :value="null">All categories</option>
          <option v-for="option in options.origin_tracks" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </label>
      <label>
        <span>Lifecycle</span>
        <select v-model="store.filters.lifecycle_phase">
          <option :value="null">All phases</option>
          <option v-for="option in options.lifecycle_phases" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </label>
      <label v-if="options.role.can_view_portfolio">
        <span>Project officer</span>
        <select v-model="store.filters.officer_id">
          <option :value="null">All officers</option>
          <option v-for="officer in options.officers" :key="officer.id" :value="officer.id">{{ officer.name }}</option>
        </select>
      </label>
      <label>
        <span>Record source</span>
        <select v-model="store.filters.record_source">
          <option v-for="option in options.record_sources" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </label>
    </div>

    <div v-if="activeChips.length" class="applied-filters" aria-label="Applied filters">
      <span>Filtered by</span>
      <button v-for="chip in activeChips" :key="chip.key" type="button" @click="clearFilter(chip.key)">
        {{ chip.label }}
        <X aria-hidden="true" />
      </button>
      <button class="reset-button" type="button" :disabled="loading" @click="store.resetFilters">Reset all</button>
    </div>
  </form>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { Check, Loader2, SlidersHorizontal, X } from 'lucide-vue-next';
import { useDashboardStore } from '@/store/dashboard';
import type { DashboardFilterPayload, DashboardFilters, DashboardScope } from '@/types/dashboard';

defineProps<{ options: DashboardFilterPayload; loading: boolean }>();

const store = useDashboardStore();
const showMore = ref(false);
const isProponent = computed(() => store.stats?.filters.role.name.toLowerCase().includes('proponent') ?? false);

type FilterKey = keyof DashboardFilters;

const advancedKeys: FilterKey[] = ['sector_id', 'stage_id', 'origin_track', 'lifecycle_phase', 'officer_id', 'record_source'];
const advancedFilterCount = computed(() => advancedKeys.filter((key) => {
  const value = store.filters[key];
  return value !== null && value !== '' && value !== 'all';
}).length);

const activeChips = computed(() => {
  const chips: Array<{ key: FilterKey; label: string }> = [];
  const stats = store.stats;
  if (!stats) return chips;
  if (store.filters.year) chips.push({ key: 'year', label: String(store.filters.year) });
  if (store.filters.due_window !== '14') chips.push({ key: 'due_window', label: stats.filters.due_windows.find((item) => item.value === store.filters.due_window)?.label ?? 'Due window' });
  if (store.filters.sector_id) chips.push({ key: 'sector_id', label: stats.filters.sectors.find((item) => item.id === store.filters.sector_id)?.name ?? 'Sector' });
  if (store.filters.stage_id) chips.push({ key: 'stage_id', label: stats.filters.stages.find((item) => item.id === store.filters.stage_id)?.name ?? 'Stage' });
  if (store.filters.origin_track) chips.push({ key: 'origin_track', label: stats.filters.origin_tracks.find((item) => item.value === store.filters.origin_track)?.label ?? 'Category' });
  if (store.filters.lifecycle_phase) chips.push({ key: 'lifecycle_phase', label: stats.filters.lifecycle_phases.find((item) => item.value === store.filters.lifecycle_phase)?.label ?? 'Lifecycle' });
  if (store.filters.officer_id) chips.push({ key: 'officer_id', label: stats.filters.officers.find((item) => item.id === store.filters.officer_id)?.name ?? 'Officer' });
  if (store.filters.record_source !== 'all') chips.push({ key: 'record_source', label: stats.filters.record_sources.find((item) => item.value === store.filters.record_source)?.label ?? 'Source' });
  return chips;
});

const setScope = async (scope: DashboardScope) => {
  if (store.filters.scope === scope) return;
  store.filters.scope = scope;
  await store.fetchDashboard();
};

const clearFilter = async (key: FilterKey) => {
  const defaults: DashboardFilters = {
    year: null,
    due_window: '14',
    scope: store.stats?.filters.role.default_scope ?? 'portfolio',
    sector_id: null,
    stage_id: null,
    origin_track: null,
    lifecycle_phase: null,
    officer_id: null,
    record_source: 'all',
  };
  (store.filters as any)[key] = defaults[key];
  await store.fetchDashboard();
};
</script>

<style scoped>
.command-bar{border-block:1px solid var(--dash-border);background:var(--dash-card);padding:.65rem .8rem}.command-row{display:flex;align-items:flex-end;gap:.55rem;min-width:0}.scope-control{display:flex;flex:none;margin:0;padding:.18rem;border:1px solid var(--dash-border);border-radius:.38rem;background:var(--dash-soft)}.scope-control legend{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}.scope-control button{height:2rem;border:0;border-radius:.28rem;background:transparent;color:var(--dash-muted);padding:0 .7rem;font:inherit;font-size:.72rem;font-weight:800;cursor:pointer}.scope-control button.active{background:var(--dash-card);color:var(--dash-accent);box-shadow:0 1px 2px rgb(15 23 42 / .12)}.compact-field{display:grid;gap:.18rem;flex:0 1 8.5rem;min-width:7rem}.due-field{flex-basis:10.5rem}.compact-field span,.more-filters label>span{color:var(--dash-muted);font-size:.63rem;font-weight:800}.compact-field select,.more-filters select{width:100%;height:2.35rem;border:1px solid var(--dash-border);border-radius:.35rem;background:var(--dash-input);color:var(--dash-text);padding:0 .55rem;font:inherit;font-size:.75rem}.filter-toggle,.apply-button{display:inline-flex;height:2.35rem;flex:none;align-items:center;justify-content:center;gap:.35rem;border-radius:.35rem;padding:0 .72rem;font:inherit;font-size:.72rem;font-weight:800;cursor:pointer}.filter-toggle{margin-left:auto;border:1px solid var(--dash-border);background:var(--dash-card);color:var(--dash-text)}.filter-toggle>span{display:grid;place-items:center;min-width:1.15rem;height:1.15rem;border-radius:50%;background:var(--dash-accent-soft);color:var(--dash-accent);font-size:.6rem}.apply-button{border:1px solid var(--dash-accent-strong);background:var(--dash-accent-strong);color:#fff}.filter-toggle svg,.apply-button svg{width:.9rem}.more-filters{display:grid;grid-template-columns:repeat(6,minmax(8rem,1fr));gap:.6rem;margin-top:.65rem;padding-top:.65rem;border-top:1px solid var(--dash-border)}.more-filters label{display:grid;min-width:0;gap:.18rem}.applied-filters{display:flex;align-items:center;flex-wrap:wrap;gap:.35rem;margin-top:.55rem;color:var(--dash-muted);font-size:.65rem}.applied-filters>span{font-weight:800}.applied-filters button{display:inline-flex;align-items:center;gap:.22rem;min-height:1.55rem;border:1px solid var(--dash-border);border-radius:99px;background:var(--dash-soft);color:var(--dash-text);padding:.15rem .45rem;font:inherit;font-size:.64rem;cursor:pointer}.applied-filters button svg{width:.65rem}.applied-filters .reset-button{border:0;background:transparent;color:var(--dash-accent);font-weight:800}.scope-control button:focus-visible,.compact-field select:focus-visible,.more-filters select:focus-visible,.filter-toggle:focus-visible,.apply-button:focus-visible,.applied-filters button:focus-visible{outline:3px solid var(--dash-focus);outline-offset:2px}.spin{animation:spin .8s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}@media(max-width:1100px){.command-row{flex-wrap:wrap}.filter-toggle{margin-left:0}.more-filters{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:680px){.command-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.scope-control{grid-column:1/-1}.scope-control button{flex:1}.compact-field,.due-field{min-width:0}.filter-toggle{width:100%}.apply-button{width:100%}.more-filters{grid-template-columns:1fr}.command-bar{padding:.65rem}}
</style>
