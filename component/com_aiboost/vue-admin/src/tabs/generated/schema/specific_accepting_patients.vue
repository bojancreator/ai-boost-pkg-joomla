<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/schema.php (key=specific_accepting_patients)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-specific-accepting-patients">Accepting New Patients</label>
      <select v-model="s.specific_accepting_patients" data-ab-field="specific_accepting_patients"
              class="ab-select" id="ab-gen-specific-accepting-patients">
        <option value="">Not specified</option>
        <option value="true">Yes</option>
        <option value="false">No</option>
      </select>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedSpecificAcceptingPatients',
  props: { s: { type: Object, required: true } },
}
</script>
