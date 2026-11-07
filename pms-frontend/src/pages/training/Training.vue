<template>
  <main class="training-page" :class="{ 'is-dark': isDarkMode }">
    <header class="training-header">
      <h1>Manuals &amp; Video Tutorials</h1>
      <p>Guidance for your {{ role || 'account' }} account.</p>
    </header>

    <p v-if="loading" class="training-state" role="status">Loading your training materials…</p>
    <div v-else-if="error" class="training-state" role="alert">
      <p>{{ error }}</p>
      <button class="training-button" @click="loadMaterials">Try again</button>
    </div>
    <p v-else-if="!materials.manual && !materials.video" class="training-state">
      Training materials are not yet available for your account type. Please contact your administrator.
    </p>
    <div v-else class="training-grid">
      <section class="training-card" aria-labelledby="manual-heading">
        <h2 id="manual-heading">User manual</h2>
        <p>Follow the illustrated steps and function guides at your own pace.</p>
        <a v-if="materials.manual" :href="materials.manual" target="_blank" rel="noopener noreferrer"
          class="training-button">Open PDF manual <span class="sr-only">in a new tab</span></a>
        <p v-else role="status">The manual is not yet available.</p>
      </section>

      <section class="training-card training-video" aria-labelledby="video-heading">
        <h2 id="video-heading">Video tutorial</h2>
        <p>Watch the simulated workflows for your account type. English captions are included in the video.</p>
        <template v-if="materials.video">
          <video :key="materials.video" :src="materials.video" controls playsinline preload="metadata"
            aria-label="Account video tutorial" @error="videoError = true">
            Your browser does not support video playback. Use the video link below to open it.
          </video>
          <p v-if="videoError" role="alert">The video could not load. Refresh the links and try again.</p>
          <a :href="materials.video" target="_blank" rel="noopener noreferrer" class="training-link">
            Open video in a new tab
          </a>
        </template>
        <p v-else role="status">The video is not yet available.</p>
      </section>
      <p class="training-footer">If a link has expired, <button class="training-link" @click="loadMaterials">refresh your training links</button>.</p>
    </div>
  </main>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { SITE_MODE } from '@/app/const';
import { useLayoutStore } from '@/store/layout';
import axiosInstance from '@/utils/axiosInstance';

const layoutStore = useLayoutStore();
const isDarkMode = computed(() => layoutStore.mode === SITE_MODE.DARK);
const role = ref('');
const materials = ref<{ manual: string | null; video: string | null }>({ manual: null, video: null });
const loading = ref(true);
const error = ref('');
const videoError = ref(false);

function mediaUrl(path: string | null): string | null {
  return path ? new URL(path, axiosInstance.defaults.baseURL || window.location.origin).href : null;
}

async function loadMaterials() {
  loading.value = true;
  error.value = '';
  videoError.value = false;
  try {
    const { data } = await axiosInstance.get('/api/training');
    role.value = data.role || '';
    materials.value = { manual: mediaUrl(data.materials.manual), video: mediaUrl(data.materials.video) };
  } catch {
    error.value = 'Your training materials could not be loaded. Please try again.';
  } finally {
    loading.value = false;
  }
}

onMounted(loadMaterials);
</script>

<style scoped>
.training-page {
  --training-surface: #ffffff;
  --training-text: #111827;
  --training-muted: #4b5563;
  --training-border: #d1d5db;
  --training-link: #1d4ed8;
  color: var(--training-text);
  max-width: 1440px;
  margin: 0 auto;
  padding: 24px;
  overflow-wrap: anywhere;
}
.training-page.is-dark {
  --training-surface: #1f2937;
  --training-text: #f9fafb;
  --training-muted: #d1d5db;
  --training-border: #6b7280;
  --training-link: #93c5fd;
}
.training-header { margin-bottom: 24px; }
.training-header h1 { font-size: clamp(1.5rem, 3vw, 2rem); font-weight: 700; line-height: 1.3; }
.training-page p { color: var(--training-muted); line-height: 1.6; margin-top: 10px; }
.training-grid { display: grid; grid-template-columns: minmax(240px, 1fr) minmax(0, 2fr); gap: 24px; align-items: start; }
.training-card, .training-state { background: var(--training-surface); border: 1px solid var(--training-border); border-radius: 12px; padding: 24px; min-width: 0; }
.training-card h2 { font-size: 1.25rem; font-weight: 600; }
.training-button { display: inline-flex; justify-content: center; background: #1d4ed8; color: #fff; padding: 10px 16px; border-radius: 8px; margin-top: 20px; font-weight: 600; }
.training-link { color: var(--training-link); text-decoration: underline; text-underline-offset: 3px; }
.training-video video { display: block; width: 100%; aspect-ratio: 16 / 11; background: #000; margin: 20px 0 16px; border-radius: 8px; }
.training-footer { grid-column: 1 / -1; }
.training-page a:focus-visible, .training-page button:focus-visible { outline: 3px solid var(--training-link); outline-offset: 4px; }
@media (max-width: 767px) {
  .training-page { padding: 16px; }
  .training-grid { grid-template-columns: minmax(0, 1fr); gap: 16px; }
  .training-card, .training-state { padding: 20px; }
}
</style>
