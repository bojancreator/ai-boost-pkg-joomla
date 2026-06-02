<template>
  <div class="ab-styleguide">
    <div class="ab-cluster" style="justify-content:space-between;margin-bottom:1rem">
      <h1 style="margin:0;font-size:1.5rem">AI Boost Design System</h1>
      <button class="ab-btn ab-btn--ghost ab-btn--sm" @click="toggleTheme">
        <span :class="['me-1', isDark ? 'icon-sun' : 'icon-moon']" aria-hidden="true"></span>
        {{ isDark ? 'Switch to light' : 'Switch to dark' }}
      </button>
    </div>
    <p class="ab-help" style="margin-bottom:1.5rem">
      Visual reference for the <code>.ab-*</code> components. Toggle the theme
      to verify every variant reads correctly on both light and dark surfaces.
      Use this page after every design-system change.
    </p>

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

    <!-- Cards -->
    <section class="ab-card" style="margin-bottom:1.25rem">
      <div class="ab-card__header">Cards</div>
      <div class="ab-card__body">
        <div class="ab-cluster" style="align-items:flex-start">
          <div class="ab-card" style="min-width:240px;flex:1">
            <div class="ab-card__header">Basic card</div>
            <div class="ab-card__body">Card body content sits here.</div>
            <div class="ab-card__footer">
              <button class="ab-btn ab-btn--primary ab-btn--sm">Action</button>
            </div>
          </div>
          <div class="ab-card" style="min-width:240px;flex:1">
            <div class="ab-card__header">
              <span class="icon-cog" aria-hidden="true"></span> With icon header
            </div>
            <div class="ab-card__body">Compact text inside a card body.</div>
          </div>
        </div>
      </div>
    </section>

    <!-- Inputs -->
    <section class="ab-card" style="margin-bottom:1.25rem">
      <div class="ab-card__header">Inputs</div>
      <div class="ab-card__body ab-stack" style="max-width:32rem">
        <div>
          <label class="ab-label" for="sg-in1">Standard input</label>
          <input id="sg-in1" type="text" class="ab-input" placeholder="Type something…" />
          <span class="ab-help">Helper text that explains the field.</span>
        </div>
        <div>
          <label class="ab-label" for="sg-in2">Invalid input</label>
          <input id="sg-in2" type="text" class="ab-input ab-input--invalid" value="not-an-email" />
          <span class="ab-help" style="color:var(--ab-danger)">Please enter a valid email.</span>
        </div>
        <div>
          <label class="ab-label" for="sg-in3">Disabled input</label>
          <input id="sg-in3" type="text" class="ab-input" value="Read only" disabled />
        </div>
      </div>
    </section>

    <!-- Badges & Tags -->
    <section class="ab-card" style="margin-bottom:1.25rem">
      <div class="ab-card__header">Badges &amp; Tags</div>
      <div class="ab-card__body ab-stack">
        <div class="ab-cluster">
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

    <!-- Tabs -->
    <section class="ab-card" style="margin-bottom:1.25rem">
      <div class="ab-card__header">Tabs</div>
      <div class="ab-card__body">
        <ul class="ab-tabs" role="tablist">
          <li>
            <button :class="['ab-tab', activeTab === 'a' ? 'ab-tab--active' : '']"
                    @click="activeTab = 'a'" type="button">SEO</button>
          </li>
          <li>
            <button :class="['ab-tab', activeTab === 'b' ? 'ab-tab--active' : '']"
                    @click="activeTab = 'b'" type="button">JSON-LD</button>
          </li>
          <li>
            <button :class="['ab-tab', activeTab === 'c' ? 'ab-tab--active' : '']"
                    @click="activeTab = 'c'" type="button">AI Visibility</button>
          </li>
        </ul>
        <p style="margin:0;color:var(--ab-text-muted)">Active tab: <strong>{{ activeTab }}</strong></p>
      </div>
    </section>

    <!-- Alerts -->
    <section class="ab-card" style="margin-bottom:1.25rem">
      <div class="ab-card__header">Alerts</div>
      <div class="ab-card__body ab-stack">
        <div class="ab-alert ab-alert--success">
          <span class="icon-checkmark" aria-hidden="true"></span>
          <div><strong>Success.</strong> Operation completed without issues.</div>
        </div>
        <div class="ab-alert ab-alert--warning">
          <span class="icon-warning" aria-hidden="true"></span>
          <div><strong>Warning.</strong> Some non-critical issues need your attention.</div>
        </div>
        <div class="ab-alert ab-alert--danger">
          <span class="icon-warning" aria-hidden="true"></span>
          <div><strong>Error.</strong> Something went wrong — see details below.</div>
        </div>
        <div class="ab-alert ab-alert--info">
          <span class="icon-info-circle" aria-hidden="true"></span>
          <div><strong>Heads up.</strong> Multilingual support requires Joomla Multilanguage to be active.</div>
        </div>
      </div>
    </section>

    <!-- Toggle -->
    <section class="ab-card" style="margin-bottom:1.25rem">
      <div class="ab-card__header">Toggle</div>
      <div class="ab-card__body ab-stack">
        <label class="ab-toggle">
          <input type="checkbox" v-model="toggle1" />
          <span class="ab-toggle__track"></span>
          <span>Enable feature ({{ toggle1 ? 'on' : 'off' }})</span>
        </label>
        <label class="ab-toggle">
          <input type="checkbox" checked disabled />
          <span class="ab-toggle__track"></span>
          <span>Disabled (always on)</span>
        </label>
      </div>
    </section>

    <p class="ab-help" style="margin-top:1.5rem">
      Toggle dark / light using the button above (writes
      <code>data-bs-theme</code> on <code>&lt;html&gt;</code>) and confirm every
      component above reads correctly in both modes.
    </p>
  </div>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue'

export default {
  name: 'StyleguidePage',

  setup() {
    const activeTab = ref('a')
    const toggle1   = ref(true)
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

    return { activeTab, toggle1, isDark, toggleTheme }
  },
}
</script>
