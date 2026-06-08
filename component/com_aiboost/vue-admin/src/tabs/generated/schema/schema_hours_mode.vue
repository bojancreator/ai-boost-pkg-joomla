<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/schema.php (key=schema_hours_mode)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-schema-hours-mode">Opening Hours Mode</label>
      <select v-model="s.schema_hours_mode" data-ab-field="schema_hours_mode"
              class="ab-select" id="ab-gen-schema-hours-mode">
        <option value="simple">Simple (one text line)</option>
        <option value="advanced">Advanced — day-by-day schedule</option>
        <option value="none">Not applicable / Hide</option>
      </select>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedSchemaHoursMode',
  props: { s: { type: Object, required: true } },
}
</script>
