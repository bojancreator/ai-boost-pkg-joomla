<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/sitemap.php (key=hreflang_sitemap)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.hreflang_sitemap" data-ab-field="hreflang_sitemap"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-hreflang-sitemap" />
      <label class="ab-check__label" for="ab-gen-hreflang-sitemap">Emit &lt;xhtml:link&gt; hreflang alternates in sitemap</label>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedHreflangSitemap',
  props: { s: { type: Object, required: true } },
}
</script>
