<template>
  <div class="ab-media-picker">
    <!--
      Native Joomla media field (joomla-field-media web component), rendered
      server-side and mounted here but VISUALLY HIDDEN. It is the functional
      engine only: its "Select" button opens the real Joomla media manager
      (folder tree + thumbnails) and routes to JCE when installed. All visible
      UI is our own preview box below, so there is no duplicated control.
    -->
    <div v-if="hasNativeField" ref="nativeHost" class="ab-media-picker__native-hidden" aria-hidden="true"></div>

    <!-- Single visual control: preview box (click to open the media manager) -->
    <div
      v-if="!compact"
      class="ab-media-picker__preview"
      :class="[
        { 'has-image': modelValue && !previewError },
        'ab-media-picker__preview--bg-' + bgMode
      ]"
      role="button"
      tabindex="0"
      :title="modelValue ? 'Click to change image' : 'Click to choose an image'"
      @click="triggerSelect"
      @keydown.enter.prevent="triggerSelect"
      @keydown.space.prevent="triggerSelect"
    >
      <img v-if="modelValue && !previewError" :src="modelValue" :alt="label || 'Preview'" @error="onPreviewError" @load="previewError = false">
      <div v-if="modelValue && !previewError" class="ab-media-picker__bg-toggle" role="group" aria-label="Preview background">
        <button
          type="button"
          :class="['ab-media-picker__bg-btn', { 'is-active': bgMode === 'light' }]"
          @click.stop="bgMode = 'light'"
          title="Light background"
          aria-label="Light background"
        >
          <svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
            <path d="M8 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM8 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 0zm0 13a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 13zm8-5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2a.5.5 0 0 1 .5.5zM3 8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2A.5.5 0 0 1 3 8zm10.657-5.657a.5.5 0 0 1 0 .707l-1.414 1.415a.5.5 0 1 1-.707-.708l1.414-1.414a.5.5 0 0 1 .707 0zm-9.193 9.193a.5.5 0 0 1 0 .707L3.05 13.657a.5.5 0 0 1-.707-.707l1.414-1.414a.5.5 0 0 1 .707 0zm9.193 2.121a.5.5 0 0 1-.707 0l-1.414-1.414a.5.5 0 0 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .707zM4.464 4.465a.5.5 0 0 1-.707 0L2.343 3.05a.5.5 0 1 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .708z"/>
          </svg>
        </button>
        <button
          type="button"
          :class="['ab-media-picker__bg-btn', { 'is-active': bgMode === 'dark' }]"
          @click.stop="bgMode = 'dark'"
          title="Dark background"
          aria-label="Dark background"
        >
          <svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
            <path d="M6 .278a.77.77 0 0 1 .08.858 7.2 7.2 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277.527 0 1.04-.055 1.533-.16a.78.78 0 0 1 .81.316.73.73 0 0 1-.031.893A8.35 8.35 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.75.75 0 0 1 6 .278z"/>
          </svg>
        </button>
      </div>
      <button
        v-if="modelValue"
        type="button"
        class="ab-media-picker__clear"
        :title="'Remove image' + (label ? ' (' + label + ')' : '')"
        aria-label="Remove image"
        @click.stop="clearImage"
      >
        <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
          <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
        </svg>
      </button>
      <div v-else class="ab-media-picker__placeholder">
        <svg width="36" height="36" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
          <path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/>
          <path d="M2.002 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2h-12zm12 1a1 1 0 0 1 1 1v6.5l-3.777-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12V3a1 1 0 0 1 1-1h12z"/>
        </svg>
        <span class="ab-media-picker__placeholder-text">
          No image selected — click here or <strong>Select</strong> to open the media manager
        </span>
      </div>
      <slot name="overlay"></slot>
    </div>

    <div v-if="recommendedSize && !compact" class="ab-help ab-media-picker__hint">{{ recommendedSize }}</div>

    <!-- URL field (paste an external URL) + Select button -->
    <div class="ab-media-picker__input-row">
      <input
        :value="modelValue"
        @input="onUrlInput($event.target.value)"
        type="url"
        class="ab-input"
        :placeholder="placeholder"
      >
      <button
        type="button"
        class="ab-btn ab-btn--secondary ab-media-picker__select-btn"
        @click="triggerSelect"
        :disabled="!hasNativeField"
        :title="hasNativeField ? 'Open the Joomla media manager' : 'Media manager unavailable — paste a URL'"
      >
        <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
          <path d="M9.828 3h3.982a2 2 0 0 1 1.992 2.181l-.637 7A2 2 0 0 1 13.174 14H2.825a2 2 0 0 1-1.991-1.819l-.637-7a2 2 0 0 1 .342-1.31L.5 3a2 2 0 0 1 2-2h3.672a2 2 0 0 1 1.414.586l.828.828A2 2 0 0 0 9.828 3zm-8.322.12C1.72 3.042 1.95 3 2.19 3h5.396l-.707-.707A1 1 0 0 0 6.172 2H2.5a1 1 0 0 0-1 .981l.006.139z"/>
        </svg>
        Select
      </button>
    </div>
  </div>
</template>

<script>
export default {
  name: 'MediaPicker',
  props: {
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'https://example.com/image.png' },
    label: { type: String, default: '' },
    recommendedSize: { type: String, default: '' },
    compact: { type: Boolean, default: false },
    // Settings key (e.g. 'org_logo') used to look up the server-rendered native
    // Joomla media field in window.aiBoostBootstrap.mediaFields.
    fieldKey: { type: String, default: '' },
  },
  emits: ['update:modelValue'],
  data() {
    return {
      previewError: false,
      bgMode: 'light',
      hasNativeField: false,
    }
  },
  watch: {
    modelValue(v) {
      this.previewError = false
      // Keep the hidden native input in sync (e.g. URL typed, cleared, or set
      // from elsewhere) so the field's own state matches Vue state.
      if (this._nativeInput && this._nativeInput.value !== (v || '')) {
        this._nativeInput.value = v || ''
      }
    },
  },
  mounted() {
    this.mountNativeField()
  },
  beforeUnmount() {
    this.stopNativeSync()
    if (this._nativeInput && this._onNativeChange) {
      this._nativeInput.removeEventListener('change', this._onNativeChange)
      this._nativeInput.removeEventListener('input', this._onNativeChange)
    }
  },
  methods: {
    /**
     * Mount the server-rendered native Joomla media field for this fieldKey.
     * Setting innerHTML lets the browser upgrade the <joomla-field-media> custom
     * element (the field.media assets are queued by App\HtmlView). The element
     * owns the Select button + modal + JCE routing; we mirror its input value
     * into Vue state so the SPA save flow stays the source of truth.
     */
    mountNativeField() {
      const map = (typeof window !== 'undefined' && window.aiBoostBootstrap && window.aiBoostBootstrap.mediaFields) || {}
      const markup = this.fieldKey ? map[this.fieldKey] : ''
      if (!markup) {
        this.hasNativeField = false
        return
      }
      this.hasNativeField = true
      this.$nextTick(() => {
        const host = this.$refs.nativeHost
        if (!host) return
        host.innerHTML = markup
        const input = host.querySelector('.field-media-input') || host.querySelector('input[type="text"], input[type="url"]')
        if (!input) return
        this._nativeInput = input
        if (this.modelValue && input.value !== this.modelValue) {
          input.value = this.modelValue
        }
        // Fast path: some media managers DO fire change/input on write-back.
        this._onNativeChange = () => this.syncFromNative()
        input.addEventListener('change', this._onNativeChange)
        input.addEventListener('input', this._onNativeChange)
      })
    },

    /**
     * Make a media-manager value usable as an <img> src + Schema/OG path.
     * JCE / the media manager frequently returns a ROOT path WITHOUT the leading
     * slash (e.g. "images/foo.png"); <img src> then resolves against the admin
     * URL (/administrator/…) and 404s, which sets previewError and hides BOTH the
     * preview image and the dark/light background toggle. Prepend the slash so it
     * resolves from the site root. Absolute / protocol-relative / data|blob URIs
     * are left untouched.
     */
    normalizeMediaUrl(v) {
      v = (v || '').trim()
      if (!v) return ''
      if (/^[a-z][a-z0-9+.-]*:/i.test(v) || v.startsWith('//')) return v
      if (v.startsWith('/')) return v
      return '/' + v.replace(/^\.?\//, '')
    },

    /** Pull the current native input value into Vue state if it changed. */
    syncFromNative() {
      if (!this._nativeInput) return
      const v = this.normalizeMediaUrl(this._nativeInput.value)
      if (v !== (this.modelValue || '')) {
        this.$emit('update:modelValue', v)
        this.previewError = false
      }
    },

    /**
     * Open the native/JCE media manager by clicking the hidden field's Select
     * button, then start a bounded poll. JCE's browser writes the picked URL
     * back to the input WITHOUT always firing a DOM event, so polling is what
     * reliably surfaces the selection into our preview.
     */
    triggerSelect() {
      const host = this.$refs.nativeHost
      const btn = host && (host.querySelector('.button-select') || host.querySelector('button'))
      if (!btn) return
      btn.click()
      this.startNativeSync()
    },

    startNativeSync() {
      this.stopNativeSync()
      let ticks = 0
      this._syncTimer = window.setInterval(() => {
        ticks++
        if (!this._nativeInput) { this.stopNativeSync(); return }
        const v = this.normalizeMediaUrl(this._nativeInput.value)
        if (v !== (this.modelValue || '')) {
          this.$emit('update:modelValue', v)
          this.previewError = false
          this.stopNativeSync() // got the write-back
          return
        }
        if (ticks > 600) this.stopNativeSync() // ~3 min safety stop (300ms × 600)
      }, 300)
    },

    stopNativeSync() {
      if (this._syncTimer) {
        window.clearInterval(this._syncTimer)
        this._syncTimer = null
      }
    },

    /** Manual URL paste: emit (the watcher mirrors it into the native input). */
    onUrlInput(v) {
      this.$emit('update:modelValue', v)
    },

    onPreviewError() {
      this.previewError = true
    },

    clearImage() {
      this.$emit('update:modelValue', '')
      if (this._nativeInput) this._nativeInput.value = ''
      this.previewError = false
    },
  },
}
</script>

<style>
.ab-media-picker {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
/* Native joomla-field-media host — present + interactable but visually hidden
   (NOT display:none, so its Select button can be clicked programmatically and
   the media-manager dialog still opens). */
.ab-media-picker__native-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  clip-path: inset(50%);
  white-space: nowrap;
  border: 0;
}
.ab-media-picker__input-row {
  display: flex;
  gap: 8px;
  align-items: stretch;
  flex-wrap: wrap;
}
.ab-media-picker__input-row .ab-input {
  flex: 1 1 240px;
  min-width: 0;
}
.ab-media-picker__select-btn {
  flex: 0 0 auto;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  white-space: nowrap;
}
.ab-media-picker__select-btn:disabled {
  opacity: .5;
  cursor: not-allowed;
}
.ab-media-picker__hint {
  margin-top: -2px;
}
.ab-media-picker__preview {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 110px;
  padding: 12px;
  border: 2px dashed var(--border-color, #dee2e6);
  border-radius: 8px;
  background: var(--secondary-bg, #f8f9fa);
  transition: border-color .2s ease, background-color .2s ease;
  cursor: pointer;
}
.ab-media-picker__preview:focus-visible {
  outline: 2px solid var(--ab-primary, #4f46e5);
  outline-offset: 2px;
}
.ab-media-picker__preview.has-image {
  border-style: solid;
  border-color: var(--border-color, #dee2e6);
  background: var(--body-bg, #fff);
}
.ab-media-picker__preview--bg-light.has-image {
  background: #ffffff !important;
  border-color: #ced4da !important;
}
.ab-media-picker__preview--bg-dark.has-image {
  background: #1f2937 !important;
  border-color: #374151 !important;
}
.ab-media-picker__preview img {
  max-height: 140px;
  max-width: 100%;
  border-radius: 4px;
  object-fit: contain;
}
.ab-media-picker__bg-toggle {
  position: absolute;
  top: 6px;
  left: 6px;
  display: inline-flex;
  gap: 2px;
  padding: 2px;
  background: rgba(0, 0, 0, .35);
  border-radius: 999px;
  backdrop-filter: blur(4px);
}
.ab-media-picker__bg-btn {
  width: 24px;
  height: 24px;
  padding: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  color: rgba(255, 255, 255, .7);
  border: 0;
  border-radius: 999px;
  cursor: pointer;
  transition: background .12s ease, color .12s ease;
}
.ab-media-picker__bg-btn:hover {
  color: #fff;
  background: rgba(255, 255, 255, .12);
}
.ab-media-picker__bg-btn.is-active {
  background: rgba(255, 255, 255, .92);
  color: #1f2937;
}
.ab-media-picker__bg-btn:focus-visible {
  outline: 2px solid #fff;
  outline-offset: 1px;
}
.ab-media-picker__clear {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 28px;
  height: 28px;
  padding: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgba(220, 53, 69, .92);
  color: #fff;
  border: 0;
  border-radius: 50%;
  cursor: pointer;
  box-shadow: 0 2px 6px rgba(0,0,0,.25);
  transition: background .15s ease, transform .15s ease;
}
.ab-media-picker__clear:hover {
  background: rgba(200, 35, 51, 1);
  transform: scale(1.08);
}
.ab-media-picker__clear:focus-visible {
  outline: 2px solid #fff;
  outline-offset: 2px;
}
.ab-media-picker__placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  color: var(--secondary-color, #6c757d);
  font-size: .82rem;
  text-align: center;
}
.ab-media-picker__placeholder-text { opacity: .85; }
</style>
