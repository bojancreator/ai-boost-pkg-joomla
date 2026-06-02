<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/schema.php (key=enable_manual_faqs)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <ProGate gate-key="enable_manual_faqs" mode="field">
      <div class="ab-check ab-toggle mb-3">
        <input v-model="s.enable_manual_faqs" data-ab-field="enable_manual_faqs"
               true-value="1" false-value="0"
               type="checkbox" class="ab-toggle__input" id="ab-gen-enable-manual-faqs" />
        <label class="ab-check__label" for="ab-gen-enable-manual-faqs">Enable Manual FAQs</label>
      </div>
    </ProGate>
  </div>
</template>

<script>
export default {
  name: 'GeneratedEnableManualFaqs',
  props: { s: { type: Object, required: true } },
}
</script>
