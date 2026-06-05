<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/schema.php (key=schema_author_entity_enabled)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.schema_author_entity_enabled" data-ab-field="schema_author_entity_enabled"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-schema-author-entity-enabled" />
      <label class="ab-check__label" for="ab-gen-schema-author-entity-enabled">Emit Person entity for each article author</label>
      <div class="ab-help">Reads Joomla user custom fields (aiboost_job_title, _bio, _website, _linkedin, _wikipedia) to build E-E-A-T-grade Person schema.</div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedSchemaAuthorEntityEnabled',
  props: { s: { type: Object, required: true } },
}
</script>
