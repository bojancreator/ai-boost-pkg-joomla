<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/code.php (key=custom_code_footer_scope)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-custom-code-footer-scope">Footer code scope</label>
      <select v-model="s.custom_code_footer_scope" data-ab-field="custom_code_footer_scope"
              class="ab-select" id="ab-gen-custom-code-footer-scope">
        <option value="all">All pages</option>
        <option value="specific">Specific menu items only</option>
      </select>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedCustomCodeFooterScope',
  props: { s: { type: Object, required: true } },
}
</script>
