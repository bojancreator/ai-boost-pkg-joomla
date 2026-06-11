<template>
  <div class="ab-analytics-tab">

    <!-- Site Verification -->
    <div class="ab-card">
      <div class="ab-card-header">✅ Site Verification</div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-3">
          <input v-model="s.enable_google_verification" data-ab-field="enable_google_verification" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="an-gsc">
          <label class="ab-check__label" for="an-gsc">Enable Google Search Console Verification</label>
        </div>

        <div class="mb-3" data-ab-field="meta_pixel_ids">
          <label class="ab-label">GSC Verification Codes</label>
          <div v-for="(code, i) in gscCodes" :key="i" class="d-flex align-items-center gap-2 mb-2">
            <input v-model="gscCodes[i]" type="text" class="ab-input font-monospace"
              :data-ab-field="i === 0 ? 'gsc_verification_code' : null"
              style="max-width:500px" placeholder="Paste the content= value from the meta tag">
            <button type="button" class="ab-btn ab-btn--sm ab-btn--ghost ab-btn--danger-ghost" style="min-width:32px"
              @click="removeGscCode(i)" :disabled="gscCodes.length <= 1" title="Remove">−</button>
          </div>
          <button type="button" class="ab-btn ab-btn--sm ab-btn--ghost mt-1" @click="addGscCode">+ Add Verification Code</button>
          <div class="ab-help">Paste the <code>content=</code> value only — without &lt;meta&gt; tags.</div>
        </div>

        <div class="mb-3">
          <label class="ab-label">Additional Verification HTML</label>
          <textarea v-model="s.gsc_additional_html" class="ab-input font-monospace" rows="3" style="max-width:500px"
            placeholder="&lt;meta name=&quot;bing-site-verification&quot; content=&quot;XXXXXXXXXX&quot; /&gt;"></textarea>
          <div class="ab-help">Inject additional &lt;meta&gt; verification tags verbatim. One per line.</div>
        </div>

        <div class="mb-0">
          <label class="ab-label">Facebook Domain Verification</label>
          <input v-model="s.fb_domain_verification" data-ab-field="fb_domain_verification" type="text" class="ab-input" style="max-width:380px"
            placeholder="abcdefgh1234567890">
          <div class="ab-help">Content value from Facebook Business Manager domain verification. Emitted as <code>&lt;meta name="facebook-domain-verification"&gt;</code>.</div>
        </div>
      </div>
    </div>

    <!-- Google Analytics 4 -->
    <div class="ab-card">
      <div class="ab-card-header">📊 Google Analytics 4</div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-3">
          <input v-model="s.enable_ga4" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="an-ga4">
          <label class="ab-check__label" for="an-ga4">Enable Google Analytics 4</label>
        </div>
        <!-- Measurement ID and consent mode remain editable in the one-product admin. -->
          <div class="mb-3">
            <label class="ab-label">GA4 Measurement ID</label>
            <input v-model="s.ga4_measurement_id" data-ab-field="ga4_measurement_id" type="text" class="ab-input font-monospace"
              placeholder="G-XXXXXXXXXX" style="max-width:210px" autocomplete="off">
          </div>
          <div class="mb-0">
            <label class="ab-label">GDPR Consent Mode</label>
            <select v-model="s.ga4_consent_mode" class="ab-select" style="max-width:340px">
              <option value="none">None (Direct inject — no GDPR)</option>
              <option value="gtm">Via GTM (skip direct GA4)</option>
              <option value="yootheme">YooTheme Pro Consent Manager (Consent Mode v2)</option>
              <option value="default_denied">Consent denied by default (custom CMP)</option>
            </select>
            <div class="ab-help">
              <em>Via GTM</em> if you manage GA4 through Google Tag Manager (avoids duplicate tracking).
              <em>YooTheme Pro Consent Manager</em> defers tracking until the visitor consents via YooTheme's
              cookie banner. <em>Consent denied by default</em> sets Consent Mode v2 to denied for a custom CMP.
            </div>
          </div>
      </div>
    </div>

    <!-- Google Tag Manager -->
    <div class="ab-card">
      <div class="ab-card-header">🏷 Google Tag Manager</div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-3">
          <input v-model="s.enable_gtm" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="an-gtm">
          <label class="ab-check__label" for="an-gtm">Enable Google Tag Manager</label>
        </div>
        <div class="mb-0">
          <label class="ab-label">GTM Container ID</label>
          <input v-model="s.gtm_container_id" data-ab-field="gtm_container_id" type="text" class="ab-input font-monospace"
            placeholder="GTM-XXXXXXX" style="max-width:180px" autocomplete="off">
        </div>
      </div>
    </div>

    <!-- Meta Pixel + events (Pro) -->
    <ProGate mode="card" label="Meta Pixel">
    <div class="ab-card">
      <div class="ab-card-header">📣 Meta Pixel <span class="ab-pro-tag">Pro</span></div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-3">
          <input v-model="s.enable_meta_pixel" data-ab-field="enable_meta_pixel" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="an-pixel">
          <label class="ab-check__label" for="an-pixel">Enable Meta Pixel</label>
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
          <div class="ab-help">Add one or more Meta Pixel IDs for this site.</div>
        </div>

        <div class="mb-0">
          <label class="ab-label">Consent Mode</label>
          <select v-model="s.pixel_consent_mode" class="ab-select" style="max-width:340px">
            <option value="none">None (direct inject)</option>
            <option value="consent_required">Consent required (revoke until granted)</option>
          </select>
          <div class="ab-help">
            <em>Consent required</em> emits <code>fbq('consent', 'revoke')</code> so the pixel holds events
            until your consent manager calls <code>fbq('consent', 'grant')</code>.
          </div>
        </div>
      </div>
    </div>

    <!-- Meta Pixel Standard Events -->
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

    <!-- Meta Pixel Custom Events -->
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
import ProGate from '../components/ProGate.vue'

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

function normalizeGscCodes(settings) {
  let codes = ['']

  try {
    const parsed = JSON.parse(settings.gsc_codes || '[""]')
    codes = Array.isArray(parsed) && parsed.length ? parsed : [settings.gsc_verification_code || '']
  } catch {}

  return codes.length ? codes : ['']
}

function normalizePixelIds(settings) {
  let pixelIds = ['']
  try {
    const parsed = JSON.parse(settings.meta_pixel_ids || '[""]')
    pixelIds = Array.isArray(parsed) && parsed.length ? parsed : [settings.meta_pixel_id || '']
  } catch {}
  return pixelIds.length ? pixelIds : ['']
}

export default {
  name: 'AnalyticsTab',
  components: { ProGate },
  props: { s: { type: Object, required: true } },

  data() {
    let evMap = {}
    try { evMap = JSON.parse(this.s.meta_pixel_standard_events || '{}') || {} } catch {}

    let customEvents = []
    try { customEvents = JSON.parse(this.s.meta_custom_events || '[]') || [] } catch {}

    return {
      gscCodes: normalizeGscCodes(this.s),
      pixelEvents: PIXEL_EVENTS,
      pixelEventsMap: evMap,
      customEvents,
      pixelIds: normalizePixelIds(this.s),
    }
  },

  watch: {
    gscCodes: {
      handler(v) {
        const codes = v.filter(Boolean)
        this.s.gsc_codes = JSON.stringify(codes.length ? codes : [''])
      },
      deep: true,
    },
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
    addGscCode()     { this.gscCodes.push('') },
    removeGscCode(i) { if (this.gscCodes.length > 1) this.gscCodes.splice(i, 1) },
    togglePixelEvent(ev, on) {
      if (on) this.pixelEventsMap[ev] = true
      else delete this.pixelEventsMap[ev]
      this.pixelEventsMap = { ...this.pixelEventsMap }
    },
    addEvent()        { this.customEvents.push({ name: '', url: '' }) },
    removeEvent(i)    { this.customEvents.splice(i, 1) },
    addPixelId()      { this.pixelIds.push('') },
    removePixelId(i)  { if (this.pixelIds.length > 1) this.pixelIds.splice(i, 1) },
  },
}
</script>

<style scoped>
.ab-analytics-tab { max-width: 860px; }
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
[data-bs-theme=dark] .ab-pro-tag { background: #2a2000; border-color: #4a3800; }
</style>
