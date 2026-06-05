<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/schema.php (key=manual_faq_scope)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-manual-faq-scope">Global FAQ - When to Apply</label>
      <select v-model="s.manual_faq_scope" data-ab-field="manual_faq_scope"
              class="ab-select" id="ab-gen-manual-faq-scope">
        <option value="fallback_all">Fallback on all pages</option>
        <option value="always_all">All pages</option>
        <option value="fallback_home">Homepage fallback only</option>
        <option value="always_home">Homepage only</option>
        <option value="disabled">Disabled</option>
      </select>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedManualFaqScope',
  props: { s: { type: Object, required: true } },
}
</script>
