<template>
  <div class="ab-vue-health">

    <PageHeader title="Health">
      <span v-if="actionMsg"
            :class="['ab-hint', actionMsgType === 'error' ? 'ab-text-danger'
                              : actionMsgType === 'success' ? 'ab-text-success' : '']">
        {{ actionMsg }}
      </span>
      <button type="button" class="ab-btn ab-btn--ghost ab-btn--sm" :disabled="rerunning" @click="rerun">
        {{ rerunning ? 'Running…' : 'Re-run checks' }}
      </button>
      <button type="button" class="ab-btn ab-btn--ghost ab-btn--sm" @click="copyReport">Copy report</button>
    </PageHeader>

    <!-- Progress bar — animates during Re-run -->
    <div v-if="rerunning" class="ab-hc-progress-wrap">
      <div class="ab-hc-progress-bar" :style="{ width: progress + '%' }"></div>
    </div>

    <div class="ab-page ab-ha-main">

        <!-- ════════════════════════════════════════════════
             HEALTH OVERVIEW
             ════════════════════════════════════════════════ -->
        <template v-if="currentSection === 'health'">

          <!-- Score header card -->
          <div class="ab-section">
            <div class="ab-section__body ab-row" style="gap:1.5rem;align-items:center;flex-wrap:wrap">

              <!-- Score circle with SVG stroke-fill animation. -->
              <div :class="['ab-hc-score-wrap', enterClass]"
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
                  <span class="ab-hc-score-label">{{ scoreLabel }}</span>
                </div>
              </div>

              <!-- Label + stat pills -->
              <div style="flex:1;min-width:220px">
                <h3 class="ab-h" style="margin-bottom:.35rem">
                  {{ scoreLabel }}
                  <AbIcon name="info" class="ab-hc-score-help" tabindex="0"
                          :title="scoreTooltip" :aria-label="scoreTooltip" />
                </h3>
                <p class="ab-hint" style="margin:0 0 .65rem">{{ scoreSummary }}</p>
                <div class="ab-stat-pills">
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

          <!-- Quick Actions card -->
          <div class="ab-section">
            <div class="ab-section__head">Quick actions</div>
            <div class="ab-section__body ab-row" style="flex-wrap:wrap">
              <!-- Internal SPA navigation (router-link) — keeps the sidebar/shell
                   and never drops to the legacy standalone PHP views. -->
              <router-link to="/settings" class="ab-btn ab-btn--ghost ab-btn--sm">Settings</router-link>
              <router-link to="/import" class="ab-btn ab-btn--ghost ab-btn--sm">Import</router-link>
              <router-link to="/redirects" class="ab-btn ab-btn--ghost ab-btn--sm">Redirects</router-link>
              <router-link to="/analyzers" class="ab-btn ab-btn--subtle ab-btn--sm">Analyzers</router-link>
              <router-link to="/urlchecker" class="ab-btn ab-btn--subtle ab-btn--sm">URL Checker</router-link>
              <button type="button" class="ab-btn ab-btn--ghost ab-btn--sm" @click="selectSection('jsonld')">JSON-LD Validator</button>
              <button type="button" class="ab-btn ab-btn--ghost ab-btn--sm" @click="selectSection('aivisibility')">AI Visibility</button>
              <button type="button" class="ab-btn ab-btn--ghost ab-btn--sm" @click="selectSection('errors')">Error Log</button>
            </div>
          </div>

          <!-- Category cards (health checks) -->
          <template v-for="cat in categoryOrder" :key="cat">
            <div v-if="catChecks(cat).length" class="ab-section">

              <div class="ab-section__head"
                   style="cursor:pointer;justify-content:flex-start;gap:.5rem" @click="toggleCat(cat)">
                <span>{{ cat }}</span>
                <span :class="['ab-badge', catBadgeClass(cat)]">{{ catBadgeLabel(cat) }}</span>
                <span class="ab-hc-chevron" style="margin-left:auto"
                      :style="{ transform: collapsed[cat] ? 'rotate(-90deg)' : '' }"
                      aria-hidden="true">▾</span>
              </div>

              <div class="ab-section__body" style="padding:0 1rem" v-show="!collapsed[cat]">
                <div v-for="check in catChecks(cat)" :key="check.id"
                     class="ab-healthrow"
                     :class="{ 'ab-healthrow--dismissed': check.dismissed }">

                  <AbIcon :name="rowIconName(check)" class="ab-healthrow__icon"
                          :style="{ color: rowIconColor(check) }" />

                  <div class="ab-healthrow__body">
                    <div class="ab-healthrow__title">
                      {{ check.label }}
                      <!-- Severity badge belongs only on a FAILING check; a passing
                           critical-severity check is "OK", so showing red CRITICAL
                           next to it (under an "All OK" category) is misleading. -->
                      <span v-if="!check.pass && !check.dismissed && check.status !== 'info'"
                            :class="['ab-badge',
                                     check.status === 'critical' ? 'ab-badge--danger' : 'ab-badge--warning']">
                        {{ check.status }}
                      </span>
                    </div>
                    <p>{{ check.message }}</p>
                    <!-- Contributing fields checklist (composite checks only) -->
                    <ul v-if="check.contributing_fields && check.contributing_fields.length"
                        class="ab-hc-contrib-list">
                      <li v-for="field in check.contributing_fields" :key="field.url"
                          class="ab-hc-contrib-item">
                        <AbIcon :name="field.pass ? 'ok' : 'warn'"
                                :style="{ color: field.pass ? 'var(--ab-success)' : 'var(--ab-warning)', fontSize: '.85rem' }" />
                        <a :href="field.url" class="ab-hc-contrib-link">{{ field.label }}</a>
                      </li>
                    </ul>
                  </div>

                  <div class="ab-healthrow__act">
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
          </template>

        </template>

        <!-- JSON-LD VALIDATOR (placeholder) -->
        <div v-else-if="currentSection === 'jsonld'" class="ab-section">
          <div class="ab-section__head">JSON-LD Validator</div>
          <div class="ab-section__body" style="text-align:center;padding:3rem 1.5rem">
            <h4 class="ab-h" style="margin-bottom:.5rem">JSON-LD Validator</h4>
            <p class="ab-hint" style="margin-bottom:1.25rem">Paste structured data JSON-LD markup and validate it against Schema.org specifications.<br>This feature is coming soon.</p>
            <a href="https://validator.schema.org" target="_blank" rel="noopener noreferrer"
               class="ab-btn ab-btn--subtle">Use Schema.org Validator (external)</a>
          </div>
        </div>

        <!-- AI VISIBILITY -->
        <div v-else-if="currentSection === 'aivisibility'" class="ab-stack">
          <div class="ab-section">
            <div class="ab-section__head">AI Visibility Score</div>
            <div class="ab-section__body">
              <div class="ab-row" style="gap:1.25rem;align-items:center;flex-wrap:wrap">
                <div class="ab-hc-score-wrap">
                  <svg class="ab-hc-score-svg" width="96" height="96" viewBox="0 0 96 96" aria-hidden="true">
                    <circle cx="48" cy="48" r="44" fill="none" stroke-width="5" class="ab-hc-score-track"/>
                    <circle cx="48" cy="48" r="44" fill="none" stroke-width="5"
                      stroke-dasharray="276.46"
                      :stroke-dashoffset="276.46"
                      stroke-linecap="round"
                      transform="rotate(-90 48 48)"
                      class="ab-hc-score-arc"
                      :class="aiScoreClass"
                      :style="{ '--ab-score-target': aiScoreOffset + 'px' }"/>
                  </svg>
                  <div class="ab-hc-score-num-overlay">
                    <span :class="['ab-hc-score-num', aiScoreClass]">{{ aiScore }}</span>
                  </div>
                </div>
                <div style="flex:1;min-width:220px">
                  <h3 class="ab-h" style="margin-bottom:.3rem">{{ aiScoreLabel }}</h3>
                  <p class="ab-hint" style="margin:0">Based on AI Visibility / GEO signals: Schema.org, llms.txt, IndexNow, robots.txt, author markup</p>
                </div>
              </div>

              <div v-if="aeoChecks.length === 0" class="ab-alert ab-alert--info" style="margin-top:1rem">
                <AbIcon name="info" class="ab-alert__icon" style="font-size:1.15rem" />
                <div>No AI Visibility checks found. Make sure the AI Visibility plugin is enabled and health checks have been run.</div>
              </div>
            </div>
          </div>

          <!-- AI Visibility checks -->
          <div v-if="aeoChecks.length" class="ab-section">
            <div class="ab-section__head">AI Visibility Checks ({{ aeoChecks.filter(c => c.pass || c.dismissed).length }}/{{ aeoChecks.length }} OK)</div>
            <div class="ab-section__body" style="padding:0 1rem">
              <div v-for="check in aeoChecks" :key="check.id"
                   class="ab-healthrow" :class="{ 'ab-healthrow--dismissed': check.dismissed }">
                <AbIcon :name="rowIconName(check)" class="ab-healthrow__icon" :style="{ color: rowIconColor(check) }" />
                <div class="ab-healthrow__body">
                  <div class="ab-healthrow__title">{{ check.label }}</div>
                  <p>{{ check.message }}</p>
                </div>
                <div class="ab-healthrow__act">
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

          <!-- Schema checks relevant to AI -->
          <div v-if="schemaChecks.length" class="ab-section">
            <div class="ab-section__head">Schema.org (AI signals)</div>
            <div class="ab-section__body" style="padding:0 1rem">
              <div v-for="check in schemaChecks" :key="check.id"
                   class="ab-healthrow" :class="{ 'ab-healthrow--dismissed': check.dismissed }">
                <AbIcon :name="rowIconName(check)" class="ab-healthrow__icon" :style="{ color: rowIconColor(check) }" />
                <div class="ab-healthrow__body">
                  <div class="ab-healthrow__title">{{ check.label }}</div>
                  <p>{{ check.message }}</p>
                </div>
                <div class="ab-healthrow__act">
                  <a v-if="!check.pass && !check.dismissed && check.fix_url"
                     :href="check.fix_url" class="ab-btn ab-btn--subtle ab-btn--sm">Fix it</a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <ErrorsPage v-else-if="currentSection === 'errors'" />

        <!-- Footer -->
        <p class="ab-hint" style="margin-top:1rem">
          &copy; 2025
          <a href="https://aiboostnow.com" target="_blank" rel="noopener">AI Boost</a>
          &nbsp;&middot;&nbsp;
          <a href="https://aiboostnow.com/docs" target="_blank" rel="noopener">Documentation</a>
        </p>

    </div><!-- end .ab-page -->
  </div>
</template>

<script>
import { reactive, computed, ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ErrorsPage from './ErrorsPage.vue'
import PageHeader from './components/PageHeader.vue'

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
  components: { ErrorsPage, PageHeader },

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
    // Severity-aware category badge. The colour and wording must track the WORST
    // failing check in the category, not just "something failed":
    //   • a failing critical → red "N critical"
    //   • only failing warnings → amber "N warnings"
    //   • nothing failing → green "All OK"
    // Otherwise a category of mild warnings screams red while its rows show amber.
    function catFailParts (cat) {
      const fails = catChecks(cat).filter(c => !c.pass && !c.dismissed && c.status !== 'info')
      const crit  = fails.filter(c => c.status === 'critical').length
      return { crit, warn: fails.length - crit, total: fails.length }
    }
    function catBadgeClass (cat) {
      const { crit, total } = catFailParts(cat)
      if (total === 0) return 'ab-badge--success'
      return crit > 0 ? 'ab-badge--danger' : 'ab-badge--warning'
    }
    function catBadgeLabel (cat) {
      const { crit, warn, total } = catFailParts(cat)
      if (total === 0) return 'All OK'
      const parts = []
      if (crit) parts.push(crit + ' critical')
      if (warn) parts.push(warn + ' warning' + (warn > 1 ? 's' : ''))
      return parts.join(', ')
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
    // Instrument SVG status icon (name + colour) for a health-check row.
    function rowIconName (check) {
      if (check.dismissed)                       return 'info'
      if (check.pass || check.status === 'info') return 'ok'
      if (check.status === 'critical')           return 'err'
      return 'warn'
    }
    function rowIconColor (check) {
      if (check.dismissed)                       return 'var(--ab-text-muted)'
      if (check.pass || check.status === 'info') return 'var(--ab-success)'
      if (check.status === 'critical')           return 'var(--ab-danger)'
      return 'var(--ab-warning)'
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
    //   2. any other target_tab           → Settings tab inside the SPA
    //      (view=app#/settings?tab=&field= — same hash contract the SPA uses;
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
        // Settings-tab target → stay in the SPA shell: tab + field live inside the
        // hash (same contract as DashboardApp.configureUrl()). The old form
        // (view=settings&tab=…) dropped out of the SPA into a sidebar-less page.
        return 'index.php?option=com_aiboost&view=app#/settings?tab=' + encodeURIComponent(tab) + fieldQs
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
      // push (not replace) so each sub-section is its own history entry — the
      // browser Back button then returns to the Health overview, not to whatever
      // page preceded Health (e.g. the Dashboard).
      if (next === 'errors') {
        router.push('/health/errors')
        return
      }
      router.push({ path: '/health', query: next === 'health' ? {} : { section: next } })
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
      catChecks, catFails, catBadgeClass, catBadgeLabel, toggleCat,
      rowClass, rowIcon, rowIconName, rowIconColor,
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
  border-right: 1px solid var(--ab-border);
  padding-right: 0;
  margin-right: 1.25rem;
  padding-top: 0;
}
.ab-ha-nav-link {
  padding: .45rem .75rem;
  font-size: .875rem;
  color: var(--ab-text);
  border-radius: 0;
  white-space: nowrap;
}
.ab-ha-nav-link:hover {
  background: var(--ab-surface-raised);
  color: var(--ab-text);
}
.ab-ha-nav-link.active {
  background: var(--ab-primary-soft, color-mix(in srgb, var(--ab-primary) 12%, var(--ab-surface)));
  color: var(--ab-primary);
  font-weight: 600;
  border-left: 3px solid var(--ab-primary);
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
  background: var(--ab-primary);
  transition: width .035s linear;
}

/* Health-row dismissed + category chevron */
.ab-healthrow--dismissed { opacity: .55; }
.ab-hc-chevron { display: inline-block; transition: transform .15s ease; font-size: 1rem; line-height: 1; color: var(--ab-text-muted); }

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
  width: 116px;
  height: 116px;
  flex-shrink: 0;
  line-height: 1;
}
.ab-hc-score-svg {
  position: absolute;
  top: 0; left: 0;
  width: 116px;
  height: 116px;
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
  stroke: var(--ab-bg-muted);
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
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
}
.ab-hc-score-label {
  font-family: var(--ab-font-mono);
  font-size: var(--ab-font-size-xs);
  text-transform: uppercase;
  letter-spacing: .03em;
  color: var(--ab-text-muted);
  margin-top: .15rem;
}
/* Arc + number colour by class — driven by restrained --ab-* tokens (theme-aware) */
.ab-hc-score--green  { stroke: var(--ab-success); }
.ab-hc-score--orange { stroke: var(--ab-warning); }
.ab-hc-score--red    { stroke: var(--ab-danger); }
.ab-hc-score-num.ab-hc-score--green  { color: var(--ab-success); }
.ab-hc-score-num.ab-hc-score--orange { color: var(--ab-warning); }
.ab-hc-score-num.ab-hc-score--red    { color: var(--ab-danger); }

/* Score help icon (Task #485) — small info-circle next to the label */
.ab-hc-score-help {
  display: inline-block;
  margin-left: .35rem;
  color: var(--ab-text-muted);
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
