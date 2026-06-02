<template>
  <div class="ab-page-import">
    <h2 class="ab-h2 mb-3">Import / Export Settings</h2>
    <p class="text-muted">
      Move your AI Boost configuration between sites. Export a portable JSON
      snapshot, then import it on another install.
    </p>

    <div class="row g-3">
      <div class="col-md-6">
        <div class="ab-card h-100">
          <div class="ab-card-body">
            <h3 class="ab-h3 mb-3">Export current settings</h3>
            <p class="text-muted small mb-3">
              Generates a fresh JSON snapshot of every AI Boost option on this site.
            </p>
            <a :href="exportUrl" class="ab-btn ab-btn--primary">
              <span class="icon-download" aria-hidden="true"></span>
              Download settings export (.json)
            </a>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="ab-card h-100">
          <div class="ab-card-body">
            <h3 class="ab-h3 mb-3">Import settings from a file</h3>
            <p class="text-muted small mb-3">
              Upload a JSON export from AI Boost. Imported values are merged over
              your current settings.
            </p>

            <label class="ab-label" for="ab-import-file">Export file (.json)</label>
            <input
              id="ab-import-file"
              ref="fileInput"
              type="file"
              accept="application/json,.json"
              class="ab-input mb-3"
              :disabled="importing"
              @change="onFileChange"
            />

            <button
              class="ab-btn ab-btn--primary"
              :disabled="!selectedFile || importing"
              @click="doImport"
            >
              <span class="icon-upload" aria-hidden="true"></span>
              {{ importing ? 'Importing…' : 'Import settings' }}
            </button>

            <div
              v-if="resultMessage"
              :class="['ab-alert', 'mt-3', resultOk ? 'ab-alert--success' : 'ab-alert--danger']"
              role="status"
            >
              {{ resultMessage }}
            </div>

            <div class="ab-help mt-3">
              For your safety, license keys and per-site identity values are never
              imported — they always come from this site's own verified license.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref } from 'vue'
import { postWithCsrf } from './api.js'

export default {
  name: 'ImportPage',
  setup() {
    const exportUrl = 'index.php?option=com_aiboost&task=settings.export'
    const importUrl = 'index.php?option=com_aiboost&task=import.upload'

    const fileInput     = ref(null)
    const selectedFile  = ref(null)
    const importing     = ref(false)
    const resultMessage = ref('')
    const resultOk      = ref(false)

    function onFileChange(e) {
      const files = e.target && e.target.files
      selectedFile.value = files && files[0] ? files[0] : null
      resultMessage.value = ''
    }

    async function doImport() {
      if (!selectedFile.value || importing.value) return

      importing.value = true
      resultMessage.value = ''

      try {
        const fd = new FormData()
        fd.append('ab_import_file', selectedFile.value)

        const res = await postWithCsrf(importUrl, fd)
        resultOk.value = !!(res && res.success)
        resultMessage.value =
          (res && res.message) || (resultOk.value ? 'Settings imported.' : 'Import failed.')

        if (resultOk.value) {
          // Reload so the SPA bootstrap picks up the merged settings.
          setTimeout(() => { window.location.reload() }, 1800)
        }
      } catch (e) {
        resultOk.value = false
        resultMessage.value =
          'Import failed: ' + (e && e.message ? e.message : 'unknown error')
      } finally {
        importing.value = false
      }
    }

    return {
      exportUrl,
      fileInput,
      selectedFile,
      importing,
      resultMessage,
      resultOk,
      onFileChange,
      doImport,
    }
  },
}
</script>
