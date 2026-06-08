<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/crawlers.php (key=aeo_crawler_default_policy)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-aeo-crawler-default-policy">Default policy for unspecified crawlers</label>
      <select v-model="s.aeo_crawler_default_policy" data-ab-field="aeo_crawler_default_policy"
              class="ab-select" id="ab-gen-aeo-crawler-default-policy">
        <option value="allow">Allow all</option>
        <option value="block">Block all</option>
      </select>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedAeoCrawlerDefaultPolicy',
  props: { s: { type: Object, required: true } },
}
</script>
