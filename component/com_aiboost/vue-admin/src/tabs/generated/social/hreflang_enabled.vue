<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/social.php (key=hreflang_enabled)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.hreflang_enabled" data-ab-field="hreflang_enabled"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-hreflang-enabled" />
      <label class="ab-check__label" for="ab-gen-hreflang-enabled">Emit hreflang &lt;link&gt; alternates</label>
      <div class="ab-help">Outputs &lt;link rel=&quot;alternate&quot; hreflang&gt; tags for every language on the site.</div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedHreflangEnabled',
  props: { s: { type: Object, required: true } },
}
</script>
