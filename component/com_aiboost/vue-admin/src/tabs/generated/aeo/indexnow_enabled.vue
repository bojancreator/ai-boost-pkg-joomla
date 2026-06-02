<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/aeo.php (key=indexnow_enabled)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <ProGate gate-key="indexnow_enabled" mode="field">
      <div class="ab-check ab-toggle mb-3">
        <input v-model="s.indexnow_enabled" data-ab-field="indexnow_enabled"
               true-value="1" false-value="0"
               type="checkbox" class="ab-toggle__input" id="ab-gen-indexnow-enabled" />
        <label class="ab-check__label" for="ab-gen-indexnow-enabled">Enable IndexNow</label>
      </div>
    </ProGate>
  </div>
</template>

<script>
export default {
  name: 'GeneratedIndexnowEnabled',
  props: { s: { type: Object, required: true } },
}
</script>
