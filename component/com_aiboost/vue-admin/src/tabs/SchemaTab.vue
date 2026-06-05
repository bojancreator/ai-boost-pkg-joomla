<template>
  <div class="ab-schema-tab">

    <!-- Core -->
    <div class="ab-card">
      <div class="ab-card-header">⚙️ Schema.org Core</div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-3">
          <input v-model="s.enable_schema" data-ab-field="enable_schema" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="sch-enable">
          <label class="ab-check__label" for="sch-enable">Enable Schema.org structured data</label>
        </div>
        <div class="ab-check ab-toggle mb-0">
          <input v-model="s.page_type_auto_detect" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="sch-auto">
          <label class="ab-check__label" for="sch-auto">Auto-detect page type (recommended)</label>
        </div>
      </div>
    </div>

    <!-- WebSite Schema -->
    <div class="ab-card" data-ab-field="schema_howto">
      <div class="ab-card-header">🌐 WebSite Schema (Homepage)</div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-2">
          <input v-model="s.website_schema_enabled" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="sch-website">
          <label class="ab-check__label" for="sch-website">Enable WebSite Schema (homepage only)</label>
        </div>
        <div class="ab-check ab-toggle mb-0">
          <input v-model="s.enable_search_action" data-ab-field="enable_search_action" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="sch-search">
          <label class="ab-check__label" for="sch-search">Include SearchAction (Sitelinks Search Box)</label>
        </div>
      </div>
    </div>

    <!-- Article Schema -->
    <div class="ab-card">
      <div class="ab-card-header">📝 Article Schema</div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-0">
          <input v-model="s.article_schema_enabled" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="sch-article">
          <label class="ab-check__label" for="sch-article">Enable Article Schema on article pages</label>
          <div class="ab-help">Outputs <code>Article</code> JSON-LD on every Joomla article page. Includes <code>datePublished</code> and <code>dateModified</code> — if an article has never been modified, <code>dateModified</code> automatically falls back to the publication date so AI engines always see a valid freshness signal.</div>
        </div>
      </div>
    </div>

    <!-- Author Entity -->
    <div class="ab-card">
      <div class="ab-card-header">👤 Author Entity</div>
      <div class="ab-card-body">
        <div class="ab-help mb-3">
          When enabled, AI Boost emits a full <code>Person</code> entity for each article's author in <code>Article</code>,
          <code>BlogPosting</code>, and <code>NewsArticle</code> schema — the person AI engines (ChatGPT, Perplexity,
          Google AI Overview) will attribute your content to. This is a strong E-E-A-T signal.
          <br><br>
          <strong>How it works:</strong> AI Boost reads the article's Joomla author (<code>created_by</code>) and pulls
          extra identity data from <strong>Joomla User Custom Fields</strong> on that user's profile. Each article gets
          <em>its own</em> author entity — perfect for multi-author sites.
        </div>

        <div class="row g-3">
          <div class="col-md-12">
            <label class="ab-label d-flex align-items-center gap-2">
              <input v-model="s.schema_author_entity_enabled" data-ab-field="schema_author_entity_enabled"
                type="checkbox" true-value="1" false-value="0" class="ab-toggle__input">
              Emit Person entity for each article's author
            </label>
            <div class="ab-help">
              When off, only the author's name is included (basic <code>Person</code> with <code>name</code> only).
            </div>
          </div>
        </div>

        <div class="ab-help mt-3" v-if="s.schema_author_entity_enabled === '1'">
          <strong>Set up Joomla User Custom Fields</strong> (Users → Custom Fields → New) using these exact names so
          AI Boost can pick them up:
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
          <div class="mt-2">
            All fields are optional. If a user has none of them, AI Boost falls back to a basic <code>Person</code> with
            just <code>name</code>.
          </div>
        </div>
      </div>
    </div>

    <!-- Business / Organization Type -->
    <div class="ab-card">
      <div class="ab-card-header">🏪 Business / Organization Type</div>
      <div class="ab-card-body">
        <div class="mb-3">
          <label class="ab-label" :data-ab-field="'schema_type'">Schema Type</label>
          <select v-model="s.schema_type" class="ab-select" style="max-width:320px">
            <option v-for="opt in schemaTypeOptions"
                    :key="opt.value"
                    :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
          <small class="ab-help text-muted">
            Defines your business type for structured data. Controls which additional schema fields appear below and enables rich results in Google &amp; AI search engines.
          </small>
        </div>
        <div v-if="hasPriceRange" class="mb-0">
          <label class="ab-label">Price Range</label>
          <select v-model="s.specific_price_range" class="ab-select" style="max-width:200px">
            <option value="">— Not specified —</option>
            <option value="$">$ (budget)</option>
            <option value="$$">$$ (moderate)</option>
            <option value="$$$">$$$ (upscale)</option>
            <option value="$$$$">$$$$ (luxury)</option>
          </select>
          <small class="ab-help text-muted">Optional price indicator shown in Google search results for local businesses. Leave blank if not applicable.</small>
        </div>

        <div v-if="selectedTypeMeta" class="ab-schema-type-panel mt-3">
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

    <!-- Hotel Details -->
    <template v-if="isType('LodgingBusiness')">
    <div class="ab-card">
      <div class="ab-card-header">🏨 Hotel Details</div>
      <div class="ab-card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="ab-label">Star Rating</label>
            <select v-model="s.specific_star_rating" class="ab-select">
              <option value="">—</option>
              <option v-for="n in 5" :key="n" :value="String(n)">{{ n }} star{{ n > 1 ? 's' : '' }}</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="ab-label">Check-in Time</label>
            <input v-model="s.specific_checkin_time" type="time" class="ab-input">
          </div>
          <div class="col-md-3">
            <label class="ab-label">Check-out Time</label>
            <input v-model="s.specific_checkout_time" type="time" class="ab-input">
          </div>
          <div class="col-md-3">
            <label class="ab-label">Pets Allowed</label>
            <select v-model="s.specific_pets_allowed" class="ab-select">
              <option value="">— Not specified —</option>
              <option value="true">Yes</option>
              <option value="false">No</option>
            </select>
          </div>
        </div>
        <div class="mt-3">
          <label class="ab-label">Cuisine / Serves</label>
          <input v-model="s.specific_serves_cuisine" type="text" class="ab-input" style="max-width:340px"
            placeholder="Italian, Mediterranean">
          <div class="ab-help">For restaurants / hotel dining. Outputs <code>servesCuisine</code>.</div>
        </div>
      </div>
    </div>
    </template>

    <!-- Food / Restaurant -->
    <div v-if="hasCuisine" class="ab-card">
      <div class="ab-card-header">🍽 Food & Restaurant Details</div>
      <div class="ab-card-body">
        <label class="ab-label">Cuisine Types</label>
        <input v-model="s.specific_serves_cuisine" type="text" class="ab-input" style="max-width:380px"
          placeholder="Italian, Vegetarian, Mediterranean">
        <div class="ab-help">Comma-separated list of cuisine types.</div>
      </div>
    </div>

    <!-- Service details — shared specific_available_service -->
    <div v-if="hasAvailableService" class="ab-card">
      <div class="ab-card-header">{{ serviceLabel }}</div>
      <div class="ab-card-body">
        <label class="ab-label">{{ serviceFieldLabel }}</label>
        <input v-model="s.specific_available_service" type="text" class="ab-input" style="max-width:380px"
          :placeholder="servicePlaceholder">
        <div class="ab-help">Outputs <code>availableService</code> in Schema.org.</div>
      </div>
    </div>

    <!-- Area served -->
    <template v-if="hasAreaServed">
    <div class="ab-card">
      <div class="ab-card-header">📍 Service Area</div>
      <div class="ab-card-body">
        <label class="ab-label">{{ areaServedLabel }}</label>
        <input v-model="s.specific_area_served" type="text" class="ab-input" style="max-width:380px"
          :placeholder="areaServedPlaceholder">
        <div class="ab-help">City, district, region, route, or market names (comma-separated).</div>
      </div>
    </div>
    </template>

    <!-- Business operations -->
    <div v-if="hasBusinessOperations" class="ab-card">
      <div class="ab-card-header">{{ operationsLabel }}</div>
      <div class="ab-card-body">
        <div class="row g-3">
          <div v-if="hasPaymentAccepted" class="col-md-6">
            <label class="ab-label">Payment Accepted</label>
            <input v-model="s.specific_payment_accepted" type="text" class="ab-input"
              placeholder="Cash, Credit Card, Bank Transfer">
            <div class="ab-help">Comma-separated payment methods. Outputs <code>paymentAccepted</code>.</div>
          </div>
          <div v-if="hasAmenityFeature" class="col-md-6">
            <label class="ab-label">Amenity Features</label>
            <input v-model="s.specific_amenity_feature" type="text" class="ab-input"
              :placeholder="amenityPlaceholder">
            <div class="ab-help">Comma-separated amenities. Outputs <code>amenityFeature</code>.</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Expertise -->
    <div v-if="hasExpertise" class="ab-card">
      <div class="ab-card-header">🎯 Expertise & Topics</div>
      <div class="ab-card-body">
        <label class="ab-label">Topics / Expertise</label>
        <input v-model="s.specific_knows_about" type="text" class="ab-input" style="max-width:480px"
          :placeholder="expertisePlaceholder">
        <div class="ab-help">Comma-separated topics. Outputs <code>knowsAbout</code> for entity disambiguation.</div>
      </div>
    </div>

    <!-- Person profile -->
    <div v-if="isType('Person')" class="ab-card">
      <div class="ab-card-header">👤 Person Profile Details</div>
      <div class="ab-card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="ab-label">Job Title</label>
            <input v-model="s.specific_job_title" type="text" class="ab-input"
              placeholder="Founder, Consultant, Author">
          </div>
          <div class="col-md-6">
            <label class="ab-label">Affiliation</label>
            <input v-model="s.specific_affiliation" type="text" class="ab-input"
              placeholder="Company, publication, association">
          </div>
          <div class="col-12">
            <label class="ab-label">Topics / Expertise</label>
            <input v-model="s.specific_knows_about" type="text" class="ab-input"
              placeholder="SEO, Joomla, technical writing, off-road travel">
            <div class="ab-help">Comma-separated topics. Outputs <code>knowsAbout</code>.</div>
          </div>
        </div>
      </div>
    </div>

    <!-- News / media profile -->
    <div v-if="isType('NewsMediaOrganization')" class="ab-card">
      <div class="ab-card-header">📰 News & Media Details</div>
      <div class="ab-card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="ab-label">Founding Date</label>
            <input v-model="s.specific_founding_date" type="text" class="ab-input"
              placeholder="2020 or 2020-05-15">
          </div>
          <div class="col-md-4">
            <label class="ab-label">Masthead URL</label>
            <input v-model="s.specific_masthead_url" type="url" class="ab-input"
              placeholder="https://example.com/about/masthead">
          </div>
          <div class="col-md-4">
            <label class="ab-label">Ethics Policy URL</label>
            <input v-model="s.specific_ethics_policy_url" type="url" class="ab-input"
              placeholder="https://example.com/editorial-policy">
          </div>
        </div>
      </div>
    </div>

    <!-- Opening Hours (local business types) -->
    <div v-if="hasHours" class="ab-card">
      <div class="ab-card-header">{{ hoursLabel }}</div>
      <div class="ab-card-body">
        <div class="mb-3">
          <label class="ab-label">Opening Hours Mode</label>
          <select v-model="s.schema_hours_mode" class="ab-select" style="max-width:320px">
            <option value="simple">Simple (one text line)</option>
            <option value="advanced">Advanced — day-by-day schedule</option>
            <option value="none">Not applicable / Hide</option>
          </select>
        </div>

        <div v-if="s.schema_hours_mode === 'simple'" class="mb-0">
          <label class="ab-label">Opening Hours Text</label>
          <input v-model="s.schema_opening_hours" type="text" class="ab-input" style="max-width:380px"
            placeholder="Mo-Fr 09:00-17:00, Sa 10:00-14:00">
          <div class="ab-help">Schema.org format: <code>Mo-Fr 09:00-17:00</code></div>
        </div>

        <template v-if="s.schema_hours_mode === 'advanced'">
           <div>
          <div class="ab-check ab-toggle mb-3">
            <input v-model="s.schema_hours_temp_closed" true-value="1" false-value="0"
              type="checkbox" class="ab-toggle__input" id="sch-temp-closed">
            <label class="ab-check__label" for="sch-temp-closed">Temporarily Closed</label>
          </div>

          <!-- Day-by-day schedule -->
          <div class="ab-sec">Weekly Schedule</div>
          <div class="ab-bh-table">
            <div v-for="(dayLabel, dk) in days" :key="dk" class="ab-bh-row">
              <div class="ab-bh-day">{{ dayLabel }}</div>
              <div class="ab-bh-closed">
                <div class="ab-check ab-toggle mb-0">
                  <input type="checkbox" class="ab-toggle__input"
                    :id="'bh-' + dk"
                    :checked="!isClosed(dk)"
                    @change="toggleDay(dk, $event.target.checked)">
                  <label class="ab-check__label" :for="'bh-' + dk">
                    {{ isClosed(dk) ? 'Closed' : 'Open' }}
                  </label>
                </div>
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

          <div class="ab-sec mt-3">Holiday Closures <span style="opacity:.5;">(optional)</span></div>
          <textarea v-model="s.schema_holiday_closed" class="ab-input font-monospace" rows="4"
            placeholder="2026-12-25&#10;2026-12-26&#10;2027-01-01"></textarea>
          <div class="ab-help">One date per line, format YYYY-MM-DD. Generates specialOpeningHoursSpecification.</div>
        </div>
        </template>
      </div>
    </div>

    <!-- FAQ Schema — Task #473: whole card is Pro -->
    <div class="ab-card">
      <div class="ab-card-header">❓ FAQ / QAPage Schema</div>
      <div class="ab-card-body">
        <div class="ab-info-box mb-3">
          <strong>ℹ️ Google update (May 2026):</strong> FAQPage is no longer a Google rich result.
          It remains a primary schema type actively used by <strong>ChatGPT, Perplexity, and Google AI Overview</strong> for citations —
          so it is still highly valuable for AI search visibility.
        </div>

        <div class="ab-check ab-toggle mb-3">
          <input v-model="s.faq_auto_detect" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="sch-faq-auto">
          <label class="ab-check__label" for="sch-faq-auto">Auto-Detect FAQ from Content</label>
          <div class="ab-help">Generates <code>FAQPage</code> JSON-LD per page from article content. Used by AI engines for citations.</div>
        </div>
          <div class="ab-check ab-toggle mb-3">
            <input v-model="s.enable_manual_faqs" data-ab-field="enable_manual_faqs" true-value="1" false-value="0"
              type="checkbox" class="ab-toggle__input" id="sch-manual-faq">
            <label class="ab-check__label" for="sch-manual-faq">Enable Manual FAQs</label>
          </div>
        <template v-if="s.enable_manual_faqs === '1'">
          <div class="mb-3">
            <label class="ab-label">Global FAQ — When to Apply</label>
            <select v-model="s.manual_faq_scope" class="ab-select" style="max-width:380px">
              <option value="fallback_all">Fallback on all pages — show if no auto-detected FAQ</option>
              <option value="always_all">All pages — always inject, ignore auto-detect</option>
              <option value="fallback_home">Homepage fallback only</option>
              <option value="always_home">Homepage only — always inject</option>
              <option value="disabled">Disabled (saved but never injected)</option>
            </select>
          </div>
            <div class="mb-3">
              <label class="ab-label">Manual FAQ Items</label>
              <textarea v-model="s.faq_items" data-ab-field="faq_items" class="ab-input font-monospace" rows="6"
                placeholder='[{"question":"Q1","answer":"A1"},{"question":"Q2","answer":"A2"}]'></textarea>
              <div class="ab-help">JSON array of <code>{"question":"…","answer":"…"}</code> objects.</div>
            </div>
          <div class="mb-3">
            <!-- Per-language FAQ translations:
                 One TranslationExpander pair per FAQ item — keys faq_{idx}_q / faq_{idx}_a.
                 Rendered dynamically from the JSON above; falls back to sample pair when empty. -->
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
            <div class="mb-0">
              <label class="ab-label">Schema Output Type</label>
              <select v-model="s.schema_faq_output_type" class="ab-select" style="max-width:380px">
                <option value="faqpage">FAQPage only <span>(recommended — used by ChatGPT, Perplexity, AI Overview)</span></option>
                <option value="qapage">QAPage only <span>(forum / user-generated Q&amp;A)</span></option>
                <option value="both">Both FAQPage and QAPage</option>
              </select>
              <div class="ab-help">
                <strong>FAQPage</strong> — classic format, preferred by most AI engines for citations.
                <strong>QAPage</strong> — <code>Question → suggestedAnswer → Answer</code> structure; best for forum-style pages.
                <strong>Both</strong> — outputs two separate JSON-LD blocks simultaneously.
              </div>
            </div>
        </template>
      </div>
    </div>

    <!-- HowTo Schema -->
    <div class="ab-card">
      <div class="ab-card-header">🔧 HowTo Schema</div>
      <div class="ab-card-body">
        <div class="ab-help mb-3">
          Outputs a <code>HowTo</code> JSON-LD block — ideal for tutorial, recipe-style, or step-by-step guide pages.
          Actively used by ChatGPT and Perplexity to generate step-by-step answers.
        </div>
        <div class="ab-check ab-toggle mb-3">
          <input v-model="howto.enabled" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="sch-howto-enabled">
          <label class="ab-check__label" for="sch-howto-enabled">Enable HowTo Schema</label>
        </div>
        <template v-if="howto.enabled === '1'">
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="ab-label">HowTo Name <span class="text-danger">*</span></label>
              <input v-model="howto.name" type="text" class="ab-input"
                placeholder="How to Install AI Boost for Joomla">
            </div>
            <div class="col-md-6">
              <label class="ab-label">Total Time <span class="text-muted">(ISO 8601)</span></label>
              <input v-model="howto.totalTime" type="text" class="ab-input"
                placeholder="PT15M">
              <div class="ab-help">e.g. <code>PT15M</code> = 15 min, <code>PT1H</code> = 1 hour, <code>PT1H30M</code> = 1h 30min</div>
            </div>
            <div class="col-12">
              <label class="ab-label">Description</label>
              <textarea v-model="howto.description" class="ab-input" rows="2"
                placeholder="A step-by-step guide to…"></textarea>
            </div>
          </div>

          <div class="ab-sec">Steps</div>
          <div v-for="(step, i) in howto.steps" :key="i" class="ab-howto-step mb-2">
            <div class="ab-howto-num">{{ i + 1 }}</div>
            <div class="flex-grow-1">
              <input v-model="step.name" type="text" class="ab-input form-control-sm mb-1"
                :placeholder="'Step ' + (i + 1) + ' name (optional)'">
              <textarea v-model="step.text" class="ab-input form-control-sm" rows="2"
                :placeholder="'Describe what the user does in step ' + (i + 1)"></textarea>
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

    <!-- Event Schema -->
    <div class="ab-card">
      <div class="ab-card-header">🎟 Event Schema</div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-3">
          <input v-model="s.events_enabled" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="sch-events">
          <label class="ab-check__label" for="sch-events">Enable Event Schema (rich results in Google, AI Overviews)</label>
        </div>
        <div class="mb-3">
          <label class="ab-label">Events Joomla Category ID</label>
          <input v-model="s.events_category_id" type="number" class="ab-input" style="max-width:140px"
            placeholder="0" min="0">
          <div class="ab-help">Joomla category ID containing event articles. Set to <code>0</code> to disable.</div>
        </div>
        <div class="mb-0">
          <label class="ab-label">Event Article IDs for translations</label>
          <input v-model="s.schema_event_article_ids" type="text" class="ab-input"
            placeholder="e.g. 42,57,103 — comma-separated Joomla article IDs">
          <div class="ab-help">
            Enter the Joomla article IDs of your event articles (comma-separated).
            A per-language description expander will appear for each — key: <code>event_{index}_desc</code> (0-based order).
          </div>
          <!-- event_{index}_desc keys — index = 0-based position in list above (matches PHP resolveEventIndex) -->
          <template v-if="parsedEventArticleIds.length > 0">
            <div v-for="(articleId, idx) in parsedEventArticleIds" :key="idx" class="ab-faq-trans-group mt-2">
              <div class="ab-faq-trans-label">Event #{{ idx }} (article #{{ articleId }}) — description</div>
              <TranslationExpander :field-key="'event_' + idx + '_desc'" field-type="textarea" />
            </div>
          </template>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
const LOCAL_BUSINESS_TYPES = ['LocalBusiness','LodgingBusiness','FoodEstablishment','Restaurant',
  'MedicalClinic','LegalService','EducationalOrganization','SportsActivityLocation',
  'Dentist','RealEstateAgent','AutomotiveBusiness','Store','TouristAttraction','ProfessionalService']

const AVAILABLE_SERVICE_TYPES = ['MedicalClinic','LegalService','EducationalOrganization',
  'SportsActivityLocation','Dentist','ProfessionalService','AutomotiveBusiness']

const CUISINE_TYPES = ['FoodEstablishment','Restaurant','LodgingBusiness']

const AREA_SERVED_TYPES = ['LocalBusiness','LegalService','EducationalOrganization','RealEstateAgent',
  'AutomotiveBusiness','ProfessionalService','Store','TouristAttraction']

const PRICE_RANGE_TYPES = ['LocalBusiness','FoodEstablishment','Restaurant','LodgingBusiness',
  'SportsActivityLocation','AutomotiveBusiness','Store','TouristAttraction']

const AMENITY_FEATURE_TYPES = ['FoodEstablishment','Restaurant','LodgingBusiness','SportsActivityLocation','Store','TouristAttraction']

const PAYMENT_ACCEPTED_TYPES = ['LocalBusiness','FoodEstablishment','Restaurant','LodgingBusiness',
  'SportsActivityLocation','AutomotiveBusiness','Store','TouristAttraction']

const EXPERTISE_TYPES = ['MedicalClinic','LegalService','EducationalOrganization','Dentist','ProfessionalService']

const SERVICE_META = {
  MedicalClinic:           { header: '🏥 Medical Settings',       label: 'Medical Specialty',      ph: 'General Practice, Cardiology, Dermatology…' },
  LegalService:            { header: '⚖️ Legal Service Settings', label: 'Legal Practice Area',     ph: 'Criminal Law, Family Law, Corporate Law…'   },
  EducationalOrganization: { header: '🎓 Education Settings',     label: 'Education Level / Type',  ph: 'Primary School, High School, University…'   },
  SportsActivityLocation:  { header: '💪 Gym / Sports Settings',  label: 'Primary Sport / Activity', ph: 'CrossFit, Boxing, Yoga…'                   },
  Dentist:                 { header: '🦷 Dental Settings',        label: 'Dental Specialty',         ph: 'General Dentistry, Orthodontics, Implants'  },
  ProfessionalService:     { header: '🧰 Professional Service Settings', label: 'Primary Service', ph: 'Consulting, accounting, design, IT support' },
  AutomotiveBusiness:      { header: '🚗 Automotive Settings',     label: 'Automotive Service',      ph: 'Repair, detailing, tyre service, inspections' },
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

import TranslationExpander from '../components/TranslationExpander.vue'

const SCHEMA_TYPE_OPTIONS = [
  { value: 'Organization',            label: 'Organization (generic)' },
  { value: 'LocalBusiness',           label: 'LocalBusiness' },
  { value: 'FoodEstablishment',       label: 'Restaurant / Cafe' },
  { value: 'Restaurant',              label: 'Restaurant (specific)' },
  { value: 'EducationalOrganization', label: 'School / University' },
  { value: 'LodgingBusiness',         label: 'Hotel / Accommodation' },
  { value: 'MedicalClinic',           label: 'Medical Clinic' },
  { value: 'LegalService',            label: 'Lawyer / Law Firm' },
  { value: 'SportsActivityLocation',  label: 'Gym / Sports Club' },
  { value: 'Dentist',                 label: 'Dentist' },
  { value: 'RealEstateAgent',         label: 'Real Estate Agency' },
  { value: 'AutomotiveBusiness',      label: 'Automotive Business' },
  { value: 'Store',                   label: 'Store / Shop' },
  { value: 'TouristAttraction',       label: 'Tourist Attraction' },
  { value: 'ProfessionalService',     label: 'Professional Service' },
  { value: 'Person',                  label: 'Person / Portfolio' },
  { value: 'NewsMediaOrganization',   label: 'News / Media' },
]

export default {
  name: 'SchemaTab',
  components: { TranslationExpander },
  props: { s: { type: Object, required: true } },

  data() {
    return {
      days:  { mon:'Monday', tue:'Tuesday', wed:'Wednesday', thu:'Thursday', fri:'Friday', sat:'Saturday', sun:'Sunday' },
      howto: parseHowto(this.s.schema_howto),
    }
  },

  watch: {
    howto: {
      handler(v) { this.s.schema_howto = JSON.stringify(v) },
      deep: true,
    },
  },

  computed: {
    schemaTypeOptions() {
      return SCHEMA_TYPE_OPTIONS
    },
    parsedFaqItems() {
      try {
        const items = JSON.parse(this.s.faq_items || '[]')
        return Array.isArray(items) ? items : []
      } catch {
        return []
      }
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
    selectedTypeMeta() {
      return TYPE_META[this.s.schema_type] || null
    },
    serviceLabel()      { return (SERVICE_META[this.s.schema_type] || {}).header || '' },
    serviceFieldLabel() { return (SERVICE_META[this.s.schema_type] || {}).label  || 'Available Service' },
    servicePlaceholder(){ return (SERVICE_META[this.s.schema_type] || {}).ph     || '' },
    areaServedLabel() { return (AREA_SERVED_META[this.s.schema_type] || {}).label || 'Area Served' },
    areaServedPlaceholder() { return (AREA_SERVED_META[this.s.schema_type] || {}).ph || 'London, Westminster, Canary Wharf' },
    operationsLabel() {
      const labels = {
        LocalBusiness: '🧾 Local Business Operations',
        FoodEstablishment: '🥡 Food Ordering & Venue Features',
        Restaurant: '🍽 Dining & Reservation Signals',
        LodgingBusiness: '🏨 Guest Payment & Amenities',
        SportsActivityLocation: '💳 Membership & Facility Features',
        AutomotiveBusiness: '🚗 Workshop Payment Options',
        Store: '🛍 Store Payment & Pickup Options',
        TouristAttraction: '🎟 Visitor Access & Amenities',
      }
      return labels[this.s.schema_type] || '🧾 Business Operations'
    },
    hoursLabel() {
      const labels = {
        LocalBusiness: '🕐 Local Opening Hours',
        FoodEstablishment: '🕐 Kitchen / Counter Hours',
        Restaurant: '🕐 Dining Hours',
        LodgingBusiness: '🕐 Reception Hours',
        MedicalClinic: '🕐 Clinic Hours',
        LegalService: '🕐 Office / Consultation Hours',
        EducationalOrganization: '🕐 Campus / Admissions Hours',
        SportsActivityLocation: '🕐 Facility / Class Hours',
        Dentist: '🕐 Dental Office Hours',
        RealEstateAgent: '🕐 Viewing / Office Hours',
        AutomotiveBusiness: '🕐 Workshop Hours',
        Store: '🕐 Store Hours',
        TouristAttraction: '🕐 Visitor Hours',
        ProfessionalService: '🕐 Service Hours',
      }
      return labels[this.s.schema_type] || '🕐 Opening Hours'
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

    isClosed(dk) {
      return (this.s['hours_' + dk + '_closed'] ?? '0') === '1'
    },

    toggleDay(dk, open) {
      this.s['hours_' + dk + '_closed'] = open ? '0' : '1'
      if (open && !this.s['hours_' + dk + '_opens'])  this.s['hours_' + dk + '_opens']  = '09:00'
      if (open && !this.s['hours_' + dk + '_closes']) this.s['hours_' + dk + '_closes'] = '17:00'
    },

    addStep() {
      if (!Array.isArray(this.howto.steps)) this.howto.steps = []
      this.howto.steps.push({ name: '', text: '' })
    },

    removeStep(i) {
      this.howto.steps.splice(i, 1)
    },
  },
}
</script>

<style scoped>
.ab-faq-trans-group {
  margin-top: 8px;
  padding: 6px 8px;
  background: var(--body-bg, #f8f9fa);
  border: 1px solid var(--border-color, #dee2e6);
  border-radius: 5px;
}
.ab-faq-trans-label {
  font-size: .78rem;
  font-weight: 600;
  color: var(--secondary-color, #6c757d);
  margin-bottom: 4px;
}
.ab-schema-tab { max-width: 860px; }
.ab-schema-type-panel {
  padding: 10px 12px;
  border: 1px solid var(--border-color, #dee2e6);
  border-radius: 6px;
  background: color-mix(in srgb, var(--body-bg, #fff) 88%, #0d6efd 12%);
}
.ab-schema-type-panel__intro strong {
  display: block;
  margin-bottom: 3px;
}
.ab-schema-type-panel__intro p {
  margin: 0 0 8px;
  color: var(--secondary-color, #6c757d);
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
  border: 1px solid color-mix(in srgb, var(--border-color, #dee2e6) 75%, #0d6efd 25%);
  border-radius: 5px;
  background: var(--body-bg, #fff);
}
.ab-schema-type-hint .icon-check {
  flex: 0 0 auto;
  margin-top: 2px;
  color: var(--success, #198754);
}
.ab-schema-type-hint strong,
.ab-schema-type-hint small {
  display: block;
}
.ab-schema-type-hint small {
  color: var(--secondary-color, #6c757d);
  font-size: .76rem;
  line-height: 1.35;
}
.ab-bh-table { display: flex; flex-direction: column; gap: 4px; }
.ab-bh-row {
  display: flex; align-items: center; gap: 12px;
  padding: 6px 0; border-bottom: 1px solid var(--border-color, #dee2e6);
}
.ab-bh-row:last-child { border-bottom: none; }
.ab-bh-day    { width: 90px; font-weight: 500; font-size: .875rem; color: var(--body-color, #212529); }
.ab-bh-closed { width: 110px; }
.ab-bh-times  { display: flex; align-items: center; gap: 6px; }
.ab-bh-time   { max-width: 110px; }
.ab-bh-sep    { color: var(--secondary-color, #6c757d); }

/* HowTo steps */
.ab-howto-step {
  display: flex; align-items: flex-start; gap: 8px;
  padding: 8px 10px;
  border: 1px solid var(--border-color, #dee2e6);
  border-radius: 6px;
  background: var(--body-bg, #fff);
}
.ab-howto-num {
  min-width: 24px; height: 24px; line-height: 24px;
  text-align: center; font-weight: 700; font-size: .8rem;
  background: #0d6efd; color: #fff; border-radius: 50%;
  margin-top: 4px;
}
.ab-howto-del { align-self: flex-start; margin-top: 2px; }

/* Info box */
.ab-info-box {
  padding: 10px 14px;
  border-left: 4px solid #0d6efd;
  background: color-mix(in srgb, #0d6efd 8%, transparent);
  border-radius: 0 6px 6px 0;
  font-size: .875rem;
  line-height: 1.5;
}
@media (max-width: 767.98px) {
  .ab-schema-type-panel__grid { grid-template-columns: 1fr; }
}
</style>
