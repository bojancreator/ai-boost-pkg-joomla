<template>
  <div class="ab-titles-meta-tab">

    <div class="ab-page-intro mb-3">
      <h2 class="ab-page-intro__title">Titles &amp; Meta</h2>
      <p class="ab-page-intro__text">
        Control how your <code>&lt;title&gt;</code> and meta description tags read in search
        results, per page type. Leave a field empty to keep the page's own value.
      </p>
    </div>

    <!-- Page Title Templates -->
    <div class="ab-card">
      <div class="ab-card-header">🏷 Page Title Templates</div>
      <div class="ab-card-body">
        <div class="ab-info-box mb-3">
          Rewrite the <code>&lt;title&gt;</code> per page type. Tokens:
          <code>{page_title}</code> <code>{site_name}</code> <code>{separator}</code>
          <code>{category}</code> <code>{year}</code>. Leave a field empty to keep Joomla's default.
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-8">
            <label class="ab-label">Title Separator</label>
            <input v-model="s.title_separator" data-ab-field="title_separator" type="text" class="ab-input" style="max-width:120px" placeholder=" | ">
          </div>
          <div class="col-md-4">
            <label class="ab-label">Max Length <span style="opacity:.5;font-weight:400;">(0 = no limit)</span></label>
            <input v-model="s.title_template_maxlen" data-ab-field="title_template_maxlen" type="number" min="0" max="120" class="ab-input" style="max-width:110px" placeholder="0">
          </div>
        </div>
        <div class="mb-3">
          <label class="ab-label">Homepage</label>
          <input v-model="s.title_template_home" data-ab-field="title_template_home" type="text" class="ab-input" placeholder="{site_name} {separator} Your tagline">
        </div>
        <div class="mb-3">
          <label class="ab-label">Article</label>
          <input v-model="s.title_template_article" data-ab-field="title_template_article" type="text" class="ab-input" placeholder="{page_title} {separator} {site_name}">
        </div>
        <div class="mb-3">
          <label class="ab-label">Category</label>
          <input v-model="s.title_template_category" data-ab-field="title_template_category" type="text" class="ab-input" placeholder="{page_title} {separator} {site_name}">
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="ab-label">Search</label>
            <input v-model="s.title_template_search" data-ab-field="title_template_search" type="text" class="ab-input" placeholder="Search {separator} {site_name}">
          </div>
          <div class="col-md-6">
            <label class="ab-label">Tag</label>
            <input v-model="s.title_template_tag" data-ab-field="title_template_tag" type="text" class="ab-input" placeholder="{page_title} {separator} {site_name}">
          </div>
        </div>
        <div class="row g-3 mb-0">
          <div class="col-md-6">
            <label class="ab-label">Default <span style="opacity:.5;font-weight:400;">(other pages)</span></label>
            <input v-model="s.title_template_default" data-ab-field="title_template_default" type="text" class="ab-input" placeholder="{page_title} {separator} {site_name}">
          </div>
          <div class="col-md-6">
            <label class="ab-label">Global Fallback <span style="opacity:.5;font-weight:400;">(legacy)</span></label>
            <input v-model="s.title_template" data-ab-field="title_template" type="text" class="ab-input" placeholder="Used when a per-type template is empty">
          </div>
        </div>
      </div>
    </div>

    <!-- Meta Description Templates -->
    <div class="ab-card">
      <div class="ab-card-header">📝 Meta Description Templates</div>
      <div class="ab-card-body">
        <div class="ab-info-box mb-3">
          Build the meta description per page type. Tokens:
          <code>{description}</code> <code>{site_name}</code> <code>{separator}</code> <code>{year}</code>.
          Leave empty to use the page's own description.
        </div>
        <div class="mb-3">
          <label class="ab-label">Max Length <span style="opacity:.5;font-weight:400;">(SEO guideline: 160)</span></label>
          <input v-model="s.meta_desc_maxlen" data-ab-field="meta_desc_maxlen" type="number" min="0" max="320" class="ab-input" style="max-width:110px" placeholder="160">
        </div>
        <div class="mb-3">
          <label class="ab-label">Article</label>
          <textarea v-model="s.meta_desc_template_article" data-ab-field="meta_desc_template_article" class="ab-input" rows="2" placeholder="{description}"></textarea>
        </div>
        <div class="mb-3">
          <label class="ab-label">Category</label>
          <textarea v-model="s.meta_desc_template_category" data-ab-field="meta_desc_template_category" class="ab-input" rows="2" placeholder="{description}"></textarea>
        </div>
        <div class="mb-3">
          <label class="ab-label">Default <span style="opacity:.5;font-weight:400;">(other pages)</span></label>
          <textarea v-model="s.meta_desc_template_default" data-ab-field="meta_desc_template_default" class="ab-input" rows="2" placeholder="{description}"></textarea>
        </div>
        <div class="mb-0">
          <label class="ab-label">Global Fallback <span style="opacity:.5;font-weight:400;">(legacy)</span></label>
          <textarea v-model="s.meta_desc_template" data-ab-field="meta_desc_template" class="ab-input" rows="2" placeholder="Used when a per-type template is empty"></textarea>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
export default {
  name: 'TitlesMetaTab',
  props: { s: { type: Object, required: true } },
}
</script>

<style scoped>
.ab-titles-meta-tab { max-width: 860px; }
.ab-info-box {
  font-size: .82rem;
  line-height: 1.6;
  color: var(--secondary-color, #6c757d);
  background: var(--secondary-bg, #f8f9fa);
  border: 1px solid var(--border-color, #dee2e6);
  border-radius: 6px;
  padding: 8px 11px;
}
</style>
