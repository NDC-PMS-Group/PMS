<template>
  <section class="panel trend-panel" aria-labelledby="portfolio-trend-heading">
    <header class="panel-head">
      <div>
        <p class="kicker">Portfolio movement</p>
        <h2 id="portfolio-trend-heading">Monthly intake and completion</h2>
      </div>
      <div class="trend-totals" aria-label="Trend totals">
        <span><i class="intake"></i>{{ intakeTotal }} intakes</span>
        <span><i class="completion"></i>{{ completionTotal }} completed</span>
      </div>
    </header>

    <div v-if="points.length" class="chart-wrap" aria-hidden="true">
      <apexchart type="area" height="230" :options="chartOptions" :series="series" />
    </div>
    <div v-else class="empty-state">No monthly activity is available for this scope.</div>

    <details v-if="points.length" class="trend-data">
      <summary>View monthly values</summary>
      <table>
        <caption>Monthly project intake and completion values</caption>
        <thead><tr><th>Month</th><th>Intakes</th><th>Completed</th></tr></thead>
        <tbody><tr v-for="point in points" :key="point.month"><td>{{ point.label }}</td><td>{{ point.intakes }}</td><td>{{ point.completions }}</td></tr></tbody>
      </table>
    </details>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { ApexOptions } from 'apexcharts';
import type { PortfolioTrendPoint } from '@/types/dashboard';

const props = defineProps<{ points: PortfolioTrendPoint[]; dark: boolean }>();
const intakeTotal = computed(() => props.points.reduce((total, point) => total + point.intakes, 0));
const completionTotal = computed(() => props.points.reduce((total, point) => total + point.completions, 0));
const series = computed(() => [
  { name: 'Intakes', data: props.points.map((point) => point.intakes) },
  { name: 'Completed', data: props.points.map((point) => point.completions) },
]);
const chartOptions = computed<ApexOptions>(() => ({
  chart: { toolbar: { show: false }, zoom: { enabled: false }, animations: { enabled: !window.matchMedia('(prefers-reduced-motion: reduce)').matches }, fontFamily: 'inherit', background: 'transparent' },
  colors: ['#2563eb', '#16a34a'],
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 2 },
  fill: { type: 'gradient', gradient: { opacityFrom: .22, opacityTo: .02, stops: [0, 95] } },
  grid: { borderColor: props.dark ? '#334155' : '#e2e8f0', strokeDashArray: 3, padding: { left: 8, right: 8 } },
  legend: { show: false },
  xaxis: { categories: props.points.map((point) => point.label), axisBorder: { show: false }, axisTicks: { show: false }, labels: { style: { colors: props.dark ? '#a8b5c7' : '#64748b', fontSize: '11px' } } },
  yaxis: { min: 0, forceNiceScale: true, labels: { formatter: (value: number) => Math.round(value).toString(), style: { colors: [props.dark ? '#a8b5c7' : '#64748b'], fontSize: '11px' } } },
  tooltip: { theme: props.dark ? 'dark' : 'light' },
}));
</script>

<style scoped>
.panel{min-width:0;border:1px solid var(--dash-border);border-radius:.45rem;background:var(--dash-card);padding:.9rem}.panel-head{display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem}.kicker{margin:0 0 .12rem;color:var(--dash-accent);font-size:.65rem;font-weight:800;text-transform:uppercase}.panel-head h2{margin:0;font-size:1rem}.trend-totals{display:flex;align-items:center;gap:.7rem;color:var(--dash-muted);font-size:.64rem;font-weight:700}.trend-totals span{display:inline-flex;align-items:center;gap:.25rem}.trend-totals i{width:.48rem;height:.48rem;border-radius:50%}.trend-totals .intake{background:#2563eb}.trend-totals .completion{background:#16a34a}.chart-wrap{height:230px;margin-top:.35rem}.trend-data{margin-top:.35rem;border-top:1px solid var(--dash-border);padding-top:.45rem;color:var(--dash-muted);font-size:.65rem}.trend-data summary{width:max-content;color:var(--dash-accent);font-weight:800;cursor:pointer}.trend-data table{width:100%;margin-top:.45rem;border-collapse:collapse}.trend-data caption{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}.trend-data th,.trend-data td{border-bottom:1px solid var(--dash-border);padding:.35rem;text-align:left}.trend-data th:not(:first-child),.trend-data td:not(:first-child){text-align:right}.empty-state{display:grid;min-height:230px;place-items:center;color:var(--dash-muted);font-size:.72rem}@media(max-width:540px){.panel-head{flex-direction:column}.trend-totals{flex-wrap:wrap}.chart-wrap{overflow:hidden}}
</style>
