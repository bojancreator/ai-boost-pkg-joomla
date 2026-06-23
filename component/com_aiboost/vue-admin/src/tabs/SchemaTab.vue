<template>
  <div class="ab-settings-tab ab-schema-tab">

    <!-- Section sub-nav — show one group at a time to tame this long tab. -->
    <div class="ab-schema-nav" role="tablist">
      <button
        v-for="sec in visibleSchemaSections" :key="sec.id"
        type="button" role="tab"
        :class="['ab-schema-nav__btn', { 'is-active': schemaSection === sec.id }]"
        :aria-selected="schemaSection === sec.id ? 'true' : 'false'"
        @click="schemaSection = sec.id"
      >{{ sec.label }}</button>
    </div>

    <!-- ═══════════ CORE ═══════════ -->
    <template v-if="schemaSection === 'core'">

      <div class="ab-section">
        <div class="ab-section__head">
          <span class="ab-section__num">01</span>
          Schema.org Core
        </div>
        <div class="ab-section__body">
          <label class="ab-toggle-row">
            <div><div class="ab-label">Enable Schema.org structured data</div></div>
            <span class="ab-toggle" :class="{'is-on': s.enable_schema === '1'}">
              <input v-model="s.enable_schema" data-ab-field="enable_schema" true-value="1" false-value="0"
                type="checkbox" class="ab-toggle__input" id="sch-enable">
              <span class="ab-toggle__track"></span>
            </span>
          </label>
        </div>
      </div>

      <div class="ab-section" data-ab-field="schema_howto">
        <div class="ab-section__head">
          <span class="ab-section__num">02</span>
          WebSite Schema (Homepage)
        </div>
        <div class="ab-section__body">
          <label class="ab-toggle-row">
            <div><div class="ab-label">Enable WebSite Schema (homepage only)</div></div>
            <span class="ab-toggle" :class="{'is-on': s.website_schema_enabled === '1'}">
              <input v-model="s.website_schema_enabled" true-value="1" false-value="0"
                type="checkbox" class="ab-toggle__input" id="sch-website">
              <span class="ab-toggle__track"></span>
            </span>
          </label>
          <label class="ab-toggle-row">
            <div><div class="ab-label">Include SearchAction (Sitelinks Search Box)</div></div>
            <span class="ab-toggle" :class="{'is-on': s.enable_search_action === '1'}">
              <input v-model="s.enable_search_action" data-ab-field="enable_search_action" true-value="1" false-value="0"
                type="checkbox" class="ab-toggle__input" id="sch-search">
              <span class="ab-toggle__track"></span>
            </span>
          </label>
        </div>
      </div>

      <div class="ab-section">
        <div class="ab-section__head">
          <span class="ab-section__num">03</span>
          Article Schema
        </div>
        <div class="ab-section__body">
          <label class="ab-toggle-row">
            <div>
              <div class="ab-label">Enable Article Schema on article pages</div>
              <div class="ab-help">Outputs <code>Article</code> JSON-LD on every Joomla article page. Includes <code>datePublished</code> and <code>dateModified</code> — if an article has never been modified, <code>dateModified</code> automatically falls back to the publication date so AI engines always see a valid freshness signal.</div>
            </div>
            <span class="ab-toggle" :class="{'is-on': s.article_schema_enabled === '1'}">
              <input v-model="s.article_schema_enabled" true-value="1" false-value="0"
                type="checkbox" class="ab-toggle__input" id="sch-article">
              <span class="ab-toggle__track"></span>
            </span>
          </label>
        </div>
      </div>

      <ProGate mode="card" label="Author Entity">
        <div class="ab-section">
          <div class="ab-section__head">
            <span class="ab-section__num">04</span>
            Author Entity
            <span class="ab-tag ab-tag--pro" style="margin-left:.4rem">Pro</span>
          </div>
          <div class="ab-section__body">
            <div class="ab-help">
              When enabled, AI Boost emits a full <code>Person</code> entity for each article's author in <code>Article</code>,
              <code>BlogPosting</code>, and <code>NewsArticle</code> schema — the person AI engines (ChatGPT, Perplexity,
              Google AI Overview) will attribute your content to. This is a strong E-E-A-T signal.
              <br><br>
              <strong>How it works:</strong> AI Boost reads the article's Joomla author (<code>created_by</code>) and pulls
              extra identity data from <strong>Joomla User Custom Fields</strong> on that user's profile.
            </div>

            <label class="ab-toggle-row">
              <div><div class="ab-label">Emit Person entity for each article's author</div></div>
              <span class="ab-toggle" :class="{'is-on': s.schema_author_entity_enabled === '1'}">
                <input v-model="s.schema_author_entity_enabled" data-ab-field="schema_author_entity_enabled"
                  type="checkbox" true-value="1" false-value="0" class="ab-toggle__input">
                <span class="ab-toggle__track"></span>
              </span>
            </label>

            <div class="ab-help" v-if="s.schema_author_entity_enabled === '1'">
              When off, only the author's name is included (basic <code>Person</code> with <code>name</code> only).
            </div>

            <div v-if="s.schema_author_entity_enabled === '1'">
              <div class="ab-help">
                <strong>Set up Joomla User Custom Fields</strong> (Users → Custom Fields → New) using these exact names:
              </div>
              <table class="table table-sm mt-2 mb-0">
                <thead>
                  <tr>
                    <th>Label</th>
                    <th>Field name</th>
                    <th>Type</th>
                    <th>Notes</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>AI Boost: Job Title</td>
                    <td><code>aiboost_job_title</code></td>
                    <td>Text</td>
                    <td>For multilingual sites add <code>aiboost_job_title_en</code>, <code>_de</code>, etc.</td>
                  </tr>
                  <tr>
                    <td>AI Boost: Bio</td>
                    <td><code>aiboost_bio</code></td>
                    <td>Textarea</td>
                    <td>Same per-language suffix pattern.</td>
                  </tr>
                  <tr>
                    <td>AI Boost: Website URL</td>
                    <td><code>aiboost_website</code></td>
                    <td>URL</td>
                    <td>Added to <code>url</code> and <code>sameAs</code>.</td>
                  </tr>
                  <tr>
                    <td>AI Boost: LinkedIn URL</td>
                    <td><code>aiboost_linkedin</code></td>
                    <td>URL</td>
                    <td>Added to <code>sameAs</code>.</td>
                  </tr>
                  <tr>
                    <td>AI Boost: Wikipedia URL</td>
                    <td><code>aiboost_wikipedia</code></td>
                    <td>URL</td>
                    <td>Strong entity disambiguation signal.</td>
                  </tr>
                </tbody>
              </table>
              <div class="ab-author-fields-row mt-2">
                <button type="button" class="ab-btn ab-btn--sm ab-btn--primary"
                  :disabled="authorFieldsBusy" @click="createAuthorFields">
                  <span v-if="authorFieldsBusy">Working…</span>
                  <span v-else>Create author custom fields</span>
                </button>
                <div class="ab-help">
                  Creates all 5 fields (group "AI Boost — Author") in one click. Safe to re-run — existing fields are never overwritten.
                </div>
                <div v-if="authorFieldsMsg" class="ab-author-fields-msg"
                  :class="authorFieldsOk ? 'ab-author-ok' : 'ab-author-err'">
                  {{ authorFieldsMsg }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </ProGate>

    </template>

    <!-- ═══════════ BUSINESS ═══════════ -->
    <template v-if="schemaSection === 'business'">

      <div class="ab-section">
        <div class="ab-section__head">
          <span class="ab-section__num">01</span>
          Business / Organization Type
        </div>
        <div class="ab-section__body">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="ab-field">
                <label class="ab-label">Category</label>
                <select v-model="schemaCategory" @change="onSchemaCategoryChange" class="ab-select">
                  <option v-for="cat in schemaCategories" :key="cat.label" :value="cat.label">{{ cat.label }}</option>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="ab-field">
                <label class="ab-label" :data-ab-field="'schema_type'">Schema Type</label>
                <select v-model="s.schema_type" class="ab-select">
                  <option v-for="opt in categoryTypes" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
              </div>
            </div>
          </div>
          <div class="ab-help">
            Pick a <strong>category</strong>, then the specific <strong>type</strong>. This defines your business
            type for structured data, controls which fields appear below, and enables rich results in Google &amp; AI search.
          </div>
          <div v-if="hasPriceRange" class="ab-field">
            <label class="ab-label">Price Range</label>
            <select v-model="s.specific_price_range" class="ab-select" style="max-width:200px">
              <option value="">— Not specified —</option>
              <option value="$">$ (budget)</option>
              <option value="$$">$$ (moderate)</option>
              <option value="$$$">$$$ (upscale)</option>
              <option value="$$$$">$$$$ (luxury)</option>
            </select>
            <div class="ab-help">Optional price indicator shown in Google search results for local businesses.</div>
          </div>
          <div v-if="selectedTypeMeta" class="ab-schema-type-panel">
            <div class="ab-schema-type-panel__intro">
              <strong>{{ selectedTypeMeta.title }}</strong>
              <p>{{ selectedTypeMeta.description }}</p>
            </div>
            <div class="ab-schema-type-panel__grid">
              <div v-for="field in selectedTypeMeta.fields" :key="field.label" class="ab-schema-type-hint">
                <span class="icon-check" aria-hidden="true"></span>
                <span>
                  <strong>{{ field.label }}</strong>
                  <small>{{ field.text }}</small>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Hotel Details (conditional, no number badge) -->
      <template v-if="isType('LodgingBusiness')">
        <div class="ab-section">
          <div class="ab-section__head">Hotel Details</div>
          <div class="ab-section__body">
            <div class="row g-3">
              <div class="col-md-3">
                <div class="ab-field">
                  <label class="ab-label">Star Rating</label>
                  <select v-model="s.specific_star_rating" class="ab-select">
                    <option value="">—</option>
                    <option v-for="n in 5" :key="n" :value="String(n)">{{ n }} star{{ n > 1 ? 's' : '' }}</option>
                  </select>
                </div>
              </div>
              <div class="col-md-3">
                <div class="ab-field">
                  <label class="ab-label">Check-in Time</label>
                  <input v-model="s.specific_checkin_time" type="time" class="ab-input">
                </div>
              </div>
              <div class="col-md-3">
                <div class="ab-field">
                  <label class="ab-label">Check-out Time</label>
                  <input v-model="s.specific_checkout_time" type="time" class="ab-input">
                </div>
              </div>
              <div class="col-md-3">
                <div class="ab-field">
                  <label class="ab-label">Pets Allowed</label>
                  <select v-model="s.specific_pets_allowed" class="ab-select">
                    <option value="">— Not specified —</option>
                    <option value="true">Yes</option>
                    <option value="false">No</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="ab-field">
              <label class="ab-label">Cuisine / Serves</label>
              <input v-model="s.specific_serves_cuisine" type="text" class="ab-input" style="max-width:340px"
                placeholder="Italian, Mediterranean">
              <div class="ab-help">For restaurants / hotel dining. Outputs <code>servesCuisine</code>.</div>
            </div>
          </div>
        </div>
      </template>

      <!-- Food / Restaurant Details -->
      <div v-if="hasCuisine" class="ab-section">
        <div class="ab-section__head">Food &amp; Restaurant Details</div>
        <div class="ab-section__body">
          <div class="ab-field">
            <label class="ab-label">Cuisine Types</label>
            <input v-model="s.specific_serves_cuisine" type="text" class="ab-input" style="max-width:380px"
              placeholder="Italian, Vegetarian, Mediterranean">
            <div class="ab-help">Comma-separated list of cuisine types.</div>
          </div>
        </div>
      </div>

      <!-- Restaurant booking — menu + reservations -->
      <div v-if="hasRestaurantBooking" class="ab-section">
        <div class="ab-section__head">Menu &amp; Reservations</div>
        <div class="ab-section__body">
          <div class="row g-3">
            <div class="col-md-8">
              <div class="ab-field">
                <label class="ab-label">Menu URL</label>
                <input v-model="s.specific_menu_url" type="url" class="ab-input"
                  placeholder="https://example.com/menu">
                <div class="ab-help">Link to your menu page or PDF. Outputs <code>hasMenu</code>.</div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="ab-field">
                <label class="ab-label">Accepts Reservations</label>
                <select v-model="s.specific_accepts_reservations" class="ab-select">
                  <option value="">— Not specified —</option>
                  <option value="true">Yes</option>
                  <option value="false">No</option>
                </select>
                <div class="ab-help">Outputs <code>acceptsReservations</code>.</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Service details -->
      <div v-if="hasAvailableService" class="ab-section">
        <div class="ab-section__head">{{ serviceLabel }}</div>
        <div class="ab-section__body">
          <div class="ab-field">
            <label class="ab-label">{{ serviceFieldLabel }}</label>
            <input v-model="s.specific_available_service" type="text" class="ab-input" style="max-width:380px"
              :placeholder="servicePlaceholder">
            <div class="ab-help">Outputs <code>availableService</code> in Schema.org.</div>
          </div>
        </div>
      </div>

      <!-- Services & Prices (Pro) -->
      <ProGate v-if="hasAvailableService" mode="card" label="Services &amp; Prices">
        <div class="ab-section" data-ab-field="schema_services">
          <div class="ab-section__head">
            Services &amp; Prices
            <span class="ab-tag ab-tag--pro" style="margin-left:.4rem">Pro</span>
          </div>
          <div class="ab-section__body">
            <div class="ab-help">
              A short, curated list of services you offer — emitted as Schema.org
              <code>makesOffer</code> so AI engines and Google can cite what you do (and the price).
              Keep it focused (5–20 services); this is a services list, <strong>not</strong> a product catalogue.
            </div>
            <div v-if="serviceRows.length" class="ab-svc-head">
              <span class="ab-svc-h-name">Service</span>
              <span class="ab-svc-h-price">Price <em>(optional)</em></span>
              <span class="ab-svc-h-cur">Currency</span>
              <span class="ab-svc-h-del"></span>
            </div>
            <div v-for="(row, idx) in serviceRows" :key="idx" class="ab-svc-row">
              <input v-model="row.name" type="text" class="ab-input ab-svc-name" placeholder="e.g. Oil change">
              <input v-model="row.price" type="text" class="ab-input ab-svc-price" placeholder="49">
              <input v-model="row.currency" type="text" class="ab-input ab-svc-cur" maxlength="3"
                placeholder="EUR" @input="row.currency = row.currency.toUpperCase()">
              <button type="button" class="ab-svc-del" title="Remove service" aria-label="Remove service"
                @click="removeService(idx)">×</button>
            </div>
            <button type="button" class="ab-btn ab-btn--secondary ab-svc-add" @click="addService">+ Add service</button>
            <div class="ab-help">Price &amp; currency are optional. Currency must be a 3-letter ISO code (EUR, USD, RSD…) to be emitted.</div>
            <div v-if="namedServices.length">
              <div v-for="(row, idx) in namedServices" :key="'svc-tr-' + idx" class="ab-faq-trans-group">
                <div class="ab-faq-trans-label">Service #{{ idx + 1 }}: {{ row.name.slice(0, 42) }}{{ row.name.length > 42 ? '…' : '' }}</div>
                <TranslationExpander :field-key="'service_' + idx + '_name'" />
              </div>
            </div>
          </div>
        </div>
      </ProGate>

      <!-- Medical / Dental specialty -->
      <div v-if="hasMedicalSpecialty" class="ab-section">
        <div class="ab-section__head">{{ isType('Dentist') ? 'Dental Specialty' : 'Medical Specialty' }}</div>
        <div class="ab-section__body">
          <div class="ab-field">
            <label class="ab-label">{{ isType('Dentist') ? 'Dental Specialty' : 'Medical Specialty' }}</label>
            <input v-model="s.specific_medical_specialty" type="text" class="ab-input" style="max-width:380px"
              :placeholder="isType('Dentist') ? 'Orthodontics, Implants, Cosmetic Dentistry' : 'Cardiology, Dermatology, General Practice'">
            <div class="ab-help">Primary specialty. Outputs <code>medicalSpecialty</code> in Schema.org.</div>
          </div>
        </div>
      </div>

      <!-- Area served -->
      <template v-if="hasAreaServed">
        <div class="ab-section">
          <div class="ab-section__head">Service Area</div>
          <div class="ab-section__body">
            <div class="ab-field">
              <label class="ab-label">{{ areaServedLabel }}</label>
              <input v-model="s.specific_area_served" type="text" class="ab-input" style="max-width:380px"
                :placeholder="areaServedPlaceholder">
              <div class="ab-help">City, district, region, route, or market names (comma-separated).</div>
            </div>
          </div>
        </div>
      </template>

      <!-- Business operations -->
      <div v-if="hasBusinessOperations" class="ab-section">
        <div class="ab-section__head">{{ operationsLabel }}</div>
        <div class="ab-section__body">
          <div class="row g-3">
            <div v-if="hasPaymentAccepted" class="col-md-6">
              <div class="ab-field">
                <label class="ab-label">Payment Accepted</label>
                <input v-model="s.specific_payment_accepted" type="text" class="ab-input"
                  placeholder="Cash, Credit Card, Bank Transfer">
                <div class="ab-help">Comma-separated payment methods. Outputs <code>paymentAccepted</code>.</div>
              </div>
            </div>
            <div v-if="hasPaymentAccepted" class="col-md-6">
              <div class="ab-field">
                <label class="ab-label">Currencies Accepted</label>
                <input v-model="s.specific_currencies_accepted" type="text" class="ab-input"
                  placeholder="EUR, USD, RSD">
                <div class="ab-help">Comma-separated ISO 4217 currency codes. Outputs <code>currenciesAccepted</code>.</div>
              </div>
            </div>
            <div v-if="hasAmenityFeature" class="col-md-6">
              <div class="ab-field">
                <label class="ab-label">Amenity Features</label>
                <input v-model="s.specific_amenity_feature" type="text" class="ab-input"
                  :placeholder="amenityPlaceholder">
                <div class="ab-help">Comma-separated amenities. Outputs <code>amenityFeature</code>.</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Expertise -->
      <div v-if="hasExpertise" class="ab-section">
        <div class="ab-section__head">Expertise &amp; Topics</div>
        <div class="ab-section__body">
          <div class="ab-field">
            <label class="ab-label">Topics / Expertise</label>
            <input v-model="s.specific_knows_about" type="text" class="ab-input" style="max-width:480px"
              :placeholder="expertisePlaceholder">
            <div class="ab-help">Comma-separated topics. Outputs <code>knowsAbout</code> for entity disambiguation.</div>
          </div>
        </div>
      </div>

      <!-- More Details (Pro) -->
      <ProGate v-if="hasMoreDetails" mode="card" label="More Details">
        <div class="ab-section" data-ab-field="specific_credentials">
          <div class="ab-section__head">
            More Details
            <span class="ab-tag ab-tag--pro" style="margin-left:.4rem">Pro</span>
          </div>
          <div class="ab-section__body">
            <div class="ab-help">Extra type-specific signals AI engines &amp; Google use to understand and cite your business.</div>
            <div class="row g-3">
              <div v-if="hasAcceptingPatients" class="col-md-6">
                <div class="ab-field">
                  <label class="ab-label">Accepting New Patients</label>
                  <select v-model="s.specific_accepting_patients" class="ab-select">
                    <option value="">— Not specified —</option>
                    <option value="true">Yes</option>
                    <option value="false">No</option>
                  </select>
                  <div class="ab-help">Outputs <code>isAcceptingNewPatients</code>.</div>
                </div>
              </div>
              <div v-if="hasRooms" class="col-md-6">
                <div class="ab-field">
                  <label class="ab-label">Number of Rooms</label>
                  <input v-model="s.specific_number_of_rooms" type="number" min="1" class="ab-input" style="max-width:160px" placeholder="e.g. 24">
                  <div class="ab-help">Outputs <code>numberOfRooms</code>.</div>
                </div>
              </div>
              <div v-if="hasCredentials" class="col-12">
                <div class="ab-field">
                  <label class="ab-label">Credentials / Licences</label>
                  <input v-model="s.specific_credentials" type="text" class="ab-input" placeholder="e.g. Board Certified, Bar Licence #12345, ISO 9001">
                  <div class="ab-help">Comma-separated. Outputs <code>hasCredential</code> (trust / E-E-A-T signal).</div>
                </div>
              </div>
              <div v-if="hasLanguages" class="col-12">
                <div class="ab-field">
                  <label class="ab-label">Languages Spoken</label>
                  <input v-model="s.specific_languages" type="text" class="ab-input" placeholder="English, Serbian, German">
                  <div class="ab-help">Comma-separated. Outputs <code>knowsLanguage</code>.</div>
                </div>
              </div>
              <div v-if="hasDiets" class="col-12">
                <div class="ab-field">
                  <label class="ab-label">Suitable for Diets</label>
                  <input v-model="s.specific_diets" type="text" class="ab-input" placeholder="Vegan, Vegetarian, Gluten-free, Halal, Kosher">
                  <div class="ab-help">Comma-separated. Emitted as <code>suitableForDiet</code>.</div>
                </div>
              </div>
              <div v-if="hasReturnPolicy" class="col-12">
                <div class="ab-field">
                  <label class="ab-label">Return Policy <span class="ab-muted">(shows a Google rich result)</span></label>
                  <div class="row g-2">
                    <div class="col-md-5">
                      <select v-model="s.specific_return_category" class="ab-select">
                        <option value="">— Not specified —</option>
                        <option value="MerchantReturnFiniteReturnWindow">Returns within N days</option>
                        <option value="MerchantReturnUnlimitedWindow">Unlimited returns</option>
                        <option value="MerchantReturnNotPermitted">No returns</option>
                      </select>
                    </div>
                    <div v-if="s.specific_return_category === 'MerchantReturnFiniteReturnWindow'" class="col-md-3">
                      <input v-model="s.specific_return_days" type="number" min="1" class="ab-input" placeholder="Days">
                    </div>
                    <div class="col-md-4">
                      <input v-model="s.specific_return_country" type="text" maxlength="2" class="ab-input" placeholder="Country (RS, US…)"
                        @input="s.specific_return_country = s.specific_return_country.toUpperCase()">
                    </div>
                  </div>
                  <div class="ab-help">Outputs <code>hasMerchantReturnPolicy</code>. Google <strong>requires</strong> the 2-letter country — nothing is emitted without it.</div>
                </div>
              </div>
              <div v-if="isBusinessNode" class="col-md-6">
                <div class="ab-field">
                  <label class="ab-label">Number of Employees</label>
                  <input v-model="s.specific_number_of_employees" type="number" min="1" class="ab-input" style="max-width:160px" placeholder="e.g. 12">
                  <div class="ab-help">Outputs <code>numberOfEmployees</code>.</div>
                </div>
              </div>
              <div v-if="hasSmoking" class="col-md-6">
                <div class="ab-field">
                  <label class="ab-label">Smoking Allowed</label>
                  <select v-model="s.specific_smoking_allowed" class="ab-select">
                    <option value="">— Not specified —</option><option value="true">Yes</option><option value="false">No</option>
                  </select>
                  <div class="ab-help">Outputs <code>smokingAllowed</code>.</div>
                </div>
              </div>
              <div v-if="hasDriveThrough" class="col-md-6">
                <div class="ab-field">
                  <label class="ab-label">Drive-Through Service</label>
                  <select v-model="s.specific_drive_through" class="ab-select">
                    <option value="">— Not specified —</option><option value="true">Yes</option><option value="false">No</option>
                  </select>
                  <div class="ab-help">Outputs <code>hasDriveThroughService</code>.</div>
                </div>
              </div>
              <div v-if="hasAccessibleFree" class="col-md-6">
                <div class="ab-field">
                  <label class="ab-label">Free Admission</label>
                  <select v-model="s.specific_accessible_free" class="ab-select">
                    <option value="">— Not specified —</option><option value="true">Yes</option><option value="false">No</option>
                  </select>
                  <div class="ab-help">Outputs <code>isAccessibleForFree</code>.</div>
                </div>
              </div>
              <div v-if="hasAudience" class="col-12">
                <div class="ab-field">
                  <label class="ab-label">Target Audience</label>
                  <input v-model="s.specific_audience" type="text" class="ab-input" placeholder="Families, Couples, Business travellers">
                  <div class="ab-help">Outputs <code>audience</code>.</div>
                </div>
              </div>
              <div v-if="hasBrand" class="col-12">
                <div class="ab-field">
                  <label class="ab-label">Brands Serviced / Sold</label>
                  <input v-model="s.specific_brand" type="text" class="ab-input" placeholder="Toyota, BMW, Audi">
                  <div class="ab-help">Comma-separated. Outputs <code>brand</code>.</div>
                </div>
              </div>
              <div v-if="isBusinessNode" class="col-12">
                <div class="ab-field">
                  <label class="ab-label">Slogan / Tagline</label>
                  <input v-model="s.specific_slogan" type="text" class="ab-input" placeholder="Your memorable tagline">
                  <div class="ab-help">Outputs <code>slogan</code>.</div>
                  <TranslationExpander field-key="schema_slogan" />
                </div>
              </div>
              <div v-if="isBusinessNode" class="col-12">
                <div class="ab-field">
                  <label class="ab-label">Awards</label>
                  <input v-model="s.specific_award" type="text" class="ab-input" placeholder="Best of 2024, Editor's Choice">
                  <div class="ab-help">Comma-separated. Outputs <code>award</code> — "award-winning" is citable.</div>
                  <TranslationExpander field-key="schema_award" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </ProGate>

      <!-- Person profile -->
      <div v-if="isType('Person')" class="ab-section">
        <div class="ab-section__head">Person Profile Details</div>
        <div class="ab-section__body">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="ab-field">
                <label class="ab-label">Job Title</label>
                <input v-model="s.specific_job_title" type="text" class="ab-input"
                  placeholder="Founder, Consultant, Author">
              </div>
            </div>
            <div class="col-md-6">
              <div class="ab-field">
                <label class="ab-label">Affiliation</label>
                <input v-model="s.specific_affiliation" type="text" class="ab-input"
                  placeholder="Company, publication, association">
              </div>
            </div>
            <div class="col-12">
              <div class="ab-field">
                <label class="ab-label">Topics / Expertise</label>
                <input v-model="s.specific_knows_about" type="text" class="ab-input"
                  placeholder="SEO, Joomla, technical writing, off-road travel">
                <div class="ab-help">Comma-separated topics. Outputs <code>knowsAbout</code>.</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- News / media profile -->
      <div v-if="isType('NewsMediaOrganization')" class="ab-section">
        <div class="ab-section__head">News &amp; Media Details</div>
        <div class="ab-section__body">
          <div class="row g-3">
            <div class="col-md-4">
              <div class="ab-field">
                <label class="ab-label">Founding Date</label>
                <input v-model="s.specific_founding_date" type="text" class="ab-input" placeholder="2020 or 2020-05-15">
              </div>
            </div>
            <div class="col-md-4">
              <div class="ab-field">
                <label class="ab-label">Masthead URL</label>
                <input v-model="s.specific_masthead_url" type="url" class="ab-input"
                  placeholder="https://example.com/about/masthead">
              </div>
            </div>
            <div class="col-md-4">
              <div class="ab-field">
                <label class="ab-label">Ethics Policy URL</label>
                <input v-model="s.specific_ethics_policy_url" type="url" class="ab-input"
                  placeholder="https://example.com/editorial-policy">
              </div>
            </div>
          </div>
        </div>
      </div>

    </template>

    <!-- ═══════════ HOURS ═══════════ -->
    <template v-if="schemaSection === 'hours'">

      <div v-if="hasHours" class="ab-section">
        <div class="ab-section__head">
          <span class="ab-section__num">01</span>
          {{ hoursLabel }}
        </div>
        <div class="ab-section__body">
          <div class="ab-eyebrow">Weekly Schedule</div>
          <div class="ab-bh-quickset">
            <button type="button" class="ab-btn ab-btn--sm ab-btn--ghost" @click="setAllDays(true)">All on</button>
            <button type="button" class="ab-btn ab-btn--sm ab-btn--ghost" @click="setAllDays(false)">All off</button>
            <button type="button" class="ab-btn ab-btn--sm ab-btn--ghost" @click="setWeekdays">Weekdays (Mon–Fri)</button>
          </div>
          <div class="ab-bh-table">
            <div v-for="(dayLabel, dk) in days" :key="dk" class="ab-bh-row">
              <div class="ab-bh-day">{{ dayLabel }}</div>
              <div class="ab-bh-closed">
                <label class="d-flex align-items-center gap-2" :for="'bh-' + dk" style="cursor:pointer;margin:0">
                  <span class="ab-toggle" :class="{'is-on': !isClosed(dk)}">
                    <input type="checkbox" class="ab-toggle__input"
                      :id="'bh-' + dk"
                      :checked="!isClosed(dk)"
                      @change="toggleDay(dk, $event.target.checked)">
                    <span class="ab-toggle__track"></span>
                  </span>
                  <span class="ab-bh-status" style="font-size:.8rem;color:var(--ab-text-muted)">{{ isClosed(dk) ? 'Closed' : 'Open' }}</span>
                </label>
              </div>
              <div v-if="!isClosed(dk)" class="ab-bh-times">
                <input type="time" class="ab-input form-control-sm ab-bh-time"
                  :value="s['hours_' + dk + '_opens'] || '09:00'"
                  @change="s['hours_' + dk + '_opens'] = $event.target.value">
                <span class="ab-bh-sep">–</span>
                <input type="time" class="ab-input form-control-sm ab-bh-time"
                  :value="s['hours_' + dk + '_closes'] || '17:00'"
                  @change="s['hours_' + dk + '_closes'] = $event.target.value">
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="hasHours" class="ab-section" data-ab-field="schema_special_hours">
        <div class="ab-section__head">
          <span class="ab-section__num">02</span>
          Holiday / Special Hours
        </div>
        <div class="ab-section__body">
          <div class="ab-help">
            Date-specific exceptions (public holidays, seasonal hours). Emitted as
            <code>specialOpeningHoursSpecification</code> so Google and AI engines show the right hours on those dates.
          </div>
          <div v-for="(row, i) in specialHours" :key="i" class="ab-special-hour-row">
            <input v-model="row.label" type="text" class="ab-input form-control-sm ab-sh-label"
              placeholder="Label (e.g. Christmas Day)">
            <input v-model="row.from" type="date" class="ab-input form-control-sm ab-sh-date" title="Date (or start of range)">
            <span class="ab-bh-sep">→</span>
            <input v-model="row.to" type="date" class="ab-input form-control-sm ab-sh-date"
              title="End date — leave empty for a single day">
            <label class="d-inline-flex align-items-center gap-1 ab-sh-closed" style="cursor:pointer;margin:0">
              <span class="ab-toggle" :class="{'is-on': row.closed}">
                <input v-model="row.closed" type="checkbox" class="ab-toggle__input">
                <span class="ab-toggle__track"></span>
              </span>
              <span style="font-size:.8rem;color:var(--ab-text-muted)">Closed</span>
            </label>
            <template v-if="!row.closed">
              <input v-model="row.opens" type="time" class="ab-input form-control-sm ab-bh-time">
              <span class="ab-bh-sep">–</span>
              <input v-model="row.closes" type="time" class="ab-input form-control-sm ab-bh-time">
            </template>
            <button type="button" class="ab-btn ab-btn--sm ab-btn--ghost ab-btn--danger-ghost ms-1"
              @click="removeSpecialHour(i)" title="Remove">✕</button>
          </div>
          <button type="button" class="ab-btn ab-btn--sm ab-btn--ghost mt-2" @click="addSpecialHour">
            + Add special date
          </button>
        </div>
      </div>

    </template>

    <!-- ═══════════ FAQ ═══════════ -->
    <template v-if="schemaSection === 'faq'">
      <ProGate mode="card" label="FAQ / QAPage">
        <div class="ab-section">
          <div class="ab-section__head">
            <span class="ab-section__num">01</span>
            FAQ / QAPage Schema
            <span class="ab-tag ab-tag--pro" style="margin-left:.4rem">Pro</span>
          </div>
          <div class="ab-section__body">
            <div class="ab-alert ab-alert--info ab-faq-note">
              <p><strong>Google update (May 2026):</strong> FAQPage is no longer a Google rich result.</p>
              <p>It remains a primary schema type actively used by <strong>ChatGPT, Perplexity, and Google AI Overview</strong> for citations.</p>
              <p>So it is still highly valuable for AI search visibility.</p>
            </div>

            <label class="ab-toggle-row">
              <div>
                <div class="ab-label">Auto-Detect FAQ from Content</div>
                <div class="ab-help">Generates <code>FAQPage</code> JSON-LD per page from article content. Used by AI engines for citations.</div>
              </div>
              <span class="ab-toggle" :class="{'is-on': s.faq_auto_detect === '1'}">
                <input v-model="s.faq_auto_detect" true-value="1" false-value="0"
                  type="checkbox" class="ab-toggle__input" id="sch-faq-auto">
                <span class="ab-toggle__track"></span>
              </span>
            </label>

            <div class="ab-field">
              <label class="ab-label">Manual FAQ Items</label>
              <textarea v-model="s.faq_items" data-ab-field="faq_items" class="ab-input font-monospace" rows="6"
                placeholder='[{"question":"Q1","answer":"A1"},{"question":"Q2","answer":"A2"}]'></textarea>
              <div class="ab-help">JSON array of <code>{"question":"…","answer":"…"}</code> objects.</div>
            </div>

            <div>
              <template v-if="parsedFaqItems.length > 0">
                <div v-for="(item, idx) in parsedFaqItems" :key="idx" class="ab-faq-trans-group">
                  <div class="ab-faq-trans-label">
                    FAQ #{{ idx + 1 }}: {{ item.question ? item.question.slice(0, 48) + (item.question.length > 48 ? '…' : '') : 'Question' }}
                  </div>
                  <TranslationExpander :field-key="'faq_' + idx + '_q'" />
                  <TranslationExpander :field-key="'faq_' + idx + '_a'" />
                </div>
              </template>
              <template v-else>
                <TranslationExpander field-key="faq_0_q" />
                <TranslationExpander field-key="faq_0_a" />
              </template>
            </div>

            <div class="ab-field">
              <label class="ab-label" data-ab-field="schema_faq_output_type">Schema Output Type</label>
              <div class="row g-3 ab-faq-output-cards">
                <div v-for="opt in faqOutputOptions" :key="opt.value" class="col-md-4">
                  <button
                    type="button"
                    class="ab-mode-card w-100 text-start"
                    :class="{ 'ab-mode-card--active': s.schema_faq_output_type === opt.value }"
                    @click="s.schema_faq_output_type = opt.value"
                  >
                    <span class="ab-mode-card__head">
                      <span class="ab-mode-card__title">{{ opt.title }}</span>
                      <span v-if="s.schema_faq_output_type === opt.value" class="ab-badge ab-badge--success">Active</span>
                    </span>
                    <span class="ab-mode-card__desc">{{ opt.desc }}</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </ProGate>
    </template>

    <!-- ═══════════ HOWTO ═══════════ -->
    <template v-if="schemaSection === 'howto'">
      <ProGate mode="card" label="HowTo Schema">
        <div class="ab-section">
          <div class="ab-section__head">
            <span class="ab-section__num">01</span>
            HowTo Schema
            <span class="ab-tag ab-tag--pro" style="margin-left:.4rem">Pro</span>
          </div>
          <div class="ab-section__body">
            <div class="ab-help">
              Outputs a <code>HowTo</code> JSON-LD block — ideal for tutorial, recipe-style, or step-by-step guide pages.
              Actively used by ChatGPT and Perplexity to generate step-by-step answers.
            </div>

            <label class="ab-toggle-row">
              <div><div class="ab-label">Enable HowTo Schema</div></div>
              <span class="ab-toggle" :class="{'is-on': howto.enabled === '1'}">
                <input v-model="howto.enabled" true-value="1" false-value="0"
                  type="checkbox" class="ab-toggle__input" id="sch-howto-enabled">
                <span class="ab-toggle__track"></span>
              </span>
            </label>

            <template v-if="howto.enabled === '1'">
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="ab-field">
                    <label class="ab-label">HowTo Name <span style="color:var(--ab-danger)">*</span></label>
                    <input v-model="howto.name" type="text" class="ab-input"
                      placeholder="How to Install AI Boost for Joomla">
                    <TranslationExpander field-key="howto_name" />
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="ab-field">
                    <label class="ab-label">Total Time <span class="ab-muted">(ISO 8601)</span></label>
                    <input v-model="howto.totalTime" type="text" class="ab-input" placeholder="PT15M">
                    <div class="ab-help">e.g. <code>PT15M</code> = 15 min, <code>PT1H</code> = 1 hour, <code>PT1H30M</code> = 1h 30min</div>
                  </div>
                </div>
                <div class="col-12">
                  <div class="ab-field">
                    <label class="ab-label">Description</label>
                    <textarea v-model="howto.description" class="ab-input" rows="2"
                      placeholder="A step-by-step guide to…"></textarea>
                    <TranslationExpander field-key="howto_desc" field-type="textarea" />
                  </div>
                </div>
              </div>

              <div class="ab-eyebrow">Steps</div>
              <div v-for="(step, i) in howto.steps" :key="i" class="ab-howto-step mb-2">
                <div class="ab-howto-num">{{ i + 1 }}</div>
                <div class="flex-grow-1">
                  <input v-model="step.name" type="text" class="ab-input form-control-sm mb-1"
                    :placeholder="'Step ' + (i + 1) + ' name (optional)'">
                  <textarea v-model="step.text" class="ab-input form-control-sm" rows="2"
                    :placeholder="'Describe what the user does in step ' + (i + 1)"></textarea>
                  <TranslationExpander :field-key="'howto_step_' + i + '_name'" />
                  <TranslationExpander :field-key="'howto_step_' + i + '_text'" field-type="textarea" />
                </div>
                <button type="button" class="ab-btn ab-btn--sm ab-btn--ghost ab-btn--danger-ghost ms-2 ab-howto-del"
                  @click="removeStep(i)" title="Remove step">✕</button>
              </div>
              <button type="button" class="ab-btn ab-btn--sm ab-btn--ghost mt-1" @click="addStep">
                + Add Step
              </button>
            </template>
          </div>
        </div>
      </ProGate>
    </template>

    <!-- ═══════════ EVENTS ═══════════ -->
    <template v-if="schemaSection === 'events'">
      <ProGate mode="card" label="Event Schema">
        <div class="ab-section">
          <div class="ab-section__head">
            <span class="ab-section__num">01</span>
            Event Schema
            <span class="ab-tag ab-tag--pro" style="margin-left:.4rem">Pro</span>
          </div>
          <div class="ab-section__body">
            <label class="ab-toggle-row">
              <div><div class="ab-label">Enable Event Schema (rich results in Google, AI Overviews)</div></div>
              <span class="ab-toggle" :class="{'is-on': s.events_enabled === '1'}">
                <input v-model="s.events_enabled" true-value="1" false-value="0"
                  type="checkbox" class="ab-toggle__input" id="sch-events">
                <span class="ab-toggle__track"></span>
              </span>
            </label>

            <div class="ab-field">
              <label class="ab-label">Events Joomla Category ID</label>
              <input v-model="s.events_category_id" type="number" class="ab-input" style="max-width:140px"
                placeholder="0" min="0">
              <div class="ab-help">Joomla category ID containing event articles. Set to <code>0</code> to disable.</div>
            </div>

            <div class="ab-field">
              <label class="ab-label">Event Articles</label>
              <ArticlePicker v-model="s.schema_event_article_ids" />
              <div class="ab-help">
                Search and pick the Joomla articles that describe your events. A per-language description
                expander appears for each below — key: <code>event_{index}_desc</code> (0-based, in the order shown).
              </div>
              <template v-if="parsedEventArticleIds.length > 0">
                <div v-for="(articleId, idx) in parsedEventArticleIds" :key="idx" class="ab-faq-trans-group mt-2">
                  <div class="ab-faq-trans-label">Event #{{ idx }} (article #{{ articleId }}) — description</div>
                  <TranslationExpander :field-key="'event_' + idx + '_desc'" field-type="textarea" />
                </div>
              </template>
            </div>
          </div>
        </div>
      </ProGate>
    </template>

  </div>
</template>

<script>
// Type groups — MIRROR SchemaBuilder.php so the admin shows exactly the fields
// the builder emits per @type. Add a new type to the right group here AND in
// SchemaBuilder.php. (Korak 3.2 #1.)
const FOOD_TYPES = ['Restaurant','CafeOrCoffeeShop','Bakery','BarOrPub','FoodEstablishment']
const MEDICAL_TYPES = ['MedicalClinic','Dentist','Physician','Pharmacy','Hospital','VeterinaryCare']
const LODGING_TYPES = ['LodgingBusiness','BedAndBreakfast','Resort']
const BEAUTY_FITNESS_TYPES = ['BeautySalon','HairSalon','NailSalon','DaySpa','HealthClub','SportsActivityLocation']
const PRO_SERVICE_TYPES = ['ProfessionalService','LegalService','AccountingService','RealEstateAgent']
const FINANCE_TYPES = ['BankOrCreditUnion','FinancialService','InsuranceAgency']

const LOCAL_BUSINESS_TYPES = ['LocalBusiness', ...FOOD_TYPES, ...MEDICAL_TYPES, ...LODGING_TYPES,
  ...BEAUTY_FITNESS_TYPES, ...PRO_SERVICE_TYPES, ...FINANCE_TYPES,
  'EducationalOrganization','ChildCare','AutomotiveBusiness','Store','TouristAttraction']

const AVAILABLE_SERVICE_TYPES = [...MEDICAL_TYPES, ...BEAUTY_FITNESS_TYPES, ...PRO_SERVICE_TYPES,
  ...FINANCE_TYPES, 'EducationalOrganization','AutomotiveBusiness','Store','ChildCare']

const CUISINE_TYPES = [...FOOD_TYPES, 'LodgingBusiness']

const AREA_SERVED_TYPES = LOCAL_BUSINESS_TYPES

const PRICE_RANGE_TYPES = ['LocalBusiness', ...FOOD_TYPES, ...LODGING_TYPES, ...BEAUTY_FITNESS_TYPES,
  'AutomotiveBusiness','Store','TouristAttraction']

const AMENITY_FEATURE_TYPES = [...FOOD_TYPES, ...LODGING_TYPES, ...BEAUTY_FITNESS_TYPES, 'Store','TouristAttraction']

const PAYMENT_ACCEPTED_TYPES = LOCAL_BUSINESS_TYPES

const EXPERTISE_TYPES = ['Person', ...MEDICAL_TYPES, ...PRO_SERVICE_TYPES, ...FINANCE_TYPES, 'EducationalOrganization']

const MEDICAL_SPECIALTY_TYPES = MEDICAL_TYPES

const RESTAURANT_BOOKING_TYPES = FOOD_TYPES

const SERVICE_META = {
  MedicalClinic:           { header: 'Medical Settings',       label: 'Services / Treatments Offered', ph: 'Check-ups, diagnostics, vaccinations…' },
  LegalService:            { header: 'Legal Service Settings', label: 'Legal Practice Area',     ph: 'Criminal Law, Family Law, Corporate Law…'   },
  EducationalOrganization: { header: 'Education Settings',     label: 'Education Level / Type',  ph: 'Primary School, High School, University…'   },
  SportsActivityLocation:  { header: 'Gym / Sports Settings',  label: 'Primary Sport / Activity', ph: 'CrossFit, Boxing, Yoga…'                   },
  Dentist:                 { header: 'Dental Settings',        label: 'Services / Treatments Offered', ph: 'Cleaning, fillings, implants, whitening'  },
  ProfessionalService:     { header: 'Professional Service Settings', label: 'Primary Service', ph: 'Consulting, accounting, design, IT support' },
  AutomotiveBusiness:      { header: 'Automotive Settings',     label: 'Automotive Service',      ph: 'Repair, detailing, tyre service, inspections' },
}

const AREA_SERVED_META = {
  FoodEstablishment:   { label: 'Delivery / Service Area', ph: 'City centre, delivery zone, nearby districts' },
  Restaurant:          { label: 'Delivery / Service Area', ph: 'Belgrade, Vracar, Stari Grad' },
  MedicalClinic:       { label: 'Patient Area Served',     ph: 'Belgrade, New Belgrade, regional patients' },
  LegalService:        { label: 'Jurisdiction / Area',     ph: 'Serbia, Belgrade, remote EU clients' },
  EducationalOrganization: { label: 'Student Area Served', ph: 'Local campus, online students, region' },
  SportsActivityLocation:  { label: 'Member Area Served',  ph: 'City, district, nearby suburbs' },
  Dentist:             { label: 'Patient Area Served',     ph: 'City, district, international patients' },
  RealEstateAgent:      { label: 'Market / Area Served', ph: 'London, Westminster, Canary Wharf' },
  AutomotiveBusiness:   { label: 'Service Area',         ph: 'Belgrade, Novi Sad, motorway corridor' },
  ProfessionalService:  { label: 'Service Area',         ph: 'United Kingdom, London, remote clients' },
  Store:                { label: 'Delivery / Shop Area', ph: 'Berlin, Brandenburg, online EU' },
  TouristAttraction:    { label: 'Region / Destination', ph: 'Zlatibor, Tara National Park, Western Serbia' },
  LocalBusiness:        { label: 'Local Area',           ph: 'City centre, district, region' },
}

const TYPE_META = {
  Organization: {
    title: 'Generic organization profile',
    description: 'Best for companies, associations, SaaS products, agencies, and brands without a public location workflow.',
    fields: [
      { label: 'Identity', text: 'Name, logo, URL, description, phone, email.' },
      { label: 'SameAs', text: 'Add social links and authority profiles.' },
      { label: 'Address', text: 'Use address only when it is public and relevant.' },
    ],
  },
  LocalBusiness: {
    title: 'Local business profile',
    description: 'Use for physical businesses where address, hours, map position, and local service area matter.',
    fields: [
      { label: 'Hours', text: 'Opening hours and temporary closures.' },
      { label: 'Price range', text: 'Budget signal for search and AI summaries.' },
      { label: 'Local area', text: 'Neighborhood, city, or region served.' },
    ],
  },
  FoodEstablishment: {
    title: 'Food establishment profile',
    description: 'Use for restaurants, cafes, bars, bakeries, catering businesses, and food venues.',
    fields: [
      { label: 'Cuisine', text: 'Cuisine types such as Serbian, Italian, vegan.' },
      { label: 'Hours', text: 'Opening hours, holiday closures, and reservations context.' },
      { label: 'Price range', text: 'Optional dining price signal.' },
    ],
  },
  Restaurant: {
    title: 'Restaurant profile',
    description: 'A narrower FoodEstablishment type for restaurants and cafes with menu or reservation intent.',
    fields: [
      { label: 'Cuisine', text: 'Cuisine styles and dietary focus.' },
      { label: 'Hours', text: 'Opening hours and closed days.' },
      { label: 'Price range', text: 'Dining price expectation.' },
    ],
  },
  LodgingBusiness: {
    title: 'Hotel and accommodation profile',
    description: 'Use for hotels, villas, apartments, hostels, cabins, resorts, and other lodging businesses.',
    fields: [
      { label: 'Rating', text: 'Star rating when officially applicable.' },
      { label: 'Times', text: 'Check-in and check-out times.' },
      { label: 'Policy', text: 'Pets allowed and dining cuisine where relevant.' },
    ],
  },
  MedicalClinic: {
    title: 'Medical clinic profile',
    description: 'Use for clinics, practices, doctors, diagnostics, private healthcare, and specialist offices.',
    fields: [
      { label: 'Specialty', text: 'Primary medical specialty or treatment area.' },
      { label: 'Hours', text: 'Office hours and urgent availability.' },
      { label: 'Address', text: 'Public clinic address and contact data.' },
    ],
  },
  LegalService: {
    title: 'Legal service profile',
    description: 'Use for law firms, lawyers, legal advisers, notaries, and legal service offices.',
    fields: [
      { label: 'Practice area', text: 'Family, corporate, criminal, immigration, IP.' },
      { label: 'Area served', text: 'Jurisdiction, city, country, or remote service market.' },
      { label: 'Contact', text: 'Phone, email, address, and opening hours.' },
    ],
  },
  EducationalOrganization: {
    title: 'Education profile',
    description: 'Use for schools, universities, academies, training centres, and course providers.',
    fields: [
      { label: 'Education type', text: 'Primary school, university, language course, bootcamp.' },
      { label: 'Identity', text: 'Campus or organization identity and official URL.' },
      { label: 'Hours', text: 'Public office or admission hours where useful.' },
    ],
  },
  SportsActivityLocation: {
    title: 'Sports and fitness profile',
    description: 'Use for gyms, yoga studios, sports clubs, arenas, courts, and activity locations.',
    fields: [
      { label: 'Activity', text: 'Primary sport, training type, or fitness focus.' },
      { label: 'Hours', text: 'Opening hours and class availability.' },
      { label: 'Price range', text: 'Membership or visit price signal.' },
    ],
  },
  Dentist: {
    title: 'Dental practice profile',
    description: 'Use for dentists, orthodontists, dental clinics, and specialist dental services.',
    fields: [
      { label: 'Specialty', text: 'General dentistry, implants, orthodontics.' },
      { label: 'Hours', text: 'Office hours and emergency availability.' },
      { label: 'Contact', text: 'Phone, address, email, and booking URL.' },
    ],
  },
  RealEstateAgent: {
    title: 'Real estate agency profile',
    description: 'Use for agents, brokerages, property consultants, rentals, and local real-estate offices.',
    fields: [
      { label: 'Area served', text: 'Neighborhoods, districts, cities, or regions.' },
      { label: 'Hours', text: 'Office hours and viewing availability.' },
      { label: 'Contact', text: 'Phone, email, address, and official URL.' },
    ],
  },
  AutomotiveBusiness: {
    title: 'Automotive business profile',
    description: 'Use for repair shops, dealerships, detailing, tyre services, inspections, and car services.',
    fields: [
      { label: 'Service', text: 'Repair, detailing, dealership, MOT, tyre service.' },
      { label: 'Area served', text: 'City, route, or pickup/delivery zone.' },
      { label: 'Hours', text: 'Workshop or showroom opening hours.' },
    ],
  },
  Store: {
    title: 'Store profile',
    description: 'Use for physical shops, retail stores, showrooms, local boutiques, and hybrid online/offline stores.',
    fields: [
      { label: 'Price range', text: 'Retail price tier.' },
      { label: 'Area served', text: 'Delivery area or shop market.' },
      { label: 'Hours', text: 'Opening hours and special closures.' },
    ],
  },
  TouristAttraction: {
    title: 'Tourist attraction profile',
    description: 'Use for destinations, museums, parks, landmarks, tours, and visitor attractions.',
    fields: [
      { label: 'Region', text: 'Destination, region, route, or city.' },
      { label: 'Hours', text: 'Visitor hours and seasonal closures.' },
      { label: 'Price range', text: 'Ticket or visit price signal.' },
    ],
  },
  ProfessionalService: {
    title: 'Professional service profile',
    description: 'Use for consultants, agencies, accountants, architects, designers, IT providers, and B2B services.',
    fields: [
      { label: 'Primary service', text: 'Consulting, accounting, design, support.' },
      { label: 'Area served', text: 'Local, national, international, or remote market.' },
      { label: 'Contact', text: 'Phone, email, URL, and public office data.' },
    ],
  },
  Person: {
    title: 'Person and portfolio profile',
    description: 'Use for personal brands, creators, consultants, authors, and portfolio sites.',
    fields: [
      { label: 'Identity', text: 'Name, URL, image/logo, description.' },
      { label: 'SameAs', text: 'Social profiles and authority pages.' },
      { label: 'Author entity', text: 'Pair with Author Entity for content attribution.' },
    ],
  },
  NewsMediaOrganization: {
    title: 'News and media profile',
    description: 'Use for newsrooms, magazines, publishers, and media organizations.',
    fields: [
      { label: 'Publisher', text: 'Organization identity and logo.' },
      { label: 'Article schema', text: 'Enable article schema for news content.' },
      { label: 'Author entity', text: 'Use author profiles for E-E-A-T signals.' },
    ],
  },

  // ── Food & Drink (siblings of FoodEstablishment / Restaurant) ──
  CafeOrCoffeeShop: {
    title: 'Cafe / coffee shop profile',
    description: 'Use for cafes, coffee shops, and casual venues serving drinks, snacks, and light meals.',
    fields: [
      { label: 'Cuisine', text: 'Coffee, pastries, brunch, and light bites.' },
      { label: 'Hours', text: 'Opening hours and busy periods.' },
      { label: 'Price range', text: 'Casual dining price signal.' },
    ],
  },
  Bakery: {
    title: 'Bakery profile',
    description: 'Use for bakeries, patisseries, and shops selling bread, pastries, and baked goods.',
    fields: [
      { label: 'Products', text: 'Bread, pastries, cakes, and custom orders.' },
      { label: 'Hours', text: 'Opening hours and fresh-batch times.' },
      { label: 'Price range', text: 'Optional price signal.' },
    ],
  },
  BarOrPub: {
    title: 'Bar / pub profile',
    description: 'Use for bars, pubs, taprooms, and venues focused on drinks and social atmosphere.',
    fields: [
      { label: 'Cuisine', text: 'Drinks, bar food, and specialities.' },
      { label: 'Hours', text: 'Opening hours and late nights.' },
      { label: 'Price range', text: 'Optional price signal.' },
    ],
  },

  // ── Health & Medical (siblings of MedicalClinic / Dentist) ──
  Physician: {
    title: 'Physician profile',
    description: 'Use for individual doctors, GPs, and specialist physicians offering consultations and care.',
    fields: [
      { label: 'Specialty', text: 'Primary medical specialty or focus.' },
      { label: 'Hours', text: 'Consultation and clinic hours.' },
      { label: 'Contact', text: 'Phone, address, and booking details.' },
    ],
  },
  Pharmacy: {
    title: 'Pharmacy profile',
    description: 'Use for pharmacies and chemists that dispense medicine and offer health products or advice.',
    fields: [
      { label: 'Services', text: 'Prescriptions, vaccinations, and health checks.' },
      { label: 'Hours', text: 'Opening hours and duty / on-call availability.' },
      { label: 'Access', text: 'Address, contact, and drive-through where relevant.' },
    ],
  },
  Hospital: {
    title: 'Hospital profile',
    description: 'Use for hospitals, medical centres, and inpatient or emergency facilities.',
    fields: [
      { label: 'Departments', text: 'Specialties and emergency services.' },
      { label: 'Hours', text: 'Visiting and emergency availability.' },
      { label: 'Contact', text: 'Address, phone, and department details.' },
    ],
  },
  VeterinaryCare: {
    title: 'Veterinary care profile',
    description: 'Use for veterinary clinics, animal hospitals, and pet care practices.',
    fields: [
      { label: 'Services', text: 'Treatments, surgery, and emergency care.' },
      { label: 'Hours', text: 'Opening hours and out-of-hours cover.' },
      { label: 'Contact', text: 'Address, phone, and booking details.' },
    ],
  },

  // ── Lodging & Travel (siblings of LodgingBusiness) ──
  BedAndBreakfast: {
    title: 'Bed & breakfast profile',
    description: 'Use for B&Bs, guesthouses, and small owner-run accommodation with breakfast included.',
    fields: [
      { label: 'Rating', text: 'Star rating when officially applicable.' },
      { label: 'Times', text: 'Check-in and check-out times.' },
      { label: 'Amenities', text: 'Breakfast, pets policy, and facilities.' },
    ],
  },
  Resort: {
    title: 'Resort profile',
    description: 'Use for resorts and holiday complexes combining lodging with on-site dining, spa, or leisure.',
    fields: [
      { label: 'Rating', text: 'Star rating when officially applicable.' },
      { label: 'Times', text: 'Check-in and check-out times.' },
      { label: 'Amenities', text: 'Pets policy, on-site dining, and facilities.' },
    ],
  },

  // ── Beauty & Fitness (siblings of SportsActivityLocation) ──
  BeautySalon: {
    title: 'Beauty salon profile',
    description: 'Use for beauty salons and studios offering skincare, makeup, and cosmetic treatments.',
    fields: [
      { label: 'Services', text: 'Treatments and beauty services offered.' },
      { label: 'Hours', text: 'Opening hours and appointment availability.' },
      { label: 'Price range', text: 'Optional price signal.' },
    ],
  },
  HairSalon: {
    title: 'Hair salon profile',
    description: 'Use for hairdressers, barbers, and hair studios offering cuts, colour, and styling.',
    fields: [
      { label: 'Services', text: 'Cuts, colour, styling, and treatments.' },
      { label: 'Hours', text: 'Opening hours and appointment availability.' },
      { label: 'Price range', text: 'Optional price signal.' },
    ],
  },
  NailSalon: {
    title: 'Nail salon profile',
    description: 'Use for nail salons and studios offering manicures, pedicures, and nail art.',
    fields: [
      { label: 'Services', text: 'Manicure, pedicure, and nail treatments.' },
      { label: 'Hours', text: 'Opening hours and appointment availability.' },
      { label: 'Price range', text: 'Optional price signal.' },
    ],
  },
  DaySpa: {
    title: 'Day spa profile',
    description: 'Use for day spas and wellness centres offering massage, treatments, and relaxation.',
    fields: [
      { label: 'Services', text: 'Massage, treatments, and wellness packages.' },
      { label: 'Hours', text: 'Opening hours and booking availability.' },
      { label: 'Amenities', text: 'Sauna, pool, and facilities where relevant.' },
    ],
  },
  HealthClub: {
    title: 'Gym / health club profile',
    description: 'Use for gyms, fitness clubs, and health centres with memberships and facilities.',
    fields: [
      { label: 'Activity', text: 'Training focus and on-site facilities.' },
      { label: 'Hours', text: 'Opening hours and class availability.' },
      { label: 'Price range', text: 'Membership or visit price signal.' },
    ],
  },

  // ── Professional Services (sibling of ProfessionalService) ──
  AccountingService: {
    title: 'Accounting service profile',
    description: 'Use for accountants, bookkeepers, tax advisers, and financial-accounting practices.',
    fields: [
      { label: 'Primary service', text: 'Accounting, tax, payroll, advisory.' },
      { label: 'Area served', text: 'Local, national, or remote market.' },
      { label: 'Contact', text: 'Phone, email, URL, and office data.' },
    ],
  },

  // ── Education & Childcare (sibling of EducationalOrganization) ──
  ChildCare: {
    title: 'Childcare profile',
    description: 'Use for nurseries, preschools, daycare, and early-years childcare providers.',
    fields: [
      { label: 'Programme', text: 'Age groups and care programmes.' },
      { label: 'Identity', text: 'Setting name and official URL.' },
      { label: 'Hours', text: 'Opening and drop-off / pick-up hours.' },
    ],
  },

  // ── Finance ──
  BankOrCreditUnion: {
    title: 'Bank / credit union profile',
    description: 'Use for banks, credit unions, and branches offering accounts, loans, and financial services.',
    fields: [
      { label: 'Services', text: 'Accounts, loans, and banking services.' },
      { label: 'Area served', text: 'Branch area or service region.' },
      { label: 'Contact', text: 'Branch address, phone, and hours.' },
    ],
  },
  FinancialService: {
    title: 'Financial service profile',
    description: 'Use for financial advisers, brokers, lenders, and investment or finance providers.',
    fields: [
      { label: 'Services', text: 'Advisory, lending, investment, or planning.' },
      { label: 'Area served', text: 'Local, national, or remote clients.' },
      { label: 'Contact', text: 'Phone, email, URL, and office data.' },
    ],
  },
  InsuranceAgency: {
    title: 'Insurance agency profile',
    description: 'Use for insurance agencies, brokers, and providers offering policies and cover.',
    fields: [
      { label: 'Services', text: 'Insurance lines and cover offered.' },
      { label: 'Area served', text: 'Local, national, or remote clients.' },
      { label: 'Contact', text: 'Phone, email, URL, and office data.' },
    ],
  },
}

function parseHowto(raw) {
  try {
    const d = JSON.parse(raw || '{}')
    if (d && typeof d === 'object') {
      if (!Array.isArray(d.steps)) d.steps = []
      return d
    }
  } catch { /**/ }
  return { enabled: '0', name: '', description: '', totalTime: '', steps: [] }
}

// Parse the stored services JSON into editable rows for the makesOffer repeater.
// Each row is { name, price, currency }; malformed input degrades to an empty list.
function parseServices(raw) {
  try {
    const rows = JSON.parse(raw || '[]')
    if (Array.isArray(rows)) {
      return rows
        .filter(r => r && typeof r === 'object')
        .map(r => ({
          name: String(r.name || ''),
          price: String(r.price || ''),
          currency: String(r.currency || ''),
        }))
    }
  } catch { /**/ }
  return []
}

// Parse the stored special-hours JSON into editable rows. Each row is
// { label, from, to, closed (bool), opens, closes }; emitted server-side as
// specialOpeningHoursSpecification by SchemaBuilder::buildSpecialOpeningHours().
function parseSpecialHours(raw) {
  try {
    const rows = JSON.parse(raw || '[]')
    if (Array.isArray(rows)) {
      return rows
        .filter(r => r && typeof r === 'object')
        .map(r => ({
          label:  String(r.label || ''),
          from:   String(r.from || ''),
          to:     String(r.to || ''),
          closed: r.closed === true || r.closed === '1' || r.closed === 1,
          opens:  String(r.opens || '09:00'),
          closes: String(r.closes || '17:00'),
        }))
    }
  } catch { /**/ }
  return []
}

import TranslationExpander from '../components/TranslationExpander.vue'
import ProGate from '../components/ProGate.vue'
import ArticlePicker from '../components/ArticlePicker.vue'
import { getCsrfTokenName } from '../api.js'

// Two-tier picker: a category groups several schema.org @types. The stored
// value is still the final schema_type (@type); the category is a UI-only
// convenience derived from the saved type on load. Every @type below is wired
// in SchemaBuilder::SCHEMA_TYPE_ALIASES + LOCAL_BUSINESS_TYPES.
const SCHEMA_CATEGORIES = [
  { label: 'Organization / Generic', anchor: 'Organization', types: [
    { value: 'Organization',          label: 'Organization (generic)' },
    { value: 'LocalBusiness',         label: 'Local Business (generic)' },
    { value: 'NewsMediaOrganization', label: 'News / Media' },
  ]},
  { label: 'Food & Drink', anchor: 'FoodEstablishment', types: [
    { value: 'Restaurant',            label: 'Restaurant' },
    { value: 'CafeOrCoffeeShop',      label: 'Cafe / Coffee Shop' },
    { value: 'Bakery',                label: 'Bakery' },
    { value: 'BarOrPub',              label: 'Bar / Pub' },
    { value: 'FoodEstablishment',     label: 'Food Establishment (generic)' },
  ]},
  { label: 'Health & Medical', anchor: 'MedicalClinic', types: [
    { value: 'MedicalClinic',         label: 'Medical Clinic' },
    { value: 'Dentist',               label: 'Dentist' },
    { value: 'Physician',             label: 'Physician / Doctor' },
    { value: 'Pharmacy',              label: 'Pharmacy' },
    { value: 'Hospital',              label: 'Hospital' },
    { value: 'VeterinaryCare',        label: 'Veterinary Care' },
  ]},
  { label: 'Lodging & Travel', anchor: 'LodgingBusiness', types: [
    { value: 'LodgingBusiness',       label: 'Hotel / Accommodation' },
    { value: 'BedAndBreakfast',       label: 'Bed & Breakfast' },
    { value: 'Resort',                label: 'Resort' },
    { value: 'TouristAttraction',     label: 'Tourist Attraction' },
  ]},
  { label: 'Beauty & Fitness', anchor: 'BeautySalon', types: [
    { value: 'BeautySalon',           label: 'Beauty Salon' },
    { value: 'HairSalon',             label: 'Hair Salon' },
    { value: 'NailSalon',             label: 'Nail Salon' },
    { value: 'DaySpa',                label: 'Day Spa' },
    { value: 'HealthClub',            label: 'Gym / Health Club' },
    { value: 'SportsActivityLocation', label: 'Sports / Activity Location' },
  ]},
  { label: 'Professional Services', anchor: 'ProfessionalService', types: [
    { value: 'ProfessionalService',   label: 'Professional Service (generic)' },
    { value: 'LegalService',          label: 'Lawyer / Law Firm' },
    { value: 'AccountingService',     label: 'Accounting Service' },
    { value: 'RealEstateAgent',       label: 'Real Estate Agency' },
  ]},
  { label: 'Retail & Automotive', anchor: 'Store', types: [
    { value: 'Store',                 label: 'Store / Shop' },
    { value: 'AutomotiveBusiness',    label: 'Automotive Business' },
  ]},
  { label: 'Education & Childcare', anchor: 'EducationalOrganization', types: [
    { value: 'EducationalOrganization', label: 'School / University' },
    { value: 'ChildCare',             label: 'Childcare / Preschool' },
  ]},
  { label: 'Finance', anchor: 'BankOrCreditUnion', types: [
    { value: 'BankOrCreditUnion',     label: 'Bank / Credit Union' },
    { value: 'FinancialService',      label: 'Financial Service' },
    { value: 'InsuranceAgency',       label: 'Insurance Agency' },
  ]},
  { label: 'Person', anchor: 'Person', types: [
    { value: 'Person',                label: 'Person / Portfolio' },
  ]},
]

const SCHEMA_TYPE_OPTIONS = SCHEMA_CATEGORIES.flatMap(c => c.types)

export default {
  name: 'SchemaTab',
  components: { TranslationExpander, ProGate, ArticlePicker },
  props: { s: { type: Object, required: true } },

  data() {
    return {
      days:  { mon:'Monday', tue:'Tuesday', wed:'Wednesday', thu:'Thursday', fri:'Friday', sat:'Saturday', sun:'Sunday' },
      howto: parseHowto(this.s.schema_howto),
      serviceRows: parseServices(this.s.schema_services),
      specialHours: parseSpecialHours(this.s.schema_special_hours),
      schemaCategory: '',
      schemaSection: 'core',
      authorFieldsBusy: false,
      authorFieldsMsg: '',
      authorFieldsOk: false,
    }
  },

  created() {
    // Derive the UI category from the saved schema_type (two-tier picker).
    const cat = SCHEMA_CATEGORIES.find(c => c.types.some(t => t.value === this.s.schema_type))
    this.schemaCategory = cat ? cat.label : SCHEMA_CATEGORIES[0].label
  },

  watch: {
    howto: {
      handler(v) { this.s.schema_howto = JSON.stringify(v) },
      deep: true,
    },
    specialHours: {
      handler(v) { this.s.schema_special_hours = JSON.stringify(v) },
      deep: true,
    },
    serviceRows: {
      handler(v) { this.s.schema_services = JSON.stringify(v) },
      deep: true,
    },
    // If the active sub-tab disappears for the new type (only Hours is
    // conditional), fall back to Core so the tab is never blank.
    hasHours(v) {
      if (!v && this.schemaSection === 'hours') this.schemaSection = 'core'
    },
  },

  computed: {
    schemaTypeOptions() {
      return SCHEMA_TYPE_OPTIONS
    },
    schemaCategories() {
      return SCHEMA_CATEGORIES
    },
    categoryTypes() {
      const cat = SCHEMA_CATEGORIES.find(c => c.label === this.schemaCategory)
      return cat ? cat.types : SCHEMA_TYPE_OPTIONS
    },
    parsedFaqItems() {
      try {
        const items = JSON.parse(this.s.faq_items || '[]')
        return Array.isArray(items) ? items : []
      } catch {
        return []
      }
    },
    faqOutputOptions() {
      return [
        { value: 'faqpage', title: 'FAQPage only', desc: 'Recommended — classic format, preferred by ChatGPT, Perplexity & AI Overview for citations.' },
        { value: 'qapage',  title: 'QAPage only',  desc: 'Question → suggestedAnswer → Answer structure; best for forum / user-generated Q&A pages.' },
        { value: 'both',    title: 'Both',         desc: 'Outputs two separate JSON-LD blocks (FAQPage and QAPage) simultaneously.' },
      ]
    },
    // Named services only, in the SAME filtered order the builder emits makesOffer.
    // The translation key service_{idx}_name uses this index so PHP + admin align.
    namedServices() {
      return this.serviceRows.filter(r => (r.name || '').trim() !== '')
    },
    parsedEventArticleIds() {
      const raw = (this.s.schema_event_article_ids || '').toString().trim()
      if (!raw) return []
      return raw.split(',')
        .map(v => parseInt(v.trim(), 10))
        .filter(n => n > 0)
    },
    hasHours() {
      return LOCAL_BUSINESS_TYPES.includes(this.s.schema_type) && this.s.schema_type !== 'NewsMediaOrganization'
    },
    // Sub-tab groups; Hours only appears for types that emit opening hours.
    // FAQ / HowTo / Events are split into their own sub-tabs (one rich-result
    // type per tab) so this long tab stays scannable.
    visibleSchemaSections() {
      return [
        { id: 'core', label: 'Basics' },
        { id: 'business', label: 'Business' },
        ...(this.hasHours ? [{ id: 'hours', label: 'Hours' }] : []),
        { id: 'faq', label: 'FAQ' },
        { id: 'howto', label: 'HowTo' },
        { id: 'events', label: 'Events' },
      ]
    },
    hasAvailableService() {
      return AVAILABLE_SERVICE_TYPES.includes(this.s.schema_type)
    },
      hasPriceRange() {
        return PRICE_RANGE_TYPES.includes(this.s.schema_type)
      },
    hasCuisine() {
      return CUISINE_TYPES.includes(this.s.schema_type)
    },
    hasAreaServed() {
      return AREA_SERVED_TYPES.includes(this.s.schema_type)
    },
    hasPaymentAccepted() {
      return PAYMENT_ACCEPTED_TYPES.includes(this.s.schema_type)
    },
    hasAmenityFeature() {
      return AMENITY_FEATURE_TYPES.includes(this.s.schema_type)
    },
    hasBusinessOperations() {
      return this.hasPaymentAccepted || this.hasAmenityFeature
    },
    hasExpertise() {
      return EXPERTISE_TYPES.includes(this.s.schema_type)
    },
    hasMedicalSpecialty() {
      return MEDICAL_SPECIALTY_TYPES.includes(this.s.schema_type)
    },
    // Faza 2b — per-type Pro detail field gates
    hasAcceptingPatients() {
      return MEDICAL_TYPES.includes(this.s.schema_type)
    },
    hasCredentials() {
      return [...MEDICAL_TYPES, ...PRO_SERVICE_TYPES, ...FINANCE_TYPES, 'EducationalOrganization'].includes(this.s.schema_type)
    },
    hasLanguages() {
      return [...LODGING_TYPES, ...MEDICAL_TYPES, ...PRO_SERVICE_TYPES, ...FINANCE_TYPES, ...BEAUTY_FITNESS_TYPES].includes(this.s.schema_type)
    },
    hasDiets() {
      return FOOD_TYPES.includes(this.s.schema_type)
    },
    hasRooms() {
      return LODGING_TYPES.includes(this.s.schema_type)
    },
    hasReturnPolicy() {
      return this.s.schema_type === 'Store' || LOCAL_BUSINESS_TYPES.includes(this.s.schema_type)
    },
    // Faza 2b (rest) gates
    isBusinessNode() {
      return this.s.schema_type !== 'Person'
    },
    hasSmoking() {
      return [...FOOD_TYPES, ...LODGING_TYPES].includes(this.s.schema_type)
    },
    hasDriveThrough() {
      return [...FOOD_TYPES, 'Pharmacy', 'BankOrCreditUnion'].includes(this.s.schema_type)
    },
    hasAccessibleFree() {
      return this.s.schema_type === 'TouristAttraction'
    },
    hasAudience() {
      return [...LODGING_TYPES, 'TouristAttraction'].includes(this.s.schema_type)
    },
    hasBrand() {
      return this.s.schema_type === 'AutomotiveBusiness'
    },
    hasMoreDetails() {
      return this.hasAcceptingPatients || this.hasCredentials || this.hasLanguages
        || this.hasDiets || this.hasRooms || this.hasReturnPolicy
        || this.hasSmoking || this.hasDriveThrough || this.hasAccessibleFree
        || this.hasAudience || this.hasBrand || this.isBusinessNode
    },
    hasRestaurantBooking() {
      return RESTAURANT_BOOKING_TYPES.includes(this.s.schema_type)
    },
    selectedTypeMeta() {
      const t = this.s.schema_type
      if (TYPE_META[t]) return TYPE_META[t]
      // Never blank: fall back to the type's category anchor, then to Organization.
      const cat = SCHEMA_CATEGORIES.find(c => c.types.some(x => x.value === t))
      return (cat && TYPE_META[cat.anchor]) || TYPE_META.Organization
    },
    serviceLabel()      { return (SERVICE_META[this.s.schema_type] || {}).header || '' },
    serviceFieldLabel() { return (SERVICE_META[this.s.schema_type] || {}).label  || 'Available Service' },
    servicePlaceholder(){ return (SERVICE_META[this.s.schema_type] || {}).ph     || '' },
    areaServedLabel() { return (AREA_SERVED_META[this.s.schema_type] || {}).label || 'Area Served' },
    areaServedPlaceholder() { return (AREA_SERVED_META[this.s.schema_type] || {}).ph || 'London, Westminster, Canary Wharf' },
    operationsLabel() {
      const labels = {
        LocalBusiness: 'Local Business Operations',
        FoodEstablishment: 'Food Ordering & Venue Features',
        Restaurant: 'Dining & Reservation Signals',
        LodgingBusiness: 'Guest Payment & Amenities',
        SportsActivityLocation: 'Membership & Facility Features',
        AutomotiveBusiness: 'Workshop Payment Options',
        Store: 'Store Payment & Pickup Options',
        TouristAttraction: 'Visitor Access & Amenities',
      }
      return labels[this.s.schema_type] || 'Business Operations'
    },
    hoursLabel() {
      const labels = {
        LocalBusiness: 'Local Opening Hours',
        FoodEstablishment: 'Kitchen / Counter Hours',
        Restaurant: 'Dining Hours',
        LodgingBusiness: 'Reception Hours',
        MedicalClinic: 'Clinic Hours',
        LegalService: 'Office / Consultation Hours',
        EducationalOrganization: 'Campus / Admissions Hours',
        SportsActivityLocation: 'Facility / Class Hours',
        Dentist: 'Dental Office Hours',
        RealEstateAgent: 'Viewing / Office Hours',
        AutomotiveBusiness: 'Workshop Hours',
        Store: 'Store Hours',
        TouristAttraction: 'Visitor Hours',
        ProfessionalService: 'Service Hours',
      }
      return labels[this.s.schema_type] || 'Opening Hours'
    },
    amenityPlaceholder() {
      const placeholders = {
        LodgingBusiness: 'Free Wi-Fi, parking, breakfast, pool',
        SportsActivityLocation: 'Showers, lockers, sauna, parking',
        TouristAttraction: 'Guided tours, parking, accessibility',
        Store: 'Parking, wheelchair access, click and collect',
        Restaurant: 'Outdoor seating, reservations, delivery',
        FoodEstablishment: 'Outdoor seating, delivery, takeaway',
      }
      return placeholders[this.s.schema_type] || 'Parking, accessibility, Wi-Fi'
    },
    expertisePlaceholder() {
      const placeholders = {
        MedicalClinic: 'Cardiology, diagnostics, preventive care',
        LegalService: 'Corporate law, family law, immigration',
        EducationalOrganization: 'Language learning, STEM, professional training',
        Dentist: 'Implants, orthodontics, cosmetic dentistry',
        ProfessionalService: 'SEO, accounting, design, IT support',
      }
      return placeholders[this.s.schema_type] || 'Primary topics and expertise'
    },
  },

  methods: {
    isType(t) { return this.s.schema_type === t },

    // When the category changes, keep schema_type valid: if the current type
    // is not in the new category, switch to that category's first type.
    onSchemaCategoryChange() {
      const cat = SCHEMA_CATEGORIES.find(c => c.label === this.schemaCategory)
      if (cat && !cat.types.some(t => t.value === this.s.schema_type)) {
        this.s.schema_type = cat.types[0].value
      }
    },

    // makesOffer repeater — the deep watcher on serviceRows serialises back to
    // s.schema_services automatically, so these just mutate the local array.
    addService() {
      this.serviceRows.push({ name: '', price: '', currency: '' })
    },
    removeService(idx) {
      this.serviceRows.splice(idx, 1)
    },

    // Holiday / special-hours repeater — the deep watcher serialises specialHours
    // back to s.schema_special_hours, so these just mutate the local array.
    addSpecialHour() {
      this.specialHours.push({ label: '', from: '', to: '', closed: false, opens: '09:00', closes: '17:00' })
    },
    removeSpecialHour(i) {
      this.specialHours.splice(i, 1)
    },

    isClosed(dk) {
      return (this.s['hours_' + dk + '_closed'] ?? '0') === '1'
    },

    toggleDay(dk, open) {
      this.s['hours_' + dk + '_closed'] = open ? '0' : '1'
      if (open && !this.s['hours_' + dk + '_opens'])  this.s['hours_' + dk + '_opens']  = '09:00'
      if (open && !this.s['hours_' + dk + '_closes']) this.s['hours_' + dk + '_closes'] = '17:00'
    },

    setAllDays(open) {
      Object.keys(this.days).forEach(dk => this.toggleDay(dk, open))
    },
    setWeekdays() {
      const week = ['mon', 'tue', 'wed', 'thu', 'fri']
      Object.keys(this.days).forEach(dk => this.toggleDay(dk, week.includes(dk)))
    },

    addStep() {
      if (!Array.isArray(this.howto.steps)) this.howto.steps = []
      this.howto.steps.push({ name: '', text: '' })
    },

    removeStep(i) {
      this.howto.steps.splice(i, 1)
    },

    // One-click creation of the 5 author user custom fields (idempotent server-side).
    async createAuthorFields() {
      this.authorFieldsBusy = true
      this.authorFieldsMsg  = ''
      try {
        const token = getCsrfTokenName()
        const base  = 'index.php?option=com_aiboost&task=settings.createAuthorFields&format=json'
        const url   = token ? `${base}&${token}=1` : base
        const res   = await fetch(url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        const data  = await res.json()
        this.authorFieldsOk  = !!data.success
        this.authorFieldsMsg = data.message || (data.success ? 'Done.' : 'Unknown error.')
      } catch (e) {
        this.authorFieldsOk  = false
        this.authorFieldsMsg = 'Request failed: ' + e.message
      } finally {
        this.authorFieldsBusy = false
      }
    },
  },
}
</script>

<style scoped>
/* Schema section sub-nav */
.ab-schema-nav {
  position: sticky;
  top: 0;
  z-index: 5;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  padding: 8px 0 10px;
  margin-bottom: 8px;
  background: var(--ab-surface);
  border-bottom: 1px solid var(--ab-border);
}
.ab-schema-nav__btn {
  padding: 6px 14px;
  font-family: var(--ab-font-mono);
  font-size: var(--ab-font-size-xs);
  font-weight: 600;
  letter-spacing: .025em;
  text-transform: uppercase;
  color: var(--ab-text-muted);
  background: transparent;
  border: 1px solid var(--ab-border);
  border-radius: var(--ab-radius);
  cursor: pointer;
  white-space: nowrap;
  transition: background .12s, color .12s, border-color .12s;
}
.ab-schema-nav__btn:hover {
  color: var(--ab-text);
  border-color: var(--ab-primary);
}
.ab-schema-nav__btn.is-active {
  color: var(--ab-primary);
  background: var(--ab-primary-soft);
  border-color: var(--ab-primary);
}
.ab-faq-trans-group {
  margin-top: 8px;
  padding: 6px 8px;
  background: var(--ab-surface-raised);
  border: 1px solid var(--ab-border);
  border-radius: var(--ab-radius);
}
.ab-faq-trans-label {
  font-size: .78rem;
  font-weight: 600;
  color: var(--ab-text-muted);
  margin-bottom: 4px;
}
/* makesOffer services repeater */
.ab-svc-head,
.ab-svc-row {
  display: grid;
  grid-template-columns: 1fr 120px 90px 32px;
  gap: 8px;
  align-items: center;
}
.ab-svc-head {
  margin-bottom: 4px;
  font-size: .72rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .03em;
  color: var(--ab-text-muted);
}
.ab-svc-head em { font-weight: 400; text-transform: none; letter-spacing: 0; opacity: .8; }
.ab-svc-row { margin-bottom: 8px; }
.ab-svc-cur { text-transform: uppercase; }
.ab-svc-del {
  width: 28px; height: 28px; padding: 0;
  display: inline-flex; align-items: center; justify-content: center;
  background: transparent; color: var(--ab-danger);
  border: 1px solid var(--ab-border); border-radius: var(--ab-radius);
  font-size: 18px; line-height: 1; cursor: pointer;
}
.ab-svc-del:hover { background: rgba(220, 53, 69, .1); border-color: rgba(220, 53, 69, .4); }
.ab-svc-add { margin-top: 2px; }
@media (max-width: 560px) {
  .ab-svc-head { display: none; }
  .ab-svc-row { grid-template-columns: 1fr 1fr; grid-auto-rows: auto; }
  .ab-svc-name { grid-column: 1 / -1; }
}
.ab-schema-type-panel {
  padding: 10px 12px;
  border: 1px solid var(--ab-border);
  border-radius: var(--ab-radius);
  background: var(--ab-surface-raised);
  margin-top: var(--ab-space-3);
}
.ab-schema-type-panel__intro strong {
  display: block;
  margin-bottom: 3px;
}
.ab-schema-type-panel__intro p {
  margin: 0 0 8px;
  color: var(--ab-text-muted);
  font-size: .875rem;
}
.ab-schema-type-panel__grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}
.ab-schema-type-hint {
  display: flex;
  align-items: flex-start;
  gap: 7px;
  min-width: 0;
  padding: 8px;
  border: 1px solid var(--ab-border);
  border-radius: var(--ab-radius);
  background: var(--ab-surface);
}
.ab-schema-type-hint .icon-check {
  flex: 0 0 auto;
  margin-top: 2px;
  color: var(--ab-success);
}
.ab-schema-type-hint strong,
.ab-schema-type-hint small {
  display: block;
}
.ab-schema-type-hint small {
  color: var(--ab-text-muted);
  font-size: .76rem;
  line-height: 1.35;
}
.ab-bh-quickset { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
.ab-faq-note { font-size: var(--ab-font-size-xs); }
.ab-faq-note p { margin: 0 0 .4rem; }
.ab-faq-note p:last-child { margin-bottom: 0; }
.ab-mode-card { display: flex; flex-direction: column; gap: .25rem; padding: 1rem; height: 100%; background: var(--ab-surface-raised); border: 2px solid var(--ab-border); border-radius: var(--ab-radius); cursor: pointer; transition: border-color .15s, box-shadow .15s; }
.ab-mode-card:hover { border-color: var(--ab-primary); }
.ab-mode-card--active { border-color: var(--ab-primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--ab-primary) 15%, transparent); }
.ab-mode-card__head { display: flex; align-items: center; justify-content: space-between; gap: .5rem; margin-bottom: .25rem; }
.ab-mode-card__title { font-weight: 600; }
.ab-mode-card__desc { font-size: .9rem; color: var(--ab-text-muted); }
.ab-bh-table { display: flex; flex-direction: column; gap: 4px; }
.ab-bh-row {
  display: flex; align-items: center; gap: 12px;
  padding: 6px 0; border-bottom: 1px solid var(--ab-border);
}
.ab-bh-row:last-child { border-bottom: none; }
.ab-bh-day    { width: 90px; font-weight: 500; font-size: .875rem; color: var(--ab-text); }
.ab-bh-closed { width: 140px; }
.ab-bh-times  { display: flex; align-items: center; gap: 6px; }
.ab-bh-time   { max-width: 110px; }
.ab-bh-sep    { color: var(--ab-text-muted); }
.ab-special-hour-row {
  display: flex; flex-wrap: wrap; align-items: center; gap: 6px;
  padding: 6px 0; border-bottom: 1px solid var(--ab-border);
}
.ab-special-hour-row:last-of-type { border-bottom: none; }
.ab-sh-label  { flex: 1 1 180px; min-width: 140px; }
.ab-sh-date   { max-width: 160px; }
.ab-sh-closed { flex-shrink: 0; }
/* Author "Create custom fields" result message */
.ab-author-fields-row { display: flex; flex-direction: column; gap: var(--ab-space-2); margin-top: var(--ab-space-3); }
.ab-author-fields-msg { font-size: .85rem; }
.ab-author-ok  { color: var(--ab-success); }
.ab-author-err { color: var(--ab-danger); }
/* HowTo steps */
.ab-howto-step {
  display: flex; align-items: flex-start; gap: 8px;
  padding: 8px 10px;
  border: 1px solid var(--ab-border);
  border-radius: var(--ab-radius);
  background: var(--ab-surface);
}
.ab-howto-num {
  min-width: 24px; height: 24px; line-height: 24px;
  text-align: center; font-weight: 700; font-size: .8rem;
  background: var(--ab-primary); color: #fff; border-radius: 50%;
  margin-top: 4px;
}
.ab-howto-del { align-self: flex-start; margin-top: 2px; }
@media (max-width: 767.98px) {
  .ab-schema-type-panel__grid { grid-template-columns: 1fr; }
}
</style>
