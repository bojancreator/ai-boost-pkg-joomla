<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/aeo.php (key=llmstxt_enabled)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.llmstxt_enabled" data-ab-field="llmstxt_enabled"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-llmstxt-enabled" />
      <label class="ab-check__label" for="ab-gen-llmstxt-enabled">Enable /llms.txt</label>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedLlmstxtEnabled',
  props: { s: { type: Object, required: true } },
}
</script>
