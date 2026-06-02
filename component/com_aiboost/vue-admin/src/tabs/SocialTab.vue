<template>
  <div class="ab-social-tab">

    <!-- OpenGraph -->
    <div class="ab-card">
      <div class="ab-card-header">📘 OpenGraph (Facebook, LinkedIn, WhatsApp)</div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-3">
          <input v-model="s.enable_opengraph" data-ab-field="enable_opengraph" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="soc-og">
          <label class="ab-check__label" for="soc-og">Enable OpenGraph tags</label>
        </div>
        <div class="mb-3">
          <label class="ab-label">OG Site Name</label>
          <input v-model="s.site_name" data-ab-field="site_name" type="text" class="ab-input" style="max-width:340px"
            placeholder="Leave empty to use Joomla site name">
          <TranslationExpander field-key="site_name" />
        </div>
        <div class="mb-3">
          <label class="ab-label">Default OG Image</label>
          <MediaPicker
            v-model="s.default_og_image" data-ab-field="default_og_image"
            label="Default OG image"
            placeholder="https://example.com/images/og-default.png"
            recommended-size="Recommended: 1200×630 px. Used when no article image is available."
          />
          <TranslationExpander field-key="default_og_image" field-type="media" />
        </div>
        <!-- Task #473 — og_description_override moved to Free tier (Social
             tab now ships OG basic + Twitter + description override on Free). -->
        <div class="mb-3">
          <label class="ab-label">Default OG Description Override</label>
          <textarea v-model="s.og_description_override" data-ab-field="og_description_override"
            class="ab-input" rows="2"
            placeholder="Leave empty to use the page meta description automatically"></textarea>
          <div class="ab-help">Per-language fallback description for social sharing. Leave empty to auto-detect from page content.</div>
        </div>
        <div class="mb-3">
          <TranslationExpander field-key="og_description_override" field-type="textarea" />
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-3">
            <label class="ab-label">Default OG Image Width</label>
            <input v-model="s.og_image_width" type="number" class="ab-input" placeholder="1200" min="200" max="4000">
          </div>
          <div class="col-md-3">
            <label class="ab-label">Default OG Image Height</label>
            <input v-model="s.og_image_height" type="number" class="ab-input" placeholder="630" min="200" max="4000">
          </div>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <div class="ab-check ab-toggle">
              <input v-model="s.enable_per_article_fields" true-value="1" false-value="0"
                type="checkbox" class="ab-toggle__input" id="soc-per-article">
              <label class="ab-check__label" for="soc-per-article">Use per-article OG image &amp; description</label>
            </div>
            <div class="ab-help">Pulls the article intro image and description for OG tags.</div>
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
              AI Boost creates 6 custom fields in <em>Content → Fields → Articles</em>
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

    <!-- OG Locale & Facebook -->
    <div class="ab-card">
      <div class="ab-card-header">🌍 Locale &amp; Facebook</div>
      <div class="ab-card-body">
        <ProGate gate-key="enable_og_locale">
          <div class="ab-check ab-toggle mb-3">
            <input v-model="s.enable_og_locale" true-value="1" false-value="0"
              type="checkbox" class="ab-toggle__input" id="soc-locale">
            <label class="ab-check__label" for="soc-locale">Add <code>og:locale</code> tag</label>
            <div class="ab-help">Outputs the active Joomla language as og:locale (e.g. <code>en_US</code>).</div>
          </div>
        </ProGate>
        <div class="mb-0">
          <label class="ab-label">Facebook App ID <span style="opacity:.5;font-weight:400;">(optional)</span></label>
          <input v-model="s.fb_app_id" type="text" class="ab-input font-monospace" style="max-width:280px"
            placeholder="123456789012345">
          <div class="ab-help">Outputs <code>fb:app_id</code> meta tag for Facebook Insights.</div>
        </div>
      </div>
    </div>

    <!-- Twitter Cards -->
    <div class="ab-card">
      <div class="ab-card-header">🐦 Twitter / X Cards</div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-3">
          <input v-model="s.enable_twitter_cards" data-ab-field="enable_twitter_cards" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="soc-twitter">
          <label class="ab-check__label" for="soc-twitter">Enable Twitter Card meta tags</label>
        </div>
        <div class="mb-0">
          <label class="ab-label">Twitter / X Site Handle <span style="opacity:.5;font-weight:400;">(optional)</span></label>
          <input v-model="s.twitter_site_handle" type="text" class="ab-input" style="max-width:240px"
            placeholder="@yourhandle">
          <div class="ab-help">Outputs <code>twitter:site</code> — your brand's Twitter username.</div>
        </div>
      </div>
    </div>

    <!-- Meta Pixel — Task #473: whole card Pro (Free = OG basic + Twitter only). -->
    <ProGate gate-key="section:social.pixel" mode="section">
    <div class="ab-card">
      <div class="ab-card-header">📣 Meta Pixel (Facebook Ads Tracking)</div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-3">
          <input v-model="s.enable_meta_pixel" data-ab-field="enable_meta_pixel" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="soc-pixel">
          <label class="ab-check__label" for="soc-pixel">Enable Meta (Facebook) Pixel</label>
        </div>

        <div class="mb-3">
          <label class="ab-label">Meta Pixel IDs</label>
          <div v-for="(id, i) in pixelIds" :key="i" class="d-flex align-items-center gap-2 mb-2">
            <input v-model="pixelIds[i]" type="text" class="ab-input font-monospace"
              :data-ab-field="i === 0 ? 'meta_pixel_id' : null"
              style="max-width:260px" placeholder="123456789012345">
            <button type="button" class="ab-btn ab-btn--sm ab-btn--ghost ab-btn--danger-ghost" style="min-width:32px"
              @click="removePixelId(i)" :disabled="pixelIds.length <= 1" title="Remove">−</button>
          </div>
          <button type="button" class="ab-btn ab-btn--sm ab-btn--ghost mt-1" @click="addPixelId">+ Add Pixel ID</button>
          <div class="ab-help">Free plan: first ID only. Up to 5 IDs on paid plans.</div>
        </div>

        <div class="mb-3">
          <label class="ab-label">GDPR Consent Mode</label>
          <select v-model="s.pixel_consent_mode" class="ab-select" style="max-width:340px">
            <option value="none">None (Direct inject — no GDPR)</option>
          </select>
        </div>

      </div>
    </div>
    </ProGate>

    <!-- Meta Pixel Standard Events -->
    <ProGate gate-key="section:social.pixel_events" mode="section">
    <div class="ab-card">
      <div class="ab-card-header">⚡ Meta Pixel Standard Events</div>
      <div class="ab-card-body">
        <p class="ab-help mb-3">Select which standard Meta Pixel events to fire on page load.</p>
        <div style="max-width:640px;overflow-x:auto;">
          <table class="table table-sm table-bordered mb-0" style="font-size:.88em;color:var(--body-color,#212529);background:var(--body-bg,#fff)">
            <thead style="background:var(--secondary-bg,#f8f9fa);color:var(--body-color,#212529)">
              <tr>
                <th style="width:28%">Event</th>
                <th>When to fire</th>
                <th style="width:56px;text-align:center">On</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(desc, ev) in pixelEvents" :key="ev">
                <td class="fw-semibold" style="white-space:nowrap;vertical-align:middle;padding:4px 8px">{{ ev }}</td>
                <td class="text-muted" style="vertical-align:middle;padding:4px 8px;font-size:.83em">{{ desc }}</td>
                <td style="text-align:center;vertical-align:middle;padding:4px 8px">
                  <label class="ab-toggle d-inline-flex justify-content-center mb-0">
                    <input type="checkbox"
                      :checked="pixelEventsMap[ev]"
                      @change="togglePixelEvent(ev, $event.target.checked)">
                    <span class="ab-toggle__track"></span>
                  </label>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    </ProGate>

    <!-- Custom Events -->
    <ProGate gate-key="section:social.pixel_custom_events" mode="section">
    <div class="ab-card">
      <div class="ab-card-header">🎯 Meta Pixel Custom Events</div>
      <div class="ab-card-body">
        <p class="ab-help mb-3">Fire custom pixel events on specific URL patterns.</p>
        <div v-for="(ev, i) in customEvents" :key="i" class="row g-2 mb-2" style="max-width:700px">
          <div class="col-4">
            <input v-model="ev.name" type="text" class="ab-input form-control-sm" placeholder="EventName">
          </div>
          <div class="col-5">
            <input v-model="ev.url" type="text" class="ab-input form-control-sm" placeholder="URL pattern (e.g. /checkout)">
          </div>
          <div class="col-3">
            <button type="button" class="ab-btn ab-btn--sm ab-btn--ghost ab-btn--danger-ghost w-100" @click="removeEvent(i)">Remove</button>
          </div>
        </div>
        <button type="button" class="ab-btn ab-btn--sm ab-btn--ghost mt-1" @click="addEvent">+ Add Custom Event</button>
        <div class="ab-help mt-1">Name: custom event name. URL pattern: fires when page URL contains this string.</div>
      </div>
    </div>
    </ProGate>

  </div>
</template>


<script>
import TranslationExpander from '../components/TranslationExpander.vue'
import MediaPicker from '../components/MediaPicker.vue'

const PIXEL_EVENTS = {
  'Purchase': 'Fire on order confirmation / thank-you page',
  'Lead': 'Fire on lead form submission page',
  'ViewContent': 'Fire on article / product detail pages',
  'Search': 'Fire on search results page',
  'AddToCart': 'Fire when a product is added to cart',
  'AddToWishlist': 'Fire when added to wishlist',
  'InitiateCheckout': 'Fire on checkout start page',
  'AddPaymentInfo': 'Fire on payment info step',
  'CompleteRegistration': 'Fire on registration success page',
  'Contact': 'Fire on contact / enquiry page load',
  'FindLocation': 'Fire on store finder page',
  'Schedule': 'Fire on booking / scheduling page',
  'StartTrial': 'Fire on trial start page',
  'SubmitApplication': 'Fire on application submit page',
  'Subscribe': 'Fire on subscription page',
}

export default {
  name: 'SocialTab',
  components: { TranslationExpander, MediaPicker },
  props: { s: { type: Object, required: true } },

  data() {
    let evMap = {}
    try { evMap = JSON.parse(this.s.meta_pixel_standard_events || '{}') || {} } catch {}

    let customEvents = []
    try { customEvents = JSON.parse(this.s.meta_custom_events || '[]') || [] } catch {}

    let pixelIds = ['']
    try {
      const parsed = JSON.parse(this.s.meta_pixel_ids || '[""]')
      pixelIds = Array.isArray(parsed) && parsed.length ? parsed : [this.s.meta_pixel_id || '']
    } catch {}
    if (!pixelIds.length) pixelIds = ['']

    return {
      pixelEvents: PIXEL_EVENTS,
      pixelEventsMap: evMap,
      customEvents,
      pixelIds,
      ogRepairBusy: false,
      ogRepairMsg:  '',
      ogRepairOk:   false,
    }
  },

  watch: {
    pixelEventsMap: {
      handler(v) { this.s.meta_pixel_standard_events = JSON.stringify(v) },
      deep: true,
    },
    customEvents: {
      handler(v) { this.s.meta_custom_events = JSON.stringify(v) },
      deep: true,
    },
    pixelIds: {
      handler(v) { this.s.meta_pixel_ids = JSON.stringify(v) },
      deep: true,
    },
  },

  methods: {
    togglePixelEvent(ev, on) {
      if (on) this.pixelEventsMap[ev] = true
      else delete this.pixelEventsMap[ev]
      this.pixelEventsMap = { ...this.pixelEventsMap }
    },
    addEvent()        { this.customEvents.push({ name: '', url: '' }) },
    removeEvent(i)    { this.customEvents.splice(i, 1) },
    addPixelId()      { this.pixelIds.push('') },
    removePixelId(i)  { if (this.pixelIds.length > 1) this.pixelIds.splice(i, 1) },

    async repairOgFields() {
      this.ogRepairBusy = true
      this.ogRepairMsg  = ''
      try {
        const cfg = window.aiBoostSettings || {}
        const token = cfg.token || document.querySelector('input[name$="[token]"]')?.value || ''
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
</style>
