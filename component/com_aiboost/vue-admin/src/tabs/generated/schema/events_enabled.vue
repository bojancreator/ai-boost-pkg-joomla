<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/schema.php (key=events_enabled)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.events_enabled" data-ab-field="events_enabled"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-events-enabled" />
      <label class="ab-check__label" for="ab-gen-events-enabled">Enable Event Schema</label>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedEventsEnabled',
  props: { s: { type: Object, required: true } },
}
</script>
