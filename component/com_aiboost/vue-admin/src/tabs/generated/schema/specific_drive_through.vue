<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/schema.php (key=specific_drive_through)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-specific-drive-through">Drive-Through Service</label>
      <select v-model="s.specific_drive_through" data-ab-field="specific_drive_through"
              class="ab-select" id="ab-gen-specific-drive-through">
        <option value="">Not specified</option>
        <option value="true">Yes</option>
        <option value="false">No</option>
      </select>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedSpecificDriveThrough',
  props: { s: { type: Object, required: true } },
}
</script>
