<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/code.php (key=enable_custom_code)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.enable_custom_code" data-ab-field="enable_custom_code"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-enable-custom-code" />
      <label class="ab-check__label" for="ab-gen-enable-custom-code">Enable Custom Code Injection</label>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedEnableCustomCode',
  props: { s: { type: Object, required: true } },
}
</script>
