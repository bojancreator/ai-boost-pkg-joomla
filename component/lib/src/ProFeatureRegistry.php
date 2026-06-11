<?php
/**
 * AI Boost — Legacy Feature Registry
 *
 * Legacy compatibility registry for surfaces that used to be Pro-gated.
 *
 * Each entry describes one historical feature surface from the old Free/Pro
 * model. Entries have two shapes:
 *
 *   - field-level: records a single settings key (e.g. `og_description_override`).
 *
 *   - section-level: gates a whole card / section that has no single key, or
 *                    that groups several Pro keys behind one upsell.
 *                    Key format: `section:{tab}.{section_id}`.
 *
 * v0.5 one-product transition: this class stays available so old callers,
 * tests, migrations and generated artifacts do not fatally break, but save
 * enforcement no longer removes settings because of Free/Pro state.
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or die;

final class ProFeatureRegistry
{
    /**
    * Static manifest of historical tier-gated UI surfaces.
     * Entry shape:
     *   key          string  settings key OR 'section:{tab}.{id}' for whole sections
     *   tab          string  tab id (schema|sitemap|social|aeo|analytics|translations|...)
     *   label        string  short label used in upsell modal
     *   lock_reason  string  'pro' | 'integration:{name}'
     *   scope        string  'field' | 'section'
     *
     * @return array<int, array<string,string>>
     */
    public static function all(): array
    {
        return [
            // ── Schema tab ──────────────────────────────────────────────
            ['key' => 'enable_manual_faqs',         'tab' => 'schema',   'label' => 'Manual FAQs',                       'lock_reason' => 'pro', 'scope' => 'field'],
            ['key' => 'faq_items',                  'tab' => 'schema',   'label' => 'Manual FAQ Items',                  'lock_reason' => 'pro', 'scope' => 'field'],
            ['key' => 'schema_faq_output_type',     'tab' => 'schema',   'label' => 'FAQ Schema Output Type',            'lock_reason' => 'pro', 'scope' => 'field'],
            ['key' => 'section:schema.faq',         'tab' => 'schema',   'label' => 'FAQ / QAPage Schema',               'lock_reason' => 'pro', 'scope' => 'section'],
            // Advanced day-by-day opening hours are FREE (emitted by the free
            // SchemaBuilder; UI not locked) — do not advertise them as Pro.
            ['key' => 'section:schema.author_entity', 'tab' => 'schema', 'label' => 'Author Entity (Person schema)',     'lock_reason' => 'pro', 'scope' => 'section'],
            ['key' => 'section:schema.howto',       'tab' => 'schema',   'label' => 'HowTo Schema',                      'lock_reason' => 'pro', 'scope' => 'section'],
            ['key' => 'section:schema.event',       'tab' => 'schema',   'label' => 'Event Schema',                      'lock_reason' => 'pro', 'scope' => 'section'],
            ['key' => 'section:schema.business_details', 'tab' => 'schema', 'label' => 'Pro business type details',       'lock_reason' => 'pro', 'scope' => 'section'],
            ['key' => 'schema_breadcrumb_pro',      'tab' => 'schema',   'label' => 'Enhanced BreadcrumbList',            'lock_reason' => 'pro', 'scope' => 'field'],

            // ── Sitemap tab ─────────────────────────────────────────────
            ['key' => 'include_tags',               'tab' => 'sitemap',  'label' => 'Tag URLs in sitemap',               'lock_reason' => 'pro', 'scope' => 'field'],
            ['key' => 'section:sitemap.priority_pertype', 'tab' => 'sitemap', 'label' => 'Per-type sitemap priority',    'lock_reason' => 'pro', 'scope' => 'section'],
            ['key' => 'section:sitemap.advanced',   'tab' => 'sitemap',  'label' => 'Sitemap advanced (index, image, hreflang)', 'lock_reason' => 'pro', 'scope' => 'section'],
            ['key' => 'section:sitemap.news',       'tab' => 'sitemap',  'label' => 'Google News Sitemap',               'lock_reason' => 'pro', 'scope' => 'section'],
            ['key' => 'ping_on_publish',            'tab' => 'sitemap',  'label' => 'Ping search engines on publish',    'lock_reason' => 'pro', 'scope' => 'field'],
            ['key' => 'hreflang_sitemap',           'tab' => 'sitemap',  'label' => 'hreflang alternates in sitemap',     'lock_reason' => 'pro', 'scope' => 'field'],

            // ── Social tab ──────────────────────────────────────────────
            // Task #473 — og_description_override is Free; kept in the
            // sectionFields() map below under section:social.locale so it is
            // NOT stripped on Free saves.
            ['key' => 'enable_og_locale',           'tab' => 'social',   'label' => 'og:locale tag',                     'lock_reason' => 'pro', 'scope' => 'field'],
            ['key' => 'hreflang_enabled',           'tab' => 'social',   'label' => 'hreflang alternate links',           'lock_reason' => 'pro', 'scope' => 'field'],
            ['key' => 'hreflang_primary_language',  'tab' => 'social',   'label' => 'Primary hreflang language',          'lock_reason' => 'pro', 'scope' => 'field'],
            ['key' => 'section:analytics.pixel_events', 'tab' => 'analytics', 'label' => 'Meta Pixel Standard Events',   'lock_reason' => 'pro', 'scope' => 'section'],
            ['key' => 'section:analytics.pixel_custom_events', 'tab' => 'analytics', 'label' => 'Meta Pixel Custom Events', 'lock_reason' => 'pro', 'scope' => 'section'],

            // ── AEO tab ─────────────────────────────────────────────────
            ['key' => 'section:aeo.llms_full',      'tab' => 'aeo',      'label' => 'llms-full.txt full-site index',     'lock_reason' => 'pro', 'scope' => 'section'],
            // Task #463: AI Crawler Rules consolidated into a single Free
            // card in CrawlersRobotsTab.vue. `crawler_bot_rules`, `ai_crawlers_enabled`
            // and `crawler_rules` are all Free now — no Pro registry entry.
            ['key' => 'section:aeo.indexnow',       'tab' => 'aeo',      'label' => 'IndexNow instant indexing',         'lock_reason' => 'pro', 'scope' => 'section'],
            // Markdown Pages + AI Signals are FREE (emitted by the free aiboost_aeo
            // plugin, no licence gate) — do not advertise Markdown as Pro.

            // ── Analytics tab — Task #473: only the "Enable GA4" toggle is Free.
            ['key' => 'ga4_measurement_id',         'tab' => 'analytics','label' => 'GA4 Measurement ID',                 'lock_reason' => 'pro', 'scope' => 'field'],
            ['key' => 'ga4_consent_mode',           'tab' => 'analytics','label' => 'GA4 GDPR Consent Mode',              'lock_reason' => 'pro', 'scope' => 'field'],
            ['key' => 'section:analytics.non_ga4',  'tab' => 'analytics','label' => 'Site Verification (GSC, Bing, Facebook)', 'lock_reason' => 'pro', 'scope' => 'section'],
            ['key' => 'section:analytics.gtm',      'tab' => 'analytics','label' => 'Google Tag Manager',                'lock_reason' => 'pro', 'scope' => 'section'],

            // ── Analytics — Meta Pixel lives with tracking setup ─────────
            ['key' => 'section:analytics.pixel',    'tab' => 'analytics','label' => 'Meta Pixel',                        'lock_reason' => 'pro', 'scope' => 'section'],

            // ── Custom Code tab — Task #473: whole tab Pro ──────────────
            ['key' => 'section:code',               'tab' => 'code',     'label' => 'Custom Code Injection',             'lock_reason' => 'pro', 'scope' => 'section'],

            // ── Debug tab — Task #473: whole tab Pro ────────────────────
            ['key' => 'section:debug',              'tab' => 'debug',    'label' => 'Debug & Diagnostics',               'lock_reason' => 'pro', 'scope' => 'section'],

            // ── Translations (cross-cutting) ────────────────────────────
            ['key' => 'section:translations.per_language', 'tab' => 'translations', 'label' => 'Per-language translations', 'lock_reason' => 'pro', 'scope' => 'section'],

            // ── v0.55.0 — historical page-level entries.
            // Historical page-level entries kept for compatibility while
            // the SPA routes remain reachable in the one-product admin.
            ['key' => 'page:redirects',     'tab' => 'redirects',    'label' => 'Redirects',           'lock_reason' => 'pro', 'scope' => 'section'],
            ['key' => 'page:urlchecker',    'tab' => 'urlchecker',   'label' => 'URL Checker',         'lock_reason' => 'pro', 'scope' => 'section'],
            ['key' => 'page:integrations',  'tab' => 'integrations', 'label' => 'Integrations',        'lock_reason' => 'pro', 'scope' => 'section'],
            ['key' => 'page:analyzers',     'tab' => 'analyzers',    'label' => 'Analyzers',           'lock_reason' => 'pro', 'scope' => 'section'],
            ['key' => 'page:licenses',      'tab' => 'licenses',     'label' => 'Licenses',            'lock_reason' => 'pro', 'scope' => 'section'],
        ];
    }

    /**
        * Historical enum-gated fields. The one-product model keeps every option
        * saveable; the method remains for compatibility with old diagnostics.
     *
    * No values are returned while the one-product model is active.
     *
     * @return array<string, array<int,string>>
     */
    public static function proOptions(): array
    {
        return [];
    }

    /**
     * Safe Free-tier fallback value for each enum-gated field. Used by
     * `stripProOptions()` when an incoming Free save carries a Pro value AND
     * the existing row has no usable Free value to fall back to.
     *
     * @return array<string, string>
     */
    public static function proOptionDefaults(): array
    {
        return [
            'schema_type' => 'Organization',
        ];
    }

    /**
    * Compatibility no-op for the retired Free/Pro enum gate.
     *
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $existing  previously-saved settings row
     */
    public static function stripProOptions(array $payload, array $existing, bool $isPro): array
    {
        return $payload;
    }

    /**
     * Map of `section:*` registry keys → concrete underlying settings keys that
     * the section historically gated. Kept for compatibility and future
     * cleanup diffs; save enforcement no longer consumes this map.
     *
     * @return array<string, array<int,string>>
     */
    public static function sectionFields(): array
    {
        return self::schemaSectionFields()
            + self::sitemapSectionFields()
            + self::socialSectionFields()
            + self::analyticsSectionFields()
            + self::codeSectionFields()
            + self::aeoSectionFields()
            + self::translationSectionFields();
    }

    /** @return array<string, array<int,string>> */
    private static function schemaSectionFields(): array
    {
        return [
            'section:schema.faq'                  => [
                'faq_auto_detect', 'enable_manual_faqs', 'faq_items',
                'schema_faq_output_type', 'manual_faq_scope',
            ],
            'section:schema.author_entity'        => ['schema_author_entity_enabled'],
            'section:schema.howto'                => ['schema_howto', 'schema_howto_enabled'],
            'section:schema.event'                => [
                'events_enabled', 'events_category_id', 'schema_event_article_ids',
                'schema_events_enabled', 'schema_events_en',
            ],
            'section:schema.business_details'     => [
                'specific_star_rating', 'specific_checkin_time', 'specific_checkout_time',
                'specific_pets_allowed', 'specific_area_served',
            ],
        ];
    }

    /** @return array<string, array<int,string>> */
    private static function sitemapSectionFields(): array
    {
        return [
            'section:sitemap.priority_pertype'    => [
                'priority_homepage', 'priority_articles', 'priority_categories', 'priority_tags',
                'sitemap_priority_articles', 'sitemap_priority_categories', 'sitemap_priority_menu',
            ],
            'section:sitemap.advanced'            => [
                'enable_sitemap_index', 'enable_image_sitemap', 'enable_hreflang',
            ],
            'section:sitemap.news'                => [
                'enable_news_sitemap', 'news_category_id', 'news_publication_name',
            ],
        ];
    }

    /** @return array<string, array<int,string>> */
    private static function socialSectionFields(): array
    {
        return [
        ];
    }

    /** @return array<string, array<int,string>> */
    private static function analyticsSectionFields(): array
    {
        return [
            'section:analytics.non_ga4'           => [
                'enable_google_verification', 'gsc_verification_code', 'gsc_codes',
                'gsc_additional_html', 'fb_domain_verification',
            ],
            'section:analytics.gtm'               => [
                'enable_gtm', 'gtm_container_id',
            ],
            'section:analytics.pixel'             => [
                'enable_meta_pixel', 'meta_pixel_id', 'meta_pixel_ids',
                'pixel_consent_mode',
            ],
            'section:analytics.pixel_events'      => ['meta_pixel_standard_events'],
            'section:analytics.pixel_custom_events' => ['meta_custom_events'],
        ];
    }

    /** @return array<string, array<int,string>> */
    private static function codeSectionFields(): array
    {
        return [
            'section:code'                        => [
                'enable_custom_code',
                'custom_code_head', 'custom_code_head_scope', 'custom_code_head_menu_ids',
                'custom_code_body', 'custom_code_body_scope', 'custom_code_body_menu_ids',
                'custom_code_footer', 'custom_code_footer_scope', 'custom_code_footer_menu_ids',
                'custom_code_scope', 'custom_code_menu_ids',
            ],
            'section:debug'                       => [
                'debug_mode', 'hide_comments', 'staging_mode',
            ],
        ];
    }

    /** @return array<string, array<int,string>> */
    private static function aeoSectionFields(): array
    {
        return [
            'section:aeo.llms_full'               => [
                'llms_full_txt_enabled', 'llms_full_max_articles',
            ],
            'section:aeo.indexnow'                => [
                'indexnow_enabled', 'indexnow_api_key', 'indexnow_auto_submit',
            ],
        ];
    }

    /** @return array<string, array<int,string>> */
    private static function translationSectionFields(): array
    {
        return [
            'section:translations.per_language'   => [],
        ];
    }

    /**
    * Flat list of keys that used to be stripped from a save payload.
    * Empty in the one-product model; retained for old callers.
     *
     * @return array<int, string>
     */
    public static function lockedSettingsKeys(): array
    {
        return [];
    }

    /**
     * @param array<string,string> $entry
     * @return array<int,string>
     */
    private static function lockedKeysForEntry(array $entry): array
    {
        $key = (string) ($entry['key'] ?? '');
        if ($key === '') {
            return [];
        }

        return strpos($key, 'section:') === 0
            ? (self::sectionFields()[$key] ?? [])
            : [$key];
    }

    /**
    * Compatibility no-op for the retired Free/Pro save gate.
     *
     * @param array<string,mixed> $payload  settings being saved
     * @param bool                $isPro    license status
     * @return array<string,mixed>
     */
    public static function stripLocked(array $payload, bool $isPro): array
    {
        return $payload;
    }
}
