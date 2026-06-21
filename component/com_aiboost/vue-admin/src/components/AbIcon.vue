<!--
  AI Boost — line-icon renderer ("Instrument" set).

  Usage:  <AbIcon name="dash" />  ·  <AbIcon name="ok" />
  Colour comes from `currentColor` (set `color` on the parent), so icons read
  correctly in both light and dark themes. Registered globally in main.js.
-->
<template>
  <svg
    class="ab-icon"
    :class="'ab-icon--' + name"
    :viewBox="vb"
    fill="none"
    stroke="currentColor"
    :stroke-width="sw"
    aria-hidden="true"
    focusable="false"
    v-html="inner"
  />
</template>

<script>
import { AB_ICONS } from '../icons.js'

export default {
  name: 'AbIcon',
  props: {
    name: { type: String, required: true },
  },
  computed: {
    entry() {
      return AB_ICONS[this.name] || ''
    },
    inner() {
      const e = this.entry
      return typeof e === 'string' ? e : (e && e.p) || ''
    },
    vb() {
      const e = this.entry
      return e && e.vb ? e.vb : '0 0 16 16'
    },
    sw() {
      const e = this.entry
      return e && e.sw ? e.sw : 1.4
    },
  },
}
</script>

<style>
.ab-icon {
  display: inline-block;
  width: 1em;
  height: 1em;
  vertical-align: -0.125em;
  flex: none;
}
</style>
