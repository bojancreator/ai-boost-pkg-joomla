<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/schema.php (key=specific_accepts_reservations)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-specific-accepts-reservations">Accepts Reservations</label>
      <select v-model="s.specific_accepts_reservations" data-ab-field="specific_accepts_reservations"
              class="ab-select" id="ab-gen-specific-accepts-reservations">
        <option value="">Not specified</option>
        <option value="true">Yes</option>
        <option value="false">No</option>
      </select>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedSpecificAcceptsReservations',
  props: { s: { type: Object, required: true } },
}
</script>
