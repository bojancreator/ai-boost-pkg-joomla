<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/debug.php (key=hide_comments)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.hide_comments" data-ab-field="hide_comments"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-hide-comments" />
      <label class="ab-check__label" for="ab-gen-hide-comments">Hide comments in HTML source</label>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedHideComments',
  props: { s: { type: Object, required: true } },
}
</script>
