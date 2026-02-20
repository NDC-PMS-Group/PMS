import { ref, onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useSystemSettingsStore } from '@/store/systemSettings'
import { storeToRefs } from 'pinia'

export function useSessionTimeout() {
  const router = useRouter()
  const systemSettingsStore = useSystemSettingsStore()
  const { sessionTimeoutMinutes, sessionTimeoutEnabled } = storeToRefs(systemSettingsStore)
  
  const timeoutId = ref<number | null>(null)
  const warningId = ref<number | null>(null)
  const showWarning = ref(false)
  const remainingSeconds = ref(60)
  const countdownInterval = ref<number | null>(null)

  const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click']

  const clearTimers = () => {
    if (timeoutId.value) {
      clearTimeout(timeoutId.value)
      timeoutId.value = null
    }
    if (warningId.value) {
      clearTimeout(warningId.value)
      warningId.value = null
    }
    if (countdownInterval.value) {
      clearInterval(countdownInterval.value)
      countdownInterval.value = null
    }
    showWarning.value = false
  }

  const logout = async () => {
    clearTimers()
    // Clear auth token
    localStorage.removeItem('token')
    sessionStorage.removeItem('token')
    
    // Redirect to login
    router.push('/login?reason=timeout')
  }

  const showTimeoutWarning = () => {
    showWarning.value = true
    remainingSeconds.value = 60 // 1 minute warning

    // Start countdown
    countdownInterval.value = window.setInterval(() => {
      remainingSeconds.value--
      if (remainingSeconds.value <= 0) {
        logout()
      }
    }, 1000)

    // Set actual logout timer
    timeoutId.value = window.setTimeout(() => {
      logout()
    }, 60000) // 1 minute
  }

  const resetTimer = () => {
    if (!sessionTimeoutEnabled.value) return

    clearTimers()

    // Show warning 1 minute before timeout
    const warningTime = (sessionTimeoutMinutes.value - 1) * 60 * 1000
    const timeoutTime = sessionTimeoutMinutes.value * 60 * 1000

    if (warningTime > 0) {
      warningId.value = window.setTimeout(() => {
        showTimeoutWarning()
      }, warningTime)
    } else {
      // If timeout is 1 minute or less, logout immediately after timeout
      timeoutId.value = window.setTimeout(() => {
        logout()
      }, timeoutTime)
    }
  }

  const extendSession = () => {
    resetTimer()
  }

  const setupListeners = () => {
    events.forEach(event => {
      window.addEventListener(event, resetTimer)
    })
  }

  const removeListeners = () => {
    events.forEach(event => {
      window.removeEventListener(event, resetTimer)
    })
  }

  const start = () => {
    if (!sessionTimeoutEnabled.value) return
    
    setupListeners()
    resetTimer()
  }

  const stop = () => {
    removeListeners()
    clearTimers()
  }

  // Watch for settings changes
  watch([sessionTimeoutMinutes, sessionTimeoutEnabled], () => {
    if (sessionTimeoutEnabled.value) {
      stop()
      start()
    } else {
      stop()
    }
  })

  onMounted(() => {
    start()
  })

  onUnmounted(() => {
    stop()
  })

  return {
    showWarning,
    remainingSeconds,
    extendSession,
    logout,
    start,
    stop,
  }
}