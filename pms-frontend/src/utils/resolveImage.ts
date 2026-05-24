const BASE_URL = import.meta.env.VITE_APP_BASE_URL as string

export function resolveImageUrl(raw: string | null | undefined): string | null {
  if (!raw) return null
  if (raw.startsWith('http://') || raw.startsWith('https://')) return raw
  const clean = raw.startsWith('/') ? raw.slice(1) : raw
  return `${BASE_URL}/storage/${clean}`
}