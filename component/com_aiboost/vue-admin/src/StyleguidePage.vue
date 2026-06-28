<template>
  <div class="ab-styleguide ab-page" style="max-width:1080px">
    <div class="ab-cluster" style="justify-content:space-between;margin-bottom:1rem">
      <h1 style="margin:0;font-size:1.5rem">AI Boost Design System</h1>
      <button class="ab-btn ab-btn--ghost ab-btn--sm" @click="toggleTheme">
        {{ isDark ? 'Switch to light' : 'Switch to dark' }}
      </button>
    </div>
    <p class="ab-help" style="margin-bottom:1.5rem">
      Visual reference for the <code>.ab-*</code> components and the Instrument
      icon set. Toggle the theme to verify every variant reads correctly on both
      light and dark surfaces. Use this page after every design-system change.
    </p>

    <!-- Icons -->
    <section class="ab-card" style="margin-bottom:1.25rem">
      <div class="ab-card__header">Icons — Instrument set</div>
      <div class="ab-card__body">
        <div class="sg-icons">
          <div v-for="name in iconNames" :key="name" class="sg-icon">
            <AbIcon :name="name" style="font-size:20px" />
            <span class="sg-icon__name">{{ name }}</span>
          </div>
        </div>
      </div>
    </section>

    <!-- Numbered sections -->
    <section class="ab-card" style="margin-bottom:1.25rem">
      <div class="ab-card__header">Numbered sections &amp; toggle rows</div>
      <div class="ab-card__body ab-stack">
        <div class="ab-section">
          <div class="ab-section__head">
            <span class="ab-section__num">01</span> Schema.org Core
          </div>
          <div class="ab-section__body">
            <div class="ab-toggle-row">
              <div>
                <div class="ab-label">Enable Schema.org structured data</div>
                <div class="ab-help">Master switch for all JSON-LD output.</div>
              </div>
              <span class="ab-toggle is-on" role="switch" aria-checked="true"><span class="ab-toggle__track"></span></span>
            </div>
            <div class="ab-toggle-row">
              <div>
                <div class="ab-label">Include SearchAction</div>
                <div class="ab-help">Sitelinks Search Box.</div>
              </div>
              <span class="ab-toggle" role="switch" aria-checked="false"><span class="ab-toggle__track"></span></span>
            </div>
          </div>
        </div>
        <div class="ab-section">
          <div class="ab-section__head">
            <span class="ab-section__num">02</span> Author Entity
            <span class="ab-tag ab-tag--pro" style="margin-left:.4rem">Pro</span>
          </div>
          <div class="ab-section__body">
            <div class="ab-field">
              <label class="ab-label">Author display name</label>
              <input class="ab-input" value="Jane Doe" />
              <span class="ab-help">Shown as the Person entity for each article.</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Module cards -->
    <section class="ab-card" style="margin-bottom:1.25rem">
      <div class="ab-card__header">Module cards</div>
      <div class="ab-card__body">
        <div class="ab-grid-cards">
          <div class="ab-modcard">
            <div class="ab-modcard__title">Schema.org</div>
            <span class="ab-badge ab-badge--success" style="align-self:flex-start">Enabled</span>
            <p>Structured data so engines understand your content.</p>
            <button class="ab-btn ab-btn--ghost ab-btn--sm" style="align-self:flex-start">Configure</button>
          </div>
          <div class="ab-modcard ab-modcard--muted">
            <div class="ab-modcard__title">Analytics</div>
            <span class="ab-badge" style="align-self:flex-start">Disabled</span>
            <p>GA4, GTM, Meta Pixel &amp; Search Console.</p>
            <button class="ab-btn ab-btn--ghost ab-btn--sm" style="align-self:flex-start">Configure</button>
          </div>
        </div>
      </div>
    </section>

    <!-- Health rows -->
    <section class="ab-card" style="margin-bottom:1.25rem">
      <div class="ab-card__header">Health rows</div>
      <div class="ab-card__body" style="padding:0 1rem">
        <div class="ab-healthrow">
          <AbIcon name="ok" class="ab-healthrow__icon" style="color:var(--ab-success)" />
          <div><div class="ab-healthrow__title">Organization name</div><p>Organization name is set.</p></div>
        </div>
        <div class="ab-healthrow">
          <AbIcon name="warn" class="ab-healthrow__icon" style="color:var(--ab-warning)" />
          <div><div class="ab-healthrow__title">Settings backup</div><p>No backup downloaded from this browser yet.</p></div>
          <div class="ab-healthrow__act"><button class="ab-btn ab-btn--subtle ab-btn--sm">Fix it</button></div>
        </div>
        <div class="ab-healthrow">
          <AbIcon name="err" class="ab-healthrow__icon" style="color:var(--ab-danger)" />
          <div><div class="ab-healthrow__title">Duplicate schema</div><p>Another extension emits Organization schema.</p></div>
          <div class="ab-healthrow__act"><button class="ab-btn ab-btn--subtle ab-btn--sm">Resolve</button></div>
        </div>
      </div>
    </section>

    <!-- Buttons -->
    <section class="ab-card" style="margin-bottom:1.25rem">
      <div class="ab-card__header">Buttons</div>
      <div class="ab-card__body ab-stack">
        <div class="ab-cluster">
          <button class="ab-btn ab-btn--primary">Primary</button>
          <button class="ab-btn ab-btn--success">Success</button>
          <button class="ab-btn ab-btn--danger">Danger</button>
          <button class="ab-btn ab-btn--ghost">Ghost</button>
          <button class="ab-btn ab-btn--subtle">Subtle</button>
          <button class="ab-btn">Default</button>
        </div>
        <div class="ab-cluster">
          <button class="ab-btn ab-btn--primary ab-btn--sm">Small primary</button>
          <button class="ab-btn ab-btn--ghost ab-btn--sm">Small ghost</button>
          <button class="ab-btn ab-btn--primary ab-btn--lg">Large primary</button>
          <button class="ab-btn ab-btn--ghost" disabled>Disabled ghost</button>
          <button class="ab-btn ab-btn--primary" disabled>Disabled primary</button>
        </div>
      </div>
    </section>

    <!-- Inputs -->
    <section class="ab-card" style="margin-bottom:1.25rem">
      <div class="ab-card__header">Inputs</div>
      <div class="ab-card__body ab-stack" style="max-width:32rem">
        <div class="ab-field">
          <label class="ab-label" for="sg-in1">Standard input</label>
          <input id="sg-in1" type="text" class="ab-input" placeholder="Type something…" />
          <span class="ab-help">Helper text that explains the field.</span>
        </div>
        <div class="ab-field is-invalid">
          <label class="ab-label" for="sg-in2">Invalid input</label>
          <input id="sg-in2" type="text" class="ab-input" value="not-an-email" />
          <span class="ab-error">Please enter a valid email.</span>
        </div>
        <div class="ab-field">
          <label class="ab-label" for="sg-sel">Select</label>
          <select id="sg-sel" class="ab-select"><option>Aggressive — always emit ours</option><option>Cooperative</option><option>Off</option></select>
        </div>
        <div class="ab-field">
          <label class="ab-label" for="sg-in3">Disabled input</label>
          <input id="sg-in3" type="text" class="ab-input" value="Read only" disabled />
        </div>
      </div>
    </section>

    <!-- Badges & Tags -->
    <section class="ab-card" style="margin-bottom:1.25rem">
      <div class="ab-card__header">Badges &amp; Tags</div>
      <div class="ab-card__body ab-stack">
        <div class="ab-cluster ab-stat-pills">
          <span class="ab-badge">Default</span>
          <span class="ab-badge ab-badge--primary">Primary</span>
          <span class="ab-badge ab-badge--success">Success</span>
          <span class="ab-badge ab-badge--warning">Warning</span>
          <span class="ab-badge ab-badge--danger">Danger</span>
          <span class="ab-badge ab-badge--info">Info</span>
        </div>
        <div class="ab-cluster">
          <span class="ab-tag ab-tag--pro">Pro</span>
          <span class="ab-tag ab-tag--free">Free</span>
          <span class="ab-tag ab-tag--beta">Beta</span>
          <span class="ab-tag">Default</span>
        </div>
      </div>
    </section>

    <!-- Tabs & pills -->
    <section class="ab-card" style="margin-bottom:1.25rem">
      <div class="ab-card__header">Tabs &amp; pills</div>
      <div class="ab-card__body ab-stack">
        <div class="ab-tabs" role="tablist">
          <button :class="['ab-tab', activeTab === 'a' ? 'is-active' : '']" @click="activeTab = 'a'" type="button">SEO Analyzer</button>
          <button :class="['ab-tab', activeTab === 'b' ? 'is-active' : '']" @click="activeTab = 'b'" type="button">JSON-LD</button>
          <button :class="['ab-tab', activeTab === 'c' ? 'is-active' : '']" @click="activeTab = 'c'" type="button">AI Visibility</button>
        </div>
        <div class="ab-pills">
          <button class="ab-pill is-active">Core</button>
          <button class="ab-pill">Business</button>
          <button class="ab-pill">Hours</button>
          <button class="ab-pill">FAQ</button>
        </div>
      </div>
    </section>

    <!-- Alerts -->
    <section class="ab-card" style="margin-bottom:1.25rem">
      <div class="ab-card__header">Alerts</div>
      <div class="ab-card__body ab-stack">
        <div class="ab-alert ab-alert--success">
          <AbIcon name="check" class="ab-alert__icon" style="font-size:1.15rem" />
          <div><div class="ab-alert__title">Success</div><div class="ab-alert__body">Operation completed without issues.</div></div>
        </div>
        <div class="ab-alert ab-alert--warning">
          <AbIcon name="warn" class="ab-alert__icon" style="font-size:1.15rem" />
          <div><div class="ab-alert__title">Warning</div><div class="ab-alert__body">Some non-critical issues need your attention.</div></div>
        </div>
        <div class="ab-alert ab-alert--danger">
          <AbIcon name="err" class="ab-alert__icon" style="font-size:1.15rem" />
          <div><div class="ab-alert__title">Error</div><div class="ab-alert__body">Something went wrong — see details below.</div></div>
        </div>
        <div class="ab-alert ab-alert--info">
          <AbIcon name="info" class="ab-alert__icon" style="font-size:1.15rem" />
          <div><div class="ab-alert__title">Heads up</div><div class="ab-alert__body">Multilingual support requires Joomla Multilanguage to be active.</div></div>
        </div>
      </div>
    </section>

    <!-- Toggle + key/value -->
    <section class="ab-card" style="margin-bottom:1.25rem">
      <div class="ab-card__header">Toggle &amp; key / value</div>
      <div class="ab-card__body ab-stack">
        <label class="ab-toggle" :class="{ 'is-on': toggle1 }">
          <input type="checkbox" v-model="toggle1" hidden />
          <span class="ab-toggle__track"></span>
          <span class="ab-toggle__label">Enable feature ({{ toggle1 ? 'on' : 'off' }})</span>
        </label>
        <label class="ab-toggle is-locked">
          <span class="ab-toggle__track"></span>
          <span class="ab-toggle__label">Locked (Pro only)</span>
        </label>
        <dl class="ab-kv" style="max-width:24rem">
          <dt>Site URL</dt><dd class="ab-mono">staging.offroadserbia.com</dd>
          <dt>AI Boost version</dt><dd class="ab-mono">0.87.x</dd>
          <dt>Joomla version</dt><dd class="ab-mono">6.1.1</dd>
        </dl>
      </div>
    </section>

    <p class="ab-help" style="margin-top:1.5rem">
      Toggle dark / light using the button above (writes <code>data-bs-theme</code>
      on <code>&lt;html&gt;</code>) and confirm every component above reads
      correctly in both modes.
    </p>
  </div>
</template>

<script>
import { ref, onMounted, onUnmounted } from 'vue'
import { AB_ICONS } from './icons.js'

export default {
  name: 'StyleguidePage',

  setup() {
    const activeTab = ref('a')
    const toggle1   = ref(true)
    const iconNames = Object.keys(AB_ICONS)
    const root      = typeof document !== 'undefined' ? document.documentElement : null
    const isDark    = ref(root ? root.getAttribute('data-bs-theme') === 'dark' : false)

    let observer = null
    onMounted(() => {
      if (!root || typeof MutationObserver === 'undefined') return
      observer = new MutationObserver(() => {
        isDark.value = root.getAttribute('data-bs-theme') === 'dark'
      })
      observer.observe(root, { attributes: true, attributeFilter: ['data-bs-theme'] })
    })
    onUnmounted(() => observer && observer.disconnect())

    function toggleTheme() {
      if (!root) return
      const next = isDark.value ? 'light' : 'dark'
      root.setAttribute('data-bs-theme', next)
      document.body.setAttribute('data-bs-theme', next)
      isDark.value = next === 'dark'
    }

    return { activeTab, toggle1, iconNames, isDark, toggleTheme }
  },
}
</script>

<style>
.sg-icons { display: grid; grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); gap: .5rem; }
.sg-icon {
  display: flex; flex-direction: column; align-items: center; gap: .4rem;
  padding: .7rem .4rem; border: 1px solid var(--ab-border); border-radius: var(--ab-radius);
  color: var(--ab-text);
}
.sg-icon__name { font-family: var(--ab-font-mono); font-size: var(--ab-font-size-xs); color: var(--ab-text-muted); }
</style>
