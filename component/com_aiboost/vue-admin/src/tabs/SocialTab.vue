<template>
  <div class="ab-settings-tab">

    <!-- 01 OpenGraph -->
    <div class="ab-section">
      <div class="ab-section__head">
        <span class="ab-section__num">01</span>
        OpenGraph (Facebook, LinkedIn, WhatsApp)
      </div>
      <div class="ab-section__body">
        <label class="ab-toggle-row">
          <div><div class="ab-label">Enable OpenGraph tags</div></div>
          <span class="ab-toggle" :class="{'is-on': s.enable_opengraph === '1'}">
            <input v-model="s.enable_opengraph" data-ab-field="enable_opengraph" true-value="1" false-value="0"
              type="checkbox" class="ab-toggle__input" id="soc-og">
            <span class="ab-toggle__track"></span>
          </span>
        </label>
        <div class="ab-field" data-ab-field="site_name">
          <label class="ab-label">OG Site Name</label>
          <input v-model="s.site_name" data-ab-field="site_name" type="text" class="ab-input" style="max-width:340px"
            placeholder="Leave empty to use Joomla site name">
          <ProGate mode="field" label="Translate"><TranslationExpander field-key="site_name" /></ProGate>
        </div>
        <div class="ab-field">
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
        <div class="ab-field">
          <label class="ab-label">Default OG Image Alt Text</label>
          <input v-model="s.default_og_image_alt" data-ab-field="default_og_image_alt" type="text" class="ab-input"
            style="max-width:480px" placeholder="Describe the image for accessibility &amp; rich previews">
          <div class="ab-help">Emitted as <code>og:image:alt</code>. Describe what the image shows.</div>
          <ProGate mode="field" label="Translate"><TranslationExpander field-key="default_og_image_alt" /></ProGate>
        </div>
        <div class="ab-field">
          <label class="ab-label">Default OG Description Override</label>
          <textarea v-model="s.og_description_override" data-ab-field="og_description_override"
            class="ab-input" rows="2"
            placeholder="Leave empty to use the page meta description automatically"></textarea>
          <div class="ab-help">Sitewide fallback description for social sharing. Leave empty to auto-detect from page content.</div>
          <ProGate mode="field" label="Translate"><TranslationExpander field-key="og_description_override" field-type="textarea" /></ProGate>
        </div>
        <div class="row g-3">
          <div class="col-md-3">
            <div class="ab-field">
              <label class="ab-label">Default OG Image Width</label>
              <input v-model="s.og_image_width" type="number" class="ab-input" placeholder="1200" min="200" max="4000">
            </div>
          </div>
          <div class="col-md-3">
            <div class="ab-field">
              <label class="ab-label">Default OG Image Height</label>
              <input v-model="s.og_image_height" type="number" class="ab-input" placeholder="630" min="200" max="4000">
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 02 Per-article OG (Pro) -->
    <ProGate mode="card" label="Per-article OG">
      <div class="ab-section">
        <div class="ab-section__head">
          <span class="ab-section__num">02</span>
          Per-article OG
          <span class="ab-tag ab-tag--pro" style="margin-left:.4rem">Pro</span>
        </div>
        <div class="ab-section__body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="ab-toggle-row">
                <div>
                  <div class="ab-label">Use per-article OG image &amp; description</div>
                  <div class="ab-help">Pulls each article's own intro image and description for its OG tags.</div>
                </div>
                <span class="ab-toggle" :class="{'is-on': s.enable_per_article_fields === '1'}">
                  <input v-model="s.enable_per_article_fields" true-value="1" false-value="0"
                    type="checkbox" class="ab-toggle__input" id="soc-per-article">
                  <span class="ab-toggle__track"></span>
                </span>
              </label>
            </div>
            <div class="col-md-6">
              <label class="ab-toggle-row">
                <div>
                  <div class="ab-label">Set og:type = article on article pages</div>
                  <div class="ab-help">Otherwise defaults to <code>website</code>.</div>
                </div>
                <span class="ab-toggle" :class="{'is-on': s.enable_article_og_type === '1'}">
                  <input v-model="s.enable_article_og_type" true-value="1" false-value="0"
                    type="checkbox" class="ab-toggle__input" id="soc-article-type">
                  <span class="ab-toggle__track"></span>
                </span>
              </label>
            </div>
          </div>
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
              <span v-if="ogRepairBusy">Working…</span>
              <span v-else>Create / Repair OG Fields</span>
            </button>
            <div v-if="ogRepairMsg" class="ab-og-repair-msg" :class="ogRepairOk ? 'ab-og-ok' : 'ab-og-err'">
              {{ ogRepairMsg }}
            </div>
          </div>
        </div>
      </div>
    </ProGate>

    <!-- 03 Locale & Facebook (Pro) -->
    <ProGate mode="card" label="OG Locale &amp; Facebook">
      <div class="ab-section">
        <div class="ab-section__head">
          <span class="ab-section__num">03</span>
          Locale &amp; Facebook
          <span class="ab-tag ab-tag--pro" style="margin-left:.4rem">Pro</span>
        </div>
        <div class="ab-section__body">
          <label class="ab-toggle-row">
            <div>
              <div class="ab-label">Add <code>og:locale</code> tag</div>
              <div class="ab-help">Outputs the active Joomla language as og:locale (e.g. <code>en_US</code>).</div>
            </div>
            <span class="ab-toggle" :class="{'is-on': s.enable_og_locale === '1'}">
              <input v-model="s.enable_og_locale" true-value="1" false-value="0"
                type="checkbox" class="ab-toggle__input" id="soc-locale">
              <span class="ab-toggle__track"></span>
            </span>
          </label>
          <div class="ab-field">
            <label class="ab-label">Facebook App ID <span class="ab-muted">(optional)</span></label>
            <input v-model="s.fb_app_id" type="text" class="ab-input font-monospace" style="max-width:280px"
              placeholder="123456789012345">
            <div class="ab-help">Outputs <code>fb:app_id</code> meta tag for Facebook Insights.</div>
          </div>
        </div>
      </div>
    </ProGate>

    <!-- 04 Twitter / X Cards -->
    <div class="ab-section">
      <div class="ab-section__head">
        <span class="ab-section__num">04</span>
        Twitter / X Cards
      </div>
      <div class="ab-section__body">
        <label class="ab-toggle-row">
          <div><div class="ab-label">Enable Twitter Card meta tags</div></div>
          <span class="ab-toggle" :class="{'is-on': s.enable_twitter_cards === '1'}">
            <input v-model="s.enable_twitter_cards" data-ab-field="enable_twitter_cards" true-value="1" false-value="0"
              type="checkbox" class="ab-toggle__input" id="soc-twitter">
            <span class="ab-toggle__track"></span>
          </span>
        </label>
        <ProGate mode="card" label="Twitter site handle">
          <div class="ab-field">
            <label class="ab-label">Twitter / X Site Handle <span class="ab-tag ab-tag--pro">Pro</span> <span class="ab-muted">(optional)</span></label>
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
.ab-og-fields-row {
  display: flex;
  align-items: flex-start;
  gap: var(--ab-space-3);
  padding: var(--ab-space-3) 0 var(--ab-space-1);
  border-top: 1px dashed var(--ab-border);
  margin-top: var(--ab-space-2);
  flex-wrap: wrap;
}
.ab-og-fields-info { flex: 1; min-width: 240px; font-size: var(--ab-font-size-sm); }
.ab-repair-btn { white-space: nowrap; flex-shrink: 0; }
.ab-og-repair-msg { width: 100%; font-size: var(--ab-font-size-xs); padding: .35rem .7rem; border-radius: var(--ab-radius); }
.ab-og-ok  { color: var(--ab-success); background: var(--ab-success-soft); }
.ab-og-err { color: var(--ab-danger);  background: var(--ab-danger-soft); }
</style>
