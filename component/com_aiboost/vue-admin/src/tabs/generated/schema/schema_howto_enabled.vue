<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/schema.php (key=schema_howto_enabled)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <ProGate gate-key="schema_howto_enabled" mode="field">
      <div class="ab-check ab-toggle mb-3">
        <input v-model="s.schema_howto_enabled" data-ab-field="schema_howto_enabled"
               true-value="1" false-value="0"
               type="checkbox" class="ab-toggle__input" id="ab-gen-schema-howto-enabled" />
        <label class="ab-check__label" for="ab-gen-schema-howto-enabled">Enable HowTo Schema</label>
      </div>
    </ProGate>
  </div>
</template>

<script>
export default {
  name: 'GeneratedSchemaHowtoEnabled',
  props: { s: { type: Object, required: true } },
}
</script>
