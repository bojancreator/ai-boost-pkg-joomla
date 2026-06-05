<template>
  <div class="ab-technical-seo-tab">
    <div class="ab-card">
      <div class="ab-card-header">Technical SEO</div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-3">
          <input v-model="s.enable_canonical" data-ab-field="enable_canonical" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="tech-canonical">
          <label class="ab-check__label" for="tech-canonical">Enable canonical URL management</label>
        </div>

        <label class="ab-label">Canonical URL Map <span style="opacity:.5;font-weight:400;">(one override per line)</span></label>
        <textarea v-model="s.canonical_url_map" data-ab-field="canonical_url_map" class="ab-input font-monospace" rows="5"
          placeholder="/old-path -> /new-path&#10;/shop -> /products"></textarea>
        <div class="ab-help mt-1">Format: <code>/source-path -> /canonical-path</code> or full URLs.</div>
      </div>
    </div>

    <div class="ab-card">
      <div class="ab-card-header">404 Monitoring</div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-2">
          <input v-model="s.redirect_404_log_enabled" data-ab-field="redirect_404_log_enabled"
            true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="tech-404-log">
          <label class="ab-check__label" for="tech-404-log">Log 404 Errors</label>
        </div>
        <div class="ab-help">
          AI Boost records front-end 404 hits into <code>#__aiboost_404_log</code>. Use the
          Redirects page to turn recurring dead URLs into permanent redirects.
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'TechnicalSeoTab',
  props: { s: { type: Object, required: true } },
}
</script>

<style scoped>
.ab-technical-seo-tab { max-width: 860px; }
</style>