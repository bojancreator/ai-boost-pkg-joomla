<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/schema.php (key=faq_auto_detect)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <ProGate gate-key="faq_auto_detect" mode="field">
      <div class="ab-check ab-toggle mb-3">
        <input v-model="s.faq_auto_detect" data-ab-field="faq_auto_detect"
               true-value="1" false-value="0"
               type="checkbox" class="ab-toggle__input" id="ab-gen-faq-auto-detect" />
        <label class="ab-check__label" for="ab-gen-faq-auto-detect">Auto-Detect FAQ from Content</label>
      </div>
    </ProGate>
  </div>
</template>

<script>
export default {
  name: 'GeneratedFaqAutoDetect',
  props: { s: { type: Object, required: true } },
}
</script>
