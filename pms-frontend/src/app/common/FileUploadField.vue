<template>
    <div class="space-y-2">
        <label class="block text-sm font-medium text-gray-700">
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </label>
        
        <!-- Upload Area -->
        <div 
            v-if="!file"
            class="relative border-2 border-dashed rounded-lg p-6 transition-colors"
            :class="isDragging ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 hover:border-indigo-400 bg-gray-50'"
            @drop.prevent="handleDrop"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
        >
            <div class="flex flex-col items-center space-y-3">
                <Upload class="w-10 h-10 text-gray-400" />
                <div class="text-center">
                    <label class="cursor-pointer">
                        <span class="text-sm font-medium text-indigo-600 hover:text-indigo-700">
                            Click to upload
                        </span>
                        <span class="text-sm text-gray-500"> or drag and drop</span>
                        <input
                            ref="fileInputRef"
                            type="file"
                            accept=".pdf"
                            class="hidden"
                            @change="handleFileSelect"
                        />
                    </label>
                    <p class="text-xs text-gray-500 mt-1">PDF only, max 5MB</p>
                </div>
            </div>
        </div>

        <!-- Uploaded File Display -->
        <div 
            v-else
            class="flex items-center justify-between p-4 bg-green-50 border border-green-200 rounded-lg"
        >
            <div class="flex items-center space-x-3 flex-1 min-w-0">
                <div class="flex-shrink-0 w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <FileText class="w-5 h-5 text-green-600" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-green-900 truncate">
                        {{ file.name }}
                    </p>
                    <p class="text-xs text-green-600">
                        {{ formatFileSize(file.size) }}
                    </p>
                </div>
            </div>
            <button
                type="button"
                @click="handleRemove"
                class="flex-shrink-0 ml-4 p-2 text-red-500 hover:text-red-700 hover:bg-red-100 rounded-lg transition-colors"
                title="Remove file"
            >
                <X class="w-5 h-5" />
            </button>
        </div>

        <!-- Error Message -->
        <p v-if="errorMessage" class="text-sm text-red-600 flex items-center">
            <AlertCircle class="w-4 h-4 mr-1" />
            {{ errorMessage }}
        </p>
    </div>
</template>

<script setup lang="ts">
    import { ref } from 'vue'
    import { Upload, FileText, X, AlertCircle } from 'lucide-vue-next'

    interface Props {
        label: string
        required?: boolean
        file: File | null
    }

    const props = withDefaults(defineProps<Props>(), {
        required: false
    })

    const emit = defineEmits<{
        upload: [file: File]
        remove: []
    }>()

    const isDragging = ref(false)
    const errorMessage = ref('')
    const fileInputRef = ref<HTMLInputElement | null>(null)

    const validateFile = (file: File): boolean => {
        errorMessage.value = ''

        // Check file type
        if (file.type !== 'application/pdf') {
            errorMessage.value = 'Please upload a PDF file only'
            return false
        }

        // Check file size (5MB max)
        const maxSize = 5 * 1024 * 1024
        if (file.size > maxSize) {
            errorMessage.value = 'File size must be less than 5MB'
            return false
        }

        return true
    }

    const handleFileSelect = (event: Event) => {
        const target = event.target as HTMLInputElement
        const file = target.files?.[0]
        
        if (file && validateFile(file)) {
            emit('upload', file)
        }
        
        // Reset input so the same file can be selected again
        if (target) {
            target.value = ''
        }
    }

    const handleDrop = (event: DragEvent) => {
        isDragging.value = false
        
        const file = event.dataTransfer?.files[0]
        if (file && validateFile(file)) {
            emit('upload', file)
        }
    }

    const handleRemove = () => {
        errorMessage.value = ''
        emit('remove')
    }

    const formatFileSize = (bytes: number): string => {
        if (bytes === 0) return '0 Bytes'
        
        const k = 1024
        const sizes = ['Bytes', 'KB', 'MB']
        const i = Math.floor(Math.log(bytes) / Math.log(k))
        
        return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i]
    }
</script>