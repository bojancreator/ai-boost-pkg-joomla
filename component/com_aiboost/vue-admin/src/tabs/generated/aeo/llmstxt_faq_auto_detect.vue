<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/aeo.php (key=llmstxt_faq_auto_detect)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.llmstxt_faq_auto_detect" data-ab-field="llmstxt_faq_auto_detect"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-llmstxt-faq-auto-detect" />
      <label class="ab-check__label" for="ab-gen-llmstxt-faq-auto-detect">Auto-Detect FAQ from Articles</label>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedLlmstxtFaqAutoDetect',
  props: { s: { type: Object, required: true } },
}
</script>
