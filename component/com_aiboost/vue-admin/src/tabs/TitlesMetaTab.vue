<template>
  <div class="ab-settings-tab">

    <!-- 01 Page Title Templates -->
    <div class="ab-section">
      <div class="ab-section__head">
        <span class="ab-section__num">01</span>
        Page Title Templates
      </div>
      <div class="ab-section__body">
        <div class="ab-help">
          Rewrite the <code>&lt;title&gt;</code> per page type. Tokens:
          <code>{page_title}</code> <code>{site_name}</code> <code>{separator}</code>
          <code>{category}</code> <code>{year}</code>. Leave a field empty to keep Joomla's default.
        </div>
        <div class="ab-row" style="gap:1.5rem;flex-wrap:wrap;align-items:flex-start">
          <div class="ab-field">
            <label class="ab-label">Title Separator</label>
            <input v-model="s.title_separator" data-ab-field="title_separator" type="text" class="ab-input" style="max-width:120px" placeholder=" | ">
          </div>
          <div class="ab-field">
            <label class="ab-label">Max Length <span class="ab-muted">(0 = no limit)</span></label>
            <input v-model="s.title_template_maxlen" data-ab-field="title_template_maxlen" type="number" min="0" max="120" class="ab-input" style="max-width:110px" placeholder="0">
          </div>
        </div>
        <div class="ab-field">
          <label class="ab-label">Homepage</label>
          <input v-model="s.title_template_home" data-ab-field="title_template_home" type="text" class="ab-input" placeholder="{site_name} {separator} Your tagline">
        </div>
        <div class="ab-field">
          <label class="ab-label">Article</label>
          <input v-model="s.title_template_article" data-ab-field="title_template_article" type="text" class="ab-input" placeholder="{page_title} {separator} {site_name}">
        </div>
        <div class="ab-field">
          <label class="ab-label">Category</label>
          <input v-model="s.title_template_category" data-ab-field="title_template_category" type="text" class="ab-input" placeholder="{page_title} {separator} {site_name}">
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="ab-field">
              <label class="ab-label">Search</label>
              <input v-model="s.title_template_search" data-ab-field="title_template_search" type="text" class="ab-input" placeholder="Search {separator} {site_name}">
            </div>
          </div>
          <div class="col-md-6">
            <div class="ab-field">
              <label class="ab-label">Tag</label>
              <input v-model="s.title_template_tag" data-ab-field="title_template_tag" type="text" class="ab-input" placeholder="{page_title} {separator} {site_name}">
            </div>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="ab-field">
              <label class="ab-label">Default <span class="ab-muted">(other pages)</span></label>
              <input v-model="s.title_template_default" data-ab-field="title_template_default" type="text" class="ab-input" placeholder="{page_title} {separator} {site_name}">
            </div>
          </div>
          <div class="col-md-6">
            <div class="ab-field">
              <label class="ab-label">Global Fallback <span class="ab-muted">(legacy)</span></label>
              <input v-model="s.title_template" data-ab-field="title_template" type="text" class="ab-input" placeholder="Used when a per-type template is empty">
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 02 Meta Description Templates -->
    <div class="ab-section">
      <div class="ab-section__head">
        <span class="ab-section__num">02</span>
        Meta Description Templates
      </div>
      <div class="ab-section__body">
        <div class="ab-help">
          Build the meta description per page type. Tokens:
          <code>{description}</code> <code>{site_name}</code> <code>{separator}</code> <code>{year}</code>.
          Leave empty to use the page's own description.
        </div>
        <div class="ab-field">
          <label class="ab-label">Max Length <span class="ab-muted">(SEO guideline: 160)</span></label>
          <input v-model="s.meta_desc_maxlen" data-ab-field="meta_desc_maxlen" type="number" min="0" max="320" class="ab-input" style="max-width:110px" placeholder="160">
        </div>
        <div class="ab-field">
          <label class="ab-label">Article</label>
          <textarea v-model="s.meta_desc_template_article" data-ab-field="meta_desc_template_article" class="ab-input" rows="2" placeholder="{description}"></textarea>
        </div>
        <div class="ab-field">
          <label class="ab-label">Category</label>
          <textarea v-model="s.meta_desc_template_category" data-ab-field="meta_desc_template_category" class="ab-input" rows="2" placeholder="{description}"></textarea>
        </div>
        <div class="ab-field">
          <label class="ab-label">Default <span class="ab-muted">(other pages)</span></label>
          <textarea v-model="s.meta_desc_template_default" data-ab-field="meta_desc_template_default" class="ab-input" rows="2" placeholder="{description}"></textarea>
        </div>
        <div class="ab-field">
          <label class="ab-label">Global Fallback <span class="ab-muted">(legacy)</span></label>
          <textarea v-model="s.meta_desc_template" data-ab-field="meta_desc_template" class="ab-input" rows="2" placeholder="Used when a per-type template is empty"></textarea>
        </div>
      </div>
    </div>

    <!-- 03 Canonical URLs -->
    <div class="ab-section">
      <div class="ab-section__head">
        <span class="ab-section__num">03</span>
        Canonical URLs
      </div>
      <div class="ab-section__body">
        <label class="ab-toggle-row">
          <div>
            <div class="ab-label">Enable canonical URL management</div>
          </div>
          <span class="ab-toggle" :class="{'is-on': s.enable_canonical === '1'}">
            <input v-model="s.enable_canonical" data-ab-field="enable_canonical" true-value="1" false-value="0"
              type="checkbox" class="ab-toggle__input">
            <span class="ab-toggle__track"></span>
          </span>
        </label>
        <div class="ab-field">
          <label class="ab-label">Canonical URL Map <span class="ab-muted">(one override per line)</span></label>
          <textarea v-model="s.canonical_url_map" data-ab-field="canonical_url_map" class="ab-input font-monospace" rows="5"
            placeholder="/old-path -> /new-path&#10;/shop -> /products"></textarea>
          <div class="ab-help">Format: <code>/source-path -> /canonical-path</code> or full URLs.</div>
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
