<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/schema.php (key=specific_return_category)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-specific-return-category">Return Policy</label>
      <select v-model="s.specific_return_category" data-ab-field="specific_return_category"
              class="ab-select" id="ab-gen-specific-return-category">
        <option value="">Not specified</option>
        <option value="MerchantReturnFiniteReturnWindow">Returns within N days</option>
        <option value="MerchantReturnUnlimitedWindow">Unlimited returns</option>
        <option value="MerchantReturnNotPermitted">No returns accepted</option>
      </select>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedSpecificReturnCategory',
  props: { s: { type: Object, required: true } },
}
</script>
