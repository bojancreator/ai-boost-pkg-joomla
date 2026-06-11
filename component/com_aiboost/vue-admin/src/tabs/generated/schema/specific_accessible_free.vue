<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/schema.php (key=specific_accessible_free)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-specific-accessible-free">Free Admission</label>
      <select v-model="s.specific_accessible_free" data-ab-field="specific_accessible_free"
              class="ab-select" id="ab-gen-specific-accessible-free">
        <option value="">Not specified</option>
        <option value="true">Yes</option>
        <option value="false">No</option>
      </select>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedSpecificAccessibleFree',
  props: { s: { type: Object, required: true } },
}
</script>
