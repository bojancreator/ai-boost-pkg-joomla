<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/aeo.php (key=indexnow_auto_submit)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.indexnow_auto_submit" data-ab-field="indexnow_auto_submit"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-indexnow-auto-submit" />
      <label class="ab-check__label" for="ab-gen-indexnow-auto-submit">Auto-submit URLs on publish/update</label>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedIndexnowAutoSubmit',
  props: { s: { type: Object, required: true } },
}
</script>
