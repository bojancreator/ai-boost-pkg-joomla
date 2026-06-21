<template>
  <div class="ab-vue-dashboard">

    <div class="ab-topbar">
      <h2 class="ab-topbar__title">Dashboard</h2>
      <div class="ab-topbar__actions">
        <span class="ab-topbar__sub">
          <template v-if="data.lastSaved">Settings last saved {{ data.lastSaved }}</template>
          <template v-else>Not configured yet</template>
        </span>
      </div>
    </div>

    <div class="ab-page">

      <!-- CRITICAL notification (item 12a) — "no settings backup yet" stays
           always-visible above the collapsible notifications panel. -->
      <div v-if="showBackupReminder && backupSignalKind === 'never'"
           class="ab-alert ab-alert--warning"
           role="status"
           aria-live="polite">
        <AbIcon name="warn" class="ab-alert__icon" />
        <div style="flex:1">
          <div class="ab-alert__title">No settings backup yet.</div>
          <div class="ab-alert__body">
            Download one now so you can restore your configuration after a
            migration, a major update, or anything unexpected.
          </div>
        </div>
        <div class="ab-row">
          <button type="button" class="ab-btn ab-btn--primary ab-btn--sm" @click="scrollToBackup">Back up now →</button>
          <button type="button" class="ab-btn ab-btn--ghost ab-btn--sm" title="Hide for 7 days" @click="dismissBackupReminder">Dismiss</button>
        </div>
      </div>

      <!-- Settings status. First-run install (no settings row yet) funnels the
           admin to Autopilot instead of a warning or a backup alarm. -->
      <a v-if="!data.hasSettings"
         :href="firstRunSetupHref"
         class="ab-alert ab-alert--info ab-link-card"
         title="Open Quick Setup — guided setup">
        <AbIcon name="bolt" class="ab-alert__icon" />
        <div style="flex:1">
          <div class="ab-alert__title">New here? Set up AI Boost in 5 minutes</div>
          <div class="ab-alert__body">
            Quick Setup asks a few quick questions about your site, then
            configures Schema.org, sitemap, social tags, and AI-search
            signals for you.
          </div>
        </div>
        <span class="ab-link-card__cta">Set up now →</span>
      </a>

      <!-- Non-critical notifications (item 12a) — collapse to a single bar; the
           open/closed choice is remembered in localStorage. -->
      <div v-if="data.hasSettings && nonCriticalNotifCount > 0" class="ab-notif-panel">
        <button type="button" class="ab-notif-panel__bar"
                :aria-expanded="notifOpen ? 'true' : 'false'"
                @click="toggleNotif">
          <span class="ab-notif-panel__title">
            <AbIcon name="info" style="font-size:14px;margin-right:.2rem" />
            Notifications
            <span class="ab-badge ms-1">{{ nonCriticalNotifCount }}</span>
          </span>
          <span class="ab-notif-panel__right">
            <span v-if="!notifOpen" class="ab-hint me-2">Open to see notifications</span>
            <span class="ab-notif-panel__chev" :class="{ 'is-open': notifOpen }" aria-hidden="true">▾</span>
          </span>
        </button>
        <div v-show="notifOpen" class="ab-notif-panel__body">
          <!-- Settings active -->
          <div class="ab-alert ab-alert--success">
            <AbIcon name="check" class="ab-alert__icon" />
            <div style="flex:1">
              Settings active — all plugins reading from <code class="ab-mono">#__aiboost_settings</code>.
            </div>
            <span class="ab-hint" style="white-space:nowrap">
              <template v-if="data.lastSaved">Settings last saved: <strong>{{ data.lastSaved }}</strong></template>
              <template v-else>Never saved</template>
            </span>
          </div>

          <!-- Stale / changed-since backup nag (non-critical) -->
          <div v-if="showBackupReminder && backupSignalKind !== 'never'"
               class="ab-alert ab-alert--warning"
               role="status" aria-live="polite">
            <AbIcon name="warn" class="ab-alert__icon" />
            <div style="flex:1">
              <template v-if="backupSignalKind === 'changes'">
                <strong>You've changed {{ changesSinceBackup }} settings since your last backup.</strong>
                Download a fresh backup so you don't lose recent configuration changes if something goes wrong.
              </template>
              <template v-else>
                <strong>Backup is {{ lastBackupAgeDays }} days old.</strong>
                Download a fresh settings backup so you don't lose recent changes if something goes wrong.
              </template>
            </div>
            <div class="ab-row">
              <button type="button" class="ab-btn ab-btn--primary ab-btn--sm" @click="scrollToBackup">Back up now →</button>
              <button type="button" class="ab-btn ab-btn--ghost ab-btn--sm" title="Hide for 7 days" @click="dismissBackupReminder">Dismiss</button>
            </div>
          </div>

          <!-- Multilingual detected (Pro discovery) -->
          <a v-if="data.multilingualLangCount >= 2"
             :href="multilingualBannerHref"
             :target="multilingualBannerTarget"
             :rel="multilingualBannerTarget === '_blank' ? 'noopener' : null"
             class="ab-alert ab-alert--info ab-link-card"
             title="Open Settings → Sitemap → hreflang">
            <AbIcon name="map" class="ab-alert__icon" />
            <div style="flex:1">
              <div class="ab-row" style="gap:.5rem">
                <span class="ab-alert__title">Multilingual — detected</span>
                <span class="ab-badge ab-badge--success">{{ data.multilingualLangCount }} languages</span>
              </div>
              <div class="ab-alert__body">
                AI Boost can emit hreflang alternates and store per-language
                translations for every field. Click to configure.
              </div>
            </div>
            <span class="ab-link-card__cta">Configure →</span>
          </a>
        </div>
      </div>

      <!-- Module status grid -->
      <div>
        <div class="ab-eyebrow" style="margin-bottom:.6rem">Module status</div>
        <div class="ab-grid-cards">
          <div v-for="(plugin, element) in plugins" :key="element" class="ab-modcard">

            <div class="ab-row" style="justify-content:space-between;align-items:flex-start;gap:.5rem">
              <div class="ab-modcard__title">{{ plugin.label }}</div>
              <span v-if="!isProEdition && plugin.tier === 'pro'" class="ab-tag ab-tag--pro" title="This feature is Pro">Pro</span>
              <span v-else-if="!isProEdition && plugin.tier === 'mixed'" class="ab-tag ab-tag--free" title="Free baseline + Pro advanced options">Free / Pro</span>
            </div>

            <span v-if="!plugin.found"       class="ab-badge" style="align-self:flex-start">Not installed</span>
            <span v-else-if="plugin.enabled" class="ab-badge ab-badge--success" style="align-self:flex-start">Enabled</span>
            <span v-else                     class="ab-badge ab-badge--danger" style="align-self:flex-start">Disabled</span>

            <p v-if="plugin.desc">{{ plugin.desc }}</p>

            <!-- Toggle buttons -->
            <div v-if="plugin.found && plugin.extension_id" class="ab-toggle-actions">
              <button v-if="!plugin.enabled"
                      type="button"
                      class="ab-btn ab-btn--success ab-btn--sm"
                      :disabled="plugin.busy"
                      @click="doToggle(element, 1)">
                {{ plugin.busy ? 'Enabling…' : 'Enable' }}
              </button>
              <template v-else>
                <button type="button"
                        :class="['ab-btn ab-btn--sm', plugin.confirming ? 'ab-btn--danger' : 'ab-btn--ghost']"
                        :disabled="plugin.busy"
                        @click="startDisable(element)">
                  {{ plugin.busy ? 'Disabling…' : plugin.confirming ? 'Confirm Disable' : 'Disable' }}
                </button>
                <a v-if="plugin.confirming"
                   href="#"
                   class="ab-hint"
                   @click.prevent="cancelDisable(element)">Cancel</a>
              </template>
            </div>

            <!-- Flash message -->
            <div v-if="plugin.flash"
                 class="ab-modcard__flash"
                 :class="plugin.flashOk ? 'ab-text-success' : 'ab-text-danger'">
              {{ plugin.flash }}
            </div>

            <!-- Configure link — deep-links to the plugin's settings tab + field -->
            <a :href="configureUrl(plugin.meta)" class="ab-configure-link">Configure →</a>

          </div>
        </div>
      </div>

      <!-- Quick actions -->
      <div class="ab-section">
        <div class="ab-section__head">Quick actions</div>
        <div class="ab-section__body ab-row" style="flex-wrap:wrap">
          <a :href="data.urls.settings" class="ab-btn ab-btn--primary ab-btn--sm">Open settings</a>
          <a :href="data.urls.redirects" class="ab-btn ab-btn--ghost ab-btn--sm">
            Redirect manager
            <span v-if="data.redirectCount > 0" class="ab-badge ms-1">{{ data.redirectCount }}</span>
          </a>
          <a :href="data.urls.pluginManager" class="ab-btn ab-btn--ghost ab-btn--sm">Manage plugins</a>
        </div>
      </div>

      <!-- Plugin conflicts card — visible when critical conflicts detected -->
      <div v-if="conflictCritical > 0" class="ab-section ab-section--danger">
        <div class="ab-section__head" style="justify-content:space-between">
          <span>
            Plugin conflicts detected
            <span class="ab-badge ab-badge--danger ms-2">{{ conflictCritical }} critical</span>
            <span v-if="conflictWarnings > 0" class="ab-badge ab-badge--warning ms-1">
              {{ conflictWarnings }} warning{{ conflictWarnings > 1 ? 's' : '' }}
            </span>
          </span>
          <a :href="data.urls.health" class="ab-btn ab-btn--danger ab-btn--sm">View all</a>
        </div>
        <div class="ab-section__body" style="padding:0 1rem">
          <!-- Show top 3 unresolved critical conflicts only -->
          <div v-for="c in topCriticalConflicts" :key="c.id" class="ab-healthrow">
            <AbIcon name="err" class="ab-healthrow__icon" style="color:var(--ab-danger)" />
            <div class="ab-healthrow__body">
              <div class="ab-healthrow__title">{{ c.label }}</div>
              <p>{{ c.message }}</p>
            </div>
            <div class="ab-healthrow__act">
              <a :href="(c.fix_actions && c.fix_actions[0]) ? c.fix_actions[0].url : (c.fix_url || data.urls.health)"
                 class="ab-btn ab-btn--subtle ab-btn--sm">Fix →</a>
            </div>
          </div>
          <!-- "...and N more" row when >3 critical conflicts -->
          <div v-if="conflictCritical > 3" class="ab-healthrow">
            <span class="ab-hint">
              + {{ conflictCritical - 3 }} more critical issue{{ conflictCritical - 3 > 1 ? 's' : '' }} —
              <a :href="data.urls.health">view all in Health report</a>
            </span>
          </div>
        </div>
      </div>

      <!-- Warnings-only card (no criticals) -->
      <div v-else-if="conflictWarnings > 0" class="ab-section ab-section--warning">
        <div class="ab-section__body ab-row" style="justify-content:space-between">
          <span class="ab-hint">
            <strong>{{ conflictWarnings }} compatibility warning{{ conflictWarnings > 1 ? 's' : '' }}</strong> detected.
          </span>
          <a :href="data.urls.health" class="ab-btn ab-btn--ghost ab-btn--sm">Review</a>
        </div>
      </div>

      <!-- No conflicts — compact status bar -->
      <div v-else-if="Array.isArray(data.conflicts)" class="ab-section">
        <div class="ab-section__body ab-row">
          <AbIcon name="ok" style="font-size:16px;color:var(--ab-success)" />
          <span class="ab-hint">
            No plugin conflicts detected.
            <a :href="data.urls.health" class="ms-1">View full Health report</a> for more details.
          </span>
        </div>
      </div>

      <!-- 404 monitoring: has errors -->
      <div v-if="data.top404 && data.top404.length" class="ab-section">
        <div class="ab-section__head" style="justify-content:space-between">
          <span>
            Top 404 errors
            <span class="ab-badge ab-badge--danger ms-2">{{ data.total404 }} URLs</span>
          </span>
          <a :href="data.urls.redirects + '&tab=404'" class="ab-btn ab-btn--ghost ab-btn--sm">View all &amp; manage redirects</a>
        </div>
        <table class="ab-table">
          <thead>
            <tr>
              <th>404 URL</th>
              <th style="width:70px">Hits</th>
              <th style="width:130px">Last seen</th>
              <th style="width:110px"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in data.top404" :key="row.id">
              <td><span class="ab-mono">{{ row.request_url }}</span></td>
              <td>
                <span :class="['ab-badge', Number(row.hits) >= 10 ? 'ab-badge--danger' : 'ab-badge--warning']">
                  {{ row.hits }}
                </span>
              </td>
              <td class="ab-muted">{{ (row.last_seen || '').substring(0, 10) }}</td>
              <td>
                <a :href="data.urls.redirects + '&from_url=' + encodeURIComponent(row.request_url)"
                   class="ab-btn ab-btn--subtle ab-btn--sm">+ Redirect</a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 404 monitoring: no errors yet -->
      <div v-else-if="data.total404 === 0" class="ab-section">
        <div class="ab-section__head">404 monitoring</div>
        <div class="ab-section__body ab-hint">
          No 404 errors logged yet. Enable <strong>Log 404 Errors</strong> in
          Settings &rarr; Sitemap &rarr; 404 Monitoring to start tracking broken URLs.
        </div>
      </div>

      <!-- Settings Backup (Task #490/#497). -->
      <div class="ab-section">
        <div class="ab-section__head">Settings backup</div>
        <div class="ab-section__body">
          <p class="ab-hint" style="margin:0 0 .9rem">
            Download a single JSON file with every option, redirect, and translation.
            <strong>Before any major change</strong> — a migration, a big update, or
            moving to another site — take a fresh backup. Uninstalling AI Boost keeps
            your data; see the full details on the
            <a :href="data.urls.import">Import / Export</a> page.
          </p>
          <div class="ab-row" style="flex-wrap:wrap">
            <button id="ab-backup-button"
                    type="button"
                    class="ab-btn ab-btn--primary ab-btn--sm"
                    data-ab-field="last_backup_at"
                    :disabled="backupBusy"
                    @click="backupNow">
              {{ backupBusy ? 'Preparing backup…' : 'Backup settings now (.json)' }}
            </button>
            <a :href="data.urls.import" class="ab-btn ab-btn--ghost ab-btn--sm">Open Import / Export →</a>
          </div>
          <p class="ab-modcard__flash" style="margin:.6rem 0 0"
             :class="[
               backupFlash
                 ? (backupFlashOk ? 'ab-muted' : 'ab-text-danger')
                 : (backupStaleness === 'never'
                     ? 'ab-text-danger'
                     : (backupStaleness === 'stale' ? 'ab-text-warning' : 'ab-muted'))
             ]"
             aria-live="polite">
            <template v-if="backupFlash">{{ backupFlash }}</template>
            <template v-else-if="lastBackupAt">
              Last backup downloaded: <strong>{{ lastBackupAtLabel }}</strong>
              <template v-if="changesSinceBackup > 0">
                — <strong>{{ changesSinceBackup }} setting{{ changesSinceBackup === 1 ? '' : 's' }} changed since</strong>.
              </template>
              <template v-if="backupStaleness === 'stale'">
                That's {{ lastBackupAgeDays }} days ago. Consider taking a fresh backup.
              </template>
            </template>
            <template v-else>
              <strong>No backup downloaded from this browser yet.</strong>
              Take one before any major change.
            </template>
          </p>
        </div>
      </div>

      <!-- Footer -->
      <p class="ab-hint">
        &copy; 2025 <a href="https://aiboostnow.com" target="_blank" rel="noopener">AI Boost</a>
        (aiboostnow.com)&nbsp;&middot;&nbsp;
        <a href="https://aiboostnow.com/docs" target="_blank" rel="noopener">Documentation</a>&nbsp;&middot;&nbsp;
        <a href="https://aiboostnow.com/pricing" target="_blank" rel="noopener">Upgrade license</a>
      </p>

    </div>
  </div>
</template>

<script>
import { reactive, computed, ref } from 'vue'
import { isProInstalled } from './api'

const CONFIRM_TIMEOUT = 3000

const PLUGIN_META = {
  aiboost_schema:    {
    color: '#8b5cf6',
    tab:   'schema',
    icon:  '<svg width="28" height="28" viewBox="0 0 16 16" fill="currentColor"><path d="M2.114 8.063V7.9c1.005-.102 1.497-.615 1.497-1.6V4.503c0-1.094.39-1.538 1.354-1.538h.273V2h-.376C3.25 2 2.49 2.759 2.49 4.352v1.524c0 1.094-.376 1.456-1.49 1.456v1.299c1.114 0 1.49.362 1.49 1.456v1.524c0 1.593.759 2.352 2.372 2.352h.376v-.964h-.273c-.964 0-1.354-.444-1.354-1.538V9.663c0-.984-.492-1.497-1.497-1.6zM13.886 7.9v.163c-1.005.103-1.497.616-1.497 1.6v1.798c0 1.094-.39 1.538-1.354 1.538h-.273v.964h.376c1.613 0 2.372-.759 2.372-2.352v-1.524c0-1.094.376-1.456 1.49-1.456V7.332c-1.114 0-1.49-.362-1.49-1.456V4.352C13.51 2.759 12.75 2 11.138 2h-.376v.964h.273c.964 0 1.354.444 1.354 1.538V6.3c0 .984.492 1.497 1.497 1.6z"/></svg>',
  },
  aiboost_sitemap:   {
    color: '#14b8a6',
    tab:   'sitemap',
    icon:  '<svg width="28" height="28" viewBox="0 0 16 16" fill="currentColor"><path d="M2 2.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5V3a.5.5 0 0 0-.5-.5H2zm0 4a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5H2zm0 4a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5H2zm3-8a.5.5 0 0 0 0 1h7a.5.5 0 0 0 0-1H5zm0 4a.5.5 0 0 0 0 1h7a.5.5 0 0 0 0-1H5zm0 4a.5.5 0 0 0 0 1h7a.5.5 0 0 0 0-1H5z"/></svg>',
  },
  aiboost_social:    {
    color: '#ec4899',
    tab:   'social',
    icon:  '<svg width="28" height="28" viewBox="0 0 16 16" fill="currentColor"><path d="M11 2.5a2.5 2.5 0 1 1 .603 1.628l-6.718 3.12a2.499 2.499 0 0 1 0 1.504l6.718 3.12a2.5 2.5 0 1 1-.488.876l-6.718-3.12a2.5 2.5 0 1 1 0-3.256l6.718-3.12A2.5 2.5 0 0 1 11 2.5z"/></svg>',
  },
  aiboost_analytics: {
    color: '#f97316',
    tab:   'analytics',
    icon:  '<svg width="28" height="28" viewBox="0 0 16 16" fill="currentColor"><path d="M1 11a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1v-3zm5-4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7zm5-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1V2z"/></svg>',
  },
  aiboost_aeo:       {
    color: '#06b6d4',
    tab:   'aeo',
    icon:  '<svg width="28" height="28" viewBox="0 0 16 16" fill="currentColor"><path d="M3.05 3.05a7 7 0 0 0 0 9.9.5.5 0 0 1-.707.707 8 8 0 0 1 0-11.314.5.5 0 0 1 .707.707zm2.122 2.122a4 4 0 0 0 0 5.656.5.5 0 1 1-.707.707 5 5 0 0 1 0-7.07.5.5 0 0 1 .707.707zm5.656-.707a.5.5 0 0 1 .707 0 5 5 0 0 1 0 7.07.5.5 0 1 1-.707-.707 4 4 0 0 0 0-5.656.5.5 0 0 1 0-.707zm2.122-2.122a.5.5 0 0 1 .707 0 8 8 0 0 1 0 11.314.5.5 0 1 1-.707-.707 7 7 0 0 0 0-9.9.5.5 0 0 1 0-.707zM10 8a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/></svg>',
  },
  aiboost_core:      {
    color: '#14b8a6',
    tab:   'general',
    field: 'conflict_mode',
    icon:  '<svg width="28" height="28" viewBox="0 0 16 16" fill="currentColor"><path d="M11.251.068a.5.5 0 0 1 .227.58L9.677 6.5H13a.5.5 0 0 1 .364.843l-8 8.5a.5.5 0 0 1-.842-.49L6.323 9.5H3a.5.5 0 0 1-.364-.843l8-8.5a.5.5 0 0 1 .615-.09z"/></svg>',
  },
  aiboost_code:      {
    color: '#f59e0b',
    tab:   'code',
    icon:  '<svg width="28" height="28" viewBox="0 0 16 16" fill="currentColor"><path d="M0 3a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3zm9.5 5.5h-3a.5.5 0 0 0 0 1h3a.5.5 0 0 0 0-1zm-6.354-.354a.5.5 0 1 0 .708.708L4.793 6.5 3.146 8.146a.5.5 0 1 0 .708.708l2-2a.5.5 0 0 0 0-.708l-2-2a.5.5 0 1 0-.708.708L4.793 6.5 3.146 8.146z"/></svg>',
  },
}

const DEFAULT_META = { color: '#6c757d', icon: '' }

// Tier badge per module: 'pro' (whole feature is Pro), 'mixed' (free core + Pro
// extras), 'free'. Custom Code is fully Pro; the rest have a free baseline + Pro
// advanced options.
const PLUGIN_TIER = {
  aiboost_code:      'pro',
  aiboost_schema:    'mixed',
  aiboost_sitemap:   'mixed',
  aiboost_social:    'mixed',
  aiboost_analytics: 'mixed',
  aiboost_aeo:       'mixed',
}

export default {
  name: 'DashboardApp',

  setup() {
    const raw = window.aiBoostDashboard || {}

    // On the Pro edition every module is fully unlocked, so the PRO / FREE-PRO
    // tier badges are noise — hide them. They only inform Free users which
    // modules have Pro extras. (Reads window.aiBoostBootstrap.isProInstall.)
    const isProEdition = isProInstalled()

    const data = reactive({
      hasSettings:          raw.hasSettings          ?? false,
      lastSaved:            raw.lastSaved            ?? null,
      changeCounter:        Number(raw.changeCounter ?? 0),
      top404:               raw.top404               ?? [],
      total404:             raw.total404             ?? 0,
      redirectCount:        raw.redirectCount        ?? 0,
      conflicts:            raw.conflicts            ?? [],
      tokenName:            raw.tokenName            ?? '',
      multilingualActive:    raw.multilingualActive    ?? false,
      multilingualLangCount: raw.multilingualLangCount ?? 0,
      multilingualCount:     raw.multilingualCount     ?? 0,
      urls: {
        appBase:       raw.urls?.appBase       ?? '',
        settings:      raw.urls?.settings      ?? '#/settings',
        health:        raw.urls?.health        ?? '#/health',
        redirects:     raw.urls?.redirects     ?? '#/redirects',
        import:        raw.urls?.import        ?? '#/import',
        pluginManager: raw.urls?.pluginManager ?? '#',
      },
    })

    const plugins = reactive({})
    for (const [element, info] of Object.entries(raw.plugins || {})) {
      // aiboost_core is the mandatory engine plugin — it is never disabled and
      // has no settings tab of its own, so it is not shown as a Module Status
      // card (its old "Configure → General" target moved into Technical SEO).
      if (element === 'aiboost_core') continue
      plugins[element] = {
        label:        info.label        ?? element,
        desc:         info.desc         ?? '',
        enabled:      info.enabled      ?? false,
        found:        info.found        ?? false,
        extension_id: info.extension_id ?? null,
        meta:         PLUGIN_META[element] || DEFAULT_META,
        tier:         PLUGIN_TIER[element] || 'free',
        busy:         false,
        confirming:   false,
        flash:        '',
        flashOk:      true,
        _timer:       null,
      }
    }

    // ── Conflict stats ───────────────────────────────────────────────────────
    const conflictCritical = computed(() =>
      data.conflicts.filter(c => c.status === 'critical' && !c.dismissed).length)
    const conflictWarnings = computed(() =>
      data.conflicts.filter(c => c.status === 'warning' && !c.dismissed).length)
    const conflictTotal = computed(() => conflictCritical.value + conflictWarnings.value)

    /** Top 3 unresolved critical conflicts — shown inline in dashboard card */
    const topCriticalConflicts = computed(() =>
      data.conflicts.filter(c => c.status === 'critical' && !c.dismissed).slice(0, 3)
    )

    async function doToggle(element, state) {
      const p = plugins[element]
      if (!p || p.busy) return

      if (p._timer) { clearTimeout(p._timer); p._timer = null }
      p.busy       = true
      p.confirming = false
      p.flash      = ''

      const body = new URLSearchParams()
      body.append('extension_id', String(p.extension_id))
      body.append('state',        String(state))
      body.append(data.tokenName, '1')

      try {
        const resp = await fetch(
          'index.php?option=com_aiboost&task=dashboard.togglePlugin&format=json',
          {
            method: 'POST',
            headers: {
              'Content-Type':     'application/x-www-form-urlencoded',
              'X-Requested-With': 'XMLHttpRequest',
            },
            body: body.toString(),
          }
        )
        const json = await resp.json()
        if (json.success) {
          p.enabled = json.newState === 1
          p.flash   = p.enabled ? 'Enabled ✓' : 'Disabled'
          p.flashOk = true
        } else {
          p.flash   = json.message || 'Error'
          p.flashOk = false
        }
      } catch {
        p.flash   = 'Network error'
        p.flashOk = false
      } finally {
        p.busy = false
        setTimeout(() => { p.flash = '' }, 3000)
      }
    }

    function startDisable(element) {
      const p = plugins[element]
      if (!p || p.busy) return

      if (!p.confirming) {
        p.confirming = true
        p._timer = setTimeout(() => {
          p.confirming = false
          p._timer     = null
        }, CONFIRM_TIMEOUT)
      } else {
        if (p._timer) { clearTimeout(p._timer); p._timer = null }
        doToggle(element, 0)
      }
    }

    function cancelDisable(element) {
      const p = plugins[element]
      if (!p) return
      if (p._timer) { clearTimeout(p._timer); p._timer = null }
      p.confirming = false
    }

    /**
     * Build the Configure deep-link for a plugin card.
     * Uses the SPA shell (view=app) with a hash route so navigation stays inside
     * the SPA — no PHP nav bar. tab and field become hash-fragment query params.
     */
    function configureUrl(meta) {
      const appBase = data.urls.appBase || data.urls.settings.split('#')[0]
      if (!meta || !meta.tab) return appBase + '#/settings'
      let hash = '/settings?tab=' + encodeURIComponent(meta.tab)
      if (meta.field) {
        hash += '&field=' + encodeURIComponent(meta.field)
      }
      return appBase + '#' + hash
    }

    // ── Danger Zone: one-click settings backup (Task #490) ──────────────
    // Streams the same JSON the Import/Export screen would download, without
    // a page navigation. Timestamp is kept in localStorage so the admin can
    // see at a glance whether they remembered to back up recently.
    const BACKUP_LS_KEY        = 'aiboost.dashboard.lastBackupAt'
    const BACKUP_DISMISS_LS_KEY = 'aiboost.dashboard.backupReminderDismissedAt'
    // Task #497 — Snapshot of the server-side change counter at the moment
    // of the last backup, so we can compute "settings changed since last
    // backup" without round-tripping to the server.
    const BACKUP_COUNTER_LS_KEY = 'aiboost.dashboard.lastBackupChangeCounter'
    // Tune-in-one-place: how many days before the "Last backup" line turns
    // amber and the top-of-dashboard reminder banner appears.
    const BACKUP_STALE_DAYS    = 30
    // Task #497 — Once this many settings have changed since the last backup
    // the change-based signal "wins" and the banner switches its wording
    // from "Backup is N days old" to "You've changed X settings since…".
    const BACKUP_CHANGE_THRESHOLD = 5
    const backupBusy    = ref(false)
    const backupFlash   = ref('')
    const backupFlashOk = ref(true)
    const lastBackupAt  = ref(
      (() => {
        try { return window.localStorage.getItem(BACKUP_LS_KEY) || '' }
        catch { return '' }
      })()
    )
    const backupReminderDismissedAt = ref(
      (() => {
        try { return window.localStorage.getItem(BACKUP_DISMISS_LS_KEY) || '' }
        catch { return '' }
      })()
    )
    // Task #497 — Counter value captured at the most recent backup. If the
    // admin has a backup timestamp but no snapshot (i.e. they backed up on
    // an older plugin version), initialise the snapshot to the current
    // server counter so we don't accuse them of unsaved changes that
    // pre-date the feature.
    const lastBackupChangeCounter = ref(
      (() => {
        try {
          const raw = window.localStorage.getItem(BACKUP_COUNTER_LS_KEY)
          if (raw === null || raw === '') {
            // Backfill on first paint so the banner stays calm for users
            // who backed up before Task #497 landed.
            try {
              const seed = String(data.changeCounter || 0)
              window.localStorage.setItem(BACKUP_COUNTER_LS_KEY, seed)
              return seed
            } catch { return '0' }
          }
          return raw
        } catch { return '0' }
      })()
    )
    const lastBackupAtLabel = computed(() => {
      if (!lastBackupAt.value) return ''
      const d = new Date(lastBackupAt.value)
      if (Number.isNaN(d.getTime())) return lastBackupAt.value
      try { return d.toLocaleString() } catch { return d.toISOString() }
    })
    // 'never' = no backup has ever been taken from this browser
    // 'stale' = last backup older than BACKUP_STALE_DAYS
    // 'fresh' = recent enough, no reminder needed
    const backupStaleness = computed(() => {
      if (!lastBackupAt.value) return 'never'
      const d = new Date(lastBackupAt.value)
      if (Number.isNaN(d.getTime())) return 'never'
      const ageDays = (Date.now() - d.getTime()) / 86400000
      return ageDays >= BACKUP_STALE_DAYS ? 'stale' : 'fresh'
    })
    const lastBackupAgeDays = computed(() => {
      if (!lastBackupAt.value) return 0
      const d = new Date(lastBackupAt.value)
      if (Number.isNaN(d.getTime())) return 0
      return Math.floor((Date.now() - d.getTime()) / 86400000)
    })
    // Task #497 — how many settings have changed since the last backup.
    // Server-side counter is monotonic, so (current − snapshot) is the
    // exact number of changed fields the admin would lose if they had to
    // restore from the last backup right now.
    const changesSinceBackup = computed(() => {
      const cur = Number(data.changeCounter || 0)
      const snap = Number(lastBackupChangeCounter.value || 0)
      const delta = cur - snap
      return delta > 0 ? delta : 0
    })
    // The change-based signal is "stronger than the time signal" once the
    // admin has crossed the change threshold. We only override the
    // time-based wording when a backup actually exists — for the "never
    // backed up" case the existing red banner is already the strongest.
    const backupSignalKind = computed(() => {
      if (backupStaleness.value === 'never') return 'never'
      if (changesSinceBackup.value >= BACKUP_CHANGE_THRESHOLD) return 'changes'
      if (backupStaleness.value === 'stale') return 'stale'
      return 'fresh'
    })
    // Dismissal is honoured for 7 days, then the banner re-appears so a
    // long-quiet site eventually nags again.
    const backupReminderDismissed = computed(() => {
      if (!backupReminderDismissedAt.value) return false
      const d = new Date(backupReminderDismissedAt.value)
      if (Number.isNaN(d.getTime())) return false
      const ageDays = (Date.now() - d.getTime()) / 86400000
      return ageDays < 7
    })
    // Backup nags only make sense once real settings exist. On a first-run
    // install (no settings row yet) there is nothing to back up, so the
    // first-run setup banner owns the top of the dashboard instead.
    const showBackupReminder = computed(() =>
      data.hasSettings && backupSignalKind.value !== 'fresh' && !backupReminderDismissed.value
    )
    function dismissBackupReminder() {
      const nowIso = new Date().toISOString()
      try { window.localStorage.setItem(BACKUP_DISMISS_LS_KEY, nowIso) } catch { /* ignore */ }
      backupReminderDismissedAt.value = nowIso
    }
    function scrollToBackup() {
      const btn = document.getElementById('ab-backup-button')
      if (!btn) return
      const card = btn.closest('.ab-card')
      ;(card || btn).scrollIntoView({ behavior: 'smooth', block: 'start' })
    }

    async function backupNow() {
      if (backupBusy.value) return
      backupBusy.value    = true
      backupFlash.value   = ''
      backupFlashOk.value = true
      try {
        // CSRF token on the export URL so the server records the backup
        // timestamp (the last_backup_at write is token-gated server-side).
        const tn = (window.aiBoostBootstrap && window.aiBoostBootstrap.tokenName) || window.aiBoostToken || ''
        const resp = await fetch(
          'index.php?option=com_aiboost&task=settings.export' + (tn ? '&' + tn + '=1' : ''),
          { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        )
        if (!resp.ok) {
          throw new Error('HTTP ' + resp.status)
        }
        const blob = await resp.blob()
        // Prefer the server-supplied filename from Content-Disposition; fall back
        // to the same pattern the PHP controller uses.
        let filename = ''
        const cd = resp.headers.get('Content-Disposition') || ''
        const m  = cd.match(/filename="?([^"]+)"?/i)
        if (m) filename = m[1]
        if (!filename) {
          const today = new Date().toISOString().slice(0, 10)
          filename = 'aiboost-settings-export-' + today + '.json'
        }
        const url = URL.createObjectURL(blob)
        const a   = document.createElement('a')
        a.href     = url
        a.download = filename
        document.body.appendChild(a)
        a.click()
        document.body.removeChild(a)
        setTimeout(() => URL.revokeObjectURL(url), 1000)

        const nowIso = new Date().toISOString()
        try { window.localStorage.setItem(BACKUP_LS_KEY, nowIso) } catch { /* ignore */ }
        lastBackupAt.value  = nowIso
        // Task #497 — Snapshot the current change counter so subsequent
        // saves are measured as "changes since last backup".
        const snap = String(data.changeCounter || 0)
        try { window.localStorage.setItem(BACKUP_COUNTER_LS_KEY, snap) } catch { /* ignore */ }
        lastBackupChangeCounter.value = snap
        backupFlash.value   = 'Backup downloaded ✓'
        backupFlashOk.value = true
      } catch (e) {
        backupFlash.value   = 'Backup failed — ' + (e && e.message ? e.message : 'network error')
        backupFlashOk.value = false
      } finally {
        backupBusy.value = false
        setTimeout(() => { backupFlash.value = '' }, 4000)
      }
    }

    const multilingualBannerHref = computed(() => {
      const appBase = (data.urls && data.urls.appBase)
        ? data.urls.appBase
        : 'index.php?option=com_aiboost&view=app'
      return appBase + '#/settings?tab=sitemap&field=enable_hreflang'
    })
    const multilingualBannerTarget = computed(() => '_self')

    // First-run setup banner — deep-link to the Autopilot page inside the
    // SPA shell, built the same way configureUrl() builds its hrefs.
    const firstRunSetupHref = computed(() => {
      const appBase = data.urls.appBase || data.urls.settings.split('#')[0]
      return appBase + '#/autopilot'
    })

    // ── Notifications panel (item 12a) ──────────────────────────────────────
    // Non-critical notifications (settings-active, multilingual, a stale-backup
    // nag) live in a collapsible panel that shrinks to a single bar; the user's
    // open/closed choice is remembered. The CRITICAL "no backup yet" notice is
    // rendered above the panel and stays always-visible.
    const NOTIF_LS_KEY = 'aiboost.dashboard.notifOpen'
    const notifOpen = ref((() => {
      try { return window.localStorage.getItem(NOTIF_LS_KEY) !== '0' } catch { return true }
    })())
    function toggleNotif() {
      notifOpen.value = !notifOpen.value
      try { window.localStorage.setItem(NOTIF_LS_KEY, notifOpen.value ? '1' : '0') } catch { /* ignore */ }
    }
    const nonCriticalNotifCount = computed(() => {
      let n = 0
      if (data.hasSettings) n++                                                  // "Settings active"
      if (showBackupReminder.value && backupSignalKind.value !== 'never') n++    // stale / changed-since backup nag
      if (data.multilingualLangCount >= 2) n++                                   // multilingual detected
      return n
    })

    return {
      data,
      plugins,
      notifOpen,
      toggleNotif,
      nonCriticalNotifCount,
      isProEdition,
      multilingualBannerHref,
      multilingualBannerTarget,
      firstRunSetupHref,
      conflictCritical,
      conflictWarnings,
      conflictTotal,
      topCriticalConflicts,
      doToggle,
      startDisable,
      cancelDisable,
      configureUrl,
      backupBusy,
      backupFlash,
      backupFlashOk,
      lastBackupAt,
      lastBackupAtLabel,
      backupStaleness,
      lastBackupAgeDays,
      changesSinceBackup,
      backupSignalKind,
      BACKUP_CHANGE_THRESHOLD,
      showBackupReminder,
      dismissBackupReminder,
      scrollToBackup,
      backupNow,
    }
  },
}
</script>

<style>
.ab-vue-dashboard code {
  color: var(--ab-text);
  background: var(--ab-bg-muted);
  padding: .1em .3em;
  border-radius: var(--ab-radius-sm);
}

/* Module-card toggle button area + flash + configure link */
.ab-toggle-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--ab-space-2);
}
.ab-modcard__flash { font-size: var(--ab-font-size-xs); }
.ab-configure-link {
  display: inline-flex;
  align-items: center;
  font-size: var(--ab-font-size-xs);
  font-weight: var(--ab-font-weight-medium);
  color: var(--ab-text-muted);
  text-decoration: none;
  margin-top: auto;
  padding-top: var(--ab-space-2);
  border-top: 1px solid var(--ab-border);
  width: 100%;
}
.ab-configure-link:hover { color: var(--ab-primary); text-decoration: none; }

/* Notifications panel (item 12a) — collapsible non-critical notifications. */
.ab-notif-panel {
  border: 1px solid var(--ab-border);
  border-radius: var(--ab-radius-md);
  overflow: hidden;
  background: var(--ab-bg-elev);
}
.ab-notif-panel__bar {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 14px;
  background: var(--ab-bg-elev-2);
  border: 0;
  cursor: pointer;
  font-family: var(--ab-font-mono);
  font-size: var(--ab-font-size-xs);
  font-weight: var(--ab-font-weight-semibold);
  letter-spacing: .06em;
  text-transform: uppercase;
  color: var(--ab-text-muted);
  text-align: left;
}
.ab-notif-panel__bar:hover { background: var(--ab-bg-muted); }
.ab-notif-panel__title { display: inline-flex; align-items: center; }
.ab-notif-panel__right { display: flex; align-items: center; }
.ab-notif-panel__chev { transition: transform .15s ease; display: inline-block; font-size: 1.1rem; line-height: 1; }
.ab-notif-panel__chev.is-open { transform: rotate(180deg); }
.ab-notif-panel__body {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px 14px;
}
.ab-notif-panel__body > * { margin-bottom: 0 !important; }
</style>
