<template>
  <section class="additional-locations" aria-label="Additional project or investment locations">
    <h3>Additional regions / provinces</h3>
    <p>Keep the main site above. Add each additional area covered by this project or investment.</p>
    <div v-for="(location, index) in modelValue || []" :key="index" class="location-row">
      <label>Region<select :aria-label="`Additional region ${index + 1}`" :value="location.region_code" @change="setRegion(index, ($event.target as HTMLSelectElement).value)">
        <option value="">Select region</option><option v-for="region in store.regions" :key="region.code" :value="region.code">{{ region.regionName || region.name }}</option>
      </select></label>
      <label>Province<select :aria-label="`Additional province ${index + 1}`" :value="location.province_code || ''" @change="setProvince(index, ($event.target as HTMLSelectElement).value)">
        <option value="">All / not applicable</option><option v-for="province in provinces[location.region_code] || []" :key="province.code" :value="province.code">{{ province.name }}</option>
      </select></label>
      <label>Address / coverage<input :value="location.address" maxlength="255" @input="update(index, { address: ($event.target as HTMLInputElement).value })" /></label>
      <button type="button" @click="emit('update:modelValue', (modelValue || []).filter((_, i) => i !== index))">Remove location {{ index + 1 }}</button>
    </div>
    <button type="button" :disabled="(modelValue?.length || 0) >= 100" @click="emit('update:modelValue', [...(modelValue || []), { region_code: '', region_name: '' }])">Add location</button>
    <p v-if="error" role="alert">{{ error }}</p>
  </section>
</template>
<script setup lang="ts">
import { ref, watch } from 'vue';
import axios from '@/utils/axiosInstance';
import { useLocationStore } from '@/store/locations';
import type { ProjectLocation } from '@/types/project';
const props = defineProps<{ modelValue?: ProjectLocation[] }>();
const emit = defineEmits<{ 'update:modelValue': [value: ProjectLocation[]] }>();
const store = useLocationStore();
const provinces = ref<Record<string, { code: string; name: string }[]>>({});
const error = ref('');
const update = (index: number, value: Partial<ProjectLocation>) => emit('update:modelValue', (props.modelValue || []).map((item, i) => i === index ? { ...item, ...value } : item));
const loadProvinces = async (code: string) => {
  if (!code || provinces.value[code]) return;
  try {
    const response = await axios.get(`/api/locations/regions/${encodeURIComponent(code)}/provinces`);
    provinces.value[code] = response.data.data ?? response.data;
    error.value = '';
  } catch { error.value = 'Unable to load provinces. Reselect the region to retry.'; }
};
const setRegion = async (index: number, code: string) => {
  const region = store.regions.find(item => item.code === code);
  update(index, { region_code: code, region_name: region?.regionName || region?.name || '', province_code: null, province_name: null });
  await loadProvinces(code);
};
const setProvince = (index: number, code: string) => {
  const region = props.modelValue?.[index].region_code || '';
  update(index, { province_code: code || null, province_name: provinces.value[region]?.find(item => item.code === code)?.name || null });
};
watch(() => props.modelValue?.map(item => item.region_code), codes => { codes?.forEach(loadProvinces); }, { immediate: true });
</script>
<style scoped>
.additional-locations{margin-top:1rem}h3{font-weight:700}p{font-size:.8rem;margin:.4rem 0}.location-row{display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:.6rem;align-items:end;margin:.7rem 0}label{font-size:.75rem}select,input{display:block;width:100%;padding:.5rem;border:1px solid #94a3b8;border-radius:.3rem;background:var(--m-surface,white);color:inherit}button{padding:.5rem;border:1px solid #94a3b8;border-radius:.3rem}button:focus-visible,input:focus-visible,select:focus-visible{outline:2px solid #2563eb;outline-offset:2px}@media(max-width:700px){.location-row{grid-template-columns:1fr}}:global(.dark) select,:global(.dark) input{background:#1e293b}
</style>
