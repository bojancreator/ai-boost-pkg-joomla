<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/schema.php (key=page_type_auto_detect)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.page_type_auto_detect" data-ab-field="page_type_auto_detect"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-page-type-auto-detect" />
      <label class="ab-check__label" for="ab-gen-page-type-auto-detect">Auto-detect page type</label>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedPageTypeAutoDetect',
  props: { s: { type: Object, required: true } },
}
</script>
