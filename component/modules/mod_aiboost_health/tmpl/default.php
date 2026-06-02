<?php
/**
 * AI Boost Health Module — Default Template (panel-based redesign, v0.11.0)
 *
 * Layout (top → bottom, single 240–300 px wide widget column):
 *   1. Brand strip   — AI Boost wordmark + version + licence pill
 *   2. Score panel   — large circle + status + critical/warning counts
 *   3. Top issues    — up to 3 failing checks (only when any)
 *   4. Plugins panel — 2×3 clickable chips deep-linking to settings tab
 *   5. Upgrade CTA   — only when on the Free tier
 *   6. Actions panel — 3-column icon buttons (Settings, Health, Analyzers,
 *                      URL Checker, Redirects, Import)
 *
 * @package     mod_aiboost_health
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

// ── Score colour bucket + arc geometry ─────────────────────────────────────
$circ = 138.23; // 2 * pi * 22
if ($abScore >= 80) {
    $scoreClass = 'ab-mod-score--green';
    $statusText = Text::_('MOD_AIBOOST_HEALTH_STATUS_GOOD');
} elseif ($abScore >= 50) {
    $scoreClass = 'ab-mod-score--orange';
    $statusText = Text::_('MOD_AIBOOST_HEALTH_STATUS_ATTENTION');
} else {
    $scoreClass = 'ab-mod-score--red';
    $statusText = Text::_('MOD_AIBOOST_HEALTH_STATUS_CRITICAL');
}
$strokeOffset = round($circ * (1 - $abScore / 100), 2);

// ── Licence pill ───────────────────────────────────────────────────────────
$licenceLabel = $abIsPro
    ? Text::_('MOD_AIBOOST_HEALTH_TIER_PRO')
    : Text::_('MOD_AIBOOST_HEALTH_TIER_FREE');
$licenceClass = $abIsPro ? 'ab-mod-pill--pro' : 'ab-mod-pill--free';

// ── Plugin chip metadata: tab slug + short label ───────────────────────────
// Element slug → settings tab id (matches Vue SPA tab keys in App.vue)
$abPluginTabs = [
    'aiboost_schema'    => 'schema',
    'aiboost_sitemap'   => 'sitemap',
    'aiboost_social'    => 'social',
    'aiboost_analytics' => 'analytics',
    'aiboost_aeo'       => 'aeo',
    'aiboost_core'      => 'sitemap',
];
?>
<style>
/* ── Card shell ──────────────────────────────────────────────────────── */
.ab-mod-card {
    border-radius: 8px;
    overflow: hidden;
}
.ab-mod-card .card-body { padding: 0; }

/* ── 1. Brand strip ──────────────────────────────────────────────────── */
.ab-mod-brand {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    background: linear-gradient(135deg, rgba(13,110,253,.08), rgba(13,110,253,.02));
    border-bottom: 1px solid var(--bs-border-color, #dee2e6);
}
.ab-mod-brand-name {
    font-weight: 700;
    font-size: .9rem;
    letter-spacing: -.01em;
    color: var(--bs-body-color, #212529);
}
.ab-mod-brand-ver {
    font-size: .7rem;
    color: var(--bs-secondary-color, #6c757d);
    margin-right: auto;
}
/* v0.55.5 — dark-mode text fix (Task #478). The Bootstrap theme variables
   weren't resolving to a light foreground inside the gradient strip on the
   Joomla cpanel, so the wordmark + version both rendered as near-black on
   a near-black panel. Explicit light colours under [data-bs-theme=dark]. */
[data-bs-theme=dark] .ab-mod-brand-name { color: #f8f9fa; }
[data-bs-theme=dark] .ab-mod-brand-ver  { color: #adb5bd; }
/* v0.55.6 — Joomla cpanel module renders outside the admin <body> theme
   inheritance, so --bs-body-color resolves to the light-mode default
   even when [data-bs-theme=dark] is set on <html>. Force explicit light
   colours on every text element inside the widget. */
[data-bs-theme=dark] .ab-mod-score-label,
[data-bs-theme=dark] .ab-mod-issues li,
[data-bs-theme=dark] .ab-mod-signal,
[data-bs-theme=dark] .ab-mod-chip,
[data-bs-theme=dark] .ab-mod-action { color: #e9ecef; }
[data-bs-theme=dark] .ab-mod-section-title,
[data-bs-theme=dark] .ab-mod-signal-lbl { color: #adb5bd; }
.ab-mod-pill {
    font-size: .65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    padding: 2px 8px;
    border-radius: 10px;
    line-height: 1.4;
}
.ab-mod-pill--free { background: #6c757d; color: #fff; }
.ab-mod-pill--pro  {
    background: linear-gradient(135deg, #0d6efd, #6610f2);
    color: #fff;
}
[data-bs-theme=dark] .ab-mod-brand {
    background: linear-gradient(135deg, rgba(96,165,250,.12), rgba(96,165,250,.02));
    border-bottom-color: #495057;
}

/* ── 2. Score panel ──────────────────────────────────────────────────── */
.ab-mod-panel {
    padding: 12px 14px;
    border-bottom: 1px solid var(--bs-border-color, #dee2e6);
}
[data-bs-theme=dark] .ab-mod-panel { border-bottom-color: #495057; }
.ab-mod-panel:last-child { border-bottom: none; }

.ab-mod-score-row {
    display: flex;
    align-items: center;
    gap: 12px;
}
.ab-mod-score-wrap {
    position: relative;
    width: 64px;
    height: 64px;
    flex-shrink: 0;
}
.ab-mod-score-wrap svg { position: absolute; top: 0; left: 0; }
.ab-mod-score-num {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    font-weight: 800;
    font-size: 1.35rem;
    line-height: 1;
}
.ab-mod-score-arc {
    animation: ab-score-fill .9s ease-out forwards;
}
@keyframes ab-score-fill {
    from { stroke-dashoffset: <?php echo $circ; ?>; }
    to   { stroke-dashoffset: <?php echo $strokeOffset; ?>; }
}
.ab-mod-score-track { stroke: var(--bs-border-color, #dee2e6); }
[data-bs-theme=dark] .ab-mod-score-track { stroke: #495057; }

.ab-mod-score--green  .ab-mod-score-arc { stroke: #198754; }
.ab-mod-score--green  .ab-mod-score-num { color: #198754; }
.ab-mod-score--orange .ab-mod-score-arc { stroke: #fd7e14; }
.ab-mod-score--orange .ab-mod-score-num { color: #fd7e14; }
.ab-mod-score--red    .ab-mod-score-arc { stroke: #dc3545; }
.ab-mod-score--red    .ab-mod-score-num { color: #dc3545; }
[data-bs-theme=dark] .ab-mod-score--green  .ab-mod-score-num,
[data-bs-theme=dark] .ab-mod-score--green  .ab-mod-score-arc { stroke: #75b798; color: #75b798; }
[data-bs-theme=dark] .ab-mod-score--red    .ab-mod-score-num,
[data-bs-theme=dark] .ab-mod-score--red    .ab-mod-score-arc { stroke: #ea868f; color: #ea868f; }

.ab-mod-score-info { min-width: 0; flex: 1; }
.ab-mod-score-label {
    font-weight: 600;
    font-size: .85rem;
    color: var(--bs-body-color, #212529);
    line-height: 1.25;
}
.ab-mod-score-counts {
    display: flex;
    gap: 6px;
    margin-top: 4px;
    flex-wrap: wrap;
}
.ab-mod-count {
    font-size: .68rem;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 4px;
    line-height: 1.4;
}
.ab-mod-count--crit { background: rgba(220,53,69,.12); color: #b02a37; }
.ab-mod-count--warn { background: rgba(253,126,20,.14); color: #b45309; }
.ab-mod-count--ok   { background: rgba(25,135,84,.12);  color: #146c43; }
[data-bs-theme=dark] .ab-mod-count--crit { background: rgba(234,134,143,.18); color: #ea868f; }
[data-bs-theme=dark] .ab-mod-count--warn { background: rgba(253,126,20,.22);  color: #ffba66; }
[data-bs-theme=dark] .ab-mod-count--ok   { background: rgba(117,183,152,.18); color: #75b798; }

/* ── 2b. Signals row ─────────────────────────────────────────────── */
.ab-mod-signals {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 6px;
    padding: 10px 14px;
}
.ab-mod-signal {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 8px 4px;
    border-radius: 6px;
    text-decoration: none;
    border: 1px solid var(--bs-border-color, #dee2e6);
    background: var(--bs-tertiary-bg, #f8f9fa);
    color: var(--bs-body-color, #212529);
    transition: all .15s;
    min-width: 0;
}
.ab-mod-signal:hover {
    border-color: #0d6efd;
    color: var(--bs-body-color, #212529);
    text-decoration: none;
    transform: translateY(-1px);
}
.ab-mod-signal-val {
    font-size: 1.05rem;
    font-weight: 700;
    line-height: 1.1;
}
.ab-mod-signal-lbl {
    font-size: .62rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--bs-secondary-color, #6c757d);
    margin-top: 2px;
    text-align: center;
}
.ab-mod-signal--ok    .ab-mod-signal-val { color: #198754; }
.ab-mod-signal--alert .ab-mod-signal-val { color: #dc3545; }
.ab-mod-signal--accent {
    background: linear-gradient(135deg, rgba(13,110,253,.08), rgba(102,16,242,.05));
    border-color: rgba(13,110,253,.3);
}
.ab-mod-signal--accent .ab-mod-signal-val { color: #0d6efd; }
[data-bs-theme=dark] .ab-mod-signal {
    background: #2b3035;
    border-color: #495057;
    color: #dee2e6;
}
[data-bs-theme=dark] .ab-mod-signal:hover { border-color: #60a5fa; color: #dee2e6; }
[data-bs-theme=dark] .ab-mod-signal--ok    .ab-mod-signal-val { color: #75b798; }
[data-bs-theme=dark] .ab-mod-signal--alert .ab-mod-signal-val { color: #ea868f; }
[data-bs-theme=dark] .ab-mod-signal--accent .ab-mod-signal-val { color: #60a5fa; }

/* ── 3. Top issues list ──────────────────────────────────────────────── */
.ab-mod-issues {
    list-style: none;
    padding: 0;
    margin: 8px 0 0;
    font-size: .75rem;
}
.ab-mod-issues li {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 2px 0;
    color: var(--bs-body-color, #212529);
}
.ab-mod-issues .ab-dot {
    width: 6px; height: 6px; border-radius: 50%;
    flex-shrink: 0;
}

/* ── 4. Plugins chip grid ────────────────────────────────────────────── */
.ab-mod-section-title {
    font-size: .65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--bs-secondary-color, #6c757d);
    margin-bottom: 8px;
}
.ab-mod-plugin-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 5px;
}
.ab-mod-chip {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 8px;
    border-radius: 6px;
    font-size: .76rem;
    font-weight: 500;
    text-decoration: none;
    border: 1px solid var(--bs-border-color, #dee2e6);
    background: var(--bs-body-bg, #fff);
    color: var(--bs-body-color, #212529);
    transition: all .15s;
    min-width: 0;
}
.ab-mod-chip:hover {
    border-color: #0d6efd;
    background: rgba(13,110,253,.04);
    color: var(--bs-body-color, #212529);
    text-decoration: none;
    transform: translateY(-1px);
}
.ab-mod-chip-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.ab-mod-chip--on  .ab-mod-chip-dot { background: #198754; box-shadow: 0 0 0 2px rgba(25,135,84,.18); }
.ab-mod-chip--off .ab-mod-chip-dot { background: #adb5bd; }
.ab-mod-chip--off .ab-mod-chip-label { color: var(--bs-secondary-color, #6c757d); }
.ab-mod-chip-label {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
[data-bs-theme=dark] .ab-mod-chip {
    background: #2b3035;
    border-color: #495057;
    color: #dee2e6;
}
[data-bs-theme=dark] .ab-mod-chip:hover {
    border-color: #60a5fa;
    background: rgba(96,165,250,.08);
    color: #dee2e6;
}

/* ── 5. Upgrade CTA ──────────────────────────────────────────────────── */
.ab-mod-cta {
    display: block;
    text-decoration: none;
    background: linear-gradient(135deg, #0d6efd, #6610f2);
    color: #fff;
    padding: 10px 14px;
    border-bottom: 1px solid rgba(0,0,0,.1);
}
.ab-mod-cta:hover { color: #fff; text-decoration: none; opacity: .92; }
.ab-mod-cta-title {
    font-weight: 700;
    font-size: .82rem;
    display: flex;
    align-items: center;
    gap: 6px;
}
.ab-mod-cta-sub {
    font-size: .7rem;
    opacity: .9;
    margin-top: 2px;
}

/* ── 6. Actions grid ─────────────────────────────────────────────────── */
/* v0.55.5 — Task #478: reduced to 4 actions in a single row. */
.ab-mod-actions {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 5px;
}
.ab-mod-action {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    padding: 8px 4px;
    border-radius: 6px;
    border: 1px solid var(--bs-border-color, #dee2e6);
    background: var(--bs-body-bg, #fff);
    color: var(--bs-body-color, #212529);
    font-size: .68rem;
    font-weight: 500;
    text-decoration: none;
    text-align: center;
    transition: all .15s;
    min-width: 0;
}
.ab-mod-action:hover {
    border-color: #0d6efd;
    background: rgba(13,110,253,.04);
    color: #0d6efd;
    text-decoration: none;
    transform: translateY(-1px);
}
.ab-mod-action [class^="icon-"] {
    font-size: 1.05rem;
    line-height: 1;
}
.ab-mod-action--alert {
    border-color: rgba(220,53,69,.4);
    background: rgba(220,53,69,.05);
    color: #b02a37;
}
.ab-mod-action--alert:hover {
    border-color: #dc3545;
    background: rgba(220,53,69,.1);
    color: #b02a37;
}
[data-bs-theme=dark] .ab-mod-action {
    background: #2b3035;
    border-color: #495057;
    color: #dee2e6;
}
[data-bs-theme=dark] .ab-mod-action:hover {
    border-color: #60a5fa;
    background: rgba(96,165,250,.08);
    color: #60a5fa;
}
</style>

<div class="card h-100 ab-mod-card ab-mod-health">
  <div class="card-body">

    <!-- ── 1. Brand strip ───────────────────────────────────────────── -->
    <div class="ab-mod-brand">
      <span class="ab-mod-brand-name"><?php echo Text::_('MOD_AIBOOST_HEALTH_BRAND'); ?></span>
      <span class="ab-mod-brand-ver">v<?php echo htmlspecialchars($abVersion, ENT_QUOTES); ?></span>
      <span class="ab-mod-pill <?php echo $licenceClass; ?>"><?php echo htmlspecialchars($licenceLabel, ENT_QUOTES); ?></span>
    </div>

    <!-- ── 2. Score panel ──────────────────────────────────────────── -->
    <div class="ab-mod-panel">
      <a href="<?php echo htmlspecialchars($abHealthUrl, ENT_QUOTES); ?>"
         class="ab-mod-score-row text-reset text-decoration-none"
         style="cursor:pointer">
        <div class="ab-mod-score-wrap <?php echo $scoreClass; ?>">
          <svg width="64" height="64" viewBox="0 0 52 52" aria-hidden="true">
            <circle cx="26" cy="26" r="22" fill="none" stroke-width="3" class="ab-mod-score-track"/>
            <circle cx="26" cy="26" r="22" fill="none" stroke-width="3"
              stroke-dasharray="<?php echo $circ; ?>"
              stroke-dashoffset="<?php echo $circ; ?>"
              stroke-linecap="round"
              transform="rotate(-90 26 26)"
              class="ab-mod-score-arc"/>
          </svg>
          <div class="ab-mod-score-num"><?php echo (int) $abScore; ?></div>
        </div>
        <div class="ab-mod-score-info">
          <div class="ab-mod-score-label"><?php echo $statusText; ?></div>
          <div class="ab-mod-score-counts">
            <?php if ($abCritical > 0): ?>
              <span class="ab-mod-count ab-mod-count--crit">
                <?php echo $abCritical; ?> <?php echo Text::_('MOD_AIBOOST_HEALTH_ISSUE_CRITICAL'); ?>
              </span>
            <?php endif; ?>
            <?php if ($abWarnings > 0): ?>
              <span class="ab-mod-count ab-mod-count--warn">
                <?php echo $abWarnings; ?> <?php echo $abWarnings !== 1
                    ? Text::_('MOD_AIBOOST_HEALTH_ISSUE_WARNINGS')
                    : Text::_('MOD_AIBOOST_HEALTH_ISSUE_WARNING'); ?>
              </span>
            <?php endif; ?>
            <?php if ($abTotal === 0): ?>
              <span class="ab-mod-count ab-mod-count--ok">
                <?php echo Text::_('MOD_AIBOOST_HEALTH_ALL_PASSED'); ?>
              </span>
            <?php endif; ?>
          </div>
        </div>
      </a>

      <!-- Top issues -->
      <?php if (!empty($abTopIssues)): ?>
        <ul class="ab-mod-issues">
          <?php foreach ($abTopIssues as $issue): ?>
            <?php
            $dotColor = $issue['status'] === 'critical' ? '#dc3545' : '#fd7e14';
            $label    = htmlspecialchars($issue['label'] ?? $issue['check'] ?? '', ENT_QUOTES);
            ?>
            <li>
              <span class="ab-dot" style="background:<?php echo $dotColor; ?>"></span>
              <span class="text-truncate"><?php echo $label; ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <!-- ── 2b. Signals row: indexed URLs + last URL scan ──────────── -->
    <div class="ab-mod-panel ab-mod-signals">
      <a href="<?php echo htmlspecialchars($abDashboardUrl, ENT_QUOTES); ?>"
         class="ab-mod-signal" title="Open AI Boost Dashboard">
        <div class="ab-mod-signal-val">
          <?php echo $abIndexedUrls !== null ? number_format($abIndexedUrls) : '—'; ?>
        </div>
        <div class="ab-mod-signal-lbl"><?php echo Text::_('MOD_AIBOOST_HEALTH_SIGNAL_INDEXED'); ?></div>
      </a>
      <a href="<?php echo htmlspecialchars($abUrlCheckerUrl, ENT_QUOTES); ?>"
         class="ab-mod-signal<?php echo $ab404Count > 0 ? ' ab-mod-signal--alert' : ' ab-mod-signal--ok'; ?>"
         title="<?php echo $ab404LastSeen
             ? 'Last 404 logged ' . htmlspecialchars($ab404LastSeen, ENT_QUOTES)
             : 'No URL scan issues recorded'; ?>">
        <div class="ab-mod-signal-val">
          <?php echo $ab404Count > 0 ? number_format($ab404Count) : '0'; ?>
        </div>
        <div class="ab-mod-signal-lbl">
          <?php echo $ab404Count > 0
              ? Text::_('MOD_AIBOOST_HEALTH_SIGNAL_SCAN_ISSUES')
              : Text::_('MOD_AIBOOST_HEALTH_SIGNAL_SCAN_CLEAN'); ?>
        </div>
      </a>
      <a href="<?php echo htmlspecialchars($abDashboardUrl, ENT_QUOTES); ?>"
         class="ab-mod-signal ab-mod-signal--accent" title="Open Dashboard">
        <div class="ab-mod-signal-val" style="font-size:1rem">→</div>
        <div class="ab-mod-signal-lbl"><?php echo Text::_('MOD_AIBOOST_HEALTH_LINK_DASHBOARD'); ?></div>
      </a>
    </div>

    <!-- ── 3. Plugins panel — REMOVED in v0.55.5 (Task #478) ──────────
         Bojan's directive: the per-plugin enabled chips added noise
         without giving the user a clear action. The same information
         is surfaced inside the Health page via dedicated checks. -->

    <!-- ── 4. Upgrade CTA (Free only) ──────────────────────────────── -->
    <?php if (!$abIsPro): ?>
      <a class="ab-mod-cta"
         href="https://aiboostnow.com/pricing?utm_source=plugin&utm_medium=admin-module&utm_campaign=upgrade"
         target="_blank" rel="noopener">
        <div class="ab-mod-cta-title">
          <span aria-hidden="true">★</span>
          <?php echo Text::_('MOD_AIBOOST_HEALTH_CTA_TITLE'); ?>
        </div>
        <div class="ab-mod-cta-sub"><?php echo Text::_('MOD_AIBOOST_HEALTH_CTA_SUB'); ?></div>
      </a>
    <?php endif; ?>

    <!-- ── 5. Quick actions ────────────────────────────────────────────
         v0.55.5 — Task #478: reduced from 6 to 4 actions in a single
         row. Dropped Import (used once at setup) and URL Checker
         (still reachable from the top nav) per Bojan's directive. -->
    <div class="ab-mod-panel">
      <div class="ab-mod-section-title"><?php echo Text::_('MOD_AIBOOST_HEALTH_ACTIONS_TITLE'); ?></div>
      <div class="ab-mod-actions">
        <a href="<?php echo htmlspecialchars($abSettingsUrl, ENT_QUOTES); ?>" class="ab-mod-action">
          <span class="icon-cog" aria-hidden="true"></span>
          <span><?php echo Text::_('MOD_AIBOOST_HEALTH_LINK_SETTINGS'); ?></span>
        </a>
        <a href="<?php echo htmlspecialchars($abHealthUrl, ENT_QUOTES); ?>"
           class="ab-mod-action<?php echo $abCritical > 0 ? ' ab-mod-action--alert' : ''; ?>">
          <span class="icon-heart" aria-hidden="true"></span>
          <span><?php echo Text::_('MOD_AIBOOST_HEALTH_LINK_HEALTH'); ?></span>
        </a>
        <a href="<?php echo htmlspecialchars($abAnalyzerUrl, ENT_QUOTES); ?>" class="ab-mod-action">
          <span class="icon-search" aria-hidden="true"></span>
          <span><?php echo Text::_('MOD_AIBOOST_HEALTH_LINK_ANALYZERS'); ?></span>
        </a>
        <a href="<?php echo htmlspecialchars($abSitemapUrl, ENT_QUOTES); ?>" class="ab-mod-action">
          <span class="icon-arrow-right" aria-hidden="true"></span>
          <span><?php echo Text::_('MOD_AIBOOST_HEALTH_LINK_REDIRECTS'); ?></span>
        </a>
      </div>
    </div>

  </div>
</div>
