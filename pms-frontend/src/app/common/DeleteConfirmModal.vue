<template>
  <div
    v-if="show"
    class="fixed inset-0 z-drawer"
    aria-labelledby="modal-title"
    role="dialog"
    aria-modal="true"
  >
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
    
    <div class="fixed inset-0 z-10 overflow-y-auto">
      <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-zink-600 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
          <!-- Icon -->
          <div class="flex items-center justify-center w-12 h-12 mx-auto mt-6 bg-red-100 rounded-full dark:bg-red-500/20">
            <AlertTriangle class="w-6 h-6 text-red-600 dark:text-red-500" />
          </div>
          
          <!-- Header -->
          <div class="mt-3 text-center sm:mt-5">
            <h3 class="text-lg font-semibold leading-6 text-slate-900 dark:text-zink-50">
              {{ title }}
            </h3>
            <div class="mt-2">
              <p class="text-sm text-slate-500 dark:text-zink-200">
                {{ message }}
              </p>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-center gap-2 px-6 py-4 mt-5 border-t border-slate-200 dark:border-zink-500">
            <button
              @click="$emit('close')"
              type="button"
              class="text-slate-500 btn bg-slate-200 border-slate-200 hover:text-slate-600 hover:bg-slate-300 hover:border-slate-300 focus:text-slate-600 focus:bg-slate-300 focus:border-slate-300 focus:ring focus:ring-slate-100 active:text-slate-600 active:bg-slate-300 active:border-slate-300 dark:bg-zink-600 dark:hover:bg-zink-500 dark:border-zink-600 dark:hover:border-zink-500 dark:text-zink-200 dark:ring-zink-400/50"
            >
              Cancel
            </button>
            <button
              @click="$emit('confirm')"
              type="button"
              class="text-white btn bg-red-500 border-red-500 hover:text-white hover:bg-red-600 hover:border-red-600 focus:text-white focus:bg-red-600 focus:border-red-600 focus:ring focus:ring-red-100 active:text-white active:bg-red-600 active:border-red-600 active:ring active:ring-red-100 dark:ring-red-400/20"
            >
              Delete
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
    import { AlertTriangle } from 'lucide-vue-next'

    // Props
    interface Props {
    show: boolean
    title: string
    message: string
    }

    defineProps<Props>()

    // Emits
    defineEmits<{
    close: []
    confirm: []
    }>()
</script>