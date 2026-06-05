<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/sitemap.php (key=enable_sitemap)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.enable_sitemap" data-ab-field="enable_sitemap"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-enable-sitemap" />
      <label class="ab-check__label" for="ab-gen-enable-sitemap">Enable XML Sitemap</label>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedEnableSitemap',
  props: { s: { type: Object, required: true } },
}
</script>
