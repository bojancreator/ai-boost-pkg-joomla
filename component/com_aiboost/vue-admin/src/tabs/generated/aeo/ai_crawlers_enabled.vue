<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/aeo.php (key=ai_crawlers_enabled)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.ai_crawlers_enabled" data-ab-field="ai_crawlers_enabled"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-ai-crawlers-enabled" />
      <label class="ab-check__label" for="ab-gen-ai-crawlers-enabled">Enable AI crawler rules</label>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedAiCrawlersEnabled',
  props: { s: { type: Object, required: true } },
}
</script>
