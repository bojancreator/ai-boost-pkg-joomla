<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/schema.php (key=website_schema_enabled)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.website_schema_enabled" data-ab-field="website_schema_enabled"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-website-schema-enabled" />
      <label class="ab-check__label" for="ab-gen-website-schema-enabled">Enable WebSite Schema (homepage only)</label>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedWebsiteSchemaEnabled',
  props: { s: { type: Object, required: true } },
}
</script>
