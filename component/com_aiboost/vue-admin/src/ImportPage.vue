<template>
  <div class="ab-page-import">

    <PageHeader title="Import / Export Settings" />

    <div class="ab-two">
      <div class="ab-card">
        <div class="ab-card__header">Export current settings</div>
        <div class="ab-card__body">
          <p class="ab-help" style="margin:0 0 .9rem">Generates a fresh JSON snapshot of every AI Boost option on this site.</p>
          <a :href="exportUrl" class="ab-btn ab-btn--primary ab-btn--sm">Download settings export (.json)</a>
        </div>
      </div>

      <div class="ab-card">
        <div class="ab-card__header">Import settings from a file</div>
        <div class="ab-card__body">
          <p class="ab-help" style="margin:0 0 .9rem">Upload a JSON export from AI Boost. Imported values are merged over your current settings.</p>
          <input
            ref="fileInput"
            type="file"
            accept="application/json,.json"
            class="ab-visually-hidden"
            :disabled="importing"
            @change="onFileChange"
          />
          <div class="ab-row">
            <button type="button" class="ab-btn ab-btn--ghost ab-btn--sm" :disabled="importing" @click="fileInput && fileInput.click()">Choose file</button>
            <span class="ab-help">{{ selectedFile ? selectedFile.name : 'No file chosen' }}</span>
          </div>
          <div style="margin-top:.8rem">
            <button
              type="button"
              class="ab-btn ab-btn--secondary ab-btn--sm"
              :disabled="!selectedFile || importing"
              @click="doImport"
            >{{ importing ? 'Importing…' : 'Import settings' }}</button>
          </div>
          <div
            v-if="resultMessage"
            :class="['ab-alert', resultOk ? 'ab-alert--success' : 'ab-alert--danger']"
            role="status"
            style="margin-top:.8rem"
          >{{ resultMessage }}</div>
          <div class="ab-help" style="margin-top:.8rem">
            For your safety, license keys and per-site identity values are never
            imported — they always come from this site's own verified license.
          </div>
        </div>
      </div>
    </div>

    <div class="ab-section ab-section--danger mt-3">
      <div class="ab-section__head">
        <AbIcon name="warning" />
        Danger Zone — Uninstall
      </div>
      <div class="ab-section__body">
        <p class="ab-help" style="margin:0 0 .7rem">
          Uninstalling removes the extension files but <strong>keeps your data</strong> — settings, every
          translation, your redirect list and the 404 log are preserved, and your licence &amp; Pro activation
          unlock again as soon as you reinstall. Generated root files (the <code>robots.txt</code> managed block,
          <code>llms.txt</code>, sitemap) are cleaned up. Export your settings above before any major change.
        </p>
        <div class="ab-row">
          <a href="https://github.com/bojancreator/aiboost-joomla/blob/main/docs/uninstall-guide.md"
             target="_blank" rel="noopener"
             class="ab-btn ab-btn--ghost ab-btn--sm">Read the uninstall guide →</a>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import { ref } from 'vue'
import { postWithCsrf, getCsrfTokenName } from './api.js'
import AbIcon from './components/AbIcon.vue'
import PageHeader from './components/PageHeader.vue'

export default {
  name: 'ImportPage',
  components: { AbIcon, PageHeader },
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

<style scoped>
.ab-two { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 680px) { .ab-two { grid-template-columns: 1fr; } }
.ab-visually-hidden { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); border: 0; }
</style>
