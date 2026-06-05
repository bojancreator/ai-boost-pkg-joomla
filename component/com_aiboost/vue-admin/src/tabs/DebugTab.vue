<template>
  <div class="ab-debug-tab">
    <!-- Task #473 — Debug tab is whole-tab Pro. -->
    <div class="ab-alert ab-alert--info">
      <strong>About this tab</strong> — Debug options below are designed to stay
      enabled on a live site. They control verbose logging, HTML comment output,
      and staging-mode suppression of analytics / IndexNow / redirects.
    </div>

    <!-- ─── Production-safe ─────────────────────────────────────────────── -->
    <div class="ab-card">
      <div class="ab-card-header">✅ Production-safe options</div>
      <div class="ab-card-body">

        <div class="ab-field">
          <div class="ab-check ab-toggle">
            <input v-model="s.debug_mode" data-ab-field="debug_mode" true-value="1" false-value="0"
              type="checkbox" class="ab-toggle__input" id="dbg-mode">
            <label class="ab-check__label" for="dbg-mode">
              Enable debug mode (verbose logging)
            </label>
          </div>
          <p class="ab-help">
            Writes per-request <code>error_log</code> lines from each AI Boost plugin
            (e.g. <code>[AI Boost: aiboost_schema] injecting 3 JSON-LD blocks</code>)
            to the PHP error log. Useful for troubleshooting in production — no
            visible front-end change to visitors, no performance impact beyond a
            single log line per plugin per request. Does not affect HTML comment
            output; for that see <em>Hide comments</em> below.
          </p>
        </div>

        <div class="ab-field">
          <div class="ab-check ab-toggle">
            <input v-model="s.hide_comments" true-value="1" false-value="0"
              type="checkbox" class="ab-toggle__input" id="dbg-hide">
            <label class="ab-check__label" for="dbg-hide">
              Hide comments in HTML source
            </label>
          </div>
          <p class="ab-help">
            By default, AI Boost emits Yoast / GTM-style annotated comments
            inside the <code>&lt;head&gt;</code> and <code>&lt;body&gt;</code>
            wrappers — sub-section labels
            (<code>&lt;!-- Schema.org --&gt;</code>,
            <code>&lt;!-- OpenGraph &amp; Twitter --&gt;</code>,
            <code>&lt;!-- AEO --&gt;</code>, <code>&lt;!-- Analytics --&gt;</code>,
            <code>&lt;!-- Custom Code --&gt;</code>,
            <code>&lt;!-- GTM (noscript) --&gt;</code>, …),
            <em>Also emitted via Joomla head</em> summary, and
            cooperative-mode <em>Skipped</em> notes. Turn this ON to strip
            every inner comment and keep only the minimal
            <code>&lt;!-- AI Boost for Joomla - Start/End --&gt;</code> outer
            pair so the rendered page source is as compact as possible.
            (The outer pair never includes version or URL — those live in
            <code>&lt;meta name="generator"&gt;</code>.)
          </p>
        </div>

        <div class="ab-field ab-field--last">
          <div class="ab-check ab-toggle">
            <input v-model="s.staging_mode" data-ab-field="staging_mode" true-value="1" false-value="0"
              type="checkbox" class="ab-toggle__input" id="dbg-staging">
            <label class="ab-check__label" for="dbg-staging">
              Staging mode (suppress real analytics / IndexNow / redirects)
            </label>
          </div>
          <p class="ab-help">
            Enable on staging / dev domains so the plugin does not pollute production
            data. Skips GA4 / GTM / Meta Pixel injection, IndexNow submissions,
            search-engine pings, canonical rewrites, redirect rules, and 404 logging.
            Schema and OpenGraph still render so you can preview them. Safe to leave
            enabled forever on non-production sites.
          </p>
        </div>

      </div>
    </div>

    <!-- ─── Error logging (Task #511) ──────────────────────────────────── -->
    <div class="ab-card">
      <div class="ab-card-header">📒 Error logging</div>
      <div class="ab-card-body">

        <div class="ab-field">
          <div class="ab-check ab-toggle">
            <input v-model="s.error_log_enabled" data-ab-field="error_log_enabled"
              true-value="1" false-value="0"
              type="checkbox" class="ab-toggle__input" id="dbg-errlog-enabled">
            <label class="ab-check__label" for="dbg-errlog-enabled">
              Enable AI Boost error log
            </label>
          </div>
          <p class="ab-help">
            Writes AI Boost warnings and errors to the dedicated
            <code>#__aiboost_error_log</code> table (and Joomla's log file)
            so you can review them from the admin instead of digging through
            <code>error_log</code>. Retention is automatic: at most 1000 rows
            or 30 days, whichever comes first.
          </p>
        </div>

        <div class="ab-field ab-field--last">
          <label class="ab-label" for="dbg-errlog-min">Minimum severity to log</label>
          <select v-model="s.error_log_min_severity" data-ab-field="error_log_min_severity"
            class="ab-select" id="dbg-errlog-min" style="max-width:340px">
            <option value="debug">Debug (very verbose — troubleshooting only)</option>
            <option value="info">Info</option>
            <option value="warning">Warning (recommended)</option>
            <option value="error">Error only</option>
          </select>
          <p class="ab-help">
            Events below this severity are dropped. Use <em>debug</em>
            temporarily when troubleshooting; <em>warning</em> is safe for
            production.
          </p>
        </div>

      </div>
    </div>

    <!--
      Task #457 — The "Simulate Professional license" toggle was removed
      from the UI. The underlying `dev_license_preview` setting is still
      honoured by PHP for backwards compat with installs that toggled it
      previously, but it is no longer exposed in the admin. See the
      "Developer Pro simulation" note in replit.md for the supported QA
      override (direct DB edit of #__aiboost_settings).
    -->
  </div>
</template>

<script>
export default {
  name: 'DebugTab',
  props: { s: { type: Object, required: true } },
}
</script>

<style scoped>
.ab-debug-tab { max-width: 860px; }

.ab-alert--info {
  background: #e7f1ff;
  border: 1px solid #b6d4fe;
  border-radius: 6px;
  padding: .75rem 1rem;
  color: #084298;
  margin-bottom: 16px;
  font-size: 13px;
  line-height: 1.5;
}

.ab-card + .ab-card { margin-top: 16px; }

.ab-card--danger .ab-card-header {
  background: #fff3cd;
  color: #664d03;
  border-bottom-color: #ffc107;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.ab-card--muted {
  border-style: dashed;
  background: #fafbfc;
}
.ab-card-body--center { text-align: center; padding: 18px; }

.ab-card-hide {
  background: transparent;
  border: 0;
  color: #664d03;
  font-size: 18px;
  line-height: 1;
  cursor: pointer;
  padding: 0 4px;
}
.ab-card-hide:hover { color: #000; }

.ab-field { padding-bottom: 14px; margin-bottom: 14px; border-bottom: 1px solid #eef0f2; }
.ab-field--last { padding-bottom: 0; margin-bottom: 0; border-bottom: 0; }

.ab-help {
  margin: 6px 0 0 36px;
  font-size: 12.5px;
  line-height: 1.5;
  color: #5c6470;
}
.ab-card-body--center .ab-help { margin-left: 0; }
.ab-help code {
  background: #f1f3f5;
  padding: 1px 5px;
  border-radius: 3px;
  font-size: 11.5px;
  color: #212529;
}
</style>
