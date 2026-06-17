<template>
  <div class="ab-vue-health">

    <!-- Progress bar — animates during Re-run -->
    <div v-if="rerunning" class="ab-hc-progress-wrap">
      <div class="ab-hc-progress-bar" :style="{ width: progress + '%' }"></div>
    </div>

    <!-- Two-column layout: left sidebar + main content -->
    <div class="ab-ha-layout">

      <!-- Left sidebar removed (v0.12.12) — Health tab opens only Health overview;
           URL Checker / Analyzers / JSON-LD Validator / AI Visibility are reachable
           from the horizontal top nav and Quick Actions. -->

      <!-- ── Main content area ── -->
      <div class="ab-ha-main">

        <!-- ════════════════════════════════════════════════
             HEALTH OVERVIEW
             ════════════════════════════════════════════════ -->
        <template v-if="currentSection === 'health'">

          <!-- Score header card -->
          <div class="ab-card mb-4">
            <div class="ab-card__body">
              <div class="d-flex align-items-center gap-4 flex-wrap">

                <!-- Score circle with SVG stroke-fill animation.
                     Tooltip on the wrap explains the scoring formula —
                     mirrors the doc comment on HealthCheckService::calculateScore(). -->
                <div :class="['ab-hc-score-wrap flex-shrink-0', enterClass]"
                     :title="scoreTooltip"
                     :aria-label="scoreTooltip">
                  <svg class="ab-hc-score-svg" width="96" height="96" viewBox="0 0 96 96" aria-hidden="true">
                    <circle cx="48" cy="48" r="44" fill="none" stroke-width="5" class="ab-hc-score-track"/>
                    <circle cx="48" cy="48" r="44" fill="none" stroke-width="5"
                      stroke-dasharray="276.46"
                      stroke-dashoffset="276.46"
                      stroke-linecap="round"
                      transform="rotate(-90 48 48)"
                      class="ab-hc-score-arc"
                      :class="scoreClass"
                      :style="{ '--ab-score-target': (276.46 * (1 - score / 100)).toFixed(2) + 'px' }"/>
                  </svg>
                  <div class="ab-hc-score-num-overlay">
                    <span :class="['ab-hc-score-num', scoreClass]">{{ displayScore }}</span>
                  </div>
                </div>

                <!-- Label + actions -->
                <div class="flex-grow-1">
                  <h2 class="fs-4 fw-bold mb-1">
                    {{ scoreLabel }}
                    <span class="icon-info-circle ab-hc-score-help"
                          role="img"
                          tabindex="0"
                          :title="scoreTooltip"
                          :aria-label="scoreTooltip"></span>
                  </h2>
                  <p class="text-muted mb-2" style="font-size:.875rem">{{ scoreSummary }}</p>
                  <div class="d-flex gap-2 flex-wrap align-items-center">
                    <button type="button" class="ab-btn ab-btn--primary ab-btn--sm"
                            :disabled="rerunning" @click="rerun">
                      <span :class="['icon-refresh me-1', rerunning ? 'ab-spin' : '']" aria-hidden="true"></span>
                      {{ rerunning ? 'Running…' : 'Re-run Checks' }}
                    </button>
                    <button type="button" class="ab-btn ab-btn--ghost ab-btn--sm" @click="copyReport">
                      <span class="icon-copy me-1" aria-hidden="true"></span> Copy Report
                    </button>
                    <span v-if="actionMsg"
                          :class="['ms-2 small', actionMsgType === 'error' ? 'text-danger'
                                                : actionMsgType === 'success' ? 'text-success'
                                                : 'text-muted']">
                      {{ actionMsg }}
                    </span>
                  </div>
                </div>

                <!-- Stats pills -->
                <div class="d-flex flex-column gap-2 flex-shrink-0 text-end">
                  <span :class="['ab-badge', critFails > 0 ? 'ab-badge--danger' : 'ab-badge--success']">
                    Critical: {{ critOk }}/{{ critTotal }} OK
                  </span>
                  <span :class="['ab-badge', warnFails > 0 ? 'ab-badge--warning' : 'ab-badge--success']">
                    Warnings: {{ warnOk }}/{{ warnTotal }} OK
                  </span>
                  <span v-if="conflictFails > 0" class="ab-badge ab-badge--danger">
                    Conflicts: {{ conflictFails }}
                  </span>
                </div>

              </div>
            </div>
          </div>

          <!-- Quick Actions card — uses AI Boost Design System (.ab-*) for full
               dark/light parity across Atum, YooTheme, and custom admin templates -->
          <div class="ab-card mb-4">
            <div class="ab-card__header">
              <span class="icon-flash" aria-hidden="true"></span>Quick Actions
            </div>
            <div class="ab-card__body" style="padding:.75rem 1rem">
              <div class="ab-cluster">
                <a :href="raw.urls && raw.urls.settings ? raw.urls.settings : '#'"
                   class="ab-btn ab-btn--ghost ab-btn--sm">
                  <span class="icon-cog" aria-hidden="true"></span>Settings
                </a>
                <a :href="raw.urls && raw.urls.import ? raw.urls.import : '#'"
                   class="ab-btn ab-btn--ghost ab-btn--sm">
                  <span class="icon-upload" aria-hidden="true"></span>Import
                </a>
                <a :href="raw.urls && raw.urls.redirects ? raw.urls.redirects : '#'"
                   class="ab-btn ab-btn--ghost ab-btn--sm">
                  <span class="icon-arrow-right" aria-hidden="true"></span>Redirects
                </a>
                <a :href="raw.urls && raw.urls.analyzer ? raw.urls.analyzer : '#'"
                   class="ab-btn ab-btn--subtle ab-btn--sm">
                  <span class="icon-search" aria-hidden="true"></span>Analyzers
                </a>
                <a :href="raw.urls && raw.urls.urlchecker ? raw.urls.urlchecker : '#'"
                   class="ab-btn ab-btn--subtle ab-btn--sm">
                  <span class="icon-link" aria-hidden="true"></span>URL Checker
                </a>
                <button type="button" class="ab-btn ab-btn--ghost ab-btn--sm"
                  @click="selectSection('jsonld')">
                  <span class="icon-code" aria-hidden="true"></span>JSON-LD Validator
                </button>
                <button type="button" class="ab-btn ab-btn--ghost ab-btn--sm"
                  @click="selectSection('aivisibility')">
                  <span class="icon-lightning" aria-hidden="true"></span>AI Visibility
                </button>
                <button type="button" class="ab-btn ab-btn--ghost ab-btn--sm"
                        @click="selectSection('errors')">
                  <span class="icon-warning" aria-hidden="true"></span>Error Log
                </button>
              </div>
            </div>
          </div>

          <!-- Category cards (health checks) -->
          <template v-for="cat in categoryOrder" :key="cat">
            <div v-if="catChecks(cat).length" class="ab-card mb-3">

              <div class="ab-card__header"
                   style="cursor:pointer" @click="toggleCat(cat)">
                <span :class="[categoryIcons[cat]]" style="color:var(--ab-text-muted)" aria-hidden="true"></span>
                <strong>{{ cat }}</strong>
                <span :class="['ab-badge', catFails(cat) > 0 ? 'ab-badge--danger' : 'ab-badge--success']">
                  {{ catFails(cat) > 0
                     ? catFails(cat) + ' issue' + (catFails(cat) > 1 ? 's' : '')
                     : 'All OK' }}
                </span>
                <span class="ms-auto icon-chevron-down ab-hc-chevron"
                      :style="{ transform: collapsed[cat] ? 'rotate(-90deg)' : '' }"
                      aria-hidden="true"></span>
              </div>

              <div class="ab-card__body" style="padding:0" v-show="!collapsed[cat]">
                <div class="ab-hc-check-list">
                  <div v-for="check in catChecks(cat)" :key="check.id"
                       :class="['ab-hc-row', rowClass(check)]">

                    <span :class="[rowIcon(check), 'ab-hc-row-icon flex-shrink-0']"
                          aria-hidden="true"></span>

                    <div class="ab-hc-row-body flex-grow-1">
                      <div class="ab-hc-row-label">
                        {{ check.label }}
                        <span v-if="check.status !== 'info'"
                              :class="['ab-badge',
                                       check.status === 'critical' ? 'ab-badge--danger' : 'ab-badge--warning']"
                              style="font-size:.65rem;vertical-align:middle">
                          {{ check.status }}
                        </span>
                      </div>
                      <div class="ab-hc-row-msg">{{ check.message }}</div>
                      <!-- Contributing fields checklist (composite checks only) -->
                      <ul v-if="check.contributing_fields && check.contributing_fields.length"
                          class="ab-hc-contrib-list">
                        <li v-for="field in check.contributing_fields" :key="field.url"
                            :class="['ab-hc-contrib-item', field.pass ? 'ab-hc-contrib--pass' : 'ab-hc-contrib--fail']">
                          <span :class="field.pass ? 'icon-checkmark-circle' : 'icon-warning'"
                                aria-hidden="true"></span>
                          <a :href="field.url" class="ab-hc-contrib-link">{{ field.label }}</a>
                        </li>
                      </ul>
                    </div>

                    <div class="ab-hc-row-actions flex-shrink-0 d-flex align-items-center gap-2 flex-wrap">
                      <template v-if="!check.pass && !check.dismissed && check.fix_actions && check.fix_actions.length">
                        <a v-for="action in check.fix_actions" :key="action.url"
                           :href="fixActionHref(action)"
                           :target="fixActionHref(action).startsWith('http') ? '_blank' : '_self'"
                           :rel="fixActionHref(action).startsWith('http') ? 'noopener noreferrer' : undefined"
                           class="ab-btn ab-btn--subtle ab-btn--sm">{{ action.label }}</a>
                      </template>
                      <a v-else-if="!check.pass && !check.dismissed && check.fix_url"
                         :href="check.fix_url"
                         class="ab-btn ab-btn--subtle ab-btn--sm">Fix it</a>
                      <button v-if="check.status !== 'info'"
                              type="button"
                              class="ab-btn ab-btn--ghost ab-btn--sm"
                              :disabled="check.busy"
                              @click="toggleDismiss(check)">
                        {{ check.busy ? '…' : (check.dismissed ? 'Restore' : 'Dismiss') }}
                      </button>
                    </div>

                  </div>
                </div>
              </div>

            </div>
          </template>

        </template>

        <!-- ════════════════════════════════════════════════
             JSON-LD VALIDATOR (placeholder)
             ════════════════════════════════════════════════ -->
        <div v-else-if="currentSection === 'jsonld'" class="ab-card">
          <div class="ab-card__header">
            <span class="icon-code" aria-hidden="true"></span><strong>JSON-LD Validator</strong>
          </div>
          <div class="ab-card__body" style="text-align:center;padding:3rem 1.5rem">
            <span class="icon-code display-4 text-muted d-block mb-3" aria-hidden="true"></span>
            <h4 class="mb-2">JSON-LD Validator</h4>
            <p class="text-muted mb-4">Paste structured data JSON-LD markup and validate it against Schema.org specifications.<br>This feature is coming soon.</p>
            <a href="https://validator.schema.org" target="_blank" rel="noopener noreferrer"
               class="ab-btn ab-btn--subtle">
              <span class="icon-external-link me-1" aria-hidden="true"></span>Use Schema.org Validator (external)
            </a>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════
             AI VISIBILITY
             ════════════════════════════════════════════════ -->
        <div v-else-if="currentSection === 'aivisibility'">
          <div class="ab-card mb-3">
            <div class="ab-card__header">
              <span class="icon-lightning" aria-hidden="true"></span><strong>AI Visibility Score</strong>
            </div>
            <div class="ab-card__body">
              <div class="d-flex align-items-center gap-3 mb-3">
                <div class="ab-hc-score-wrap flex-shrink-0">
                  <svg width="96" height="96" viewBox="0 0 96 96" aria-hidden="true">
                    <circle cx="48" cy="48" r="44" fill="none" stroke-width="5" class="ab-hc-score-track"/>
                    <circle cx="48" cy="48" r="44" fill="none" stroke-width="5"
                      stroke-dasharray="276.46"
                      :stroke-dashoffset="276.46"
                      stroke-linecap="round"
                      transform="rotate(-90 48 48)"
                      class="ab-hc-score-arc"
                      :style="{ '--ab-score-target': aiScoreOffset + 'px' }"/>
                  </svg>
                  <div class="ab-hc-score-num-overlay">
                    <span :class="['ab-hc-score-num', aiScoreClass]">{{ aiScore }}</span>
                  </div>
                </div>
                <div>
                  <h5 class="mb-1">{{ aiScoreLabel }}</h5>
                  <p class="text-muted small mb-0">Based on AI Visibility / GEO signals: Schema.org, llms.txt, IndexNow, robots.txt, author markup</p>
                </div>
              </div>

              <div v-if="aeoChecks.length === 0" class="ab-alert ab-alert--info">
                No AI Visibility checks found. Make sure the AI Visibility plugin is enabled and health checks have been run.
              </div>
            </div>
          </div>

          <!-- AI Visibility checks -->
          <div v-if="aeoChecks.length" class="ab-card mb-3">
            <div class="ab-card__header">
              <strong>AI Visibility Checks ({{ aeoChecks.filter(c => c.pass || c.dismissed).length }}/{{ aeoChecks.length }} OK)</strong>
            </div>
            <div class="ab-card__body" style="padding:0">
              <div class="ab-hc-check-list">
                <div v-for="check in aeoChecks" :key="check.id"
                     :class="['ab-hc-row', rowClass(check)]">
                  <span :class="[rowIcon(check), 'ab-hc-row-icon flex-shrink-0']" aria-hidden="true"></span>
                  <div class="ab-hc-row-body flex-grow-1">
                    <div class="ab-hc-row-label">{{ check.label }}</div>
                    <div class="ab-hc-row-msg">{{ check.message }}</div>
                  </div>
                  <div class="ab-hc-row-actions flex-shrink-0">
                    <a v-if="!check.pass && !check.dismissed && check.fix_url"
                       :href="check.fix_url" class="ab-btn ab-btn--subtle ab-btn--sm">Fix it</a>
                    <button v-if="check.status !== 'info'" type="button"
                            class="ab-btn ab-btn--ghost ab-btn--sm"
                            :disabled="check.busy" @click="toggleDismiss(check)">
                      {{ check.busy ? '…' : (check.dismissed ? 'Restore' : 'Dismiss') }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Schema checks relevant to AI -->
          <div v-if="schemaChecks.length" class="ab-card mb-3">
            <div class="ab-card__header">
              <strong>Schema.org (AI signals)</strong>
            </div>
            <div class="ab-card__body" style="padding:0">
              <div class="ab-hc-check-list">
                <div v-for="check in schemaChecks" :key="check.id"
                     :class="['ab-hc-row', rowClass(check)]">
                  <span :class="[rowIcon(check), 'ab-hc-row-icon flex-shrink-0']" aria-hidden="true"></span>
                  <div class="ab-hc-row-body flex-grow-1">
                    <div class="ab-hc-row-label">{{ check.label }}</div>
                    <div class="ab-hc-row-msg">{{ check.message }}</div>
                  </div>
                  <div class="ab-hc-row-actions flex-shrink-0">
                    <a v-if="!check.pass && !check.dismissed && check.fix_url"
                       :href="check.fix_url" class="ab-btn ab-btn--subtle ab-btn--sm">Fix it</a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <ErrorsPage v-else-if="currentSection === 'errors'" />

      </div><!-- end main content -->
    </div><!-- end layout -->

    <!-- Footer -->
    <p class="text-muted small mt-3">
      &copy; 2025
      <a href="https://aiboostnow.com" target="_blank" rel="noopener">AI Boost</a>
      &nbsp;&middot;&nbsp;
      <a href="https://aiboostnow.com/docs" target="_blank" rel="noopener">Documentation</a>
    </p>

  </div>
</template>

<script>
import { reactive, computed, ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ErrorsPage from './ErrorsPage.vue'

const CATEGORY_ORDER = ['General', 'Conflicts', 'Schema', 'Sitemap', 'Social', 'Analytics', 'AEO', 'Crawlers & Robots', 'Integrations', 'License']

// Fix-action targets that are full pages inside the Vue admin SPA shell
// (view=app hash routes) rather than tabs of the Settings form. Any
// target_tab NOT listed here is treated as a Settings tab and deep-linked
// via the same ?tab=&field= contract HealthCheckService::settingsUrl() uses.
const SPA_PAGE_ROUTES = {
  errors:       '/health/errors',
  licenses:     '/licenses',
  integrations: '/integrations',
  redirects:    '/redirects',
  analyzers:    '/analyzers',
  autopilot:    '/autopilot',
  urlchecker:   '/urlchecker',
  import:       '/import',
}
const CATEGORY_ICONS = {
  General:   'icon-home',
  Conflicts: 'icon-warning',
  Schema:    'icon-list',
  Sitemap:   'icon-sitemap',
  Social:    'icon-share',
  Analytics: 'icon-chart-line',
  AEO:       'icon-lightning',
  'Crawlers & Robots': 'icon-shield',
  Integrations: 'icon-link',
  License:   'icon-key',
}

export default {
  name: 'HealthApp',
  components: { ErrorsPage },

  setup () {
    const raw   = window.aiBoostHealth || {}
    const score = raw.score ?? 0
    const route = useRoute()
    const router = useRouter()

    const checks        = reactive((raw.checks || []).map(c => ({ ...c, busy: false })))
    const rerunning     = ref(false)
    const progress      = ref(0)
    const actionMsg     = ref('')
    const actionMsgType = ref('')
    const collapsed     = reactive({})
    const currentSection = ref(resolveRouteSection())

    // ── Count-up animation ───────────────────────────────────────────────────
    const displayScore = ref(0)
    const enterClass   = ref('')

    onMounted(() => {
      const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches

      if (reduced) {
        displayScore.value = score
        enterClass.value   = 'ab-hc-score--enter'
        return
      }

      enterClass.value = 'ab-hc-score--enter'

      const duration  = 800
      const startTime = performance.now()

      function easeOut (t) { return 1 - Math.pow(1 - t, 3) }

      function tick (now) {
        const elapsed  = now - startTime
        const prog     = Math.min(elapsed / duration, 1)
        displayScore.value = Math.round(easeOut(prog) * score)
        if (prog < 1) requestAnimationFrame(tick)
      }

      requestAnimationFrame(tick)
    })

    let actionTimer   = null
    let progressTimer = null

    function setMsg (msg, type) {
      clearTimeout(actionTimer)
      actionMsg.value     = msg
      actionMsgType.value = type
      if (msg) actionTimer = setTimeout(() => { actionMsg.value = '' }, 4000)
    }

    // ── Computed stats ──────────────────────────────────────────────────────
    const scoreLabel = computed(() => {
      if (score >= 80) return 'Good'
      if (score >= 50) return 'Needs Work'
      return 'Critical Issues'
    })

    const scoreClass = computed(() => {
      if (score >= 80) return 'ab-hc-score--green'
      if (score >= 50) return 'ab-hc-score--orange'
      return 'ab-hc-score--red'
    })

    const critFails = computed(() =>
      checks.filter(c => c.status === 'critical' && !c.pass && !c.dismissed && c.category !== 'Conflicts').length)
    const critTotal = computed(() =>
      checks.filter(c => c.status === 'critical' && c.category !== 'Conflicts').length)
    const critOk = computed(() =>
      critTotal.value - checks.filter(c => c.status === 'critical' && !c.pass && c.category !== 'Conflicts').length)

    const warnFails = computed(() =>
      checks.filter(c => c.status === 'warning' && !c.pass && !c.dismissed && c.category !== 'Conflicts').length)
    const warnTotal = computed(() =>
      checks.filter(c => c.status === 'warning' && c.category !== 'Conflicts').length)
    const warnOk = computed(() =>
      warnTotal.value - checks.filter(c => c.status === 'warning' && !c.pass && c.category !== 'Conflicts').length)

    const conflictFails = computed(() =>
      checks.filter(c => c.category === 'Conflicts' && !c.dismissed).length)

    // Plain-English explanation of the score formula. Mirrors the doc
     // comment on HealthCheckService::calculateScore() — keep both in
     // lockstep when the penalty constants or excluded categories change.
    const scoreTooltip =
      'Site Health score = the share of weighted checks that pass — '
      + 'each failing critical counts 3× a warning. It reflects partial '
      + 'success and only reaches 0 when every scoring check fails. '
      + 'Informational, dismissed, and Conflicts-category items do not '
      + 'lower the score — conflicts are reported separately in the '
      + 'Conflicts pill.'

    const scoreSummary = computed(() => {
      const parts = []
      if (critFails.value) parts.push(critFails.value + ' critical issue' + (critFails.value > 1 ? 's' : ''))
      if (warnFails.value) parts.push(warnFails.value + ' warning' + (warnFails.value > 1 ? 's' : ''))
      if (conflictFails.value) parts.push(conflictFails.value + ' conflict' + (conflictFails.value > 1 ? 's' : ''))
      return parts.length ? parts.join(', ') + ' found.' : 'All checks passed.'
    })

    // ── AI Visibility computed ───────────────────────────────────────────────
    const aeoChecks = computed(() =>
      checks.filter(c => c.category === 'AEO'))

    const schemaChecks = computed(() =>
      checks.filter(c => c.category === 'Schema' &&
        (c.id || '').match(/schema_plugin|org_name|org_logo|author/i)))

    const aiScore = computed(() => {
      // Prefer the authoritative weighted score computed server-side
      // (info_ai_visibility_score) so the AI Visibility panel and the Health
      // info row never show two different numbers for the same thing.
      const phpCheck = checks.find(c => c.id === 'info_ai_visibility_score')
      const m = phpCheck && (phpCheck.message || '').match(/(\d+)\s*\/\s*100/)
      if (m) return parseInt(m[1], 10)
      // Fallback: pass-ratio of AEO checks (older builds without the PHP score).
      const total  = aeoChecks.value.length
      if (!total) return 0
      const passed = aeoChecks.value.filter(c => c.pass || c.status === 'info').length
      return Math.round((passed / total) * 100)
    })

    const aiScoreClass = computed(() => {
      if (aiScore.value >= 80) return 'ab-hc-score--green'
      if (aiScore.value >= 50) return 'ab-hc-score--orange'
      return 'ab-hc-score--red'
    })

    const aiScoreLabel = computed(() => {
      if (aiScore.value >= 80) return 'Good AI visibility'
      if (aiScore.value >= 50) return 'AI visibility needs work'
      return 'Poor AI visibility'
    })

    const aiScoreOffset = computed(() =>
      parseFloat((276.46 * (1 - aiScore.value / 100)).toFixed(2)))

    // ── Category helpers ────────────────────────────────────────────────────
    function catChecks (cat) {
      return checks.filter(c => (c.category || 'General') === cat)
    }
    function catFails (cat) {
      return catChecks(cat).filter(c => !c.pass && !c.dismissed && c.status !== 'info').length
    }
    function toggleCat (cat) {
      collapsed[cat] = !collapsed[cat]
    }

    // ── Row helpers ─────────────────────────────────────────────────────────
    function rowClass (check) {
      if (check.dismissed)                       return 'ab-hc-row--dismissed'
      if (check.pass || check.status === 'info') return 'ab-hc-row--pass'
      if (check.status === 'critical')           return 'ab-hc-row--fail-critical'
      return 'ab-hc-row--fail-warning'
    }
    function rowIcon (check) {
      if (check.dismissed)                       return 'icon-minus-circle ab-hc-icon--dismissed'
      if (check.pass || check.status === 'info') return 'icon-checkmark-circle ab-hc-icon--pass'
      if (check.status === 'critical')           return 'icon-warning ab-hc-icon--critical'
      return 'icon-info-circle ab-hc-icon--warning'
    }

    // ── Re-run with animated progress bar ───────────────────────────────────
    function rerun () {
      if (rerunning.value) return
      rerunning.value = true
      progress.value  = 0

      const step = () => {
        if (progress.value < 85) {
          progress.value += 2
          progressTimer = setTimeout(step, 35)
        }
      }
      progressTimer = setTimeout(step, 35)

      const fd = new FormData()
      fd.append(raw.tokenName || '', '1')

      fetch(raw.urls?.rerun || 'index.php?option=com_aiboost&task=health.rerun&format=json', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd,
      })
        .then(r => r.json())
        .then(res => {
          clearTimeout(progressTimer)
          progress.value = 100
          if (res.success) {
            setTimeout(() => location.reload(), 350)
          } else {
            rerunning.value = false
            progress.value  = 0
            setMsg(res.message || 'Error running checks.', 'error')
          }
        })
        .catch(() => {
          clearTimeout(progressTimer)
          rerunning.value = false
          progress.value  = 0
          setMsg('Network error. Please try again.', 'error')
        })
    }

    // ── Dismiss / Restore ────────────────────────────────────────────────────
    // Build the final href for a fix action, honouring the structured
    // contract (target_tab + target_field) when present. Health is
    // rendered in its own page (view=health), so navigating to another
    // tab or page is a full page nav, with the field passed as a real
    // query param (before the hash) so the destination view can scroll
    // to / highlight its [data-ab-field] element after mount.
    //
    // Resolution order:
    //   1. target_tab naming an SPA page  → view=app hash route
    //   2. any other target_tab           → Settings form deep link
    //      (?tab=&field= — same contract as settingsUrl() server-side;
    //      covers manifest-declared fix actions, which carry no url)
    //   3. explicit url                   → as-is
    //   4. nothing usable                 → '#'
    function fixActionHref (action) {
      if (!action) return '#'
      if (action.target_tab) {
        const tab     = String(action.target_tab)
        const field   = action.target_field ? String(action.target_field) : ''
        const fieldQs = field ? '&field=' + encodeURIComponent(field) : ''
        const spaRoute = Object.prototype.hasOwnProperty.call(SPA_PAGE_ROUTES, tab)
          ? SPA_PAGE_ROUTES[tab]
          : ''
        if (spaRoute) {
          return 'index.php?option=com_aiboost&view=app' + fieldQs + '#' + spaRoute
        }
        return 'index.php?option=com_aiboost&view=settings&tab=' + encodeURIComponent(tab) + fieldQs
      }
      return action.url || '#'
    }

    function resolveSection (section) {
      return ['health', 'jsonld', 'aivisibility', 'errors'].includes(section) ? section : 'health'
    }

    function resolveRouteSection () {
      if (route.path === '/health/errors') return 'errors'
      return resolveSection(route.query.section)
    }

    function selectSection (section) {
      const next = resolveSection(section)
      currentSection.value = next
      if (next === 'errors') {
        router.replace('/health/errors')
        return
      }
      router.replace({ path: '/health', query: next === 'health' ? {} : { section: next } })
    }

    watch(() => route.fullPath, () => {
      currentSection.value = resolveRouteSection()
    })

    function toggleDismiss (check) {
      if (check.busy) return
      const action = check.dismissed ? 'restore' : 'dismiss'
      check.busy = true

      const fd = new FormData()
      fd.append(raw.tokenName || '', '1')
      fd.append('check_id', check.id)
      fd.append('action', action)

      fetch(raw.urls?.dismiss || 'index.php?option=com_aiboost&task=health.dismiss&format=json', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd,
      })
        .then(r => r.json())
        .then(res => {
          check.busy = false
          if (res.success) {
            check.dismissed = action === 'dismiss'
            setMsg(action === 'dismiss' ? 'Check dismissed.' : 'Check restored.', 'success')
          } else {
            setMsg(res.message || 'Error saving.', 'error')
          }
        })
        .catch(() => {
          check.busy = false
          setMsg('Network error.', 'error')
        })
    }

    // ── Copy report ─────────────────────────────────────────────────────────
    function copyReport () {
      const lines = [
        'AI Boost for Joomla — Health Report',
        'Score: ' + score + '/100',
        'Generated: ' + new Date().toLocaleString(),
        '============================================================',
      ]
      CATEGORY_ORDER.forEach(cat => {
        const list = catChecks(cat)
        if (!list.length) return
        lines.push('')
        lines.push('── ' + cat.toUpperCase() + ' ──')
        list.forEach(c => {
          const st = c.dismissed ? '[DISMISSED]' : (c.pass ? '[OK]' : '[FAIL]')
          lines.push(st + ' ' + c.label + ': ' + c.message)
        })
      })
      lines.push('')
      lines.push('— AI Boost (aiboostnow.com)')
      const text = lines.join('\n')

      if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(text)
          .then(() => setMsg('Report copied to clipboard.', 'success'))
          .catch(() => fallbackCopy(text))
      } else {
        fallbackCopy(text)
      }
    }

    function fallbackCopy (text) {
      const ta = document.createElement('textarea')
      ta.value = text
      ta.style.cssText = 'position:fixed;left:-9999px'
      document.body.appendChild(ta)
      ta.select()
      try {
        document.execCommand('copy')
        setMsg('Report copied to clipboard.', 'success')
      } catch (e) {
        setMsg('Could not copy — please select manually.', 'error')
      }
      document.body.removeChild(ta)
    }

    return {
      raw,
      score,
      displayScore,
      enterClass,
      checks,
      categoryOrder: CATEGORY_ORDER,
      categoryIcons: CATEGORY_ICONS,
      rerunning, progress, actionMsg, actionMsgType, collapsed,
      currentSection,
      scoreLabel, scoreClass, scoreSummary, scoreTooltip,
      critFails, critTotal, critOk,
      warnFails, warnTotal, warnOk,
      conflictFails,
      aeoChecks, schemaChecks,
      aiScore, aiScoreClass, aiScoreLabel, aiScoreOffset,
      catChecks, catFails, toggleCat,
      rowClass, rowIcon,
      rerun, toggleDismiss, copyReport,
      fixActionHref,
      selectSection,
    }
  },
}
</script>

<style>
/* ── Health Vue app — adapts to Joomla theme automatically ─────────── */

/* Two-column layout */
.ab-ha-layout {
  display: flex;
  align-items: flex-start;
}
.ab-ha-main {
  flex: 1;
  min-width: 0;
}
.ab-ha-sidebar {
  width: 175px;
  min-width: 155px;
  flex-shrink: 0;
  border-right: 1px solid var(--ab-border, #dee2e6);
  padding-right: 0;
  margin-right: 1.25rem;
  padding-top: 0;
}
.ab-ha-nav-link {
  padding: .45rem .75rem;
  font-size: .875rem;
  color: var(--ab-text, inherit);
  border-radius: 0;
  white-space: nowrap;
}
.ab-ha-nav-link:hover {
  background: var(--ab-bg-muted, #f8f9fa);
  color: var(--ab-text, inherit);
}
.ab-ha-nav-link.active {
  background: var(--ab-primary-soft, #cfe2ff);
  color: var(--ab-primary, #2a6496);
  font-weight: 600;
  border-left: 3px solid var(--ab-primary, #2a6496);
  padding-left: calc(.75rem - 3px);
}
[data-bs-theme=dark] .ab-ha-nav-link.active {
  background: rgba(42, 100, 150, .18);
}

/* Progress bar: fixed at top of viewport */
.ab-hc-progress-wrap {
  position: fixed;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: rgba(0,0,0,.1);
  z-index: 9999;
}
.ab-hc-progress-bar {
  height: 100%;
  background: #0d6efd;
  transition: width .035s linear;
}

/* Spin animation for Re-run icon */
@keyframes ab-hc-spin {
  to { transform: rotate(360deg); }
}
.ab-spin {
  display: inline-block;
  animation: ab-hc-spin .7s linear infinite;
}

/* Score circle SVG layout (v0.12.10 — wrap MUST be a sized positioning context) */
.ab-hc-score-wrap {
  position: relative;
  width: 96px;
  height: 96px;
  flex-shrink: 0;
  line-height: 1;
}
.ab-hc-score-svg {
  position: absolute;
  top: 0; left: 0;
  width: 96px;
  height: 96px;
}
.ab-hc-score-num {
  font-size: 1.75rem;
  font-weight: 700;
  line-height: 1;
  background: none !important;
  padding: 0 !important;
  border: 0 !important;
}
.ab-hc-score-track {
  stroke: var(--bs-border-color, #dee2e6);
}
[data-bs-theme=dark] .ab-hc-score-track {
  stroke: #495057;
}
.ab-hc-score-arc {
  animation: ab-hc-stroke-fill .9s ease-out forwards;
}
@keyframes ab-hc-stroke-fill {
  from { stroke-dashoffset: 276.46px; }
  to   { stroke-dashoffset: var(--ab-score-target, 0px); }
}
.ab-hc-score-num-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
}
/* Arc colour by class on the arc element itself */
.ab-hc-score--green  { stroke: #198754; }
.ab-hc-score--orange { stroke: #fd7e14; }
.ab-hc-score--red    { stroke: #dc3545; }
/* Number colour via parent num-overlay */
.ab-hc-score-num.ab-hc-score--green  { color: #198754; }
.ab-hc-score-num.ab-hc-score--orange { color: #7c3504; }
.ab-hc-score-num.ab-hc-score--red    { color: #dc3545; }
[data-bs-theme=dark] .ab-hc-score-num.ab-hc-score--green  { color: #75b798; }
[data-bs-theme=dark] .ab-hc-score-num.ab-hc-score--orange { color: #fd7e14; }
[data-bs-theme=dark] .ab-hc-score-num.ab-hc-score--red    { color: #ea868f; }
[data-bs-theme=dark] .ab-hc-score--green  { stroke: #75b798; }
[data-bs-theme=dark] .ab-hc-score--red    { stroke: #ea868f; }

/* Score help icon (Task #485) — small info-circle next to the label */
.ab-hc-score-help {
  display: inline-block;
  margin-left: .35rem;
  color: var(--ab-text-muted, #6c757d);
  cursor: help;
  font-size: .85rem;
  vertical-align: middle;
  opacity: .7;
}
.ab-hc-score-help:hover,
.ab-hc-score-help:focus {
  opacity: 1;
  outline: none;
}

/* Score circle entrance animation */
@keyframes ab-hc-score-enter {
  from { transform: scale(0.6); opacity: 0; }
  to   { transform: scale(1);   opacity: 1; }
}
.ab-hc-score--enter {
  animation: ab-hc-score-enter .6s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}
@media (prefers-reduced-motion: reduce) {
  .ab-hc-score--enter,
  .ab-hc-score-arc {
    animation: none;
    stroke-dashoffset: var(--ab-score-target, 0px);
  }
}

/* Contributing fields checklist — shown inside composite check rows */
.ab-hc-contrib-list {
  list-style: none;
  margin: .45rem 0 0;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: .25rem .75rem;
}
.ab-hc-contrib-item {
  display: flex;
  align-items: center;
  gap: .3rem;
  font-size: .8rem;
  white-space: nowrap;
}
.ab-hc-contrib-item span {
  font-size: .8rem;
  flex-shrink: 0;
}
.ab-hc-contrib--pass span { color: #198754; }
.ab-hc-contrib--fail span { color: #dc3545; }
[data-bs-theme=dark] .ab-hc-contrib--pass span { color: #75b798; }
[data-bs-theme=dark] .ab-hc-contrib--fail span { color: #ea868f; }
.ab-hc-contrib-link {
  color: inherit;
  text-decoration: none;
  border-bottom: 1px dotted currentColor;
  opacity: .85;
}
.ab-hc-contrib-link:hover {
  opacity: 1;
  text-decoration: none;
  border-bottom-style: solid;
}
</style>
