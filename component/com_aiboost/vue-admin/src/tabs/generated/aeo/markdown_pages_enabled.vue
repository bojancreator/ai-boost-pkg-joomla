<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/aeo.php (key=markdown_pages_enabled)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <ProGate gate-key="markdown_pages_enabled" mode="field">
      <div class="ab-check ab-toggle mb-3">
        <input v-model="s.markdown_pages_enabled" data-ab-field="markdown_pages_enabled"
               true-value="1" false-value="0"
               type="checkbox" class="ab-toggle__input" id="ab-gen-markdown-pages-enabled" />
        <label class="ab-check__label" for="ab-gen-markdown-pages-enabled">Serve pages as Markdown for AI agents</label>
      </div>
    </ProGate>
  </div>
</template>

<script>
export default {
  name: 'GeneratedMarkdownPagesEnabled',
  props: { s: { type: Object, required: true } },
}
</script>
