<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/general.php (key=integration_falang_enabled)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.integration_falang_enabled" data-ab-field="integration_falang_enabled"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-integration-falang-enabled" />
      <label class="ab-check__label" for="ab-gen-integration-falang-enabled">Falang integration</label>
      <div class="ab-help">When on, AI Boost adds Falang-aware hreflang, Schema.org and OpenGraph output for translated content. Turning it off stops that output but keeps all your Falang settings.</div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedIntegrationFalangEnabled',
  props: { s: { type: Object, required: true } },
}
</script>
