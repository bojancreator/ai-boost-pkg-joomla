<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/schema.php (key=article_schema_enabled)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.article_schema_enabled" data-ab-field="article_schema_enabled"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-article-schema-enabled" />
      <label class="ab-check__label" for="ab-gen-article-schema-enabled">Enable Article Schema on article pages</label>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedArticleSchemaEnabled',
  props: { s: { type: Object, required: true } },
}
</script>
