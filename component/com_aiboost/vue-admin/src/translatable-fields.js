/**
 * AI Boost — Authoritative TRANSLATABLE_FIELDS mapping
 *
 * Single source of truth for all field keys that support per-language
 * translations stored in #__aiboost_translations.
 *
 * Rules:
 *   - field_key must match the settings column name (v-model key in Vue) AND
 *     the key used in TranslationService::get() calls in PHP service classes.
 *   - Dynamic FAQ keys follow the pattern faq_{idx}_q / faq_{idx}_a
 *     (resolved at runtime by SchemaBuilder).
 *   - Pro-only fields are marked with pro: true.
 *
 * Consumers:
 *   - TranslationExpander.vue (reads TRANSLATABLE_FIELDS for UI hints)
 *   - SettingsController.php saveTranslations() accepts any key listed here
 *   - SchemaBuilder.php, OgTagBuilder.php, LlmsTxtGenerator.php (runtime)
 */

export const TRANSLATABLE_FIELDS = [
  // ── Organisation (OrgTab) ──────────────────────────────────────────────────
  { key: 'org_name',            label: 'Organisation Name',         tab: 'org',     pro: true },
  { key: 'org_description',     label: 'Organisation Description',  tab: 'org',     pro: true },
  { key: 'org_logo',            label: 'Organisation Logo URL',     tab: 'org',     pro: true },
  { key: 'org_address_street',  label: 'Address — Street',         tab: 'org',     pro: true },
  { key: 'org_address_city',    label: 'Address — City',           tab: 'org',     pro: true },
  { key: 'org_logo_alt',        label: 'Organisation Logo Alt Text', tab: 'org',   pro: true },

  // ── Social / OpenGraph (SocialTab) ────────────────────────────────────────
  { key: 'site_name',                label: 'OG Site Name',                      tab: 'social',  pro: true },
  { key: 'default_og_image',         label: 'Default OG Image URL',              tab: 'social',  pro: true },
  { key: 'default_og_image_alt',     label: 'Default OG Image Alt Text',         tab: 'social',  pro: true },
  { key: 'og_description_override',  label: 'Default OG Description Override',   tab: 'social',  pro: true },

  // ── Sitemap (SitemapTab) ──────────────────────────────────────────────────
  { key: 'news_publication_name', label: 'News Publication Name',   tab: 'sitemap', pro: true },

  // ── AEO / llms.txt (AeoTab) ───────────────────────────────────────────────
  { key: 'llmstxt_description', label: 'Site Description for AI',   tab: 'aeo',     pro: true },

  // ── Schema.org — FAQ items (SchemaTab, dynamic) ───────────────────────────
  // Keys generated at runtime: faq_0_q, faq_0_a, faq_1_q, faq_1_a, …
  // SchemaTab renders one TranslationExpander pair per parsed faq_items entry.
  // LlmsTxtGenerator also resolves these keys for /llms.txt FAQ section.
  { key: 'faq_0_q',            label: 'FAQ #1 Question',            tab: 'schema',  pro: true, dynamic: true },
  { key: 'faq_0_a',            label: 'FAQ #1 Answer',              tab: 'schema',  pro: true, dynamic: true },

  // ── Schema.org — Event descriptions (SchemaTab, dynamic) ─────────────────
  // Key pattern: event_{article_id}_desc — where article_id is the Joomla
  // article ID of the event article (entered by admin in schema_event_article_ids).
  // SchemaBuilder::buildEvent() reads this key using $this->id (article_id).
  { key: 'event_0_desc',       label: 'Event Description (article #…)', tab: 'schema', pro: true, dynamic: true },
]

export default TRANSLATABLE_FIELDS
