<template>
  <div
    :style="sizeStyle"
    :class="[
      'relative inline-flex items-center justify-center rounded-full overflow-hidden shrink-0 select-none',
      !hasAvatar ? bgClass : '',
    ]"
  >
    <!-- Photo -->
    <img
      v-if="hasAvatar"
      :src="avatarUrl!"
      :alt="alt || initials"
      class="w-full h-full object-cover"
      @error="onImgError"
    />

    <!-- Initials fallback -->
    <span
      v-else
      :style="{ fontSize: fontSize }"
      class="font-semibold leading-none text-white"
    >
      {{ initials }}
    </span>

    <!-- Online indicator -->
    <span
      v-if="online !== undefined"
      :class="[
        'absolute bottom-0 right-0 rounded-full border-2 border-white dark:border-gray-800',
        online ? 'bg-green-500' : 'bg-gray-400',
      ]"
      :style="indicatorSize"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useAvatar, type AvatarSource } from '@/composables/useAvatar'

// ─── Props ────────────────────────────────────────────────────────────────────

const props = withDefaults(
  defineProps<{
    /** User object or raw URL string */
    user?: AvatarSource | string | null
    /** Pixel size of the avatar (width & height). Default: 32 */
    size?: number
    /** Alt text for the image */
    alt?: string
    /** Show green/grey online indicator dot */
    online?: boolean
    /** Override the auto-generated initials */
    initialsOverride?: string
  }>(),
  {
    user: null,
    size: 32,
    online: undefined,
  }
)

// ─── Avatar resolution ────────────────────────────────────────────────────────

const imgError = ref(false)

const { avatarUrl: resolvedUrl, initials: resolvedInitials, hasAvatar: resolvedHasAvatar } = useAvatar(
  () => props.user
)

// If img fails to load, fall back to initials
const hasAvatar = computed(() => resolvedHasAvatar.value && !imgError.value)
const avatarUrl = computed(() => resolvedUrl.value)
const initials = computed(() => props.initialsOverride || resolvedInitials.value)

function onImgError() {
  imgError.value = true
}

// ─── Sizing ───────────────────────────────────────────────────────────────────

const sizeStyle = computed(() => ({
  width: `${props.size}px`,
  height: `${props.size}px`,
}))

const fontSize = computed(() => {
  const s = props.size
  if (s <= 24) return '9px'
  if (s <= 32) return '11px'
  if (s <= 40) return '13px'
  if (s <= 56) return '16px'
  if (s <= 80) return '22px'
  return '28px'
})

const indicatorSize = computed(() => {
  const d = Math.max(8, Math.round(props.size * 0.22))
  return { width: `${d}px`, height: `${d}px` }
})

// ─── Initials background ──────────────────────────────────────────────────────

/**
 * Deterministic colour from the initials string so the same user
 * always gets the same colour across the app.
 */
const bgClass = computed(() => {
  const str = initials.value || '?'
  const colors = [
    'bg-blue-500',
    'bg-indigo-500',
    'bg-purple-500',
    'bg-pink-500',
    'bg-rose-500',
    'bg-orange-500',
    'bg-amber-500',
    'bg-teal-500',
    'bg-cyan-500',
    'bg-green-600',
  ]
  // Simple hash from char codes
  const hash = str.split('').reduce((acc, c) => acc + c.charCodeAt(0), 0)
  return colors[hash % colors.length]
})
</script>