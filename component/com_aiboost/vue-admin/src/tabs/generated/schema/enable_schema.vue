<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/schema.php (key=enable_schema)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.enable_schema" data-ab-field="enable_schema"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-enable-schema" />
      <label class="ab-check__label" for="ab-gen-enable-schema">Enable Schema.org structured data</label>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedEnableSchema',
  props: { s: { type: Object, required: true } },
}
</script>
