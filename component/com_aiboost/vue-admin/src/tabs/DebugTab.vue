<template>
  <div class="ab-settings-tab">

    <div class="ab-alert ab-alert--info" role="note">
      <AbIcon name="info" class="ab-alert__icon" style="font-size:1.15rem" />
      <div style="max-width:62ch">
        <div class="ab-alert__title">About this tab</div>
        <div class="ab-alert__body">
          Debug options below are designed to stay enabled on a live site. They control
          verbose logging, HTML comment output, and staging-mode suppression of
          analytics / IndexNow / redirects.
        </div>
      </div>
    </div>

    <!-- 01 Production-safe options -->
    <div class="ab-section">
      <div class="ab-section__head">
        <span class="ab-section__num">01</span>
        Production-safe options
      </div>
      <div class="ab-section__body">
        <label class="ab-toggle-row">
          <div>
            <div class="ab-label">Enable debug mode (verbose logging)</div>
            <div class="ab-help">
              Writes per-request <code>error_log</code> lines from each AI Boost plugin
              to the PHP error log. Useful for troubleshooting in production — no
              visible front-end change, no performance impact.
            </div>
          </div>
          <span class="ab-toggle" :class="{'is-on': s.debug_mode === '1'}">
            <input v-model="s.debug_mode" data-ab-field="debug_mode" true-value="1" false-value="0"
              type="checkbox" class="ab-toggle__input" id="dbg-mode">
            <span class="ab-toggle__track"></span>
          </span>
        </label>

        <label class="ab-toggle-row">
          <div>
            <div class="ab-label">Hide comments in HTML source</div>
            <div class="ab-help">
              By default, AI Boost emits annotated comments inside <code>&lt;head&gt;</code>
              and <code>&lt;body&gt;</code> wrappers. Turn this ON to strip every inner
              comment — only the minimal <code>&lt;!-- AI Boost for Joomla - Start/End --&gt;</code>
              pair remains.
            </div>
          </div>
          <span class="ab-toggle" :class="{'is-on': s.hide_comments === '1'}">
            <input v-model="s.hide_comments" true-value="1" false-value="0"
              type="checkbox" class="ab-toggle__input" id="dbg-hide">
            <span class="ab-toggle__track"></span>
          </span>
        </label>

        <label class="ab-toggle-row">
          <div>
            <div class="ab-label">Staging mode <span class="ab-muted">(suppress real analytics / IndexNow / redirects)</span></div>
            <div class="ab-help">
              Enable on staging / dev domains. Skips GA4 / GTM / Meta Pixel injection,
              IndexNow submissions, search-engine pings, canonical rewrites, redirect rules,
              and 404 logging. Schema and OpenGraph still render for preview. Safe to leave
              enabled forever on non-production sites.
            </div>
          </div>
          <span class="ab-toggle" :class="{'is-on': s.staging_mode === '1'}">
            <input v-model="s.staging_mode" data-ab-field="staging_mode" true-value="1" false-value="0"
              type="checkbox" class="ab-toggle__input" id="dbg-staging">
            <span class="ab-toggle__track"></span>
          </span>
        </label>
      </div>
    </div>

    <!-- 02 Error logging -->
    <div class="ab-section">
      <div class="ab-section__head">
        <span class="ab-section__num">02</span>
        Error logging
      </div>
      <div class="ab-section__body">
        <label class="ab-toggle-row">
          <div>
            <div class="ab-label">Enable AI Boost error log</div>
            <div class="ab-help">
              Writes AI Boost warnings and errors to <code>#__aiboost_error_log</code>
              (and Joomla's log file). Retention: at most 1000 rows or 30 days.
            </div>
          </div>
          <span class="ab-toggle" :class="{'is-on': s.error_log_enabled === '1'}">
            <input v-model="s.error_log_enabled" data-ab-field="error_log_enabled"
              true-value="1" false-value="0"
              type="checkbox" class="ab-toggle__input" id="dbg-errlog-enabled">
            <span class="ab-toggle__track"></span>
          </span>
        </label>

        <div class="ab-field">
          <label class="ab-label" for="dbg-errlog-min">Minimum severity to log</label>
          <select v-model="s.error_log_min_severity" data-ab-field="error_log_min_severity"
            class="ab-select" id="dbg-errlog-min" style="max-width:340px">
            <option value="debug">Debug (very verbose — troubleshooting only)</option>
            <option value="info">Info</option>
            <option value="warning">Warning (recommended)</option>
            <option value="error">Error only</option>
          </select>
          <div class="ab-help">Events below this severity are dropped. Use <em>debug</em> temporarily when troubleshooting.</div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
export default {
  name: 'DebugTab',
  props: { s: { type: Object, required: true } },
}
</script>
