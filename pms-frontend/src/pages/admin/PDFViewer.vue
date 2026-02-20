<template>
  <div class="flex flex-col h-screen bg-gray-100">
    <!-- Header with actions -->
    <div class="flex items-center justify-between px-6 py-4 bg-white border-b border-gray-200 shadow-sm">
      <h1 class="flex-1 m-0 text-lg font-semibold text-center text-gray-900">{{ documentTitle }}</h1>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex flex-col items-center justify-center flex-1 gap-4">
      <div class="w-12 h-12 border-4 border-gray-200 border-t-blue-500 rounded-full animate-spin"></div>
      <p class="m-0 text-base text-gray-600">Loading PDF...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="flex flex-col items-center justify-center flex-1 gap-4">
      <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-red-500">
        <circle cx="12" cy="12" r="10"/>
        <line x1="12" y1="8" x2="12" y2="12"/>
        <line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      <h2 class="m-0 text-2xl font-semibold text-gray-900">Failed to Load PDF</h2>
      <p class="m-0 text-center text-gray-600 max-w-md">{{ error }}</p>
      <button @click="retry" class="flex items-center gap-2 px-6 py-2 text-sm font-medium text-white bg-blue-500 border-none rounded-md cursor-pointer transition-colors hover:bg-blue-600 mt-2">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="23 4 23 10 17 10"/>
          <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
        </svg>
        Try Again
      </button>
    </div>

    <!-- PDF Display -->
    <div v-else class="flex flex-1 p-0 overflow-hidden">
      <iframe 
        ref="pdfIframe"
        :src="pdfUrl" 
        class="w-full h-full border-0"
        frameborder="0"
        @load="onPdfLoad"
        @error="onPdfError"
      ></iframe>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const type = ref(route.params.type as string)
const id = ref(route.params.id as string)
const loading = ref(true)
const error = ref<string | null>(null)
const pdfIframe = ref<HTMLIFrameElement | null>(null)

// Get API base URL from environment or use default
// Use VITE_APP_BASE_URL to match your existing .env configuration
const apiBaseUrl = import.meta.env.VITE_APP_BASE_URL || 'http://localhost:8000'

const pdfUrl = computed(() => {
  // Handle file attachments (stored PDFs)
  if(['pr_status_attachment', 'asset_attachment', 'supply_attachment', 'assignment_document', 'repair_document'].includes(type.value)) {
    return `${apiBaseUrl}/api/v1/admin/pdf/file/${type.value}/${id.value}`
  }
  
  // Handle generated PDFs
  return `${apiBaseUrl}/api/v1/admin/pdf/view/${type.value}/${id.value}`
})

const documentTitle = computed(() => {
  const typeMap: Record<string, string> = {
    ptr: 'Property Transfer Report',
    invoice: 'Invoice Receipt',
    par: 'Property Acknowledgment Receipt',
    ics: 'Inventory Custodian Slip',
    pr_status_attachment: 'Procurement Status Attachment',
    asset_attachment: 'Asset Attachment',
    supply_attachment: 'Supply Attachment'
  }
  return typeMap[type.value.toLowerCase()] || `${type.value.toUpperCase()} Document`
})

const retry = () => {
  error.value = null
  loading.value = true
  
  // Force reload the iframe
  if (pdfIframe.value) {
    pdfIframe.value.src = pdfUrl.value
  }
}

const onPdfLoad = () => {
  // Give it a small delay to ensure content is loaded
  setTimeout(() => {
    loading.value = false
  }, 300)
}

const onPdfError = () => {
  loading.value = false
  error.value = 'Unable to load the PDF. Please check your connection and try again.'
}

onMounted(() => {
  // Initial load timeout fallback
  setTimeout(() => {
    if (loading.value) {
      loading.value = false
    }
  }, 5000)
})
</script>

<style scoped>
@media (max-width: 768px) {
  .pdf-header {
    flex-wrap: wrap;
    padding: 0.75rem 1rem;
  }

  .document-title {
    width: 100%;
    order: -1;
    margin-bottom: 0.5rem;
    font-size: 1rem;
    text-align: left;
  }

  .actions {
    margin-left: auto;
  }

  .action-button span {
    display: none;
  }

  .pdf-display {
    padding: 0;
  }
}

/* Print Styles */
@media print {
  .pdf-header {
    display: none;
  }

  .pdf-display {
    padding: 0;
  }

  .pdf-iframe {
    border: none;
    box-shadow: none;
  }
}
</style>