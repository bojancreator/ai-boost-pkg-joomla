<template>
  <div class="ab-technical-seo-tab">

    <!-- Domain & Environment (moved from the old General tab) -->
    <div class="ab-card">
      <div class="ab-card-header">🌐 Domain &amp; Environment</div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-3">
          <input v-model="s.auto_domain_detection" data-ab-field="auto_domain_detection" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="tech-auto-domain">
          <label class="ab-check__label" for="tech-auto-domain">Auto-detect domain <span style="color:#6c757d;">(recommended)</span></label>
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

    <!-- Page Title Templates (NEW editor) -->
    <div class="ab-card">
      <div class="ab-card-header">🏷 Page Title Templates</div>
      <div class="ab-card-body">
        <div class="ab-info-box mb-3">
          Rewrite the <code>&lt;title&gt;</code> per page type. Tokens:
          <code>{page_title}</code> <code>{site_name}</code> <code>{separator}</code>
          <code>{category}</code> <code>{year}</code>. Leave a field empty to keep Joomla's default.
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-8">
            <label class="ab-label">Title Separator</label>
            <input v-model="s.title_separator" data-ab-field="title_separator" type="text" class="ab-input" style="max-width:120px" placeholder=" | ">
          </div>
          <div class="col-md-4">
            <label class="ab-label">Max Length <span style="opacity:.5;font-weight:400;">(0 = no limit)</span></label>
            <input v-model="s.title_template_maxlen" data-ab-field="title_template_maxlen" type="number" min="0" max="120" class="ab-input" style="max-width:110px" placeholder="0">
          </div>
        </div>
        <div class="mb-3">
          <label class="ab-label">Homepage</label>
          <input v-model="s.title_template_home" data-ab-field="title_template_home" type="text" class="ab-input" placeholder="{site_name} {separator} Your tagline">
        </div>
        <div class="mb-3">
          <label class="ab-label">Article</label>
          <input v-model="s.title_template_article" data-ab-field="title_template_article" type="text" class="ab-input" placeholder="{page_title} {separator} {site_name}">
        </div>
        <div class="mb-3">
          <label class="ab-label">Category</label>
          <input v-model="s.title_template_category" data-ab-field="title_template_category" type="text" class="ab-input" placeholder="{page_title} {separator} {site_name}">
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="ab-label">Search</label>
            <input v-model="s.title_template_search" data-ab-field="title_template_search" type="text" class="ab-input" placeholder="Search {separator} {site_name}">
          </div>
          <div class="col-md-6">
            <label class="ab-label">Tag</label>
            <input v-model="s.title_template_tag" data-ab-field="title_template_tag" type="text" class="ab-input" placeholder="{page_title} {separator} {site_name}">
          </div>
        </div>
        <div class="row g-3 mb-0">
          <div class="col-md-6">
            <label class="ab-label">Default <span style="opacity:.5;font-weight:400;">(other pages)</span></label>
            <input v-model="s.title_template_default" data-ab-field="title_template_default" type="text" class="ab-input" placeholder="{page_title} {separator} {site_name}">
          </div>
          <div class="col-md-6">
            <label class="ab-label">Global Fallback <span style="opacity:.5;font-weight:400;">(legacy)</span></label>
            <input v-model="s.title_template" data-ab-field="title_template" type="text" class="ab-input" placeholder="Used when a per-type template is empty">
          </div>
        </div>
      </div>
    </div>

    <!-- Meta Description Templates (NEW editor) -->
    <div class="ab-card">
      <div class="ab-card-header">📝 Meta Description Templates</div>
      <div class="ab-card-body">
        <div class="ab-info-box mb-3">
          Build the meta description per page type. Tokens:
          <code>{description}</code> <code>{site_name}</code> <code>{separator}</code> <code>{year}</code>.
          Leave empty to use the page's own description.
        </div>
        <div class="mb-3">
          <label class="ab-label">Max Length <span style="opacity:.5;font-weight:400;">(SEO guideline: 160)</span></label>
          <input v-model="s.meta_desc_maxlen" data-ab-field="meta_desc_maxlen" type="number" min="0" max="320" class="ab-input" style="max-width:110px" placeholder="160">
        </div>
        <div class="mb-3">
          <label class="ab-label">Article</label>
          <textarea v-model="s.meta_desc_template_article" data-ab-field="meta_desc_template_article" class="ab-input" rows="2" placeholder="{description}"></textarea>
        </div>
        <div class="mb-3">
          <label class="ab-label">Category</label>
          <textarea v-model="s.meta_desc_template_category" data-ab-field="meta_desc_template_category" class="ab-input" rows="2" placeholder="{description}"></textarea>
        </div>
        <div class="mb-3">
          <label class="ab-label">Default <span style="opacity:.5;font-weight:400;">(other pages)</span></label>
          <textarea v-model="s.meta_desc_template_default" data-ab-field="meta_desc_template_default" class="ab-input" rows="2" placeholder="{description}"></textarea>
        </div>
        <div class="mb-0">
          <label class="ab-label">Global Fallback <span style="opacity:.5;font-weight:400;">(legacy)</span></label>
          <textarea v-model="s.meta_desc_template" data-ab-field="meta_desc_template" class="ab-input" rows="2" placeholder="Used when a per-type template is empty"></textarea>
        </div>
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
