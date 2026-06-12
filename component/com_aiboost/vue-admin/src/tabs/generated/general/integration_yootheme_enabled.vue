<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/general.php (key=integration_yootheme_enabled)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.integration_yootheme_enabled" data-ab-field="integration_yootheme_enabled"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-integration-yootheme-enabled" />
      <label class="ab-check__label" for="ab-gen-integration-yootheme-enabled">YOOtheme Pro integration</label>
      <div class="ab-help">When on, AI Boost reads YOOtheme Pro page content for FAQ/gallery Schema.org and per-page meta. Turning it off stops that output but keeps all your YOOtheme settings.</div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedIntegrationYoothemeEnabled',
  props: { s: { type: Object, required: true } },
}
</script>
