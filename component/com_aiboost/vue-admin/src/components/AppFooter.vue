<template>
  <footer class="ab-footer">
    <span class="ab-footer__brand">AI Boost</span>
    <span v-if="version" class="ab-footer__version">{{ version }}</span>
    <span class="ab-footer__spacer"></span>
    <a class="ab-footer__link" :href="docsUrl" target="_blank" rel="noopener">Docs</a>
    <a class="ab-footer__link" :href="supportUrl" target="_blank" rel="noopener">Support</a>
  </footer>
</template>

<script>
/** Thin, muted global footer shown at the bottom of every SPA page. */
export default {
  name: 'AppFooter',
  setup() {
    const boot = window.aiBoostBootstrap || {}
    const version = boot.version ? ('v' + String(boot.version).replace(/^v/, '')) : ''
    return {
      version,
      docsUrl: 'https://aiboostnow.com/docs',
      supportUrl: 'https://aiboostnow.com/account',
    }
  },
}
</script>

<style>
.ab-footer {
  display: flex;
  align-items: center;
  gap: var(--ab-space-3);
  margin-top: var(--ab-space-5);
  padding: var(--ab-space-3) var(--ab-space-4);
  border-top: 1px solid var(--ab-border);
  font-size: var(--ab-font-size-xs);
  color: var(--ab-text-muted);
}
.ab-footer__brand { font-weight: var(--ab-font-weight-semibold); }
.ab-footer__version { font-family: var(--ab-font-mono); }
.ab-footer__spacer { flex: 1; }
.ab-footer__link { color: var(--ab-text-muted); text-decoration: none; }
.ab-footer__link:hover { color: var(--ab-primary); text-decoration: none; }
</style>
