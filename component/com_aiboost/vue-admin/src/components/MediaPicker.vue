<template>
  <div class="ab-media-picker">
    <!--
      Hidden DOM input that lives at the fieldId we pass to com_media / JCE.
      JCE's hand-back code does `document.getElementById(element).value = url` on
      the OPENER window before calling jInsertFieldValue. Without this node, JCE
      throws "Cannot set properties of null" and the URL never propagates back.
      We watch this input's value to mirror it into Vue state.
    -->
    <input :id="fieldId" ref="hiddenSink" type="hidden" :value="modelValue">
    <div class="ab-media-picker__input-row">
      <input
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
        type="url"
        class="ab-input"
        :placeholder="placeholder"
      >
      <button type="button" class="ab-btn ab-btn--secondary" @click="openMedia" title="Open Joomla Media Manager">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
          <path d="M9.828 3h3.982a2 2 0 0 1 1.992 2.181l-.637 7A2 2 0 0 1 13.174 14H2.825a2 2 0 0 1-1.991-1.819l-.637-7a2 2 0 0 1 .342-1.31L.5 3a2 2 0 0 1 2-2h3.672a2 2 0 0 1 1.414.586l.828.828A2 2 0 0 0 9.828 3zm-8.322.12C1.72 3.042 1.95 3 2.19 3h5.396l-.707-.707A1 1 0 0 0 6.172 2H2.5a1 1 0 0 0-1 .981l.006.139z"/>
        </svg>
        Browse Media
      </button>
    </div>
    <div v-if="recommendedSize && !compact" class="ab-help ab-media-picker__hint">{{ recommendedSize }}</div>
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
      @click="openMedia"
      @keydown.enter.prevent="openMedia"
      @keydown.space.prevent="openMedia"
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
          No image selected — paste URL above or click <strong>Browse Media</strong>
        </span>
      </div>
      <slot name="overlay"></slot>
    </div>
  </div>
</template>

<script>
let __abMpCounter = 0

export default {
  name: 'MediaPicker',
  props: {
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'https://example.com/image.png' },
    label: { type: String, default: '' },
    recommendedSize: { type: String, default: '' },
    compact: { type: Boolean, default: false },
  },
  emits: ['update:modelValue'],
  data() {
    return {
      previewError: false,
      bgMode: 'light',
      fieldId: 'ab-mp-' + (++__abMpCounter) + '-' + Math.random().toString(36).slice(2, 8),
    }
  },
  watch: {
    modelValue() { this.previewError = false },
  },
  mounted() {
    // Always-on sink watcher: JCE/3rd-party media browsers can hand back even
    // without a prior openMedia() click in the same instance (e.g. they remember
    // the fieldId from a previous popup). Patch the hidden input's value setter
    // up-front so any direct .value = url assignment is propagated to Vue state.
    this._installSinkWatcher()
  },
  beforeUnmount() {
    this._cleanupListeners()
    if (this._popup && !this._popup.closed) { try { this._popup.close() } catch (e) {} }
  },
  methods: {
    /**
     * Open Joomla Media Manager in a popup window.
     * Uses Joomla 4/5/6 selection-mode params (asset, fieldid, author) so the SPA
     * fires postMessage / jInsertFieldValue when the user picks a file.
     * We also inject our own Insert/Cancel toolbar into the popup as a guarantee
     * that the user always sees a clear action — Joomla SPA's own Select button
     * can be hidden depending on theme / version.
     */
    openMedia() {
      const fieldId = this.fieldId
      this._cleanupListeners()

      // Open popup centered, sized to give the media SPA room.
      // Params explained:
      //   option=com_media + view=media + tmpl=component → Joomla 4/5/6 SPA selection mode
      //   asset=com_content, author=1                    → permission context for media
      //   mediatypes=0                                   → Joomla SPA: "all media types"
      //   fieldid                                        → return-id used by jInsertFieldValue
      //
      //   JCE compatibility: when JCE editor is installed it intercepts com_media
      //   and routes to its own File Browser. JCE filters files by `mediatype`
      //   (singular!) and `filter` (extensions). Without these JCE shows folders
      //   but no files. We pass an explicit image-friendly set so users see images.
      const url = 'index.php?option=com_media&view=media&tmpl=component'
        + '&asset=com_content'
        + '&mediatypes=0'
        + '&fieldid=' + encodeURIComponent(fieldId)
        + '&author=1'
        + '&mediatype=images'
        + '&filter=' + encodeURIComponent('jpg,jpeg,png,gif,webp,svg,avif,bmp,ico')
      const w = 1100, h = 740
      const left = Math.max(0, Math.round((screen.width  - w) / 2))
      const top  = Math.max(0, Math.round((screen.height - h) / 2))
      this._popup = window.open(
        url, 'aiboost_media_picker_' + fieldId,
        'width=' + w + ',height=' + h
        + ',left=' + left + ',top=' + top
        + ',resizable=yes,scrollbars=yes,toolbar=no,menubar=no,location=no,status=no'
      )
      if (!this._popup) { return }
      this._popup.focus()

      // (0) Watch the hidden sink input. JCE (and some Joomla 3-era media
      // browsers) hand back by doing `document.getElementById(fieldId).value = url`
      // directly — that doesn't fire input/change events, so we hijack the input's
      // value setter to detect the assignment and propagate it into Vue state.
      this._installSinkWatcher()

      // (1) postMessage listener — Joomla 4/5/6 SPA fires these when user selects.
      // Source-window check: the message must come from OUR popup OR any window
      // nested inside it (J5/6 media SPA posts from inner iframes, not the top
      // popup window). v0.55.8 — loosened from strict equality to ancestor walk,
      // which is what was silently dropping every legit media-selected message
      // on Free + recent Joomla builds.
      const isFromOurPopup = (src) => {
        if (!this._popup || !src) return false
        try {
          let w = src
          for (let i = 0; i < 10 && w; i++) {
            if (w === this._popup) return true
            if (w.parent === w) return false
            w = w.parent
          }
        } catch (e) { /* cross-origin access blocked — fail closed */ return false }
        return false
      }
      this._messageHandler = (event) => {
        if (!event.data) return
        if (event.source && !isFromOurPopup(event.source)) return
        const d = event.data
        let url = ''
        // Joomla 4/5/6 media SPA — has shipped under several type names over
        // the years. Accept any of them; the payload key (`selectedData`,
        // `data`, `item`, or root-level `url`) also varies.
        const looksMedia =
             d.type        === 'mediaSelected'
          || d.type        === 'media-selected'
          || d.name        === 'media-select'
          || d.messageType === 'joomla:file:selected'
          || d.messageType === 'joomla:content-select'
        if (looksMedia) {
          const sel = d.selectedData || d.data || d.item || d
          url = sel.url || sel.path || sel.src || ''
        }
        if (!url && typeof d.url  === 'string') url = d.url
        if (!url && typeof d.path === 'string') url = d.path
        if (url) this._applyUrl(this._normalizeUrl(url))
      }
      window.addEventListener('message', this._messageHandler)

      // (2) Global jInsertFieldValue callback — required for legacy "Insert" button.
      // Ownership-safe: capture the previous global, install our own handler that
      // delegates to the previous one for IDs we don't own. On cleanup, only restore
      // if the current global is still our handler (another picker may have replaced it).
      const prevGlobal = window.jInsertFieldValue
      const myHandler = (value, returnedFieldId) => {
        if (!returnedFieldId || returnedFieldId === fieldId) {
          this._applyUrl(this._normalizeUrl(value))
        } else if (typeof prevGlobal === 'function') {
          prevGlobal(value, returnedFieldId)
        }
      }
      window.jInsertFieldValue = myHandler
      this._prevGlobalInsert = prevGlobal
      this._myHandler = myHandler

      // (3) Inject our own Insert / Cancel toolbar into the popup as a guarantee.
      // Wait for popup DOM ready, then inject. Same origin so DOM access is allowed.
      this._injectToolbar()

      // (4) Poll for popup closed so we clean up listeners
      this._pollTimer = window.setInterval(() => {
        if (!this._popup || this._popup.closed) this._cleanupListeners()
      }, 500)
    },

    /**
     * Watch the hidden sink input for direct .value assignments.
     * JCE's hand-back code does `document.getElementById(element).value = url`
     * on the opener — without an event. We replace the input's value setter
     * with one that calls our handler whenever a non-empty URL is written.
     */
    _installSinkWatcher() {
      const el = this.$refs.hiddenSink
      if (!el || el.__abSinkPatched) return
      const proto = window.HTMLInputElement && window.HTMLInputElement.prototype
      const desc = proto && Object.getOwnPropertyDescriptor(proto, 'value')
      if (!desc || !desc.set) return
      const origSet = desc.set
      const origGet = desc.get
      const onWrite = (v) => {
        const s = (v == null) ? '' : String(v)
        if (s && s !== this.modelValue) {
          this._applyUrl(this._normalizeUrl(s))
        }
      }
      try {
        Object.defineProperty(el, 'value', {
          configurable: true,
          get() { return origGet.call(this) },
          set(v) { origSet.call(this, v); onWrite(v) },
        })
        el.__abSinkPatched = true
        this._sinkEl = el
      } catch (e) {}
    },

    _injectToolbar() {
      const popup = this._popup
      if (!popup || popup.closed) return

      // Retry from the OPENER window, not the popup. popup.setTimeout is
      // lost whenever the popup navigates (e.g. com_media → com_jce redirect),
      // so timers scheduled inside it never fire. A 400ms interval in our own
      // window keeps trying across every navigation. Each attempt re-checks
      // whether our bar is still present (popup navigation wipes the DOM).
      if (this._injectTimer) {
        try { window.clearInterval(this._injectTimer) } catch (e) {}
        this._injectTimer = null
      }
      this._injectTimer = window.setInterval(() => {
        if (!this._popup || this._popup.closed) {
          window.clearInterval(this._injectTimer)
          this._injectTimer = null
          return
        }
        let doc
        try { doc = this._popup.document } catch (e) { return }
        if (!doc || !doc.body) return
        // Bar already present in current document — nothing to do this tick.
        if (doc.getElementById('aiboost-mp-bar')) return
        // Inject now. Wrap in try so a transient navigation doesn't break us.
        try { attempt(doc) } catch (e) {}
      }, 400)

      const attempt = (doc) => {
        if (doc.getElementById('aiboost-mp-bar')) return

        const style = doc.createElement('style')
        style.textContent = `
          #aiboost-mp-bar {
            position: fixed; left: 0; right: 0; bottom: 0; z-index: 999999;
            display: flex; gap: 10px; align-items: center;
            padding: 10px 16px; background: #1e2532; color: #fff;
            box-shadow: 0 -4px 14px rgba(0,0,0,.25);
            font: 14px/1.3 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
          }
          #aiboost-mp-bar button {
            padding: 8px 18px; border: 0; border-radius: 6px; cursor: pointer;
            font-weight: 600; font-size: 14px; font-family: inherit;
          }
          #aiboost-mp-insert { background: #4f8cff; color: #fff; }
          #aiboost-mp-insert:disabled { opacity: .45; cursor: not-allowed; }
          #aiboost-mp-cancel { background: transparent; color: #fff; border: 1px solid rgba(255,255,255,.3); }
          #aiboost-mp-status { margin-left: auto; opacity: .85; font-size: 13px;
            max-width: 60%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
          body { padding-bottom: 70px !important; }
        `
        doc.head.appendChild(style)

        const bar = doc.createElement('div')
        bar.id = 'aiboost-mp-bar'
        bar.innerHTML =
          '<button type="button" id="aiboost-mp-insert" disabled>Insert selected image</button>' +
          '<button type="button" id="aiboost-mp-cancel">Cancel</button>' +
          '<span id="aiboost-mp-status">Click an image first, then press Insert.</span>'
        doc.body.appendChild(bar)

        const insertBtn = doc.getElementById('aiboost-mp-insert')
        const cancelBtn = doc.getElementById('aiboost-mp-cancel')
        const statusEl  = doc.getElementById('aiboost-mp-status')

        // Track last selected image URL from clicks inside the media browser.
        let lastUrl = ''
        const setLast = (u) => {
          if (!u) return
          lastUrl = u
          insertBtn.disabled = false
          const norm = this._normalizeUrl(u)
          const short = norm.length > 70 ? '…' + norm.slice(-68) : norm
          statusEl.textContent = 'Selected: ' + short
        }

        // Click handler covers many media SPA implementations across J4-6 + JCE.
        // JCE file items use selectors: li.file, .upload-file, .item.file, [data-id].
        const FILE_SEL = '.media-browser-image, .media-browser-item, .media-browser-image-thumb,'
          + ' li.file, .file, .upload-file, .item.file,'
          + ' [data-src], [data-url], [data-id]'
        const extractUrl = (node) => {
          if (!node) return ''
          let u = node.getAttribute && (
            node.getAttribute('data-url') ||
            node.getAttribute('data-src') ||
            node.getAttribute('data-path') ||
            node.getAttribute('data-href') ||
            node.getAttribute('href')
          )
          if (!u) {
            const img = node.querySelector && node.querySelector('img')
            u = img && (img.getAttribute('data-src') || img.getAttribute('src'))
          }
          // JCE files often store path in data-id with the relative URL
          if (!u && node.getAttribute) {
            const dataId = node.getAttribute('data-id')
            if (dataId && /\.(jpe?g|png|gif|webp|svg|avif|bmp|ico)(\?|$)/i.test(dataId)) u = dataId
          }
          return u || ''
        }
        const onClick = (e) => {
          const node = e.target.closest(FILE_SEL)
          if (!node) return
          const u = extractUrl(node)
          if (u) setLast(u)
        }
        doc.addEventListener('click', onClick, true)

        // Listen for Joomla's own media selection events fired inside the popup doc
        const onMediaEvt = (evt) => {
          const det = evt.detail || {}
          const item = det.item || det.selectedData || det.data || det
          const u = item.url || item.path || item.src || ''
          if (u) setLast(u)
        }
        doc.addEventListener('onMediaFileSelected', onMediaEvt)

        // Confirm / cancel
        insertBtn.addEventListener('click', () => {
          if (!lastUrl) return
          this._applyUrl(this._normalizeUrl(lastUrl))
        })
        cancelBtn.addEventListener('click', () => {
          try { this._popup && this._popup.close() } catch (err) {}
          this._cleanupListeners()
        })

        // Double-click a file = instant insert
        doc.addEventListener('dblclick', (e) => {
          const node = e.target.closest(FILE_SEL)
          if (!node) return
          const u = extractUrl(node)
          if (u) this._applyUrl(this._normalizeUrl(u))
        }, true)
      }
      // attempt() is invoked by the setInterval above; no immediate call needed
    },

    /**
     * Normalize a URL returned by Joomla media manager.
     * Joomla 4+ paths look like "local-images:/folder/file.jpg" — the public
     * URL is usually "/images/folder/file.jpg". If we already get an absolute
     * URL, return as-is. If we get a thumbnail path, strip the thumb prefix.
     */
    _normalizeUrl(raw) {
      if (!raw) return ''
      let u = String(raw).trim()
      if (/^https?:\/\//i.test(u) || u.startsWith('//')) return u

      // Strip thumbnail wrapper if media SPA gave us a /media/com_media/v3/thumbnails/... URL
      const thumbIdx = u.indexOf('/media/com_media/')
      if (thumbIdx >= 0) {
        // Use as-is if it's already a working asset URL
        return u
      }

      // Adapter scheme like "local-images:/foo.jpg" → "images/foo.jpg"
      const m = u.match(/^([a-z0-9_-]+):\/?(.*)$/i)
      if (m) {
        const adapter = m[1]            // e.g. local-images
        let rest = m[2].replace(/^\/+/, '')
        // "local-images" → "images" folder. Generic fallback: strip "local-" prefix.
        const folder = adapter.replace(/^local-/, '')
        if (rest.startsWith(folder + '/')) {
          u = '/' + rest
        } else {
          u = '/' + folder + '/' + rest
        }
      }
      if (!u.startsWith('/') && !/^https?:/i.test(u)) u = '/' + u
      // Convert to absolute if popup origin is known
      try {
        if (this._popup && this._popup.location && this._popup.location.origin) {
          return this._popup.location.origin + u
        }
      } catch (e) {}
      return u
    },

    _applyUrl(url) {
      if (!url) return
      this.$emit('update:modelValue', url)
      this.previewError = false
      try { this._popup && this._popup.close() } catch (e) {}
      this._cleanupListeners()
    },

    _cleanupListeners() {
      if (this._messageHandler) {
        window.removeEventListener('message', this._messageHandler)
        this._messageHandler = null
      }
      // Restore the global jInsertFieldValue only if it still points to OUR handler;
      // otherwise another picker (or other code) has taken ownership and we must
      // not clobber it.
      if (this._myHandler !== undefined) {
        if (window.jInsertFieldValue === this._myHandler) {
          if (typeof this._prevGlobalInsert === 'function') {
            window.jInsertFieldValue = this._prevGlobalInsert
          } else {
            try { delete window.jInsertFieldValue } catch (e) { window.jInsertFieldValue = undefined }
          }
        }
        this._myHandler = undefined
        this._prevGlobalInsert = undefined
      }
      if (this._pollTimer) {
        window.clearInterval(this._pollTimer)
        this._pollTimer = null
      }
      if (this._injectTimer) {
        try { window.clearInterval(this._injectTimer) } catch (e) {}
        this._injectTimer = null
      }
    },

    onPreviewError() {
      this.previewError = true
    },

    clearImage() {
      this.$emit('update:modelValue', '')
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
.ab-media-picker__input-row .ab-btn {
  flex: 0 0 auto;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  white-space: nowrap;
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
