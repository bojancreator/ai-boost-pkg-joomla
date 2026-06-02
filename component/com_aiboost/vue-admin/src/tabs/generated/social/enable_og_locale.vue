<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/social.php (key=enable_og_locale)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <ProGate gate-key="enable_og_locale" mode="field">
      <div class="ab-check ab-toggle mb-3">
        <input v-model="s.enable_og_locale" data-ab-field="enable_og_locale"
               true-value="1" false-value="0"
               type="checkbox" class="ab-toggle__input" id="ab-gen-enable-og-locale" />
        <label class="ab-check__label" for="ab-gen-enable-og-locale">Add og:locale tag</label>
      </div>
    </ProGate>
  </div>
</template>

<script>
export default {
  name: 'GeneratedEnableOgLocale',
  props: { s: { type: Object, required: true } },
}
</script>
