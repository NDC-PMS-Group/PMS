<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
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
  XCircle,
  Image as ImageIcon,
  UploadCloud
} from 'lucide-vue-next'

import axiosInstance from '@/utils/axiosInstance'
import { useLayoutStore } from '@/store/layout'

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
const uploadingLogo = ref(false)
const logoError = ref(false)

// Reset logo error when logo path changes
watch(() => formData.value.app_logo, () => {
  logoError.value = false
})

// Filtered settings: omit project_code_prefix
const filteredSettings = computed(() => {
  const filtered: Record<string, any[]> = {}
  Object.entries(settings.value).forEach(([groupName, groupSettings]) => {
    const items = groupSettings.filter(setting => setting.key !== 'project_code_prefix')
    if (items.length > 0) {
      filtered[groupName] = items
    }
  })
  return filtered
})

// Initialize form data
const initializeFormData = () => {
  const data: Record<string, any> = {}
  
  Object.values(filteredSettings.value).flat().forEach(setting => {
    data[setting.key] = setting.value
  })
  
  formData.value = data
}

// Check if form has changes
const hasChanges = computed(() => {
  return Object.values(filteredSettings.value).flat().some(setting => {
    return formData.value[setting.key] !== setting.value
  })
})

// Logo Upload Handler
const handleLogoUpload = async (event: Event, key: string) => {
  const target = event.target as HTMLInputElement
  if (!target.files || target.files.length === 0) return

  const file = target.files[0]
  const uploadData = new FormData()
  uploadData.append('logo', file)

  uploadingLogo.value = true
  try {
    const response = await axiosInstance.post('/api/settings/upload-logo', uploadData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    formData.value[key] = response.data.logo_url
    toast.success('Logo uploaded successfully. Remember to click Save Changes to apply!')
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to upload logo image')
  } finally {
    uploadingLogo.value = false
  }
}

// Save settings
const saveSettings = async () => {
  if (!canUpdate(permissionKey)) {
    toast.error('You do not have permission to update settings')
    return
  }

  saving.value = true
  saveSuccess.value = false

  try {
    const settingsToUpdate = Object.values(filteredSettings.value).flat()
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
    
    // Apply theme changes reactively if theme was updated
    const themeSetting = settingsToUpdate.find(s => s.key === 'app_theme')
    if (themeSetting) {
      const layoutStore = useLayoutStore()
      layoutStore.changeSiteMode(themeSetting.value)
    }

    // Apply title changes reactively
    const nameSetting = settingsToUpdate.find(s => s.key === 'app_name')
    if (nameSetting) {
      document.title = nameSetting.value
    }

    // Refresh settings and public settings
    await Promise.all([
      systemSettingsStore.fetchSettings(),
      systemSettingsStore.fetchPublicSettings()
    ])
    
    initializeFormData()

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

// Get group color badge classes
const getIconBadgeClass = (group: string) => {
  const classes: Record<string, string> = {
    security: 'text-red-600 bg-red-50 dark:bg-red-950/20 border-red-200 dark:border-red-900/30',
    general: 'text-blue-600 bg-blue-50 dark:bg-blue-950/20 border-blue-200 dark:border-blue-900/30',
  }
  return classes[group] || 'text-slate-600 bg-slate-50 dark:bg-slate-800/30 border-slate-200 dark:border-slate-700/30'
}

// Get group description
const getGroupDescription = (group: string) => {
  const descriptions: Record<string, string> = {
    general: 'Customize your application name, branding logo, active theme, and primary colors.',
    upload: 'Set file attachment size thresholds and allowed extension restrictions.',
    notification: 'Manage globally enabled email alerts and in-app system notification behaviors.',
    system: 'Configure auto-archiving schedules, background cleanups, and trash retention rules.',
  }
  return descriptions[group] || 'Configure system preference settings.'
}

const getFallbackLogoIcon = () => {
  return ImageIcon
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
  <div class="p-0 md:p-6 space-y-6 text-slate-900 dark:text-slate-100">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">System Settings</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Configure system-wide settings and preferences</p>
      </div>
      <div class="flex items-center gap-2.5">
        <button
          @click="refreshSettings"
          :disabled="loading"
          class="px-4 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 flex items-center gap-2 disabled:opacity-50 text-sm font-semibold shadow-sm transition-colors"
        >
          <RefreshCw :size="16" :class="{ 'animate-spin': loading }" />
          Refresh
        </button>
        <button
          v-if="canUpdate(permissionKey) && hasChanges"
          @click="resetForm"
          class="px-4 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 text-sm font-semibold shadow-sm transition-colors"
        >
          Reset Changes
        </button>
        <button
          v-if="canUpdate(permissionKey)"
          @click="saveSettings"
          :disabled="saving || !hasChanges"
          class="px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-semibold shadow-sm transition-colors"
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
        class="bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900/30 rounded-xl p-4 flex items-center gap-3"
      >
        <CheckCircle :size="20" class="text-emerald-600 dark:text-emerald-400 flex-shrink-0" />
        <span class="text-emerald-800 dark:text-emerald-300 font-medium text-sm">Settings saved successfully!</span>
      </div>
    </Transition>

    <!-- Loading State -->
    <div v-if="loading && Object.keys(settings).length === 0" class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-12">
      <div class="flex flex-col items-center justify-center">
        <div class="animate-spin rounded-full h-9 w-9 border-2 border-slate-200 dark:border-slate-700 border-b-blue-600 dark:border-b-blue-400 mb-3"></div>
        <p class="text-slate-500 dark:text-slate-400 text-sm">Loading settings...</p>
      </div>
    </div>

    <!-- Settings Groups -->
    <div v-else class="space-y-6">
      <div
        v-for="(groupSettings, groupName) in filteredSettings"
        :key="groupName"
        class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden"
      >
        <!-- Group Header -->
        <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50">
          <div class="flex items-center gap-4">
            <div :class="getIconBadgeClass(groupName)" class="p-2.5 rounded-xl border flex items-center justify-center">
              <component :is="getGroupIcon(groupName)" :size="20" />
            </div>
            <div>
              <h2 class="text-base font-bold text-slate-900 dark:text-white capitalize">
                {{ groupName }} Settings
              </h2>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 leading-normal">
                {{ getGroupDescription(groupName) }}
              </p>
            </div>
          </div>
        </div>

        <!-- Settings List -->
        <div class="p-6 space-y-0">
          <div
            v-for="setting in groupSettings"
            :key="setting.key"
            class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 py-6 border-b border-slate-100 dark:border-slate-800/60 last:border-b-0 last:pb-0 first:pt-0"
          >
            <!-- Label & Description -->
            <div class="flex-1 max-w-2xl">
              <label :for="setting.key" class="block text-sm font-semibold text-slate-900 dark:text-slate-100 mb-1">
                {{ setting.label }}
              </label>
              <p v-if="setting.description" class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                {{ setting.description }}
              </p>
              
              <!-- Changed Indicator -->
              <div
                v-if="formData[setting.key] !== setting.value"
                class="mt-2 inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-amber-50 dark:bg-amber-950/20 text-[10px] font-semibold text-amber-700 dark:text-amber-400 border border-amber-200/30"
              >
                <Clock :size="10" />
                <span>Unsaved changes</span>
              </div>
            </div>

            <!-- Input Column -->
            <div class="w-full sm:w-80 flex sm:justify-end flex-shrink-0">
              <!-- Boolean (Toggle Switch) -->
              <div v-if="setting.type === 'boolean'" class="flex items-center">
                <label :for="setting.key" class="relative inline-flex items-center cursor-pointer select-none">
                  <input
                    :id="setting.key"
                    v-model="formData[setting.key]"
                    type="checkbox"
                    class="sr-only peer"
                    :disabled="!canUpdate(permissionKey)"
                  />
                  <div class="w-11 h-6 bg-slate-200 dark:bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 dark:after:border-slate-600 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
              </div>

              <!-- Integer / Float -->
              <div v-else-if="setting.type === 'integer' || setting.type === 'float'" class="flex items-center gap-2.5 w-full">
                <input
                  :id="setting.key"
                  v-model.number="formData[setting.key]"
                  type="number"
                  :step="setting.type === 'float' ? '0.01' : '1'"
                  class="w-full px-3.5 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-right font-medium text-sm"
                  :disabled="!canUpdate(permissionKey)"
                />
                <span v-if="setting.key === 'max_file_upload_size'" class="text-xs text-slate-500 dark:text-slate-400 font-bold whitespace-nowrap">
                  MB
                </span>
                <span v-else-if="setting.key.includes('days')" class="text-xs text-slate-500 dark:text-slate-400 font-bold whitespace-nowrap">
                  Days
                </span>
              </div>

              <!-- Custom Logo Upload Picker -->
              <div
                v-else-if="setting.type === 'string' && setting.key === 'app_logo'"
                class="flex items-center gap-3.5 w-full"
              >
                <div class="w-14 h-14 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 flex items-center justify-center p-1.5 overflow-hidden flex-shrink-0 shadow-inner">
                  <img
                    v-if="formData[setting.key] && !logoError"
                    :src="formData[setting.key]"
                    @error="logoError = true"
                    alt="App Logo"
                    class="max-w-full max-h-full object-contain"
                  />
                  <div v-else class="text-slate-400 dark:text-slate-600">
                    <component :is="getFallbackLogoIcon()" :size="22" />
                  </div>
                </div>
                <div class="flex-1">
                  <input
                    type="file"
                    accept="image/*"
                    class="hidden"
                    :id="'file-' + setting.key"
                    @change="handleLogoUpload($event, setting.key)"
                    :disabled="!canUpdate(permissionKey) || uploadingLogo"
                  />
                  <label
                    :for="'file-' + setting.key"
                    class="cursor-pointer px-3.5 py-2 bg-white dark:bg-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 inline-flex items-center gap-2 text-xs font-semibold shadow-sm transition-colors"
                    :class="{ 'opacity-50 pointer-events-none': uploadingLogo }"
                  >
                    <UploadCloud :size="14" v-if="!uploadingLogo" />
                    <span v-if="uploadingLogo">Uploading...</span>
                    <span v-else>Choose Logo</span>
                  </label>
                  <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1.5 leading-normal">PNG, JPG, WEBP, SVG (max 4MB)</p>
                </div>
              </div>

              <!-- Custom String Inputs (Theme Select) -->
              <select
                v-else-if="setting.type === 'string' && setting.key === 'app_theme'"
                :id="setting.key"
                v-model="formData[setting.key]"
                class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-semibold text-sm appearance-none cursor-pointer"
                :style="{ backgroundImage: `url(\&quot;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E\&quot;)`, backgroundPosition: 'right 0.85rem center', backgroundRepeat: 'no-repeat', backgroundSize: '1rem', paddingRight: '2.25rem' }"
                :disabled="!canUpdate(permissionKey)"
              >
                <option value="light">☀️ Light</option>
                <option value="dark">🌙 Dark</option>
                <option value="system">💻 System</option>
              </select>

              <!-- Custom Color Picker -->
              <div
                v-else-if="setting.type === 'string' && setting.key === 'app_primary_color'"
                class="flex items-center gap-3 w-full"
              >
                <div class="relative flex items-center justify-center flex-shrink-0">
                  <input
                    v-model="formData[setting.key]"
                    type="color"
                    class="absolute inset-0 w-8 h-8 opacity-0 cursor-pointer"
                    :disabled="!canUpdate(permissionKey)"
                  />
                  <div
                    class="w-8 h-8 rounded-full border border-slate-200 dark:border-slate-700 shadow-inner"
                    :style="{ backgroundColor: formData[setting.key] }"
                  ></div>
                </div>
                <input
                  v-model="formData[setting.key]"
                  type="text"
                  pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$"
                  class="w-full px-3.5 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 uppercase font-mono text-center font-bold text-sm tracking-wider"
                  :disabled="!canUpdate(permissionKey)"
                />
              </div>

              <!-- Generic String -->
              <input
                v-else-if="setting.type === 'string'"
                :id="setting.key"
                v-model="formData[setting.key]"
                type="text"
                class="w-full px-3.5 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-medium text-sm"
                :disabled="!canUpdate(permissionKey)"
              />

              <!-- Float -->
              <input
                v-else-if="setting.type === 'float'"
                :id="setting.key"
                v-model.number="formData[setting.key]"
                type="number"
                step="0.01"
                class="w-full px-3.5 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-medium text-sm text-right"
                :disabled="!canUpdate(permissionKey)"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- No Permission Message -->
    <div v-if="!canUpdate(permissionKey)" class="bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/30 rounded-xl p-4 flex items-center gap-3">
      <XCircle :size="20" class="text-amber-600 dark:text-amber-400 flex-shrink-0" />
      <span class="text-amber-800 dark:text-amber-300 text-sm font-medium">You do not have permission to modify system settings. Contact your administrator.</span>
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