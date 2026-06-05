<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/social.php (key=enable_per_article_fields)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.enable_per_article_fields" data-ab-field="enable_per_article_fields"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-enable-per-article-fields" />
      <label class="ab-check__label" for="ab-gen-enable-per-article-fields">Use per-article OG image and description</label>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedEnablePerArticleFields',
  props: { s: { type: Object, required: true } },
}
</script>
