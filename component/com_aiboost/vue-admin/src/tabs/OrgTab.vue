<template>
  <div class="ab-org-tab">

    <!-- Identity -->
    <div class="ab-card">
      <div class="ab-card-header">🏢 Identity</div>
      <div class="ab-card-body">
        <div class="mb-3">
          <label class="ab-label">Organization Name</label>
          <input v-model="s.org_name" data-ab-field="org_name" type="text" class="ab-input" placeholder="Acme Inc.">
          <TranslationExpander field-key="org_name" />
        </div>
        <div class="mb-3">
          <label class="ab-label">Organization Description</label>
          <textarea v-model="s.org_description" class="ab-input" rows="3"
            placeholder="Brief description used in Schema.org markup…"></textarea>
          <TranslationExpander field-key="org_description" />
        </div>
        <div class="mb-0" data-ab-field="org_logo">
          <label class="ab-label">Organization Logo</label>
          <MediaPicker
            v-model="s.org_logo"
            label="Organization logo"
            placeholder="https://example.com/images/logo.png"
            recommended-size="Recommended: square PNG/SVG ≥ 112×112 px."
          />
          <TranslationExpander field-key="org_logo" field-type="media" />
        </div>
      </div>
    </div>

    <!-- Contact -->
    <div class="ab-card">
      <div class="ab-card-header">📞 Contact Information</div>
      <div class="ab-card-body">
        <div class="mb-3">
          <label class="ab-label">Organization URL</label>
          <input v-model="s.org_url" type="url" class="ab-input"
            placeholder="https://example.com">
          <div class="ab-help">Official website URL (auto-detected if empty).</div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="ab-label">Email Address</label>
            <input v-model="s.org_email" type="email" class="ab-input" placeholder="info@example.com">
          </div>
          <div class="col-md-6">
            <label class="ab-label">Phone Number</label>
            <input v-model="s.org_phone" type="tel" class="ab-input" placeholder="+1 212 555 0123">
            <div class="ab-help">E.164 format: <code>+CountryCode Area Number</code></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Social Media -->
    <div class="ab-card">
      <div class="ab-card-header">🔗 Social Media Links</div>
      <div class="ab-card-body">
        <div class="ab-help mb-3">Each URL is included in Schema.org sameAs. Clear to remove.</div>
        <div class="row g-2">
          <div v-for="(meta, key) in socialPlatforms" :key="key" class="col-md-6">
            <label class="ab-label" :style="`color:${meta.color}`">
              <span v-html="meta.icon" style="margin-right:5px;vertical-align:middle"></span>
              {{ meta.label }}
            </label>
            <input v-model="s['social_' + key]" type="url" class="ab-input form-control-sm"
              :placeholder="key === 'twitter' ? 'https://x.com/yourprofile' : 'https://' + key + '.com/yourprofile'">
          </div>
        </div>
      </div>
    </div>

    <!-- Address -->
    <div class="ab-card">
      <div class="ab-card-header">📍 Address</div>
      <div class="ab-card-body">
        <div class="row g-3 mb-3">
          <div class="col-md-5">
            <label class="ab-label">Street Address</label>
            <input v-model="s.org_address_street" type="text" class="ab-input" placeholder="123 Main Street">
            <TranslationExpander field-key="org_address_street" />
          </div>
          <div class="col-md-4">
            <label class="ab-label">City / Locality</label>
            <input v-model="s.org_address_city" type="text" class="ab-input" placeholder="New York">
            <TranslationExpander field-key="org_address_city" />
          </div>
          <div class="col-md-3">
            <label class="ab-label">State / Region</label>
            <input v-model="s.org_address_state" type="text" class="ab-input" placeholder="NY">
          </div>
        </div>
        <div class="row g-3 mb-0">
          <div class="col-md-3">
            <label class="ab-label">Postal Code</label>
            <input v-model="s.org_address_zip" type="text" class="ab-input" placeholder="10001">
          </div>
          <div class="col-md-3">
            <label class="ab-label">Country Code</label>
            <input v-model="s.org_address_country" type="text" class="ab-input"
              placeholder="US" maxlength="2">
            <div class="ab-help">ISO 3166-1 alpha-2</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Geo Coordinates -->
    <div class="ab-card">
      <div class="ab-card-header">🗺 Geographic Coordinates</div>
      <div class="ab-card-body">
        <div class="row g-3">
          <div class="col-md-5">
            <label class="ab-label">Latitude</label>
            <input v-model="s.org_latitude" type="text" class="ab-input" placeholder="44.8178">
          </div>
          <div class="col-md-5">
            <label class="ab-label">Longitude</label>
            <input v-model="s.org_longitude" type="text" class="ab-input" placeholder="20.4569">
          </div>
        </div>
        <div class="ab-help mt-1">
          Find coordinates at
          <a href="https://www.openstreetmap.org" target="_blank" rel="noopener">openstreetmap.org</a>
        </div>
      </div>
    </div>

    <!-- Aggregate Rating -->
    <div class="ab-card">
      <div class="ab-card-header">⭐ Guest / Customer Rating (AggregateRating)</div>
      <div class="ab-card-body">
        <div class="ab-help mb-3" style="color:#6c757d">Adds AggregateRating to Schema.org output. Leave empty if not applicable.</div>
        <div class="row g-3">
          <div class="col-md-2">
            <label class="ab-label">Rating Value</label>
            <input v-model="s.rating_value" type="text" class="ab-input" placeholder="4.8">
          </div>
          <div class="col-md-2">
            <label class="ab-label">Review Count</label>
            <input v-model="s.rating_count" type="number" class="ab-input" placeholder="127" min="0">
          </div>
          <div class="col-md-2">
            <label class="ab-label">Best Rating</label>
            <input v-model="s.rating_best" type="number" class="ab-input" placeholder="5" min="1">
          </div>
          <div class="col-md-2">
            <label class="ab-label">Worst Rating</label>
            <input v-model="s.rating_worst" type="number" class="ab-input" placeholder="1" min="1">
          </div>
          <div class="col-md-4">
            <label class="ab-label">Rating Source</label>
            <input v-model="s.rating_source" type="text" class="ab-input" placeholder="Google Reviews">
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import TranslationExpander from '../components/TranslationExpander.vue'
import MediaPicker from '../components/MediaPicker.vue'

export default {
  name: 'OrgTab',
  components: { TranslationExpander, MediaPicker },
  props: { s: { type: Object, required: true } },

  data() {
    return {
      socialPlatforms: {
        facebook:  { label: 'Facebook',    color: '#1877f2', icon: '<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M24 12.07C24 5.41 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.04V9.41c0-3.02 1.8-4.7 4.54-4.7 1.31 0 2.68.24 2.68.24v2.97h-1.5c-1.5 0-1.96.93-1.96 1.89v2.26h3.32l-.53 3.5h-2.8V24C19.62 23.1 24 18.1 24 12.07"/></svg>' },
        instagram: { label: 'Instagram',   color: '#e1306c', icon: '<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M12 2.16c3.2 0 3.58.01 4.85.07 3.25.15 4.77 1.69 4.92 4.92.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.15 3.23-1.66 4.77-4.92 4.92-1.27.06-1.64.07-4.85.07s-3.58-.01-4.85-.07c-3.26-.15-4.77-1.7-4.92-4.92C2.1 15.58 2.09 15.2 2.09 12s.01-3.58.07-4.85C2.31 3.69 3.85 2.15 7.1 2.23 8.38 2.17 8.77 2.16 12 2.16zm0-2.16C8.74 0 8.33.02 7.05.07 2.7.27.27 2.7.07 7.05.02 8.33 0 8.74 0 12s.02 3.67.07 4.95c.2 4.36 2.62 6.78 6.98 6.98C8.33 23.98 8.74 24 12 24s3.67-.02 4.95-.07c4.35-.2 6.78-2.62 6.98-6.98.05-1.28.07-1.69.07-4.95s-.02-3.67-.07-4.95c-.2-4.35-2.62-6.78-6.98-6.98C15.67.02 15.26 0 12 0zm0 5.84a6.16 6.16 0 1 0 0 12.32A6.16 6.16 0 0 0 12 5.84zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.4-11.85a1.44 1.44 0 1 0 0 2.88 1.44 1.44 0 0 0 0-2.88z"/></svg>' },
        youtube:   { label: 'YouTube',     color: '#ff0000', icon: '<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M23.5 6.19a3.02 3.02 0 0 0-2.12-2.14C19.5 3.67 12 3.67 12 3.67s-7.5 0-9.38.38A3.02 3.02 0 0 0 .5 6.19C.13 8.07 0 10.03 0 12s.13 3.93.5 5.81a3.02 3.02 0 0 0 2.12 2.13C4.5 20.33 12 20.33 12 20.33s7.5 0 9.38-.39a3.02 3.02 0 0 0 2.12-2.13C23.87 15.93 24 13.97 24 12s-.13-3.93-.5-5.81zM9.75 15.02V8.98L15.5 12l-5.75 3.02z"/></svg>' },
        twitter:   { label: 'Twitter / X', color: '#000000', icon: '<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>' },
        linkedin:  { label: 'LinkedIn',    color: '#0a66c2', icon: '<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 23.2 24 22.222 24h.003z"/></svg>' },
        tiktok:    { label: 'TikTok',      color: '#010101', icon: '<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1V9.01a6.29 6.29 0 0 0-.79-.05 6.34 6.34 0 0 0-6.34 6.34 6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.33-6.34V9.05a8.19 8.19 0 0 0 4.78 1.52V7.12a4.85 4.85 0 0 1-1.01-.43z"/></svg>' },
      },
    }
  },
}
</script>

<style scoped>
.ab-org-tab { max-width: 860px; }
</style>
