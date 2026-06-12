<template>
  <div class="ab-scope-block mb-2">
    <label class="ab-label fw-semibold text-secondary small mb-1">
      Apply {{ field }} code to
    </label>
    <div class="d-flex gap-4 mb-2">
      <div class="ab-check">
        <input class="ab-toggle__input" type="radio"
          :id="labelId + '-all'" :name="labelId"
          value="all"
          :checked="(s[scopeKey] || 'all') === 'all'"
          @change="onScopeChange('all')">
        <label class="ab-check__label" :for="labelId + '-all'"><strong>All pages</strong></label>
      </div>
      <div class="ab-check">
        <input class="ab-toggle__input" type="radio"
          :id="labelId + '-specific'" :name="labelId"
          value="specific"
          :checked="s[scopeKey] === 'specific'"
          @change="onScopeChange('specific')">
        <label class="ab-check__label" :for="labelId + '-specific'"><strong>Specific menu items only</strong></label>
      </div>
    </div>
    <div v-if="s[scopeKey] === 'specific'" class="mb-1">
      <div v-if="menuGroups.length === 0" class="text-muted small">No menu items found.</div>
      <select v-else multiple class="ab-select ab-menu-select"
        @change="onSelectChange">
        <optgroup v-for="group in menuGroups" :key="group.type" :label="group.type">
          <option v-for="item in group.items" :key="item.id" :value="item.id"
            :selected="selectedIds.includes(item.id)"
            :style="'padding-left:' + (Math.max(0, item.level - 1) * 14) + 'px'">
            {{ '\u00a0'.repeat(Math.max(0, item.level - 1) * 2) }}{{ item.title }}
          </option>
        </optgroup>
      </select>
      <div class="ab-help mt-1">
        Hold <kbd>Ctrl</kbd> (Windows) or <kbd>⌘ Cmd</kbd> (Mac) to select multiple items.
        <strong>{{ count }}</strong> item{{ count !== 1 ? 's' : '' }} selected.
      </div>
    </div>
  </div>
</template>

<script>
/**
 * ScopeSelector — the "Apply <head|body|footer> code to" control used three
 * times in the Custom Code tab. Radio pair (all pages / specific menu items)
 * plus a grouped menu multi-select. Writes custom_code_<field>_scope straight
 * onto the shared settings object and mirrors the selection into
 * custom_code_<field>_menu_ids (JSON-encoded array of menu item IDs).
 *
 * Must stay a compiled SFC: the shipped bundle uses the runtime-only Vue
 * build, so an inline `template:` string component would silently render
 * nothing in production.
 */
export default {
  name: 'ScopeSelector',
  props: {
    field: { type: String, required: true },
    s: { type: Object, required: true },
    menuGroups: { type: Array, required: true },
    selectedIds: { type: Array, required: true },
  },
  emits: ['update:selectedIds'],
  computed: {
    scopeKey()   { return `custom_code_${this.field}_scope` },
    menuIdsKey() { return `custom_code_${this.field}_menu_ids` },
    labelId()    { return `cc-${this.field}-scope` },
    count()      { return this.selectedIds.length },
  },
  methods: {
    onScopeChange(val) {
      this.s[this.scopeKey] = val
    },
    onSelectChange(e) {
      const selected = Array.from(e.target.selectedOptions).map(o => Number(o.value))
      this.$emit('update:selectedIds', selected)
      this.s[this.menuIdsKey] = JSON.stringify(selected)
    },
  },
}
</script>

<style scoped>
.ab-scope-block {
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 6px;
  padding: 12px 14px;
}
.ab-menu-select {
  min-height: 160px;
  max-height: 280px;
  font-size: .875rem;
}
.ab-menu-select option { padding: 3px 6px; }
.ab-menu-select option:checked { background: #0d6efd; color: #fff; }
</style>
