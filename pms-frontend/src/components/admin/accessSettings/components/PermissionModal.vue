<script lang="ts" setup>
import { ref, computed } from 'vue'
import { useAccessSettingsStore } from '@/store/accessSettings'
import { toast } from 'vue3-toastify'
import { Save, X } from 'lucide-vue-next'
import type { PermissionFormData } from '@/types/accessSettings'

interface Emits {
  (e: 'close'): void
  (e: 'saved'): void
}

const emit = defineEmits<Emits>()

const accessStore = useAccessSettingsStore()

// State
const form = ref({
  resource: '',
  description: '',
  actions: {
    view: false,
    create: false,
    update: false,
    delete: false
  }
})

const loading = computed(() => accessStore.loading)

const closeModal = () => {
  emit('close')
}

const handleSubmit = async () => {
  if (!form.value.resource.trim()) {
    toast.error('Resource name is required')
    return
  }

  const selectedActions = Object.entries(form.value.actions)
    .filter(([_, checked]) => checked)
    .map(([action]) => action)

  if (selectedActions.length === 0) {
    toast.error('Please select at least one action')
    return
  }

  try {
    // Create permissions for each selected action
    const promises = selectedActions.map(action => {
      const data: PermissionFormData = {
        resource: form.value.resource,
        action: action,
        description: form.value.description || undefined
      }
      return accessStore.createPermission(data)
    })

    await Promise.all(promises)
    toast.success(`${selectedActions.length} permission(s) created successfully`)
    emit('saved')
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to create permissions')
    console.error(error)
  }
}
</script>

<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-70 flex items-center justify-center z-50 p-4 !mt-0">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md max-h-[90vh] overflow-hidden border border-gray-200 dark:border-gray-700">
      
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
        <div>
          <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
            Add New Permissions
          </h2>
          <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            Create permissions for a resource
          </p>
        </div>
        <button
          @click="closeModal"
          class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
          :disabled="loading"
        >
          <X :size="20" class="text-gray-500 dark:text-gray-400" />
        </button>
      </div>
      
      <!-- Loading Overlay -->
      <div v-if="loading" class="absolute inset-0 bg-white dark:bg-gray-800 bg-opacity-75 dark:bg-opacity-75 flex items-center justify-center z-10 rounded-lg">
        <div class="flex flex-col items-center">
          <svg class="w-8 h-8 animate-spin text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          <span class="mt-2 text-sm text-gray-600 dark:text-gray-400">Creating permissions...</span>
        </div>
      </div>
      
      <!-- Form Content -->
      <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)] modal-scroll">
        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div>
            <label for="resource" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Resource Name <span class="text-red-500">*</span>
            </label>
            <input
              id="resource"
              v-model="form.resource"
              type="text"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 focus:border-gray-500 dark:focus:border-gray-400 focus:ring-2 focus:ring-gray-200 dark:focus:ring-gray-400/50 outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
              placeholder="e.g., projects, users, reports"
              required
            />
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Use lowercase, no spaces (use underscores)</p>
          </div>

          <div>
            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Description
            </label>
            <input
              id="description"
              v-model="form.description"
              type="text"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 focus:border-gray-500 dark:focus:border-gray-400 focus:ring-2 focus:ring-gray-200 dark:focus:ring-gray-400/50 outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
              placeholder="e.g., Project Management, User Management"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Select Actions <span class="text-red-500">*</span>
            </label>
            <div class="space-y-2">
              <label class="flex items-center space-x-3 cursor-pointer p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors border border-gray-200 dark:border-gray-600">
                <input
                  v-model="form.actions.view"
                  type="checkbox"
                  class="h-4 w-4 text-green-600 border-gray-300 dark:border-gray-600 rounded focus:ring-green-500"
                />
                <div class="flex-1">
                  <span class="text-sm font-medium text-gray-700 dark:text-gray-300">View / Read</span>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400 font-mono bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                  {{ form.resource || 'resource' }}.view
                </span>
              </label>

              <label class="flex items-center space-x-3 cursor-pointer p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors border border-gray-200 dark:border-gray-600">
                <input
                  v-model="form.actions.create"
                  type="checkbox"
                  class="h-4 w-4 text-blue-600 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500"
                />
                <div class="flex-1">
                  <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Create / Add</span>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400 font-mono bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                  {{ form.resource || 'resource' }}.create
                </span>
              </label>

              <label class="flex items-center space-x-3 cursor-pointer p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors border border-gray-200 dark:border-gray-600">
                <input
                  v-model="form.actions.update"
                  type="checkbox"
                  class="h-4 w-4 text-yellow-600 border-gray-300 dark:border-gray-600 rounded focus:ring-yellow-500"
                />
                <div class="flex-1">
                  <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Update / Edit</span>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400 font-mono bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                  {{ form.resource || 'resource' }}.update
                </span>
              </label>

              <label class="flex items-center space-x-3 cursor-pointer p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors border border-gray-200 dark:border-gray-600">
                <input
                  v-model="form.actions.delete"
                  type="checkbox"
                  class="h-4 w-4 text-red-600 border-gray-300 dark:border-gray-600 rounded focus:ring-red-500"
                />
                <div class="flex-1">
                  <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Delete / Remove</span>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400 font-mono bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                  {{ form.resource || 'resource' }}.delete
                </span>
              </label>
            </div>
          </div>
        </form>
      </div>
      
      <!-- Footer -->
      <div class="flex justify-end gap-3 p-6 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30">
        <button
          @click="closeModal"
          class="px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-sm font-medium disabled:opacity-50"
          :disabled="loading"
        >
          Cancel
        </button>
        <button
          @click="handleSubmit"
          class="px-4 py-2.5 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800 transition-colors flex items-center gap-2 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
          :disabled="loading"
        >
          <svg v-if="loading" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
          </svg>
          <Save v-else :size="16" />
          <span>Create Permissions</span>
        </button>
      </div>
      
    </div>
  </div>
</template>