<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/crawlers.php (key=crawler_rules)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-crawler-rules">Custom robots.txt rules</label>
      <textarea v-model="s.crawler_rules" data-ab-field="crawler_rules"
                class="ab-input" rows="4" id="ab-gen-crawler-rules"></textarea>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedCrawlerRules',
  props: { s: { type: Object, required: true } },
}
</script>
