<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/code.php (key=custom_code_head)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <ProGate gate-key="custom_code_head" mode="field">
      <div class="mb-3">
        <label class="ab-label" for="ab-gen-custom-code-head">Inject before &lt;/head&gt;</label>
        <textarea v-model="s.custom_code_head" data-ab-field="custom_code_head"
                  class="ab-input" rows="4" id="ab-gen-custom-code-head"></textarea>
      </div>
    </ProGate>
  </div>
</template>

<script>
export default {
  name: 'GeneratedCustomCodeHead',
  props: { s: { type: Object, required: true } },
}
</script>
