<template>
  <div class="ab-settings-tab">

    <!-- 01 Domain & Environment -->
    <div class="ab-section">
      <div class="ab-section__head">
        <span class="ab-section__num">01</span>
        Domain &amp; Environment
      </div>
      <div class="ab-section__body">
        <label class="ab-toggle-row">
          <div>
            <div class="ab-label">Auto-detect domain <span class="ab-muted">(recommended)</span></div>
          </div>
          <span class="ab-toggle" :class="{'is-on': s.auto_domain_detection === '1'}">
            <input v-model="s.auto_domain_detection" data-ab-field="auto_domain_detection" true-value="1" false-value="0"
              type="checkbox" class="ab-toggle__input">
            <span class="ab-toggle__track"></span>
          </span>
        </label>
        <div class="ab-field">
          <label class="ab-label">Manual Domain <span class="ab-muted">(if auto-detect is off)</span></label>
          <input v-model="s.manual_domain" data-ab-field="manual_domain" type="url" class="ab-input"
            placeholder="https://example.com" style="max-width:340px">
        </div>
      </div>
    </div>

    <!-- 02 Conflict Resolution Mode -->
    <div class="ab-section">
      <div class="ab-section__head">
        <span class="ab-section__num">02</span>
        Conflict Resolution Mode
      </div>
      <div class="ab-section__body">
        <div class="ab-help">
          Controls how AI Boost behaves when another SEO/Analytics extension is already producing the
          same meta tag, JSON-LD block, or analytics snippet. Detected duplicates appear in
          <em>Health → Conflicts</em>.
        </div>
        <div class="ab-field">
          <label class="ab-label">Mode</label>
          <select v-model="s.conflict_mode" data-ab-field="conflict_mode" class="ab-select" style="max-width:480px">
            <option value="cooperative">Cooperative — remove our duplicate when another tool already emits it (recommended)</option>
            <option value="aggressive">Aggressive — always emit ours (duplicates allowed); still warn in Health</option>
            <option value="off">Off — always emit ours and silence the conflict warnings</option>
          </select>
          <div class="ab-help">
            <strong>Cooperative</strong> — if another extension emits the same tag, AI Boost removes its own copy. No duplicates; conflicts still listed in Health.<br>
            <strong>Aggressive</strong> — always inject our tags (duplicates allowed). Use when AI Boost is the sole source of truth.<br>
            <strong>Off</strong> — always inject our tags and silence Health conflict warnings.
          </div>
        </div>
      </div>
    </div>

    <!-- 03 Canonical URLs -->
    <div class="ab-section">
      <div class="ab-section__head">
        <span class="ab-section__num">03</span>
        Canonical URLs
      </div>
      <div class="ab-section__body">
        <label class="ab-toggle-row">
          <div>
            <div class="ab-label">Enable canonical URL management</div>
          </div>
          <span class="ab-toggle" :class="{'is-on': s.enable_canonical === '1'}">
            <input v-model="s.enable_canonical" data-ab-field="enable_canonical" true-value="1" false-value="0"
              type="checkbox" class="ab-toggle__input">
            <span class="ab-toggle__track"></span>
          </span>
        </label>
        <div class="ab-field">
          <label class="ab-label">Canonical URL Map <span class="ab-muted">(one override per line)</span></label>
          <textarea v-model="s.canonical_url_map" data-ab-field="canonical_url_map" class="ab-input font-monospace" rows="5"
            placeholder="/old-path -> /new-path&#10;/shop -> /products"></textarea>
          <div class="ab-help">Format: <code>/source-path -> /canonical-path</code> or full URLs.</div>
        </div>
      </div>
    </div>

    <!-- 04 404 Monitoring -->
    <div class="ab-section">
      <div class="ab-section__head">
        <span class="ab-section__num">04</span>
        404 Monitoring
      </div>
      <div class="ab-section__body">
        <label class="ab-toggle-row">
          <div>
            <div class="ab-label">Log 404 Errors</div>
            <div class="ab-help">
              AI Boost records front-end 404 hits into <code>#__aiboost_404_log</code>.
              Use the Redirects page to turn recurring dead URLs into permanent redirects.
            </div>
          </div>
          <span class="ab-toggle" :class="{'is-on': s.redirect_404_log_enabled === '1'}">
            <input v-model="s.redirect_404_log_enabled" data-ab-field="redirect_404_log_enabled"
              true-value="1" false-value="0" type="checkbox" class="ab-toggle__input">
            <span class="ab-toggle__track"></span>
          </span>
        </label>
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
