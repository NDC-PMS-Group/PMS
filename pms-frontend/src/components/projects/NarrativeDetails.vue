<template>
  <div class="narrative-details">
    <div v-for="(table, index) in content.tables" :key="index" class="narrative-table">
      <label v-if="!readonly">Table caption<input v-model="table.caption" maxlength="255" @input="publish" /></label>
      <div class="table-scroll" tabindex="0" :aria-label="`${label} table ${index + 1}`">
        <table>
          <caption v-if="readonly">{{ table.caption }}</caption>
          <tbody><tr v-for="(row, rowIndex) in table.rows" :key="rowIndex">
            <td v-for="(_, columnIndex) in row" :key="columnIndex">
              <span v-if="readonly">{{ row[columnIndex] }}</span>
              <textarea v-else v-model="row[columnIndex]" :aria-label="`${label}, table ${index + 1}, row ${rowIndex + 1}, column ${columnIndex + 1}`" maxlength="2000" rows="2" @input="publish" />
            </td>
            <td v-if="!readonly"><button type="button" :disabled="table.rows.length === 1" :aria-label="`Remove row ${rowIndex + 1}`" @click="table.rows.splice(rowIndex, 1); publish()">Remove row</button></td>
          </tr></tbody>
        </table>
      </div>
      <div v-if="!readonly" class="narrative-actions">
        <button type="button" :disabled="table.rows.length >= 50" @click="table.rows.push(Array(table.rows[0].length).fill('')); publish()">Add row</button>
        <button type="button" :disabled="table.rows[0].length >= 12" @click="table.rows.forEach(row => row.push('')); publish()">Add column</button>
        <button type="button" :disabled="table.rows[0].length <= 1" @click="table.rows.forEach(row => row.pop()); publish()">Remove last column</button>
        <button type="button" @click="content.tables.splice(index, 1); publish()">Remove table</button>
      </div>
    </div>
    <figure v-for="(visual, index) in content.images" :key="index">
      <img v-if="safeImage(visual.data)" :src="visual.data" :alt="visual.caption" loading="lazy" />
      <figcaption v-if="readonly">{{ visual.caption }}</figcaption>
      <template v-else>
        <label>Image description<input v-model="visual.caption" maxlength="255" @input="publish" /></label>
        <button type="button" @click="content.images.splice(index, 1); publish()">Remove image</button>
      </template>
    </figure>
    <div v-if="!readonly" class="narrative-actions">
      <button type="button" :disabled="content.tables.length >= 5" @click="content.tables.push({ caption: '', rows: [['', ''], ['', '']] }); publish()">Add table</button>
      <label class="upload-label">Add chart / diagram image<input type="file" accept="image/png,image/jpeg,image/webp" :disabled="content.images.length >= 3" @change="uploadImage" /></label>
      <span>PNG, JPEG or WebP, up to 500 KB each.</span>
    </div>
    <p v-if="error" role="alert">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import type { NarrativeContent } from '@/types/project';
const props = defineProps<{ modelValue?: NarrativeContent; label: string; readonly?: boolean }>();
const emit = defineEmits<{ 'update:modelValue': [value: NarrativeContent] }>();
const content = ref<NarrativeContent>({ tables: [], images: [] });
const error = ref('');
watch(() => props.modelValue, value => {
  content.value = JSON.parse(JSON.stringify(value || { tables: [], images: [] }));
}, { immediate: true });
const publish = () => emit('update:modelValue', JSON.parse(JSON.stringify(content.value)));
const safeImage = (data: string) => /^data:image\/(png|jpeg|webp);base64,[A-Za-z0-9+/=]+$/.test(data);
const uploadImage = async (event: Event) => {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  input.value = '';
  error.value = '';
  if (!file) return;
  if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 512000) {
    error.value = 'Choose a PNG, JPEG or WebP image no larger than 500 KB.';
    return;
  }
  const reader = new FileReader();
  reader.onerror = () => { error.value = 'Unable to read the image. Please try again.'; };
  reader.onload = () => {
    if (content.value.images.length >= 3) return;
    content.value.images.push({ caption: file.name.slice(0, 255), data: String(reader.result) });
    publish();
  };
  reader.readAsDataURL(file);
};
</script>

<style scoped>
.narrative-details{min-width:0;margin-top:.6rem;color:inherit}.narrative-table,figure{margin:.6rem 0;padding:.6rem;border:1px solid #94a3b8;border-radius:.4rem}.table-scroll{overflow:auto;max-width:100%}table{width:100%;border-collapse:collapse}td{border:1px solid #94a3b8;min-width:7rem;padding:.3rem;white-space:pre-wrap;overflow-wrap:anywhere}textarea,input{width:100%;border:1px solid #94a3b8;border-radius:.25rem;background:transparent;color:inherit;padding:.35rem;font:inherit}textarea{resize:vertical}.narrative-actions{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;margin-top:.5rem;font-size:.75rem}button,.upload-label{border:1px solid #94a3b8;border-radius:.3rem;padding:.4rem .6rem;cursor:pointer;font-size:.75rem}button:disabled{opacity:.5;cursor:default}.upload-label input{display:block;max-width:15rem}label{display:block;font-size:.75rem}img{max-width:100%;max-height:24rem;object-fit:contain}button:focus-visible,input:focus-visible,textarea:focus-visible{outline:2px solid #2563eb;outline-offset:2px}p[role=alert]{color:#dc2626;font-size:.8rem}caption,figcaption{text-align:left;font-weight:600;padding:.3rem}
</style>
