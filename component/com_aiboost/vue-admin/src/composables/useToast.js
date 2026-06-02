/**
 * AI Boost — Global Toast store (Task #513).
 *
 * Tiny pub/sub used by ToastStack.vue. Any component can grab the
 * helper via:
 *
 *   import { useToast } from './composables/useToast.js'
 *   const toast = useToast()
 *   toast.error('Save failed')
 *   toast.success('Settings saved')
 *
 * The store lives outside the Vue app instance so non-component code
 * (api.js wrapper, errorReporter.js) can call it without injecting
 * anything. ToastStack subscribes to the reactive `toasts` array and
 * renders them in a fixed-position stack.
 */

import { reactive } from 'vue'

const state = reactive({
  toasts: [],   // [{ id, severity, message, timer }]
})

let nextId = 1

const DEFAULT_TIMEOUT_MS = 6000

function push (severity, message, opts = {}) {
  if (!message) return null
  const text = String(message).slice(0, 400)
  const id = nextId++
  const timeoutMs = Number(opts.timeoutMs ?? DEFAULT_TIMEOUT_MS)
  const toast = { id, severity, message: text, createdAt: Date.now() }
  state.toasts.push(toast)
  if (timeoutMs > 0) {
    setTimeout(() => dismiss(id), timeoutMs)
  }
  // Hard cap: never keep more than 6 visible — older ones drop.
  while (state.toasts.length > 6) state.toasts.shift()
  return id
}

function dismiss (id) {
  const idx = state.toasts.findIndex(t => t.id === id)
  if (idx >= 0) state.toasts.splice(idx, 1)
}

function clear () {
  state.toasts.splice(0, state.toasts.length)
}

const api = {
  toasts: state.toasts,
  dismiss,
  clear,
  info:    (m, o) => push('info', m, o),
  success: (m, o) => push('success', m, o),
  warning: (m, o) => push('warning', m, o),
  error:   (m, o) => push('error', m, o),
}

export function useToast () {
  return api
}

// Convenience: also expose the singleton so non-Vue modules (api.js,
// errorReporter.js) can import the helpers directly without wrapping.
export const toast = api
