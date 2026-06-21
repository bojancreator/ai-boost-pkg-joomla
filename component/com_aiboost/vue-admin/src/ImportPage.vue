<template>
  <div class="ab-page-import">

    <div class="ab-page-intro">
      <h2 class="ab-page-title">Import / Export</h2>
      <p class="ab-page-desc">Move your AI Boost configuration between sites. Export a portable JSON snapshot, then import it on another install.</p>
    </div>

    <div class="row g-3">
      <div class="col-md-6">
        <div class="ab-section h-100">
          <div class="ab-section__head">Export settings</div>
          <div class="ab-section__body">
            <p class="ab-help mb-3">Generates a fresh JSON snapshot of every AI Boost option on this site.</p>
            <a :href="exportUrl" class="ab-btn ab-btn--primary">
              <AbIcon name="upload" style="transform:scaleY(-1)" />
              Download settings export (.json)
            </a>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="ab-section h-100">
          <div class="ab-section__head">Import from a file</div>
          <div class="ab-section__body">
            <p class="ab-help mb-3">Upload a JSON export from AI Boost. Imported values are merged over your current settings.</p>

            <div class="ab-field">
              <label class="ab-label" for="ab-import-file">Export file (.json)</label>
              <input
                id="ab-import-file"
                ref="fileInput"
                type="file"
                accept="application/json,.json"
                class="ab-input"
                :disabled="importing"
                @change="onFileChange"
              />
            </div>

            <button
              class="ab-btn ab-btn--primary"
              :disabled="!selectedFile || importing"
              @click="doImport"
            >
              <AbIcon name="upload" />
              {{ importing ? 'Importing…' : 'Import settings' }}
            </button>

            <div
              v-if="resultMessage"
              :class="['ab-alert', 'mt-3', resultOk ? 'ab-alert--success' : 'ab-alert--danger']"
              role="status"
            >{{ resultMessage }}</div>

            <div class="ab-help mt-3">
              For your safety, license keys and per-site identity values are never
              imported — they always come from this site's own verified license.
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="ab-section mt-3" style="border-left:3px solid var(--ab-danger)">
      <div class="ab-section__head" style="color:var(--ab-danger)">
        <AbIcon name="warning" />
        Danger Zone — Uninstall
      </div>
      <div class="ab-section__body">
        <p class="mb-2">
          Uninstalling the AI Boost package from
          <strong>System → Manage → Extensions</strong> removes the extension files
          but <strong>keeps your data</strong>. Uninstalling:
        </p>
        <ul class="mb-3 small">
          <li><strong>Preserves</strong> all settings, every per-language translation, your redirect list, and the 404 log</li>
          <li><strong>Preserves</strong> your licence and Pro activation — Pro features unlock again as soon as you reinstall</li>
          <li>Removes the extension files and cleans up generated root files (<code>robots.txt</code> managed block, <code>llms.txt</code>, sitemap files)</li>
          <li>Clears developer override keys</li>
        </ul>
        <p class="ab-help mb-3">
          Reinstalling restores full function with your data intact. Before any major
          change, download a settings export above — it is a single JSON file containing every option, redirect, and translation.
        </p>
        <a href="https://github.com/bojancreator/aiboost-joomla/blob/main/docs/uninstall-guide.md"
           target="_blank" rel="noopener"
           class="ab-btn ab-btn--ghost ab-btn--sm">
          Read the uninstall guide →
        </a>
      </div>
    </div>

  </div>
</template>

<script>
import { ref } from 'vue'
import { postWithCsrf, getCsrfTokenName } from './api.js'
import AbIcon from './components/AbIcon.vue'

export default {
  name: 'ImportPage',
  components: { AbIcon },
  setup() {
    const exportUrl = 'index.php?option=com_aiboost&task=settings.export&' + getCsrfTokenName() + '=1'
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
        resultMessage.value = (res && res.message) || (resultOk.value ? 'Settings imported.' : 'Import failed.')
        if (resultOk.value) setTimeout(() => { window.location.reload() }, 1800)
      } catch (e) {
        resultOk.value = false
        resultMessage.value = 'Import failed: ' + (e && e.message ? e.message : 'unknown error')
      } finally {
        importing.value = false
      }
    }

    return { exportUrl, fileInput, selectedFile, importing, resultMessage, resultOk, onFileChange, doImport }
  },
}
</script>
