<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/sitemap.php (key=ping_bing)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.ping_bing" data-ab-field="ping_bing"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-ping-bing" />
      <label class="ab-check__label" for="ab-gen-ping-bing">Ping Bing on sitemap request</label>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedPingBing',
  props: { s: { type: Object, required: true } },
}
</script>
