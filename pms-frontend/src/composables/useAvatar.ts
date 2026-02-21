/**
 * useAvatar composable
 *
 * Centralized avatar URL resolution. Accepts a user object, a raw URL string,
 * or null/undefined. Returns a resolved full URL or null if no photo exists.
 *
 * Usage examples:
 *   const { avatarUrl, initials } = useAvatar(user)
 *   const { avatarUrl } = useAvatar({ profile_photo_url: 'avatars/1/photo.jpg', first_name: 'Juan', last_name: 'Cruz' })
 *   const { avatarUrl } = useAvatar('avatars/1/photo.jpg')   // raw path only, no initials
 */

import { computed, toValue, type MaybeRefOrGetter } from 'vue'

const BASE_URL = import.meta.env.VITE_APP_BASE_URL as string

// ─── Types ────────────────────────────────────────────────────────────────────

export interface AvatarSource {
  profile_photo_url?: string | null
  first_name?: string | null
  last_name?: string | null
  full_name?: string | null
  username?: string | null
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Builds a full public URL from a raw storage path or an already-full URL.
 * Returns null if the input is empty.
 */
function resolveUrl(raw: string | null | undefined): string | null {
  if (!raw) return null
  if (raw.startsWith('http://') || raw.startsWith('https://')) return raw
  // Strip leading slash if present to avoid double slashes
  const clean = raw.startsWith('/') ? raw.slice(1) : raw
  return `${BASE_URL}/storage/${clean}`
}

/**
 * Derives initials from a user-like object.
 * Priority: first+last → full_name → username → '?'
 */
function resolveInitials(source: AvatarSource): string {
  if (source.first_name && source.last_name) {
    return (source.first_name[0] + source.last_name[0]).toUpperCase()
  }
  if (source.full_name) {
    const parts = source.full_name.trim().split(/\s+/)
    if (parts.length >= 2) {
      return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
    }
    return parts[0][0].toUpperCase()
  }
  if (source.username) {
    return source.username[0].toUpperCase()
  }
  return '?'
}

// ─── Composable ───────────────────────────────────────────────────────────────

export function useAvatar(source: MaybeRefOrGetter<AvatarSource | string | null | undefined>) {
  /**
   * Resolved full public URL to the avatar image, or null if no photo.
   */
  const avatarUrl = computed((): string | null => {
    const val = toValue(source)
    if (!val) return null
    if (typeof val === 'string') return resolveUrl(val)
    return resolveUrl(val.profile_photo_url)
  })

  /**
   * Initials derived from the user's name. Empty string if source is a raw string.
   */
  const initials = computed((): string => {
    const val = toValue(source)
    if (!val || typeof val === 'string') return '?'
    return resolveInitials(val)
  })

  /**
   * True if a valid avatar URL exists.
   */
  const hasAvatar = computed((): boolean => avatarUrl.value !== null)

  return { avatarUrl, initials, hasAvatar }
}