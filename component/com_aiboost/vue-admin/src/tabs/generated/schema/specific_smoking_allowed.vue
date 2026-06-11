<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/schema.php (key=specific_smoking_allowed)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-specific-smoking-allowed">Smoking Allowed</label>
      <select v-model="s.specific_smoking_allowed" data-ab-field="specific_smoking_allowed"
              class="ab-select" id="ab-gen-specific-smoking-allowed">
        <option value="">Not specified</option>
        <option value="true">Yes</option>
        <option value="false">No</option>
      </select>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedSpecificSmokingAllowed',
  props: { s: { type: Object, required: true } },
}
</script>
