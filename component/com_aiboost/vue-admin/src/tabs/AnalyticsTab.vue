<template>
  <div class="ab-analytics-tab">

    <!-- Site Verification — Task #473: whole card Pro (GA4 only on Free) -->
    <ProGate gate-key="section:analytics.non_ga4" mode="section">
    <div class="ab-card">
      <div class="ab-card-header">✅ Site Verification</div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-3">
          <input v-model="s.enable_google_verification" data-ab-field="enable_google_verification" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="an-gsc">
          <label class="ab-check__label" for="an-gsc">Enable Google Search Console Verification</label>
        </div>

        <div class="mb-3">
          <label class="ab-label">GSC Verification Codes</label>
          <div v-for="(code, i) in gscCodes" :key="i" class="d-flex align-items-center gap-2 mb-2">
            <input v-model="gscCodes[i]" type="text" class="ab-input font-monospace"
              :data-ab-field="i === 0 ? 'gsc_verification_code' : null"
              style="max-width:500px" placeholder="Paste the content= value from the meta tag">
            <button type="button" class="ab-btn ab-btn--sm ab-btn--ghost ab-btn--danger-ghost" style="min-width:32px"
              @click="removeGscCode(i)" :disabled="gscCodes.length <= 1" title="Remove">−</button>
          </div>
          <button type="button" class="ab-btn ab-btn--sm ab-btn--ghost mt-1" @click="addGscCode">+ Add Verification Code</button>
          <div class="ab-help">Paste the <code>content=</code> value only — without &lt;meta&gt; tags. Free plan: first code only.</div>
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
    </ProGate>

    <!-- Google Analytics 4 — Free tier -->
    <div class="ab-card">
      <div class="ab-card-header">📊 Google Analytics 4</div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-3">
          <input v-model="s.enable_ga4" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="an-ga4">
          <label class="ab-check__label" for="an-ga4">Enable Google Analytics 4</label>
        </div>
        <!-- Task #473 — Free = "Enable GA4" toggle only. Measurement ID and
             GDPR Consent Mode are Pro fields (gated individually so the labels
             still render with a Pro lock instead of vanishing). -->
        <ProGate gate-key="ga4_measurement_id">
          <div class="mb-3">
            <label class="ab-label">GA4 Measurement ID</label>
            <input v-model="s.ga4_measurement_id" data-ab-field="ga4_measurement_id" type="text" class="ab-input font-monospace"
              placeholder="G-XXXXXXXXXX" style="max-width:210px" autocomplete="off">
          </div>
        </ProGate>
        <ProGate gate-key="ga4_consent_mode">
          <div class="mb-0">
            <label class="ab-label">GDPR Consent Mode</label>
            <select v-model="s.ga4_consent_mode" class="ab-select" style="max-width:340px">
              <option value="none">None (Direct inject — no GDPR)</option>
              <option value="gtm">Via GTM (skip direct GA4)</option>
            </select>
            <div class="ab-help">Use <em>Via GTM</em> if you manage GA4 through Google Tag Manager to avoid duplicate tracking.</div>
          </div>
        </ProGate>
      </div>
    </div>

    <!-- Google Tag Manager — Task #473: Pro -->
    <ProGate gate-key="section:analytics.gtm" mode="section">
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
    </ProGate>

  </div>
</template>

<script>
export default {
  name: 'AnalyticsTab',
  props: { s: { type: Object, required: true } },

  data() {
    let gscCodes = ['']
    try {
      const parsed = JSON.parse(this.s.gsc_codes || '[""]')
      gscCodes = Array.isArray(parsed) && parsed.length ? parsed : [this.s.gsc_verification_code || '']
    } catch {}
    if (!gscCodes.length) gscCodes = ['']

    return { gscCodes }
  },

  watch: {
    gscCodes: {
      handler(v) {
        const codes = v.filter(Boolean)
        this.s.gsc_codes = JSON.stringify(codes.length ? codes : [''])
      },
      deep: true,
    },
  },

  methods: {
    addGscCode()     { this.gscCodes.push('') },
    removeGscCode(i) { if (this.gscCodes.length > 1) this.gscCodes.splice(i, 1) },
  },
}
</script>

<style scoped>
.ab-analytics-tab { max-width: 860px; }
</style>
