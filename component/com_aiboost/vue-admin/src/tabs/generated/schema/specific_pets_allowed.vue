<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/schema.php (key=specific_pets_allowed)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-specific-pets-allowed">Pets Allowed</label>
      <select v-model="s.specific_pets_allowed" data-ab-field="specific_pets_allowed"
              class="ab-select" id="ab-gen-specific-pets-allowed">
        <option value="">Not specified</option>
        <option value="true">Yes</option>
        <option value="false">No</option>
      </select>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedSpecificPetsAllowed',
  props: { s: { type: Object, required: true } },
}
</script>
