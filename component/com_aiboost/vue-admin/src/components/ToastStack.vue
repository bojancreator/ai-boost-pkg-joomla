<template>
  <div class="ab-toast-stack" role="region" aria-live="polite" aria-label="Notifications">
    <transition-group name="ab-toast" tag="div">
      <div v-for="t in toasts" :key="t.id"
           :class="['ab-toast', 'ab-toast--' + t.severity]"
           role="alert">
        <span :class="['ab-toast__icon', iconClass(t.severity)]" aria-hidden="true"></span>
        <div class="ab-toast__body">{{ t.message }}</div>
        <button type="button" class="ab-toast__close"
                aria-label="Dismiss" @click="dismiss(t.id)">×</button>
      </div>
    </transition-group>
  </div>
</template>

<script>
import { useToast } from '../composables/useToast.js'

export default {
  name: 'ToastStack',

  setup () {
    const { toasts, dismiss } = useToast()
    function iconClass (sev) {
      switch (sev) {
        case 'error':   return 'icon-warning'
        case 'warning': return 'icon-warning-circle'
        case 'success': return 'icon-checkmark-circle'
        default:        return 'icon-info-circle'
      }
    }
    return { toasts, dismiss, iconClass }
  },
}
</script>

<style>
.ab-toast-stack {
  position: fixed;
  top: 1rem;
  right: 1rem;
  z-index: 10500;
  display: flex;
  flex-direction: column;
  gap: .5rem;
  max-width: min(420px, calc(100vw - 2rem));
  pointer-events: none;
}

.ab-toast {
  pointer-events: auto;
  display: flex;
  align-items: flex-start;
  gap: .55rem;
  padding: .6rem .65rem .6rem .75rem;
  background: #fff;
  color: #1f2933;
  border: 1px solid #d0d7e2;
  border-left-width: 4px;
  border-radius: 6px;
  box-shadow: 0 4px 14px rgba(0, 0, 0, .12);
  font-size: .875rem;
  line-height: 1.35;
}

.ab-toast__icon { margin-top: .15rem; flex: 0 0 auto; }
.ab-toast__body { flex: 1 1 auto; word-break: break-word; white-space: pre-wrap; }
.ab-toast__close {
  flex: 0 0 auto;
  background: transparent;
  border: 0;
  font-size: 1.25rem;
  line-height: 1;
  padding: 0 .15rem;
  cursor: pointer;
  color: inherit;
  opacity: .55;
}
.ab-toast__close:hover { opacity: 1; }

.ab-toast--error   { border-left-color: #d24a4a; background: #fdf3f3; }
.ab-toast--warning { border-left-color: #f4a73a; background: #fef7ec; }
.ab-toast--success { border-left-color: #2e9e6c; background: #effaf3; }
.ab-toast--info    { border-left-color: #2db7e6; background: #eef9fd; }

/* Dark mode hint — Joomla Atum sets data-bs-theme="dark" on <html>. */
html[data-bs-theme="dark"] .ab-toast {
  background: #1f2933;
  color: #eaeef3;
  border-color: #2c3744;
}
html[data-bs-theme="dark"] .ab-toast--error   { background: #3a1f1f; }
html[data-bs-theme="dark"] .ab-toast--warning { background: #3a2e1a; }
html[data-bs-theme="dark"] .ab-toast--success { background: #1c3128; }
html[data-bs-theme="dark"] .ab-toast--info    { background: #1a2e3a; }

.ab-toast-enter-from { opacity: 0; transform: translateX(16px); }
.ab-toast-enter-active,
.ab-toast-leave-active { transition: opacity .18s ease, transform .18s ease; }
.ab-toast-leave-to   { opacity: 0; transform: translateX(16px); }
</style>
