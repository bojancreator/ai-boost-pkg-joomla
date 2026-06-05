<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/aeo.php (key=llms_full_txt_enabled)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.llms_full_txt_enabled" data-ab-field="llms_full_txt_enabled"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-llms-full-txt-enabled" />
      <label class="ab-check__label" for="ab-gen-llms-full-txt-enabled">Enable /llms-full.txt</label>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedLlmsFullTxtEnabled',
  props: { s: { type: Object, required: true } },
}
</script>
