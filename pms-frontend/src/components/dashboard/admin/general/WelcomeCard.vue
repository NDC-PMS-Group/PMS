<script lang="ts" setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import { Sun, Cloud, Moon, Clock } from 'lucide-vue-next'
import { useAuthStore } from '@/store/auth'
import { useLayoutStore } from '@/store/layout'
import { SITE_MODE } from '@/app/const'

// ── Stores ──────────────────────────────────────────────────────────────
const authStore   = useAuthStore()
const layoutStore = useLayoutStore()
const isDark      = computed(() => layoutStore.mode === SITE_MODE.DARK)

// ── User display name ───────────────────────────────────────────────────
const displayName = computed(() => {
  const u = authStore.user
  if (!u) return 'there'
  if (u.first_name) return u.first_name
  if (u.full_name)  return u.full_name.split(' ')[0]
  if (u.email)      return u.email.split('@')[0]
  return 'there'
})

const userRole = computed(() => authStore.user?.role?.name || '')

// ── PST clock — ticks every second using Asia/Manila timezone ───────────
const now = ref(new Date())
let tickInterval: number | undefined

onMounted(() => {
  tickInterval = window.setInterval(() => {
    now.value = new Date()
  }, 1000)
})
onBeforeUnmount(() => {
  if (tickInterval !== undefined) clearInterval(tickInterval)
})

const phTimeFormatter = new Intl.DateTimeFormat('en-PH', {
  timeZone: 'Asia/Manila',
  hour:     '2-digit',
  minute:   '2-digit',
  second:   '2-digit',
  hour12:   true,
})
const phDateFormatter = new Intl.DateTimeFormat('en-PH', {
  timeZone: 'Asia/Manila',
  weekday:  'long',
  year:     'numeric',
  month:    'long',
  day:      'numeric',
})

// Greeting + icon by hour-of-day in PH time.
const phHour = computed(() => {
  // Pull just the hour in Manila — Intl gives us "07" / "23" in 24h mode.
  const h = new Intl.DateTimeFormat('en-PH', {
    timeZone: 'Asia/Manila',
    hour:     '2-digit',
    hour12:   false,
  }).format(now.value)
  return parseInt(h, 10)
})

const greeting = computed(() => {
  const h = phHour.value
  if (h >= 5  && h < 12) return 'Good morning'
  if (h >= 12 && h < 18) return 'Good afternoon'
  return 'Good evening'
})

const greetingIcon = computed(() => {
  const h = phHour.value
  if (h >= 5  && h < 12) return Sun
  if (h >= 12 && h < 18) return Cloud
  return Moon
})

const timeString = computed(() => phTimeFormatter.format(now.value))
const dateString = computed(() => phDateFormatter.format(now.value))
</script>

<template>
  <div
    class="welcome-card relative overflow-hidden rounded-xl col-span-12
           flex flex-col md:flex-row md:items-center justify-between
           gap-4 px-6 py-5"
    :class="isDark ? 'is-dark' : 'is-light'"
  >
    <!-- Subtle dotted overlay -->
    <div class="overlay-dots absolute inset-0 pointer-events-none" />

    <!-- ── Left: greeting ─────────────────────────────────────────────── -->
    <div class="relative z-10 flex items-center gap-3 min-w-0">
      <div
        class="flex-shrink-0 w-11 h-11 rounded-xl flex items-center justify-center"
        :class="isDark ? 'bg-white/10' : 'bg-white/40'"
      >
        <component
          :is="greetingIcon"
          class="h-5 w-5"
          :class="isDark ? 'text-amber-300' : 'text-amber-500'"
        />
      </div>
      <div class="min-w-0">
        <h2
          class="text-lg font-bold leading-tight truncate"
          :class="isDark ? 'text-white' : 'text-gray-800'"
        >
          {{ greeting }}, {{ displayName }}
        </h2>
        <p
          class="text-xs mt-0.5"
          :class="isDark ? 'text-white/60' : 'text-gray-600'"
        >
          <template v-if="userRole">
            Signed in as
            <span class="font-semibold capitalize">{{ userRole }}</span>
          </template>
          <template v-else>
            Welcome back to your dashboard.
          </template>
        </p>
      </div>
    </div>

    <!-- ── Right: live PST clock ─────────────────────────────────────── -->
    <div
      class="relative z-10 flex items-center gap-3 px-4 py-2 rounded-xl
             flex-shrink-0"
      :class="isDark ? 'bg-white/10 border border-white/15' : 'bg-white/40 border border-white/50'"
    >
      <Clock
        class="h-5 w-5 flex-shrink-0"
        :class="isDark ? 'text-blue-300' : 'text-blue-600'"
      />
      <div class="text-right">
        <div
          class="font-mono font-bold text-base tabular-nums leading-tight"
          :class="isDark ? 'text-white' : 'text-gray-800'"
        >
          {{ timeString }}
          <span class="ml-1 text-[10px] font-semibold tracking-wide opacity-70">PST</span>
        </div>
        <div
          class="text-[11px] leading-tight mt-0.5"
          :class="isDark ? 'text-white/60' : 'text-gray-600'"
        >
          {{ dateString }}
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.welcome-card.is-light {
  background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 50%, #93c5fd 100%);
}
.welcome-card.is-dark {
  background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 60%, #0f4c81 100%);
}
.overlay-dots {
  background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/svg%3E");
}
</style>
