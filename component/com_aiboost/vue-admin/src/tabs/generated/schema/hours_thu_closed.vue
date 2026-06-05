<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/schema.php (key=hours_thu_closed)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.hours_thu_closed" data-ab-field="hours_thu_closed"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-hours-thu-closed" />
      <label class="ab-check__label" for="ab-gen-hours-thu-closed">Thu Closed</label>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedHoursThuClosed',
  props: { s: { type: Object, required: true } },
}
</script>
