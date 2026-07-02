const configuredBaseUrl = import.meta.env.VITE_APP_BASE_URL as string | undefined
const localHosts = ['localhost', '127.0.0.1', '::1']

function getRuntimeBaseUrl(): string {
  if (typeof window === 'undefined') {
    return configuredBaseUrl || ''
  }

  if (!configuredBaseUrl) {
    return window.location.origin
  }

  try {
    const configured = new URL(configuredBaseUrl)
    const isLocalConfiguredBase = localHosts.includes(configured.hostname)
    const isLocalRuntime = localHosts.includes(window.location.hostname)

    return isLocalConfiguredBase && !isLocalRuntime
      ? window.location.origin
      : configured.origin
  } catch {
    return window.location.origin
  }
}

export function resolveImageUrl(raw: string | null | undefined): string | null {
  if (!raw) return null

  const baseUrl = getRuntimeBaseUrl()
  const clean = raw.replace(/^\/+/, '')

  if (clean === 'assets/images/logo.png') {
    return null
  }

  if (raw.startsWith('http://') || raw.startsWith('https://')) {
    try {
      const url = new URL(raw)
      if (url.pathname.replace(/^\/+/, '') === 'assets/images/logo.png') {
        return null
      }
      return localHosts.includes(url.hostname) && baseUrl
        ? `${baseUrl}${url.pathname}${url.search}${url.hash}`
        : raw
    } catch {
      return raw
    }
  }

  const storagePath = clean.startsWith('storage/')
    ? clean
    : `storage/${clean}`

  return baseUrl ? `${baseUrl}/${storagePath}` : `/${storagePath}`
}
