<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/crawlers.php (key=scraper_bytespider)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.scraper_bytespider" data-ab-field="scraper_bytespider"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-scraper-bytespider" />
      <label class="ab-check__label" for="ab-gen-scraper-bytespider">Block Bytespider</label>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedScraperBytespider',
  props: { s: { type: Object, required: true } },
}
</script>
