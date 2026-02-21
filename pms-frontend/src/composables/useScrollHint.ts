import { ref, onUnmounted } from 'vue'

export function useScrollHint(elementId: string) {
  const showScrollHint = ref(false)
  let hideTimeout: ReturnType<typeof setTimeout> | null = null
  let el: HTMLElement | null = null

  const handleWheel = (e: WheelEvent) => {
    if (e.ctrlKey || e.metaKey) {
      showScrollHint.value = false
      if (hideTimeout) clearTimeout(hideTimeout)
    } else {
      showScrollHint.value = true
      if (hideTimeout) clearTimeout(hideTimeout)
      hideTimeout = setTimeout(() => { showScrollHint.value = false }, 1200)
    }
  }

  const handleMouseLeave = () => {
    showScrollHint.value = false
    if (hideTimeout) clearTimeout(hideTimeout)
  }

  const attachScrollHint = () => {
    el = document.getElementById(elementId)
    if (!el) return
    el.addEventListener('wheel', handleWheel, { passive: true })
    el.addEventListener('mouseleave', handleMouseLeave)
  }

  onUnmounted(() => {
    if (!el) return
    el.removeEventListener('wheel', handleWheel)
    el.removeEventListener('mouseleave', handleMouseLeave)
    if (hideTimeout) clearTimeout(hideTimeout)
  })

  return { showScrollHint, attachScrollHint }
}