<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/schema.php (key=specific_price_range)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-specific-price-range">Price Range</label>
      <select v-model="s.specific_price_range" data-ab-field="specific_price_range"
              class="ab-select" id="ab-gen-specific-price-range">
        <option value="">Not specified</option>
        <option value="$">$ (budget)</option>
        <option value="$$">$$ (moderate)</option>
        <option value="$$$">$$$ (upscale)</option>
        <option value="$$$$">$$$$ (luxury)</option>
      </select>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedSpecificPriceRange',
  props: { s: { type: Object, required: true } },
}
</script>
