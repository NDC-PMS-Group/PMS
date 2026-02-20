<script setup lang="ts">
import { computed } from 'vue'
import { Clock, AlertTriangle } from 'lucide-vue-next'

const props = defineProps<{
  show: boolean
  remainingSeconds: number
}>()

const emit = defineEmits<{
  extend: []
  logout: []
}>()

const formattedTime = computed(() => {
  const minutes = Math.floor(props.remainingSeconds / 60)
  const seconds = props.remainingSeconds % 60
  return `${minutes}:${seconds.toString().padStart(2, '0')}`
})

const progressPercentage = computed(() => {
  return (props.remainingSeconds / 60) * 100
})
</script>

<template>
  <Transition name="modal">
    <div
      v-if="show"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[10000] p-4"
      @click.self="emit('extend')"
    >
      <div class="bg-white rounded-lg max-w-md w-full p-6 shadow-2xl animate-bounce-in">
        <!-- Icon -->
        <div class="flex justify-center mb-4">
          <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center">
            <AlertTriangle :size="32" class="text-yellow-600" />
          </div>
        </div>

        <!-- Title -->
        <h2 class="text-2xl font-bold text-gray-900 text-center mb-2">
          Session Timeout Warning
        </h2>

        <!-- Message -->
        <p class="text-gray-600 text-center mb-6">
          Your session is about to expire due to inactivity. You will be logged out automatically.
        </p>

        <!-- Countdown -->
        <div class="mb-6">
          <div class="flex items-center justify-center gap-2 mb-3">
            <Clock :size="24" class="text-yellow-600" />
            <span class="text-4xl font-bold text-gray-900">
              {{ formattedTime }}
            </span>
          </div>

          <!-- Progress Bar -->
          <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
            <div
              class="h-full transition-all duration-1000 ease-linear"
              :class="remainingSeconds <= 10 ? 'bg-red-500' : 'bg-yellow-500'"
              :style="{ width: `${progressPercentage}%` }"
            ></div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex gap-3">
          <button
            @click="emit('logout')"
            class="flex-1 px-4 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-medium transition-colors"
          >
            Logout Now
          </button>
          <button
            @click="emit('extend')"
            class="flex-1 px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium transition-colors"
          >
            Stay Logged In
          </button>
        </div>

        <!-- Note -->
        <p class="text-xs text-gray-500 text-center mt-4">
          Click anywhere or move your mouse to stay logged in
        </p>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.animate-bounce-in {
  animation: bounceIn 0.5s ease-out;
}

@keyframes bounceIn {
  0% {
    opacity: 0;
    transform: scale(0.3);
  }
  50% {
    opacity: 1;
    transform: scale(1.05);
  }
  70% {
    transform: scale(0.9);
  }
  100% {
    transform: scale(1);
  }
}
</style>