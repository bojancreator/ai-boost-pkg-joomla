<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/debug.php (key=staging_mode)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.staging_mode" data-ab-field="staging_mode"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-staging-mode" />
      <label class="ab-check__label" for="ab-gen-staging-mode">Staging mode</label>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedStagingMode',
  props: { s: { type: Object, required: true } },
}
</script>
