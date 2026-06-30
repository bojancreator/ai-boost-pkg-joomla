<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/aeo.php (key=markdown_alternate_noindex)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.markdown_alternate_noindex" data-ab-field="markdown_alternate_noindex"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-markdown-alternate-noindex" />
      <label class="ab-check__label" for="ab-gen-markdown-alternate-noindex">Keep the Markdown copy out of search engines</label>
      <div class="ab-help">When on, the Markdown/AI alternate of a page (its .md / ?markdown=1 version) sends a noindex signal (X-Robots-Tag) so the plain-text copy never competes with the original page in search results. The normal HTML page is unaffected — it stays indexable and in the sitemap. Requires &quot;Serve pages as Markdown for AI agents&quot; to be on.</div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedMarkdownAlternateNoindex',
  props: { s: { type: Object, required: true } },
}
</script>
