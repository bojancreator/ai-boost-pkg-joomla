<template>
  <div class="ab-social-tab">

    <!-- OpenGraph (Free baseline — sitewide defaults only) -->
    <div class="ab-card">
      <div class="ab-card-header">📘 OpenGraph (Facebook, LinkedIn, WhatsApp)</div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-3">
          <input v-model="s.enable_opengraph" data-ab-field="enable_opengraph" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="soc-og">
          <label class="ab-check__label" for="soc-og">Enable OpenGraph tags</label>
        </div>
        <div class="mb-3" data-ab-field="site_name">
          <label class="ab-label">OG Site Name</label>
          <input v-model="s.site_name" data-ab-field="site_name" type="text" class="ab-input" style="max-width:340px"
            placeholder="Leave empty to use Joomla site name">
          <ProGate mode="field" label="Translate"><TranslationExpander field-key="site_name" /></ProGate>
        </div>
        <div class="mb-3">
          <label class="ab-label">Default OG Image</label>
          <MediaPicker
            v-model="s.default_og_image" data-ab-field="default_og_image"
            field-key="default_og_image"
            label="Default OG image"
            placeholder="https://example.com/images/og-default.png"
            recommended-size="Recommended: 1200×630 px. Shown when a page has no image."
          />
          <ProGate mode="field" label="Translate"><TranslationExpander field-key="default_og_image" field-type="media" /></ProGate>
        </div>
        <div class="mb-3">
          <label class="ab-label">Default OG Image Alt Text</label>
          <input v-model="s.default_og_image_alt" data-ab-field="default_og_image_alt" type="text" class="ab-input"
            style="max-width:480px" placeholder="Describe the image for accessibility &amp; rich previews">
          <div class="ab-help">Emitted as <code>og:image:alt</code>. Describe what the image shows.</div>
          <ProGate mode="field" label="Translate"><TranslationExpander field-key="default_og_image_alt" /></ProGate>
        </div>
        <!-- Default OpenGraph description override. -->
        <div class="mb-3">
          <label class="ab-label">Default OG Description Override</label>
          <textarea v-model="s.og_description_override" data-ab-field="og_description_override"
            class="ab-input" rows="2"
            placeholder="Leave empty to use the page meta description automatically"></textarea>
          <div class="ab-help">Sitewide fallback description for social sharing. Leave empty to auto-detect from page content.</div>
          <ProGate mode="field" label="Translate"><TranslationExpander field-key="og_description_override" field-type="textarea" /></ProGate>
        </div>
        <div class="row g-3 mb-0">
          <div class="col-md-3">
            <label class="ab-label">Default OG Image Width</label>
            <input v-model="s.og_image_width" type="number" class="ab-input" placeholder="1200" min="200" max="4000">
          </div>
          <div class="col-md-3">
            <label class="ab-label">Default OG Image Height</label>
            <input v-model="s.og_image_height" type="number" class="ab-input" placeholder="630" min="200" max="4000">
          </div>
        </div>
      </div>
    </div>

    <!-- Per-article OG (Pro) — custom fields per article -->
    <ProGate mode="card" label="Per-article OG">
      <div class="ab-card">
        <div class="ab-card-header">📝 Per-article OG <span class="ab-pro-tag">Pro</span></div>
        <div class="ab-card-body">
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <div class="ab-check ab-toggle">
                <input v-model="s.enable_per_article_fields" true-value="1" false-value="0"
                  type="checkbox" class="ab-toggle__input" id="soc-per-article">
                <label class="ab-check__label" for="soc-per-article">Use per-article OG image &amp; description</label>
              </div>
              <div class="ab-help">Pulls each article's own intro image and description for its OG tags.</div>
            </div>
            <div class="col-md-6">
              <div class="ab-check ab-toggle">
                <input v-model="s.enable_article_og_type" true-value="1" false-value="0"
                  type="checkbox" class="ab-toggle__input" id="soc-article-type">
                <label class="ab-check__label" for="soc-article-type">Set og:type = article on article pages</label>
              </div>
              <div class="ab-help">Otherwise defaults to <code>website</code>.</div>
            </div>
          </div>

          <!-- OG Custom Fields repair -->
          <div class="ab-og-fields-row">
            <div class="ab-og-fields-info">
              <strong>Per-article OG custom fields</strong>
              <span class="ab-help d-block">
                AI Boost Pro creates 6 custom fields in <em>Content → Fields → Articles</em>
                (group "AI Boost — OpenGraph") so editors can override OG title, description,
                image, type, video URL and Twitter Card per article.
                If the fields are missing, click the button to create them.
              </span>
            </div>
            <button type="button" class="ab-btn ab-btn--sm ab-btn--ghost ab-repair-btn"
              :disabled="ogRepairBusy" @click="repairOgFields">
              <span v-if="ogRepairBusy">⏳ Working…</span>
              <span v-else>🔧 Create / Repair OG Fields</span>
            </button>
            <div v-if="ogRepairMsg" class="ab-og-repair-msg" :class="ogRepairOk ? 'ab-og-ok' : 'ab-og-err'">
              {{ ogRepairMsg }}
            </div>
          </div>
        </div>
      </div>
    </ProGate>

    <!-- OG Locale & Facebook (Pro) -->
    <ProGate mode="card" label="OG Locale &amp; Facebook">
      <div class="ab-card">
        <div class="ab-card-header">🌍 Locale &amp; Facebook <span class="ab-pro-tag">Pro</span></div>
        <div class="ab-card-body">
          <div class="ab-check ab-toggle mb-3">
            <input v-model="s.enable_og_locale" true-value="1" false-value="0"
              type="checkbox" class="ab-toggle__input" id="soc-locale">
            <label class="ab-check__label" for="soc-locale">Add <code>og:locale</code> tag</label>
            <div class="ab-help">Outputs the active Joomla language as og:locale (e.g. <code>en_US</code>).</div>
          </div>
          <div class="mb-0">
            <label class="ab-label">Facebook App ID <span style="opacity:.5;font-weight:400;">(optional)</span></label>
            <input v-model="s.fb_app_id" type="text" class="ab-input font-monospace" style="max-width:280px"
              placeholder="123456789012345">
            <div class="ab-help">Outputs <code>fb:app_id</code> meta tag for Facebook Insights.</div>
          </div>
        </div>
      </div>
    </ProGate>

    <!-- Twitter Cards -->
    <div class="ab-card">
      <div class="ab-card-header">🐦 Twitter / X Cards</div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-3">
          <input v-model="s.enable_twitter_cards" data-ab-field="enable_twitter_cards" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="soc-twitter">
          <label class="ab-check__label" for="soc-twitter">Enable Twitter Card meta tags</label>
        </div>
        <ProGate mode="card" label="Twitter site handle">
          <div class="mb-0">
            <label class="ab-label">Twitter / X Site Handle <span class="ab-pro-tag">Pro</span> <span style="opacity:.5;font-weight:400;">(optional)</span></label>
            <input v-model="s.twitter_site_handle" type="text" class="ab-input" style="max-width:240px"
              placeholder="@yourhandle">
            <div class="ab-help">Outputs <code>twitter:site</code> — your brand's Twitter username.</div>
          </div>
        </ProGate>
      </div>
    </div>

  </div>
</template>


<script>
import TranslationExpander from '../components/TranslationExpander.vue'
import MediaPicker from '../components/MediaPicker.vue'
import ProGate from '../components/ProGate.vue'
import { getCsrfTokenName } from '../api.js'

export default {
  name: 'SocialTab',
  components: { TranslationExpander, MediaPicker, ProGate },
  props: { s: { type: Object, required: true } },

  data() {
    return {
      ogRepairBusy: false,
      ogRepairMsg:  '',
      ogRepairOk:   false,
    }
  },

  methods: {
    async repairOgFields() {
      this.ogRepairBusy = true
      this.ogRepairMsg  = ''
      try {
        const token = getCsrfTokenName()
        const base  = 'index.php?option=com_aiboost&task=settings.repairOgFields&format=json'
        const url   = token ? `${base}&${token}=1` : base
        const res   = await fetch(url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        const data  = await res.json()
        this.ogRepairOk  = !!data.success
        this.ogRepairMsg = data.message || (data.success ? 'Done.' : 'Unknown error.')
      } catch (e) {
        this.ogRepairOk  = false
        this.ogRepairMsg = 'Request failed: ' + e.message
      } finally {
        this.ogRepairBusy = false
      }
    },
  },
}
</script>

<style scoped>
.ab-social-tab { max-width: 860px; }

.ab-pro-tag {
  font-size: .62rem;
  font-weight: 700;
  letter-spacing: .04em;
  text-transform: uppercase;
  color: #b8860b;
  background: #fffbf0;
  border: 1px solid #ffe8a1;
  border-radius: 999px;
  padding: 1px 7px;
  vertical-align: middle;
}

.ab-og-fields-row {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 0 4px;
  border-top: 1px dashed var(--border-color, #dee2e6);
  margin-top: 12px;
  flex-wrap: wrap;
}
.ab-og-fields-info { flex: 1; min-width: 240px; font-size: .875rem; }
.ab-repair-btn { white-space: nowrap; flex-shrink: 0; }
.ab-og-repair-msg { width: 100%; font-size: .82rem; padding: 6px 10px; border-radius: 5px; }
.ab-og-ok  { color: #0f5132; background: #d1e7dd; }
.ab-og-err { color: #842029; background: #f8d7da; }
[data-bs-theme=dark] .ab-pro-tag { background: #2a2000; border-color: #4a3800; }
</style>
