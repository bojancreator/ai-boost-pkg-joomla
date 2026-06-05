<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/social.php (key=pixel_consent_mode)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-pixel-consent-mode">GDPR Consent Mode</label>
      <select v-model="s.pixel_consent_mode" data-ab-field="pixel_consent_mode"
              class="ab-select" id="ab-gen-pixel-consent-mode">
        <option value="none">None</option>
      </select>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedPixelConsentMode',
  props: { s: { type: Object, required: true } },
}
</script>
