<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/schema.php (key=schema_faq_output_type)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-schema-faq-output-type">Schema Output Type</label>
      <select v-model="s.schema_faq_output_type" data-ab-field="schema_faq_output_type"
              class="ab-select" id="ab-gen-schema-faq-output-type">
        <option value="faqpage">FAQPage only</option>
        <option value="qapage">QAPage only</option>
        <option value="both">Both FAQPage and QAPage</option>
      </select>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedSchemaFaqOutputType',
  props: { s: { type: Object, required: true } },
}
</script>
