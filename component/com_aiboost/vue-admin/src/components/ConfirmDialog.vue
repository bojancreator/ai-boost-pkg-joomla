<template>
  <div class="ab-confirm-overlay" @click.self="$emit('cancel')">
    <div class="ab-confirm" role="dialog" aria-modal="true" :aria-label="title">
      <div class="ab-confirm__title">
        <span class="icon-warning me-2" aria-hidden="true"></span>{{ title }}
      </div>
      <div class="ab-confirm__body">{{ message }}</div>
      <div class="ab-confirm__actions">
        <button type="button" class="ab-btn ab-btn--ghost ab-btn--sm" @click="$emit('cancel')">
          {{ cancelLabel }}
        </button>
        <button type="button" class="ab-btn ab-btn--primary ab-btn--sm" ref="confirmBtn" @click="$emit('confirm')">
          {{ confirmLabel }}
        </button>
      </div>
    </div>
  </div>
</template>

<script>
/**
 * AI Boost — ConfirmDialog: a small themed replacement for the browser's
 * native confirm() dialog. Controlled by the parent via v-if; emits
 * `confirm` / `cancel`. Esc and backdrop click both cancel.
 */
export default {
  name: 'ConfirmDialog',
  props: {
    title:        { type: String, default: 'Are you sure?' },
    message:      { type: String, default: '' },
    confirmLabel: { type: String, default: 'Confirm' },
    cancelLabel:  { type: String, default: 'Cancel' },
  },
  emits: ['confirm', 'cancel'],
  mounted() {
    this._onKey = (e) => { if (e.key === 'Escape') this.$emit('cancel') }
    window.addEventListener('keydown', this._onKey)
    this.$nextTick(() => { try { this.$refs.confirmBtn && this.$refs.confirmBtn.focus() } catch (e) { /* noop */ } })
  },
  beforeUnmount() { window.removeEventListener('keydown', this._onKey) },
}
</script>

<style scoped>
.ab-confirm-overlay {
  position: fixed;
  inset: 0;
  z-index: 1080;
  background: rgba(0, 0, 0, .45);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}
.ab-confirm {
  background: var(--ab-bg-elev, #fff);
  color: var(--ab-text, #1a1a1a);
  border: 1px solid var(--ab-border, #e5e7eb);
  border-radius: 10px;
  box-shadow: 0 12px 48px rgba(0, 0, 0, .3);
  max-width: 420px;
  width: 100%;
  padding: 20px 22px;
}
.ab-confirm__title { font-size: 1.05rem; font-weight: 700; margin-bottom: 10px; }
.ab-confirm__body {
  font-size: .9rem;
  color: var(--ab-text-muted, #555);
  margin-bottom: 18px;
  line-height: 1.55;
}
.ab-confirm__actions { display: flex; justify-content: flex-end; gap: 8px; }
</style>
