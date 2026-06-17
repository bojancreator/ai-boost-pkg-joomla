<template>
  <div class="ab-technical-seo-tab">

    <!-- Domain & Environment (moved from the old General tab) -->
    <div class="ab-card">
      <div class="ab-card-header">🌐 Domain &amp; Environment</div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-3">
          <input v-model="s.auto_domain_detection" data-ab-field="auto_domain_detection" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="tech-auto-domain">
          <label class="ab-check__label" for="tech-auto-domain">Auto-detect domain <span style="color:var(--ab-text-muted)">(recommended)</span></label>
        </div>
        <div class="mb-0">
          <label class="ab-label">Manual Domain <span style="opacity:.5;font-weight:400;">(if auto-detect is off)</span></label>
          <input v-model="s.manual_domain" data-ab-field="manual_domain" type="url" class="ab-input"
            placeholder="https://example.com" style="max-width:340px">
        </div>
      </div>
    </div>

    <!-- Conflict Resolution Mode (moved from the old General tab) -->
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
            <option value="cooperative">Cooperative — remove our duplicate when another tool already emits it (recommended)</option>
            <option value="aggressive">Aggressive — always emit ours (duplicates allowed); still warn in Health</option>
            <option value="off">Off — always emit ours and silence the conflict warnings</option>
          </select>
        </div>
        <ul class="ab-help" style="margin:0 0 0 1.1rem;padding:0;line-height:1.6">
          <li><strong>Cooperative</strong> — at the final render, if another extension already emits OpenGraph or Organization JSON-LD, AI Boost removes <em>its own</em> duplicate — never the other tool's. This also catches tags injected late (e.g. 4SEO). No duplicate tags; conflicts still listed in Health.</li>
          <li><strong>Aggressive</strong> — we always inject our tags (duplicates allowed). Use this only if you trust AI Boost as the single source of truth. Health still lists the conflicts.</li>
          <li><strong>Off</strong> — we always inject our tags <em>and</em> silence the conflict warnings in Health ("I know, stop telling me").</li>
        </ul>
      </div>
    </div>

    <!-- Canonical URLs -->
    <div class="ab-card">
      <div class="ab-card-header">🔗 Canonical URLs</div>
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

    <!-- 404 Monitoring -->
    <div class="ab-card">
      <div class="ab-card-header">🛰 404 Monitoring</div>
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
.ab-info-box {
  font-size: .82rem;
  line-height: 1.6;
  color: var(--secondary-color, #6c757d);
  background: var(--secondary-bg, #f8f9fa);
  border: 1px solid var(--border-color, #dee2e6);
  border-radius: 6px;
  padding: 8px 11px;
}
</style>
