<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { usePermission } from '@/composables/usePermission'
import { useSystemSettingsStore } from '@/store/systemSettings'
import { storeToRefs } from 'pinia'
import { toast } from 'vue3-toastify'
import { 
  Settings, 
  Save, 
  RefreshCw,
  Shield,
  Clock,
  CheckCircle,
  XCircle
} from 'lucide-vue-next'

// Store
const systemSettingsStore = useSystemSettingsStore()
const { settings, loading } = storeToRefs(systemSettingsStore)

// Permission checking
const { canUpdate } = usePermission()
const permissionKey = 'system_settings'

// Form state
const formData = ref<Record<string, any>>({})
const saving = ref(false)
const saveSuccess = ref(false)

// Initialize form data
const initializeFormData = () => {
  const data: Record<string, any> = {}
  
  Object.values(settings.value).flat().forEach(setting => {
    data[setting.key] = setting.value
  })
  
  formData.value = data
}

// Check if form has changes
const hasChanges = computed(() => {
  return Object.values(settings.value).flat().some(setting => {
    return formData.value[setting.key] !== setting.value
  })
})

// Save settings
const saveSettings = async () => {
  if (!canUpdate(permissionKey)) {
    toast.error('You do not have permission to update settings')
    return
  }

  saving.value = true
  saveSuccess.value = false

  try {
    const settingsToUpdate = Object.values(settings.value).flat()
      .filter(setting => formData.value[setting.key] !== setting.value)
      .map(setting => ({
        key: setting.key,
        value: formData.value[setting.key]
      }))

    if (settingsToUpdate.length === 0) {
      toast.success('No changes to save')
      return
    }

    await systemSettingsStore.updateSettings(settingsToUpdate)
    saveSuccess.value = true
    
    setTimeout(() => {
      saveSuccess.value = false
    }, 3000)

    toast.success('Settings saved successfully')
  } catch (error) {
    toast.error('Failed to save settings')
  } finally {
    saving.value = false
  }
}

// Reset form
const resetForm = () => {
  initializeFormData()
}

// Refresh settings
const refreshSettings = async () => {
  try {
    await systemSettingsStore.fetchSettings()
    initializeFormData()
  } catch (error) {
    toast.error('Failed to refresh settings')
  }
}

// Get group icon
const getGroupIcon = (group: string) => {
  const icons: Record<string, any> = {
    security: Shield,
    general: Settings,
  }
  return icons[group] || Settings
}

// Get group color
const getGroupColor = (group: string) => {
  const colors: Record<string, string> = {
    security: 'text-red-600 bg-red-50 border-red-200',
    general: 'text-blue-600 bg-blue-50 border-blue-200',
  }
  return colors[group] || 'text-gray-600 bg-gray-50 border-gray-200'
}

// Initialize
onMounted(async () => {
  try {
    await systemSettingsStore.initialize()
    initializeFormData()
  } catch (error) {
    console.error('Failed to initialize:', error)
  }
})
</script>

<template>
  <div class="p-0 md:p-6 space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">System Settings</h1>
        <p class="text-sm text-gray-600 mt-1">Configure system-wide settings and preferences</p>
      </div>
      <div class="flex items-center gap-3">
        <button
          @click="refreshSettings"
          :disabled="loading"
          class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 flex items-center gap-2 disabled:opacity-50"
        >
          <RefreshCw :size="16" :class="{ 'animate-spin': loading }" />
          Refresh
        </button>
        <button
          v-if="canUpdate(permissionKey) && hasChanges"
          @click="resetForm"
          class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
        >
          Reset Changes
        </button>
        <button
          v-if="canUpdate(permissionKey)"
          @click="saveSettings"
          :disabled="saving || !hasChanges"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <Save :size="16" />
          Save Changes
        </button>
      </div>
    </div>

    <!-- Save Success Banner -->
    <Transition name="fade">
      <div
        v-if="saveSuccess"
        class="bg-green-50 border border-green-200 rounded-lg p-4 flex items-center gap-3"
      >
        <CheckCircle :size="20" class="text-green-600" />
        <span class="text-green-800 font-medium">Settings saved successfully!</span>
      </div>
    </Transition>

    <!-- Loading State -->
    <div v-if="loading && Object.keys(settings).length === 0" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-8">
      <div class="flex flex-col items-center justify-center">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 dark:border-blue-400 mb-2"></div>
        <p class="text-gray-500 dark:text-gray-400">Loading settings...</p>
      </div>
    </div>

    <!-- Settings Groups -->
    <div v-else class="space-y-6">
      <div
        v-for="(groupSettings, groupName) in settings"
        :key="groupName"
        class="bg-white rounded-lg border border-gray-200 overflow-hidden"
      >
        <!-- Group Header -->
        <div :class="getGroupColor(groupName)" class="px-6 py-4 border-b">
          <div class="flex items-center gap-3">
            <component :is="getGroupIcon(groupName)" :size="24" />
            <div>
              <h2 class="text-lg font-semibold capitalize">{{ groupName }} Settings</h2>
            </div>
          </div>
        </div>

        <!-- Settings List -->
        <div class="p-6 space-y-6">
          <div
            v-for="setting in groupSettings"
            :key="setting.key"
            class="border-b border-gray-100 pb-6 last:border-b-0 last:pb-0"
          >
            <div class="flex items-start justify-between gap-6">
              <!-- Label & Description -->
              <div class="flex-1">
                <label :for="setting.key" class="block text-sm font-medium text-gray-900 mb-1">
                  {{ setting.label }}
                </label>
                <p v-if="setting.description" class="text-sm text-gray-600">
                  {{ setting.description }}
                </p>
              </div>

              <!-- Input -->
              <div class="w-64">
                <!-- Boolean (Toggle) -->
                <div v-if="setting.type === 'boolean'" class="flex items-center justify-end">
                  <label class="relative inline-flex items-center cursor-pointer">
                    <input
                      :id="setting.key"
                      v-model="formData[setting.key]"
                      type="checkbox"
                      class="sr-only peer"
                      :disabled="!canUpdate(permissionKey)"
                    />
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                  </label>
                </div>

                <!-- Integer -->
                <div v-else-if="setting.type === 'integer'" class="flex items-center gap-2">
                  <input
                    :id="setting.key"
                    v-model.number="formData[setting.key]"
                    type="number"
                    min="1"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    :disabled="!canUpdate(permissionKey)"
                  />
                  <span v-if="setting.key === 'session_timeout_minutes'" class="text-sm text-gray-600 whitespace-nowrap">
                    minutes
                  </span>
                </div>

                <!-- String -->
                <input
                  v-else-if="setting.type === 'string'"
                  :id="setting.key"
                  v-model="formData[setting.key]"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  :disabled="!canUpdate(permissionKey)"
                />

                <!-- Float -->
                <input
                  v-else-if="setting.type === 'float'"
                  :id="setting.key"
                  v-model.number="formData[setting.key]"
                  type="number"
                  step="0.01"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  :disabled="!canUpdate(permissionKey)"
                />
              </div>
            </div>

            <!-- Changed Indicator -->
            <div
              v-if="formData[setting.key] !== setting.value"
              class="mt-2 flex items-center gap-2 text-sm text-amber-600"
            >
              <Clock :size="14" />
              <span>Unsaved changes</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- No Permission Message -->
    <div v-if="!canUpdate(permissionKey)" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 flex items-center gap-3">
      <XCircle :size="20" class="text-yellow-600" />
      <span class="text-yellow-800">You do not have permission to modify system settings. Contact your administrator.</span>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>