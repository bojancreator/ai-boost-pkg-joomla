/**
 * useColorScheme — reactive Joomla admin theme tracker.
 *
 * Watches the `data-bs-theme` attribute on <html> and <body> (Atum sets either
 * one depending on Joomla version) and exposes a reactive ref that is 'dark'
 * or 'light'. Components can react to theme changes without polling.
 */

import { ref, onMounted, onBeforeUnmount } from 'vue'

function readScheme() {
  if (typeof document === 'undefined') return 'light'
  const fromHtml = document.documentElement.getAttribute('data-bs-theme')
  if (fromHtml) return fromHtml
  const fromBody = document.body && document.body.getAttribute('data-bs-theme')
  if (fromBody) return fromBody
  // Fallback to OS preference
  if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
    return 'dark'
  }
  return 'light'
}

export function useColorScheme() {
  const scheme = ref(readScheme())
  let observer = null

  function update() {
    const next = readScheme()
    if (next !== scheme.value) scheme.value = next
  }

  onMounted(() => {
    observer = new MutationObserver(update)
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme', 'class'] })
    if (document.body) {
      observer.observe(document.body, { attributes: true, attributeFilter: ['data-bs-theme', 'class'] })
    }
    update()
  })

  onBeforeUnmount(() => {
    if (observer) observer.disconnect()
  })

  return { scheme }
}
