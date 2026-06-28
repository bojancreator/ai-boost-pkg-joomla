<template>
  <div class="ab-analyzer-page">

    <PageHeader title="Analyzers" />

    <!-- Tab navigation -->
    <ul class="ab-tabs" role="tablist">
      <li role="presentation">
        <button :class="['ab-tab', activeTab === 'seo' ? 'ab-tab--active' : '']"
                @click="activeTab = 'seo'" type="button" role="tab">
          <AbIcon name="search" /> SEO Analyzer
        </button>
      </li>
      <li role="presentation">
        <button :class="['ab-tab', activeTab === 'jsonld' ? 'ab-tab--active' : '']"
                @click="activeTab = 'jsonld'" type="button" role="tab">
          <AbIcon name="code" /> JSON-LD Validator
        </button>
      </li>
      <li role="presentation">
        <button :class="['ab-tab', activeTab === 'ai' ? 'ab-tab--active' : '']"
                @click="activeTab = 'ai'" type="button" role="tab">
          <AbIcon name="ai" /> AI Visibility
        </button>
      </li>
    </ul>

    <!-- ── SEO ANALYZER ─────────────────────────────────────────────────── -->
    <div v-show="activeTab === 'seo'">
      <div class="ab-section mb-4">
        <div class="ab-section__head">
          <h2 class="fs-5 mb-0">SEO Analyzer</h2>
        </div>
        <div class="ab-section__body">
          <p class="text-muted small mb-3">
            Get an SEO score and actionable recommendations covering title, meta description, schema, OG tags, canonicals, robots.txt, sitemap, and more. Scan a single page or your whole site.
          </p>

          <!-- Mode switch: single URL vs batch -->
          <ul class="ab-mode-tabs mb-3" role="tablist">
            <li role="presentation">
              <button :class="['ab-mode-tab', seoMode === 'single' ? 'ab-mode-tab--active' : '']"
                      @click="seoMode = 'single'" type="button" role="tab"
                      :disabled="batchScanning">
                Single URL
              </button>
            </li>
            <li role="presentation">
              <button :class="['ab-mode-tab', seoMode === 'batch' ? 'ab-mode-tab--active' : '']"
                      @click="seoMode = 'batch'" type="button" role="tab"
                      :disabled="seoLoading">
                Multiple URLs (batch)
              </button>
            </li>
          </ul>

          <!-- Single URL mode -->
          <div v-if="seoMode === 'single'">
            <div class="ab-input-group mb-3">
              <span class="ab-input-prefix"><span class="icon-globe" aria-hidden="true"></span></span>
              <input
                v-model="seoUrl"
                type="url"
                class="ab-input ab-input-group__field"
                placeholder="https://example.com/your-page"
                @keyup.enter="runSeo"
                :disabled="seoLoading"
              />
              <button class="ab-btn ab-btn--primary ab-input-group__btn" @click="runSeo" :disabled="seoLoading || !seoUrl">
                <span :class="[seoLoading ? 'ab-spin icon-refresh' : 'icon-search']" aria-hidden="true"></span>
                {{ seoLoading ? 'Analyzing…' : 'Analyze' }}
              </button>
            </div>
            <div v-if="seoError" class="ab-alert ab-alert--danger small">{{ seoError }}</div>
          </div>

          <!-- Batch mode -->
          <div v-else>
            <div class="d-flex flex-wrap gap-2 mb-2">
              <button class="ab-btn ab-btn--ghost" @click="loadSitemapForBatch" :disabled="batchBusy">
                <span :class="[batchLoadingSitemap ? 'ab-spin icon-refresh' : 'icon-download']" aria-hidden="true"></span>
                {{ batchLoadingSitemap ? 'Loading…' : 'Load from sitemap' }}
              </button>
              <button class="ab-btn ab-btn--ghost" @click="batchUrls = ''" :disabled="batchBusy">Clear</button>
              <span class="text-muted small align-self-center ms-auto">
                {{ batchUrlCount }} URL{{ batchUrlCount === 1 ? '' : 's' }} ready
              </span>
            </div>
            <label class="ab-label small text-muted">URLs (one per line, max 50 per scan)</label>
            <textarea v-model="batchUrls" rows="6" class="ab-input font-monospace"
                      placeholder="https://example.com/page-1&#10;https://example.com/page-2"
                      :disabled="batchScanning"></textarea>
            <div v-if="batchSitemapMsg" class="small mt-2" :class="batchSitemapMsgOk ? 'text-success' : 'text-danger'">
              {{ batchSitemapMsg }}
            </div>

            <div class="mt-3 d-flex flex-wrap gap-2">
              <button class="ab-btn ab-btn--primary" @click="runBatchSeo"
                      :disabled="batchBusy || !batchUrlCount">
                <span :class="[batchScanning ? 'ab-spin icon-refresh' : 'icon-search']" aria-hidden="true"></span>
                {{ batchScanning ? `Scanning ${batchProgress.done}/${batchProgress.total}…` : 'Scan all URLs' }}
              </button>
              <button v-if="batchScanning" class="ab-btn ab-btn--ghost ab-btn--danger-ghost"
                      @click="cancelBatch">Cancel</button>
            </div>

            <!-- Progress bar -->
            <div v-if="batchScanning || (batchProgress.total > 0 && batchProgress.done < batchProgress.total)" class="mt-3">
              <div class="d-flex justify-content-between small text-muted mb-1">
                <span>Progress: {{ batchProgress.done }} / {{ batchProgress.total }} URLs ({{ batchProgressPct }}%)</span>
                <span v-if="batchScanning">Batch size: {{ BATCH_SIZE }}</span>
              </div>
              <div class="ab-progress" role="progressbar"
                   :aria-valuenow="batchProgressPct" aria-valuemin="0" aria-valuemax="100">
                <div class="ab-progress__bar" :style="{ width: batchProgressPct + '%' }"></div>
              </div>
            </div>

            <div v-if="batchErrors.length" class="ab-alert ab-alert--warning small mt-3 mb-0">
              {{ batchErrors.length }} URL(s) failed to scan — aggregate results may be incomplete.
              <details class="mt-1">
                <summary>Details</summary>
                <ul class="mb-0">
                  <li v-for="(err, i) in batchErrors" :key="i"><code>{{ err }}</code></li>
                </ul>
              </details>
            </div>
            <div v-if="batchError" class="ab-alert ab-alert--danger small mt-3 mb-0">{{ batchError }}</div>
          </div>
        </div>
      </div>

      <!-- SEO Results (single-URL mode) -->
      <div v-if="seoMode === 'single' && seoResult" class="ab-analyzer-results">

        <!-- Score header -->
        <div class="ab-section mb-4">
          <div class="ab-section__body d-flex align-items-center gap-4 flex-wrap">
            <div :class="['ab-score-circle flex-shrink-0', scoreClass(seoResult.score)]">
              {{ seoResult.score }}
            </div>
            <div class="flex-grow-1">
              <h3 class="fs-5 fw-bold mb-1">{{ scoreLabel(seoResult.score) }}</h3>
              <p class="text-muted small mb-1">Analyzed: <code>{{ seoResult.url }}</code></p>
              <div class="d-flex gap-2 flex-wrap mt-2">
                <span class="ab-badge ab-badge--danger">{{ countSeverity(seoResult.checks, 'error') }} Errors</span>
                <span class="ab-badge ab-badge--warning">{{ countSeverity(seoResult.checks, 'warning') }} Warnings</span>
                <span class="ab-badge ab-badge--info">{{ countSeverity(seoResult.checks, 'info') }} Info</span>
                <span class="ab-badge ab-badge--success">{{ countSeverity(seoResult.checks, 'pass') }} Passed</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Checks list -->
        <div class="ab-section">
          <div class="ab-section__head">SEO Check Details</div>
          <div class="ab-check-list">
            <div v-for="c in seoResult.checks" :key="c.id" class="ab-check-item">
              <div class="ab-check-row" @click="toggleExpand(c.id)" role="button">
                <span :class="['flex-shrink-0 mt-1', severityIcon(c.severity)]" aria-hidden="true"></span>
                <div class="flex-grow-1">
                  <div class="fw-semibold small">{{ c.label }}</div>
                  <div class="text-muted small">{{ c.message }}</div>
                </div>
                <span :class="['ab-badge ab-check-badge', severityBadge(c.severity)]">{{ c.severity }}</span>
                <span v-if="hasFixDetails(c)"
                      :class="['icon-arrow-down ms-2 ab-expand-chevron', expanded[c.id] ? 'ab-expand-chevron--open' : '']"
                      aria-hidden="true"></span>
              </div>

              <!-- Expanded detail panel -->
              <div v-if="expanded[c.id] && hasFixDetails(c)" class="ab-check-detail">
                <div v-if="c.why" class="ab-check-detail__block">
                  <div class="ab-check-detail__label">
                    <span class="icon-info-circle me-1" aria-hidden="true"></span>Why it matters
                  </div>
                  <div class="ab-check-detail__text">{{ c.why }}</div>
                </div>
                <div v-if="c.suggestion" class="ab-check-detail__block">
                  <div class="ab-check-detail__label">
                    <span class="icon-lightbulb me-1" aria-hidden="true"></span>Suggested fix
                  </div>
                  <div class="ab-check-detail__text">{{ c.suggestion }}</div>
                </div>

                <!-- Action buttons -->
                <div v-if="c.severity !== 'pass'" class="ab-check-detail__actions">
                  <!-- apply_setting: server flips a toggle -->
                  <button v-if="c.fix_action === 'apply_setting'"
                          class="ab-btn ab-btn--primary ab-btn--sm"
                          :disabled="fixingId === c.id"
                          @click="applyFix(c)">
                    <span :class="[fixingId === c.id ? 'ab-spin icon-refresh' : 'icon-wrench']" aria-hidden="true"></span>
                    {{ fixingId === c.id ? 'Applying…' : 'Fix It' }}
                  </button>

                  <!-- open_tab: deep-link to Settings -->
                  <a v-if="c.fix_action === 'open_tab'"
                     :href="settingsLink(c)"
                     class="ab-btn ab-btn--primary ab-btn--sm">
                    <span class="icon-cog" aria-hidden="true"></span>
                    Open Settings → {{ tabLabel(c.fix_payload && c.fix_payload.tab) }}
                  </a>

                  <!-- open_editor: deep-link to the Joomla article editor when resolvable -->
                  <a v-if="c.fix_action === 'open_editor' && c.fix_payload && c.fix_payload.edit_url"
                     :href="c.fix_payload.edit_url"
                     class="ab-btn ab-btn--primary ab-btn--sm">
                    <span class="icon-edit" aria-hidden="true"></span>
                    Open in article editor
                  </a>
                  <!-- Fallback when we cannot resolve an article ID -->
                  <a v-else-if="c.fix_action === 'open_editor'"
                     :href="seoResult.url"
                     target="_blank" rel="noopener"
                     class="ab-btn ab-btn--ghost ab-btn--sm"
                     title="Could not auto-detect article ID — opening the public page instead.">
                    <span class="icon-out ab-icon-single" aria-hidden="true"></span>
                    Open page (article not detected)
                  </a>

                  <!-- After-apply feedback -->
                  <span v-if="fixedIds.includes(c.id)" class="text-success small ms-2">
                    <span class="icon-check" aria-hidden="true"></span> Applied — re-run analyzer to verify
                  </span>
                  <span v-if="fixErrors[c.id]" class="text-danger small ms-2">{{ fixErrors[c.id] }}</span>
                </div>

                <!-- Affected pages list (H1 / Image Alt multi-page aggregation) -->
                <details v-if="c.affected_urls && c.affected_urls.length" class="ab-affected-urls mt-2">
                  <summary class="ab-affected-urls__summary">
                    <span class="icon-warning me-1" aria-hidden="true"></span>
                    {{ c.affected_urls.length }} affected page{{ c.affected_urls.length !== 1 ? 's' : '' }}
                  </summary>
                  <ul class="ab-affected-url-list">
                    <li v-for="(page, pi) in c.affected_urls" :key="pi">
                      <a :href="page.url" target="_blank" rel="noopener">{{ page.url }}</a>
                      <a v-if="page.edit_url" :href="page.edit_url" class="ab-edit-link ms-2">
                        <span class="icon-edit" aria-hidden="true"></span> Edit article
                      </a>
                    </li>
                  </ul>
                </details>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- SEO Results (batch / multi-URL mode, aggregated) -->
      <div v-if="seoMode === 'batch' && batchAggregate" class="ab-analyzer-results">

        <!-- Aggregate score header -->
        <div class="ab-section mb-4">
          <div class="ab-section__body d-flex align-items-center gap-4 flex-wrap">
            <div :class="['ab-score-circle flex-shrink-0', scoreClass(batchAggregate.avgScore)]">
              {{ batchAggregate.avgScore }}
            </div>
            <div class="flex-grow-1">
              <h3 class="fs-5 fw-bold mb-1">{{ scoreLabel(batchAggregate.avgScore) }} (average across {{ batchAggregate.scannedCount }} page{{ batchAggregate.scannedCount !== 1 ? 's' : '' }})</h3>
              <p class="text-muted small mb-1">
                Scanned {{ batchAggregate.scannedCount }} page{{ batchAggregate.scannedCount !== 1 ? 's' : '' }}
                <span v-if="batchAggregate.failedCount"> · {{ batchAggregate.failedCount }} failed to fetch</span>
              </p>
              <div class="d-flex gap-2 flex-wrap mt-2">
                <span class="ab-badge ab-badge--danger">{{ batchAggregate.totals.error }} Errors</span>
                <span class="ab-badge ab-badge--warning">{{ batchAggregate.totals.warning }} Warnings</span>
                <span class="ab-badge ab-badge--info">{{ batchAggregate.totals.info }} Info</span>
                <span class="ab-badge ab-badge--success">{{ batchAggregate.totals.pass }} Passed</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Aggregated checks list -->
        <div class="ab-section">
          <div class="ab-section__head">Site-wide SEO Check Summary</div>
          <div class="ab-check-list">
            <template v-for="(c, idx) in batchAggregate.checks" :key="c.id">
              <div v-if="idx === batchFirstPassIndex" class="ab-check-divider" role="separator" aria-label="All passing below">
                <span class="ab-check-divider__line" aria-hidden="true"></span>
                <span class="ab-check-divider__label">
                  <span class="icon-check me-1" aria-hidden="true"></span>
                  Issues found above · All passing below
                </span>
                <span class="ab-check-divider__line" aria-hidden="true"></span>
              </div>
            <div class="ab-check-item">
              <div class="ab-check-row" @click="toggleExpand(c.id)" role="button">
                <span :class="['flex-shrink-0 mt-1', severityIcon(c.severity)]" aria-hidden="true"></span>
                <div class="flex-grow-1">
                  <div class="fw-semibold small">{{ c.label }}</div>
                  <div class="text-muted small">
                    <span v-if="c.failCount > 0">
                      {{ c.failCount }} of {{ batchAggregate.scannedCount }} page{{ batchAggregate.scannedCount !== 1 ? 's' : '' }} affected
                    </span>
                    <span v-else>All {{ batchAggregate.scannedCount }} page{{ batchAggregate.scannedCount !== 1 ? 's' : '' }} pass this check.</span>
                  </div>
                </div>
                <span :class="['ab-badge ab-check-badge', severityBadge(c.severity)]">{{ c.severity }}</span>
                <span v-if="hasFixDetails(c)"
                      :class="['icon-arrow-down ms-2 ab-expand-chevron', expanded[c.id] ? 'ab-expand-chevron--open' : '']"
                      aria-hidden="true"></span>
              </div>

              <div v-if="expanded[c.id] && hasFixDetails(c)" class="ab-check-detail">
                <div v-if="c.why" class="ab-check-detail__block">
                  <div class="ab-check-detail__label">
                    <span class="icon-info-circle me-1" aria-hidden="true"></span>Why it matters
                  </div>
                  <div class="ab-check-detail__text">{{ c.why }}</div>
                </div>
                <div v-if="c.suggestion" class="ab-check-detail__block">
                  <div class="ab-check-detail__label">
                    <span class="icon-lightbulb me-1" aria-hidden="true"></span>Suggested fix
                  </div>
                  <div class="ab-check-detail__text">{{ c.suggestion }}</div>
                </div>

                <div v-if="c.severity !== 'pass'" class="ab-check-detail__actions">
                  <button v-if="c.fix_action === 'apply_setting'"
                          class="ab-btn ab-btn--primary ab-btn--sm"
                          :disabled="fixingId === c.id"
                          @click="applyFix(c)">
                    <span :class="[fixingId === c.id ? 'ab-spin icon-refresh' : 'icon-wrench']" aria-hidden="true"></span>
                    {{ fixingId === c.id ? 'Applying…' : 'Fix It' }}
                  </button>

                  <a v-if="c.fix_action === 'open_tab'"
                     :href="settingsLink(c)"
                     class="ab-btn ab-btn--primary ab-btn--sm">
                    <span class="icon-cog" aria-hidden="true"></span>
                    Open Settings → {{ tabLabel(c.fix_payload && c.fix_payload.tab) }}
                  </a>

                  <span v-if="fixedIds.includes(c.id)" class="text-success small ms-2">
                    <span class="icon-check" aria-hidden="true"></span> Applied — re-run analyzer to verify
                  </span>
                  <span v-if="fixErrors[c.id]" class="text-danger small ms-2">{{ fixErrors[c.id] }}</span>
                </div>

                <!-- Aggregated affected pages list -->
                <details v-if="c.affected_urls && c.affected_urls.length" class="ab-affected-urls mt-2" open>
                  <summary class="ab-affected-urls__summary">
                    <span class="icon-warning me-1" aria-hidden="true"></span>
                    {{ c.affected_urls.length }} affected page{{ c.affected_urls.length !== 1 ? 's' : '' }}
                  </summary>
                  <ul class="ab-affected-url-list">
                    <li v-for="(page, pi) in c.affected_urls" :key="pi">
                      <a :href="page.url" target="_blank" rel="noopener">{{ page.url }}</a>
                      <a v-if="page.edit_url" :href="page.edit_url" class="ab-edit-link ms-2">
                        <span class="icon-edit" aria-hidden="true"></span> Edit article
                      </a>
                    </li>
                  </ul>
                </details>
              </div>
            </div>
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- ── JSON-LD VALIDATOR ─────────────────────────────────────────────── -->
    <div v-show="activeTab === 'jsonld'">
      <div class="ab-section mb-4">
        <div class="ab-section__head">
          <h2 class="fs-5 mb-0">JSON-LD Validator</h2>
        </div>
        <div class="ab-section__body">
          <p class="text-muted small mb-3">
            Paste your JSON-LD structured data (the content of a <code>&lt;script type="application/ld+json"&gt;</code> tag) to validate it against Schema.org rules, or fetch it automatically from any URL.
          </p>

          <!-- Fetch from URL -->
          <div class="ab-input-group mb-3">
            <span class="ab-input-prefix"><span class="icon-globe" aria-hidden="true"></span></span>
            <input
              v-model="fetchFromUrl"
              type="url"
              class="ab-input ab-input-group__field"
              placeholder="Fetch JSON-LD from URL…"
              :disabled="fetchLoading"
            />
            <button class="ab-btn ab-btn--ghost ab-input-group__btn" @click="fetchFromUrlAction" :disabled="fetchLoading || !fetchFromUrl">
              <span :class="[fetchLoading ? 'ab-spin icon-refresh' : 'icon-download']" aria-hidden="true"></span>
              {{ fetchLoading ? 'Fetching…' : 'Fetch' }}
            </button>
          </div>
          <div v-if="fetchBlocks.length > 1" class="mb-3">
            <label class="ab-label">Multiple JSON-LD blocks found — select one:</label>
            <select v-model="selectedBlockIndex" class="ab-select ab-select--sm">
              <option v-for="(b, i) in fetchBlocks" :key="i" :value="i">Block {{ i + 1 }}: {{ blockPreview(b) }}</option>
            </select>
          </div>
          <div v-if="fetchError" class="ab-alert ab-alert--danger small mb-3">{{ fetchError }}</div>

          <!-- Textarea -->
          <label class="ab-label" for="ab-jsonld-input">JSON-LD Code</label>
          <textarea
            id="ab-jsonld-input"
            v-model="jsonldInput"
            class="ab-input ab-jsonld-textarea"
            rows="12"
            placeholder='{"@context":"https://schema.org","@type":"Organization","name":"…"}'
            spellcheck="false"
          ></textarea>

          <div class="d-flex gap-2 mt-3 flex-wrap">
            <button class="ab-btn ab-btn--primary" @click="validateJsonLd" :disabled="jsonldLoading || !jsonldInput.trim()">
              <span :class="[jsonldLoading ? 'ab-spin icon-refresh' : 'icon-check-circle']" aria-hidden="true"></span>
              {{ jsonldLoading ? 'Validating…' : 'Validate JSON-LD' }}
            </button>
            <button class="ab-btn ab-btn--ghost" @click="prettyPrint" :disabled="!jsonldInput.trim()">
              <span class="icon-indent" aria-hidden="true"></span> Pretty Print
            </button>
            <button class="ab-btn ab-btn--ghost" @click="clearJsonLd">
              <span class="icon-times" aria-hidden="true"></span> Clear
            </button>
          </div>
          <div v-if="jsonldError" class="ab-alert ab-alert--danger small mt-3">{{ jsonldError }}</div>
        </div>
      </div>

      <!-- JSON-LD Results -->
      <div v-if="jsonldResult" class="ab-section">
        <div class="ab-section__head">
          <span>Validation Results</span>
          <span class="ms-2 small text-muted" v-if="jsonldResult.type">Type: <strong>{{ jsonldResult.type }}</strong></span>
          <div :class="['ab-score-circle ab-score-circle--sm flex-shrink-0 ms-auto', scoreClass(jsonldResult.score)]">
            {{ jsonldResult.score }}
          </div>
        </div>
        <div class="ab-check-list">
          <div v-for="(issue, i) in jsonldResult.issues" :key="i" class="ab-check-row">
            <span :class="['flex-shrink-0 mt-1', severityIcon(issue.level)]" aria-hidden="true"></span>
            <div class="flex-grow-1">
              <div class="fw-semibold small">{{ issue.label }}</div>
              <div class="text-muted small">{{ issue.message }}</div>
            </div>
            <span :class="['ab-badge ab-check-badge', severityBadge(issue.level)]">{{ issue.level }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── AI VISIBILITY ─────────────────────────────────────────────────── -->
    <div v-show="activeTab === 'ai'">
      <div class="ab-section mb-4">
        <div class="ab-section__head">
          <h2 class="fs-5 mb-0">AI Visibility Analyzer</h2>
        </div>
        <div class="ab-section__body">
          <p class="text-muted small mb-3">
            Check how visible your site is to AI search engines like ChatGPT, Perplexity, Google AI Overviews, and Bing Copilot. Analyzes llms.txt, robots.txt AI directives, IndexNow, and structured data signals.
          </p>
          <div class="ab-input-group mb-3">
            <span class="ab-input-prefix"><span class="icon-globe" aria-hidden="true"></span></span>
            <input
              v-model="aiUrl"
              type="url"
              class="ab-input ab-input-group__field"
              :placeholder="defaultBaseUrl"
              @keyup.enter="runAiVisibility"
              :disabled="aiLoading"
            />
            <button class="ab-btn ab-btn--primary ab-input-group__btn" @click="runAiVisibility" :disabled="aiLoading">
              <span :class="[aiLoading ? 'ab-spin icon-refresh' : 'icon-magic']" aria-hidden="true"></span>
              {{ aiLoading ? 'Checking…' : 'Check AI Visibility' }}
            </button>
          </div>
          <p class="text-muted small mb-0">Leave blank to analyze the current site (<code>{{ defaultBaseUrl }}</code>).</p>
          <div v-if="aiError" class="ab-alert ab-alert--danger small mt-3">{{ aiError }}</div>
        </div>
      </div>

      <!-- AI Visibility Results -->
      <div v-if="aiResult">

        <!-- Score header -->
        <div class="ab-section mb-4">
          <div class="ab-section__body d-flex align-items-center gap-4 flex-wrap">
            <div :class="['ab-score-circle flex-shrink-0', scoreClass(aiResult.score)]">
              {{ aiResult.score }}
            </div>
            <div class="flex-grow-1">
              <h3 class="fs-5 fw-bold mb-1">{{ aiScoreLabel(aiResult.score) }}</h3>
              <p class="text-muted small mb-1">Base URL: <code>{{ aiResult.baseUrl }}</code></p>
              <div class="d-flex gap-2 flex-wrap mt-2">
                <span class="ab-badge ab-badge--danger">{{ countSeverity(aiResult.checks, 'error') }} Errors</span>
                <span class="ab-badge ab-badge--warning">{{ countSeverity(aiResult.checks, 'warning') }} Warnings</span>
                <span class="ab-badge ab-badge--info">{{ countSeverity(aiResult.checks, 'info') }} Info</span>
                <span class="ab-badge ab-badge--success">{{ countSeverity(aiResult.checks, 'pass') }} Passed</span>
              </div>
            </div>
          </div>
        </div>

        <!-- AI crawler summary -->
        <div class="ab-section mb-4">
          <div class="ab-section__head">AI Crawler Status</div>
          <div class="ab-section__body">
            <div class="row g-2">
              <div v-for="c in crawlerChecks" :key="c.id" class="col-sm-6 col-md-4">
                <div :class="['ab-crawler-pill d-flex align-items-center gap-2 p-2 rounded small',
                              c.pass ? 'ab-crawler-pill--ok' : 'ab-crawler-pill--warn']">
                  <span :class="c.pass ? 'icon-check text-success' : 'icon-warning text-warning'" aria-hidden="true"></span>
                  <span class="text-truncate">{{ c.label }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Full checks list -->
        <div class="ab-section">
          <div class="ab-section__head">AI Signal Details</div>
          <div class="ab-check-list">
            <div v-for="c in nonCrawlerChecks" :key="c.id" class="ab-check-row">
              <span :class="['flex-shrink-0 mt-1', severityIcon(c.severity)]" aria-hidden="true"></span>
              <div class="flex-grow-1">
                <div class="fw-semibold small">{{ c.label }}</div>
                <div class="text-muted small">{{ c.message }}</div>
              </div>
              <span :class="['ab-badge ab-check-badge', severityBadge(c.severity)]">{{ c.severity }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import AbIcon from './components/AbIcon.vue'
import PageHeader from './components/PageHeader.vue'

export default {
  name: 'AnalyzerPage',
  components: { AbIcon, PageHeader },

  data() {
    const cfg = window.aiBoostAnalyzer || {}
    return {
      activeTab: 'seo',

      // Config from PHP
      token:    cfg.token    || '',
      tokenName: cfg.tokenName || '',
      endpoints: cfg.endpoints || {},
      defaultBaseUrl: cfg.baseUrl || '',

      // SEO tab
      seoMode:    'single',          // 'single' | 'batch'
      seoUrl:     cfg.baseUrl || '',
      seoLoading: false,
      seoError:   null,
      seoResult:  null,

      // SEO batch (multi-URL) state
      BATCH_SIZE:          5,
      batchUrls:           '',
      batchLoadingSitemap: false,
      batchScanning:       false,
      batchCancelled:      false,
      batchSitemapMsg:     '',
      batchSitemapMsgOk:   false,
      batchProgress:       { done: 0, total: 0 },
      batchErrors:         [],
      batchError:          null,
      batchAggregate:      null,    // { avgScore, scannedCount, failedCount, totals, checks }

      // JSON-LD tab
      jsonldInput:   '',
      jsonldLoading: false,
      jsonldError:   null,
      jsonldResult:  null,
      fetchFromUrl:  '',
      fetchLoading:  false,
      fetchError:    null,
      fetchBlocks:   [],
      selectedBlockIndex: 0,

      // AI Visibility tab
      aiUrl:     '',
      aiLoading: false,
      aiError:   null,
      aiResult:  null,

      // Per-check fix UI state
      expanded:   {},
      fixingId:   null,
      fixedIds:   [],
      fixErrors:  {},
    }
  },

  computed: {
    crawlerChecks() {
      if (!this.aiResult) return []
      return this.aiResult.checks.filter(c => c.id.startsWith('crawler_'))
    },
    nonCrawlerChecks() {
      if (!this.aiResult) return []
      return this.aiResult.checks.filter(c => !c.id.startsWith('crawler_'))
    },
    batchUrlList() {
      return this.batchUrls
        .split('\n')
        .map(s => s.trim())
        .filter(Boolean)
    },
    batchUrlCount() {
      return this.batchUrlList.length
    },
    batchBusy() {
      return this.batchScanning || this.batchLoadingSitemap
    },
    batchProgressPct() {
      const t = this.batchProgress.total
      return t > 0 ? Math.round((this.batchProgress.done / t) * 100) : 0
    },
    batchFirstPassIndex() {
      if (!this.batchAggregate || !this.batchAggregate.checks) return -1
      const checks = this.batchAggregate.checks
      const idx = checks.findIndex(c => c.failCount === 0)
      // Only show divider if there is at least one failing check before the first passing one
      if (idx <= 0) return -1
      return idx
    },
  },

  watch: {
    selectedBlockIndex(idx) {
      if (this.fetchBlocks[idx] !== undefined) {
        this.jsonldInput = this.fetchBlocks[idx]
      }
    },
  },

  methods: {
    // ── SEO ────────────────────────────────────────────────────────────────
    async runSeo() {
      this.seoError  = null
      this.seoResult = null
      const url = this.seoUrl.trim()
      if (!url) { this.seoError = 'Please enter a URL.'; return }
      this.seoLoading = true
      try {
        const fd = new FormData()
        fd.append(this.tokenName, this.token)
        fd.append('url', url)
        const res  = await fetch(this.endpoints.runSeo, { method: 'POST', body: fd })
        const data = await res.json()
        if (!data.success) { this.seoError = data.message || 'Analysis failed.'; return }
        this.seoResult = data.result
        if (data.result.error) { this.seoError = data.result.error }
      } catch (e) {
        this.seoError = 'Request failed: ' + e.message
      } finally {
        this.seoLoading = false
      }
    },

    // ── SEO batch (multi-URL) ──────────────────────────────────────────────
    async loadSitemapForBatch() {
      this.batchSitemapMsg = ''
      this.batchSitemapMsgOk = false
      this.batchLoadingSitemap = true
      try {
        const fd = new FormData()
        fd.append(this.tokenName, '1')
        const res  = await fetch(this.endpoints.getSitemapUrls, { method: 'POST', body: fd, credentials: 'same-origin' })
        const data = await res.json()
        if (!data.success) {
          this.batchSitemapMsgOk = false
          this.batchSitemapMsg   = data.message || 'Could not load sitemap.'
          return
        }
        const urls = Array.isArray(data.urls) ? data.urls : []
        this.batchUrls = urls.join('\n')
        this.batchSitemapMsgOk = true
        this.batchSitemapMsg   = `Loaded ${data.count} URL(s) from ${data.sitemapUrl}.` +
          (urls.length > 50 ? ' Scan will process the first 50 URLs per run.' : '')
      } catch (e) {
        this.batchSitemapMsgOk = false
        this.batchSitemapMsg   = 'Failed to load sitemap: ' + e.message
      } finally {
        this.batchLoadingSitemap = false
      }
    },

    cancelBatch() {
      this.batchCancelled = true
    },

    async runBatchSeo() {
      this.batchError     = null
      this.batchErrors    = []
      this.batchAggregate = null
      this.expanded       = {}
      this.fixedIds       = []
      this.fixErrors      = {}

      const all = this.batchUrlList.slice(0, 50)
      if (!all.length) { this.batchError = 'Add at least one URL.'; return }

      this.batchScanning  = true
      this.batchCancelled = false
      this.batchProgress  = { done: 0, total: all.length }

      const perUrlResults = []  // array of analyze() results
      const BATCH = this.BATCH_SIZE

      for (let i = 0; i < all.length; i += BATCH) {
        if (this.batchCancelled) break
        const slice = all.slice(i, i + BATCH)
        const settled = await Promise.all(slice.map(u => this._runSingleSeoForBatch(u)))
        for (const r of settled) {
          if (r.ok) {
            perUrlResults.push(r.result)
          } else {
            this.batchErrors.push(`${r.url}: ${r.error}`)
          }
        }
        this.batchProgress = { done: Math.min(i + BATCH, all.length), total: all.length }
      }

      this.batchAggregate = this._aggregateBatchResults(perUrlResults)
      this.batchScanning  = false
    },

    async _runSingleSeoForBatch(url) {
      try {
        const fd = new FormData()
        fd.append(this.tokenName, this.token)
        fd.append('url', url)
        const res  = await fetch(this.endpoints.runSeo, { method: 'POST', body: fd, credentials: 'same-origin' })
        const data = await res.json()
        if (!data.success) {
          return { ok: false, url, error: data.message || 'Analysis failed.' }
        }
        if (data.result && data.result.error) {
          return { ok: false, url, error: data.result.error }
        }
        return { ok: true, url, result: data.result }
      } catch (e) {
        return { ok: false, url, error: e.message || String(e) }
      }
    },

    /**
     * Aggregate N per-URL analyzer results into a single check list, grouping
     * affected_urls per check-id. The summary severity for each check is the
     * worst severity seen across all scanned URLs (error > warning > info > pass).
     */
    _aggregateBatchResults(perUrlResults) {
      const scannedCount = perUrlResults.length
      if (scannedCount === 0) {
        return {
          avgScore:     0,
          scannedCount: 0,
          failedCount:  this.batchErrors.length,
          totals:       { pass: 0, info: 0, warning: 0, error: 0 },
          checks:       [],
        }
      }

      // Severity ranking — pick the worst across URLs per check id
      const rank = { pass: 0, info: 1, warning: 2, error: 3 }
      const rankToLevel = ['pass', 'info', 'warning', 'error']

      // checkId -> { id, label, severity, why, suggestion, fix_action,
      //              fix_payload, failCount, affected_urls: [{url, edit_url}] }
      const agg = new Map()

      // To keep stable ordering, follow the first result's check ordering.
      const orderedIds = []

      for (const result of perUrlResults) {
        const url    = result.url
        const checks = Array.isArray(result.checks) ? result.checks : []
        for (const c of checks) {
          if (!agg.has(c.id)) {
            orderedIds.push(c.id)
            agg.set(c.id, {
              id:          c.id,
              label:       c.label,
              severity:    'pass',
              why:         c.why || '',
              suggestion:  c.suggestion || '',
              fix_action:  c.fix_action || 'none',
              fix_payload: c.fix_payload || null,
              failCount:   0,
              affected_urls: [],
              _seenUrls:   new Set(),
            })
          }
          const entry  = agg.get(c.id)
          const sev    = c.severity || 'pass'
          if (rank[sev] > rank[entry.severity]) entry.severity = sev

          // Prefer richest fix metadata seen (open_tab/apply_setting > none)
          if ((!entry.why || entry.why === '') && c.why) entry.why = c.why
          if ((!entry.suggestion || entry.suggestion === '') && c.suggestion) entry.suggestion = c.suggestion
          if ((entry.fix_action === 'none' || !entry.fix_action) && c.fix_action && c.fix_action !== 'none') {
            entry.fix_action  = c.fix_action
            entry.fix_payload = c.fix_payload || entry.fix_payload
          }

          if (sev !== 'pass') {
            entry.failCount++
            // Use affected_urls from the per-URL result when present (carries
            // edit_url for h1/img_alt); otherwise synthesise an entry from the
            // result URL plus the open_editor edit_url if available.
            let pageEntries = []
            if (Array.isArray(c.affected_urls) && c.affected_urls.length) {
              pageEntries = c.affected_urls
            } else {
              const editUrl = c.fix_payload && c.fix_payload.edit_url ? c.fix_payload.edit_url : null
              const e = { url }
              if (editUrl) e.edit_url = editUrl
              pageEntries = [e]
            }
            for (const p of pageEntries) {
              if (!p || !p.url || entry._seenUrls.has(p.url)) continue
              entry._seenUrls.add(p.url)
              entry.affected_urls.push(p)
            }
          }
        }
      }

      // Build totals (over the aggregated, deduplicated check list)
      const totals = { pass: 0, info: 0, warning: 0, error: 0 }
      const checks = []
      for (const id of orderedIds) {
        const entry = agg.get(id)
        delete entry._seenUrls
        totals[entry.severity] = (totals[entry.severity] || 0) + 1
        checks.push(entry)
      }

      // Sort by failCount descending so the checks affecting the most pages
      // surface first; checks where all pages pass are grouped at the bottom.
      checks.sort((a, b) => b.failCount - a.failCount)

      // Average score across successfully analysed URLs
      const totalScore = perUrlResults.reduce((s, r) => s + (typeof r.score === 'number' ? r.score : 0), 0)
      const avgScore   = Math.round(totalScore / scannedCount)

      return {
        avgScore,
        scannedCount,
        failedCount: this.batchErrors.length,
        totals,
        checks,
      }
    },

    // ── JSON-LD ────────────────────────────────────────────────────────────
    async fetchFromUrlAction() {
      this.fetchError  = null
      this.fetchBlocks = []
      const url = this.fetchFromUrl.trim()
      if (!url) return
      this.fetchLoading = true
      try {
        const fd = new FormData()
        fd.append(this.tokenName, this.token)
        fd.append('url', url)
        const res  = await fetch(this.endpoints.fetchUrl, { method: 'POST', body: fd })
        const data = await res.json()
        if (!data.success) { this.fetchError = data.message || 'Fetch failed.'; return }
        if (!data.count) { this.fetchError = 'No JSON-LD blocks found at that URL.'; return }
        this.fetchBlocks        = data.jsonldBlocks
        this.selectedBlockIndex = 0
        this.jsonldInput        = this.fetchBlocks[0]
      } catch (e) {
        this.fetchError = 'Fetch failed: ' + e.message
      } finally {
        this.fetchLoading = false
      }
    },

    async validateJsonLd() {
      this.jsonldError  = null
      this.jsonldResult = null
      const input = this.jsonldInput.trim()
      if (!input) { this.jsonldError = 'Paste JSON-LD code first.'; return }
      this.jsonldLoading = true
      try {
        const fd = new FormData()
        fd.append(this.tokenName, this.token)
        fd.append('json_string', input)
        const res  = await fetch(this.endpoints.validateJsonLd, { method: 'POST', body: fd })
        const data = await res.json()
        if (!data.success) { this.jsonldError = data.message || 'Validation failed.'; return }
        this.jsonldResult = data.result
      } catch (e) {
        this.jsonldError = 'Request failed: ' + e.message
      } finally {
        this.jsonldLoading = false
      }
    },

    prettyPrint() {
      try {
        const parsed     = JSON.parse(this.jsonldInput)
        this.jsonldInput = JSON.stringify(parsed, null, 2)
        this.jsonldError = null
      } catch (e) {
        this.jsonldError = 'Cannot pretty-print: ' + e.message
      }
    },

    clearJsonLd() {
      this.jsonldInput   = ''
      this.jsonldResult  = null
      this.jsonldError   = null
      this.fetchBlocks   = []
      this.fetchFromUrl  = ''
      this.fetchError    = null
    },

    blockPreview(block) {
      try {
        const p = JSON.parse(block)
        return p['@type'] ? '@type: ' + p['@type'] : block.substring(0, 50) + '…'
      } catch {
        return block.substring(0, 50) + '…'
      }
    },

    // ── AI Visibility ──────────────────────────────────────────────────────
    async runAiVisibility() {
      this.aiError  = null
      this.aiResult = null
      this.aiLoading = true
      try {
        const fd = new FormData()
        fd.append(this.tokenName, this.token)
        const baseUrl = this.aiUrl.trim() || this.defaultBaseUrl
        fd.append('base_url', baseUrl)
        const res  = await fetch(this.endpoints.runAiVisibility, { method: 'POST', body: fd })
        const data = await res.json()
        if (!data.success) { this.aiError = data.message || 'Analysis failed.'; return }
        this.aiResult = data.result
        if (data.result.error) { this.aiError = data.result.error }
      } catch (e) {
        this.aiError = 'Request failed: ' + e.message
      } finally {
        this.aiLoading = false
      }
    },

    // ── Shared helpers ─────────────────────────────────────────────────────
    scoreClass(score) {
      if (score >= 80) return 'ab-score--good'
      if (score >= 50) return 'ab-score--ok'
      return 'ab-score--poor'
    },

    scoreLabel(score) {
      if (score >= 80) return 'Good SEO Score'
      if (score >= 50) return 'Needs Improvement'
      return 'Poor — Action Required'
    },

    aiScoreLabel(score) {
      if (score >= 80) return 'Excellent AI Visibility'
      if (score >= 50) return 'Moderate AI Visibility'
      return 'Low AI Visibility — Action Required'
    },

    countSeverity(checks, level) {
      return (checks || []).filter(c => (c.severity || c.level) === level).length
    },

    severityIcon(level) {
      const map = {
        pass:    'icon-check-circle text-success',
        error:   'icon-times-circle text-danger',
        warning: 'icon-warning text-warning',
        info:    'icon-info-circle text-info',
      }
      return map[level] || 'icon-circle'
    },

    // ── SEO check fix-it helpers ───────────────────────────────────────────
    hasFixDetails(c) {
      return !!(c.why || c.suggestion || (c.fix_action && c.fix_action !== 'none') || (c.affected_urls && c.affected_urls.length))
    },

    toggleExpand(id) {
      this.expanded = { ...this.expanded, [id]: !this.expanded[id] }
    },

    tabLabel(tab) {
      const map = {
        general:  'General', org: 'Organization', schema: 'Schema',
        sitemap:  'Sitemap', social: 'Social', analytics: 'Analytics',
        aeo:      'AI Visibility', code: 'Code', debug: 'Debug',
      }
      return map[tab] || (tab ? tab.charAt(0).toUpperCase() + tab.slice(1) : 'Settings')
    },

    settingsLink(c) {
      // SPA settings deep-link: tab + field live inside the hash
      // (…view=app#/settings?tab=X&field=Y). The first hash param needs '?',
      // not '&' — so look only at the part after '#', not the whole URL.
      const base = (this.endpoints.settingsView || 'index.php?option=com_aiboost&view=app#/settings')
      const p    = c.fix_payload || {}
      const hashHasQuery = base.includes('#') && base.split('#')[1].includes('?')
      const sep  = hashHasQuery ? '&' : '?'
      const qs   = []
      if (p.tab)   qs.push('tab='   + encodeURIComponent(p.tab))
      if (p.field) qs.push('field=' + encodeURIComponent(p.field))
      return qs.length ? base + sep + qs.join('&') : base
    },

    async applyFix(c) {
      this.fixErrors = { ...this.fixErrors, [c.id]: null }
      const p = c.fix_payload || {}
      if (!p.setting) {
        this.fixErrors = { ...this.fixErrors, [c.id]: 'Missing setting key.' }
        return
      }
      this.fixingId = c.id
      try {
        const fd = new FormData()
        fd.append(this.tokenName, this.token)
        fd.append('setting', p.setting)
        fd.append('value',   p.value || '1')
        const res  = await fetch(this.endpoints.applyFix, { method: 'POST', body: fd })
        const data = await res.json()
        if (!data.success) {
          this.fixErrors = { ...this.fixErrors, [c.id]: data.message || 'Fix failed.' }
          return
        }
        if (!this.fixedIds.includes(c.id)) this.fixedIds.push(c.id)
      } catch (e) {
        this.fixErrors = { ...this.fixErrors, [c.id]: 'Request failed: ' + e.message }
      } finally {
        this.fixingId = null
      }
    },

    severityBadge(level) {
      const map = {
        pass:    'ab-badge--success',
        error:   'ab-badge--danger',
        warning: 'ab-badge--warning',
        info:    'ab-badge--info',
      }
      return map[level] || ''
    },
  },
}
</script>

<style scoped>
.ab-score-circle {
  width: 5rem;
  height: 5rem;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.6rem;
  font-weight: 700;
  color: #fff;
}
.ab-score-circle--sm {
  width: 3rem;
  height: 3rem;
  font-size: 1rem;
}
.ab-score--good  { background: #28a745; }
.ab-score--ok    { background: #fd7e14; }
.ab-score--poor  { background: #dc3545; }

.ab-jsonld-textarea {
  font-size: .82rem;
  resize: vertical;
  font-family: monospace;
}

/* Input group: prefix icon + ab-input + ab-btn connected */
.ab-input-group {
  display: flex;
  gap: 0;
}
.ab-input-prefix {
  display: flex;
  align-items: center;
  padding: .4rem .65rem;
  background: var(--ab-surface-raised);
  border: 1px solid var(--ab-border);
  border-right: none;
  border-radius: var(--ab-radius) 0 0 var(--ab-radius);
  color: var(--ab-text-muted);
  flex-shrink: 0;
}
.ab-input-group__field {
  border-radius: 0 !important;
  border-right: none !important;
  flex: 1;
}
.ab-input-group__btn {
  border-radius: 0 var(--ab-radius) var(--ab-radius) 0 !important;
  white-space: nowrap;
}

/* Check list (replaces Bootstrap list-group) */
.ab-check-list {
  border-top: 1px solid var(--ab-border);
}
.ab-check-row {
  display: flex;
  align-items: flex-start;
  gap: .75rem;
  padding: .65rem 1rem;
  border-bottom: 1px solid var(--ab-border);
}
.ab-check-badge {
  margin-left: auto;
  flex-shrink: 0;
  align-self: center;
}
.ab-check-item {
  border-bottom: 1px solid var(--ab-border);
}
.ab-check-divider {
  display: flex;
  align-items: center;
  gap: .75rem;
  padding: .5rem 1rem;
  background: var(--ab-surface-raised);
  border-bottom: 1px solid var(--ab-border);
}
.ab-check-divider__line {
  flex: 1;
  height: 1px;
  background: var(--ab-border);
}
.ab-check-divider__label {
  font-size: .75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .04em;
  color: var(--ab-success);
  white-space: nowrap;
}
.ab-check-item .ab-check-row {
  border-bottom: none;
  cursor: pointer;
  transition: background .15s;
}
.ab-check-item .ab-check-row:hover {
  background: var(--ab-surface-raised);
}
.ab-expand-chevron {
  align-self: center;
  color: var(--ab-text-muted);
  transition: transform .15s ease;
}
.ab-expand-chevron--open {
  transform: rotate(180deg);
}
.ab-check-detail {
  padding: .75rem 1rem 1rem 2.6rem;
  background: var(--ab-surface-raised);
  border-top: 1px dashed var(--ab-border);
}
.ab-check-detail__block + .ab-check-detail__block {
  margin-top: .6rem;
}
.ab-check-detail__label {
  font-size: .78rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .03em;
  color: var(--ab-text-muted);
  margin-bottom: .2rem;
}
.ab-check-detail__text {
  font-size: .85rem;
  color: var(--ab-text);
  line-height: 1.5;
}
.ab-check-detail__actions {
  margin-top: .85rem;
  display: flex;
  align-items: center;
  gap: .5rem;
  flex-wrap: wrap;
}
.ab-btn--sm {
  padding: .3rem .65rem;
  font-size: .82rem;
}

/* Affected pages list inside expanded check detail */
.ab-affected-urls {
  border: 1px solid var(--ab-border);
  border-radius: var(--ab-radius);
  background: var(--ab-surface);
  padding: .3rem .6rem;
}
.ab-affected-urls__summary {
  font-size: .8rem;
  font-weight: 600;
  cursor: pointer;
  color: var(--ab-text-muted);
  user-select: none;
  list-style: none;
}
.ab-affected-urls__summary::-webkit-details-marker { display: none; }
.ab-affected-url-list {
  margin: .4rem 0 .1rem 0;
  padding-left: 1rem;
  font-size: .82rem;
  list-style: disc;
}
.ab-affected-url-list li {
  padding: .15rem 0;
  word-break: break-all;
}
.ab-edit-link {
  font-size: .78rem;
  color: var(--ab-text-muted);
  text-decoration: none;
  white-space: nowrap;
}
.ab-edit-link:hover { text-decoration: underline; }

/* AI crawler pills */
.ab-crawler-pill {
  font-size: .8rem;
  border: 1px solid var(--ab-border);
  border-radius: var(--ab-radius);
  background: var(--ab-surface);
}
.ab-crawler-pill--ok   { border-color: var(--ab-success, #28a745); }
.ab-crawler-pill--warn { border-color: var(--ab-warning, #ffc107); }

.ab-spin {
  display: inline-block;
  animation: ab-spin 1s linear infinite;
}
@keyframes ab-spin { to { transform: rotate(360deg); } }

/* Mode tabs (single URL vs batch) inside the SEO analyzer card */
.ab-mode-tabs {
  display: flex;
  gap: .25rem;
  list-style: none;
  padding: .25rem;
  margin: 0 0 .75rem 0;
  background: var(--ab-surface-raised);
  border-radius: var(--ab-radius);
  width: fit-content;
}
.ab-mode-tab {
  border: 0;
  background: transparent;
  padding: .35rem .85rem;
  font-size: .85rem;
  font-weight: 600;
  color: var(--ab-text-muted);
  border-radius: calc(var(--ab-radius) - 2px);
  cursor: pointer;
}
.ab-mode-tab:disabled { opacity: .5; cursor: not-allowed; }
.ab-mode-tab--active {
  background: var(--ab-surface);
  color: var(--ab-text);
  box-shadow: 0 1px 2px rgba(0,0,0,.08);
}

/* Dark theme: ensure mode tabs stay legible against dark surfaces */
[data-bs-theme=dark] .ab-mode-tabs {
  background: #2a2f36;
}
[data-bs-theme=dark] .ab-mode-tab {
  color: #adb5bd;
}
[data-bs-theme=dark] .ab-mode-tab--active {
  background: #3a4047;
  color: #f8f9fa;
  box-shadow: 0 1px 2px rgba(0,0,0,.4);
}

/* Progress bar (matches UrlCheckerPage) */
.ab-progress {
  height: 8px;
  background: var(--ab-surface-raised);
  border-radius: 4px;
  overflow: hidden;
}
.ab-progress__bar {
  height: 100%;
  background: linear-gradient(90deg, #10b981 0%, #06b6d4 100%);
  transition: width .3s ease-out;
}
</style>
