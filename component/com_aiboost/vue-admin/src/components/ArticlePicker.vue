<template>
  <div class="ab-article-picker">
    <!-- Selected articles (chips) -->
    <div v-if="selected.length" class="ab-ap-selected">
      <div v-for="(a, idx) in selected" :key="a.id" class="ab-ap-chip">
        <span class="ab-ap-chip__idx">#{{ idx }}</span>
        <span class="ab-ap-chip__title">{{ a.title || ('Article ' + a.id) }}</span>
        <span class="ab-ap-chip__id">id {{ a.id }}</span>
        <button type="button" class="ab-ap-chip__x" title="Remove" @click="remove(a.id)">✕</button>
      </div>
    </div>
    <p v-else class="ab-help mb-1">No event articles selected yet — search below to add them.</p>

    <!-- Search box -->
    <div class="ab-ap-search">
      <input
        type="text" class="ab-input form-control-sm"
        v-model="query"
        placeholder="Search published articles by title…"
        @input="onInput"
        @focus="onInput"
        @blur="onBlur">
      <span v-if="loading" class="ab-ap-loading" aria-hidden="true">⏳</span>
    </div>

    <!-- Results dropdown -->
    <ul v-if="open && results.length" class="ab-ap-results">
      <li
        v-for="r in results" :key="r.id"
        :class="{ 'is-selected': isSelected(r.id) }"
        @mousedown.prevent="add(r)">
        <span class="ab-ap-results__title">{{ r.title }}</span>
        <span class="ab-ap-results__meta">{{ r.category ? r.category + ' · ' : '' }}id {{ r.id }}</span>
      </li>
    </ul>
    <p v-else-if="open && query && !loading" class="ab-help mt-1">No matching published articles.</p>
  </div>
</template>

<script>
const SEARCH_URL = 'index.php?option=com_aiboost&task=settings.searchArticles&format=json'

export default {
  name: 'ArticlePicker',
  props: {
    // Comma-separated Joomla article IDs (the stored schema_event_article_ids).
    modelValue: { type: String, default: '' },
  },
  emits: ['update:modelValue'],
  data() {
    return {
      selected: [],     // [{ id, title }]
      query:    '',
      results:  [],     // [{ id, title, category }]
      open:     false,
      loading:  false,
    }
  },
  watch: {
    modelValue(v) {
      // Re-sync only when an EXTERNAL change brings a different id-set than ours.
      if (this.idsString() !== this.normalizeIds(v).join(',')) {
        this.resolveSelected()
      }
    },
  },
  mounted() {
    this.resolveSelected()
  },
  beforeUnmount() {
    if (this._t) clearTimeout(this._t)
    if (this._blurT) clearTimeout(this._blurT)
  },
  methods: {
    normalizeIds(v) {
      return (v || '').toString().split(',')
        .map(x => parseInt(x.trim(), 10))
        .filter(n => n > 0)
    },
    idsString() {
      return this.selected.map(a => a.id).join(',')
    },
    isSelected(id) {
      return this.selected.some(a => a.id === id)
    },

    // Resolve the current modelValue IDs to {id,title} (preserving order).
    async resolveSelected() {
      const ids = this.normalizeIds(this.modelValue)
      if (!ids.length) { this.selected = []; return }
      try {
        const data = await this.fetchArticles({ ids: ids.join(',') })
        const byId = {}
        for (const a of data) byId[a.id] = a
        // Keep the user's order; fall back to a bare id when an article is gone.
        this.selected = ids.map(id => byId[id] || { id, title: '' })
      } catch (_e) {
        this.selected = ids.map(id => ({ id, title: '' }))
      }
    },

    onInput() {
      this.open = true
      if (this._t) clearTimeout(this._t)
      this._t = setTimeout(() => this.runSearch(), 250)
    },
    onBlur() {
      // Delay so a result @mousedown registers before the list closes.
      this._blurT = setTimeout(() => { this.open = false }, 150)
    },

    async runSearch() {
      this.loading = true
      try {
        this.results = await this.fetchArticles({ q: this.query.trim() })
      } catch (_e) {
        this.results = []
      } finally {
        this.loading = false
      }
    },

    async fetchArticles(params) {
      const usp = new URLSearchParams(params)
      const res = await fetch(SEARCH_URL + '&' + usp.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      })
      const data = await res.json()
      return (data && data.articles) || []
    },

    add(r) {
      if (this.isSelected(r.id)) return
      this.selected.push({ id: r.id, title: r.title })
      this.query = ''
      this.results = []
      this.open = false
      this.emit()
    },
    remove(id) {
      this.selected = this.selected.filter(a => a.id !== id)
      this.emit()
    },
    emit() {
      this.$emit('update:modelValue', this.idsString())
    },
  },
}
</script>

<style scoped>
.ab-article-picker { max-width: 560px; }

.ab-ap-selected { display: flex; flex-direction: column; gap: 4px; margin-bottom: 6px; }
.ab-ap-chip {
  display: flex; align-items: center; gap: 8px;
  padding: 4px 8px; border-radius: 6px;
  background: var(--ab-bg-muted, #eef0f3);
  border: 1px solid var(--ab-border, #e2e6ec);
  color: var(--ab-text, #1f2937);
  font-size: .85rem;
}
.ab-ap-chip__idx { font-weight: 700; color: var(--ab-text-muted, #6b7280); flex-shrink: 0; }
.ab-ap-chip__title { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ab-ap-chip__id { color: var(--ab-text-muted, #6b7280); font-size: .78rem; flex-shrink: 0; }
.ab-ap-chip__x {
  border: 0; background: transparent; cursor: pointer; color: var(--ab-text-muted, #6b7280);
  padding: 0 2px; flex-shrink: 0;
}
.ab-ap-chip__x:hover { color: var(--ab-danger, #dc2626); }

.ab-ap-search { position: relative; }
.ab-ap-loading { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); }

/* Rendered IN NORMAL FLOW (not absolute): the parent .ab-card has
   overflow:hidden, so an absolutely-positioned dropdown would be clipped at the
   card edge. In flow the card grows to fit and the list is never clipped, in any
   theme. Tokens are the real theme-aware --ab-* names (an earlier draft used
   non-existent --ab-surface*, which fell back to white in dark mode). */
.ab-ap-results {
  list-style: none; margin: 4px 0 0; padding: 4px;
  max-height: 240px; overflow-y: auto;
  background: var(--ab-bg-elev, #fff);
  color: var(--ab-text, #1f2937);
  border: 1px solid var(--ab-border, #e2e6ec); border-radius: 6px;
  box-shadow: var(--ab-shadow, 0 2px 6px rgba(0,0,0,.08));
}
.ab-ap-results li {
  display: flex; flex-direction: column; gap: 1px;
  padding: 6px 8px; border-radius: 4px; cursor: pointer;
}
.ab-ap-results li:hover { background: var(--ab-bg-muted, #eef0f3); }
.ab-ap-results li.is-selected { opacity: .5; }
.ab-ap-results__title { font-size: .85rem; }
.ab-ap-results__meta { font-size: .75rem; color: var(--ab-text-muted, #6b7280); }
</style>
