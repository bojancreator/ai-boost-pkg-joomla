<template>
  <div class="ab-general-tab">
    <div class="ab-card">
      <div class="ab-card-header">🌐 Domain &amp; Environment</div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-3">
          <input v-model="s.auto_domain_detection" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="g-auto-domain">
          <label class="ab-check__label" for="g-auto-domain">Auto-detect domain <span style="color:#6c757d;">(recommended)</span></label>
        </div>
        <div class="mb-0">
          <label class="ab-label">Manual Domain <span style="opacity:.5;font-weight:400;">(if auto-detect is off)</span></label>
          <input v-model="s.manual_domain" data-ab-field="manual_domain" type="url" class="ab-input"
            placeholder="https://example.com" style="max-width:340px">
        </div>
      </div>
    </div>

    <!-- Conflict Resolution Mode -->
    <div class="ab-card">
      <div class="ab-card-header">🛡️ Conflict Resolution Mode</div>
      <div class="ab-card-body">
        <p class="ab-help mb-2">
          Controls how AI Boost behaves when another SEO/Analytics extension (Joomla Core OG, 4SEO, Sh404SEF,
          EFSEO, Google Analytics extension, etc.) is already producing the same meta tag, JSON-LD block,
          or analytics snippet. Detected duplicates appear in <em>Health → Conflicts</em>.
        </p>
        <div class="mb-2">
          <label class="ab-label">Mode</label>
          <select v-model="s.conflict_mode" data-ab-field="conflict_mode" class="ab-select" style="max-width:340px">
            <option value="cooperative">Cooperative — skip our tag when one already exists (recommended)</option>
            <option value="aggressive">Aggressive — always emit our tag (may produce duplicates)</option>
            <option value="off">Off — disable conflict handling entirely</option>
          </select>
        </div>
        <ul class="ab-help" style="margin:0 0 0 1.1rem;padding:0;line-height:1.6">
          <li><strong>Cooperative</strong> — aiboost_social skips OG when another extension set og:title; aiboost_schema skips Organization JSON-LD; aiboost_analytics skips GA4/GTM/Meta Pixel when their loaders are already present; aiboost_aeo skips AI meta tags when present. No duplicates.</li>
          <li><strong>Aggressive</strong> — we always inject our tags. Use this only if you trust AI Boost as the single source of truth and have disabled the other extension's tag output.</li>
          <li><strong>Off</strong> — same behaviour as Aggressive, plus we never check for conflicts. For debugging only.</li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneralTab',
  props: { s: { type: Object, required: true } },
}
</script>

<style scoped>
.ab-general-tab { max-width: 860px; }
.ab-row--invalid    { border-left-color: #f85149; }
.ab-row--invalid    .ab-row-text { color: #ff7b72; }

.ab-lvl--warn   { border-left-color: #d29922; }
.ab-lvl--danger { border-left-color: #f85149; background: rgba(248,81,73,.06); }
</style>
