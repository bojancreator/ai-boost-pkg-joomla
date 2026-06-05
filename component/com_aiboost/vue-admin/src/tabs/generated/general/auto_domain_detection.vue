<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/general.php (key=auto_domain_detection)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.auto_domain_detection" data-ab-field="auto_domain_detection"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-auto-domain-detection" />
      <label class="ab-check__label" for="ab-gen-auto-domain-detection">Auto-detect domain</label>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedAutoDomainDetection',
  props: { s: { type: Object, required: true } },
}
</script>
