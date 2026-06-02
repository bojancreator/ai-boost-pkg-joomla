<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/schema.php (key=schema_breadcrumb_pro)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <ProGate gate-key="schema_breadcrumb_pro" mode="field">
      <div class="ab-check ab-toggle mb-3">
        <input v-model="s.schema_breadcrumb_pro" data-ab-field="schema_breadcrumb_pro"
               true-value="1" false-value="0"
               type="checkbox" class="ab-toggle__input" id="ab-gen-schema-breadcrumb-pro" />
        <label class="ab-check__label" for="ab-gen-schema-breadcrumb-pro">Enhanced BreadcrumbList (Pro)</label>
        <div class="ab-help">Emit a richer BreadcrumbList with per-item images and structured position metadata. Free tier emits the basic BreadcrumbList.</div>
      </div>
    </ProGate>
  </div>
</template>

<script>
export default {
  name: 'GeneratedSchemaBreadcrumbPro',
  props: { s: { type: Object, required: true } },
}
</script>
