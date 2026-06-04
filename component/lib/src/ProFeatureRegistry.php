<?php
/**
 * AI Boost — Pro Feature Registry
 *
 * Single source of truth for every Pro-gated UI option in the admin SPA.
 *
 * Each entry describes one Pro feature surface that the SPA must lock when
 * the active license is Free. Entries have two shapes:
 *
 *   - field-level: gates a single settings key (e.g. `og_description_override`).
 *                  Vue `<ProGate gate-key="og_description_override">` wraps the
 *                  corresponding input.
 *
 *   - section-level: gates a whole card / section that has no single key, or
 *                    that groups several Pro keys behind one upsell.
 *                    Key format: `section:{tab}.{section_id}`.
 *
 * Why one PHP registry instead of inline `ab-badge-pro` spans:
 *   1. UI gating (Vue) reads from this list — no per-tab copy-paste.
 *   2. Save gating (SettingsController::save → stripLocked()) reads the same
 *      list, so Pro values can never be written by a Free install even if the
 *      UI is bypassed.
 *   3. Health check (`info_pro_gating_active`) verifies the list is non-empty
 *      and exposed to the SPA — regression smoke test.
 *
 * Convention (CONVENTIONS.md §7): every new Pro field or section MUST land
 * here in the same commit. PRs that add Pro UI without a registry entry are
 * rejected.
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
     * Static manifest of every Pro-gated UI surface.
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
            ['key' => 'section:schema.hours_advanced', 'tab' => 'schema','label' => 'Advanced day-by-day Opening Hours', 'lock_reason' => 'pro', 'scope' => 'section'],
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
            ['key' => 'section:social.pixel_events', 'tab' => 'social',  'label' => 'Meta Pixel Standard Events',        'lock_reason' => 'pro', 'scope' => 'section'],
            ['key' => 'section:social.pixel_custom_events', 'tab' => 'social', 'label' => 'Meta Pixel Custom Events',    'lock_reason' => 'pro', 'scope' => 'section'],

            // ── AEO tab ─────────────────────────────────────────────────
            ['key' => 'section:aeo.llms_full',      'tab' => 'aeo',      'label' => 'llms-full.txt full-site index',     'lock_reason' => 'pro', 'scope' => 'section'],
            // Task #463: AI Crawler Rules consolidated into a single Free
            // card in AeoTab.vue. `crawler_bot_rules`, `ai_crawlers_enabled`
            // and `crawler_rules` are all Free now — no Pro registry entry.
            ['key' => 'section:aeo.indexnow',       'tab' => 'aeo',      'label' => 'IndexNow instant indexing',         'lock_reason' => 'pro', 'scope' => 'section'],
            ['key' => 'section:aeo.markdown',       'tab' => 'aeo',      'label' => 'Markdown Pages for AI agents',      'lock_reason' => 'pro', 'scope' => 'section'],

            // ── Analytics tab — Task #473: only the "Enable GA4" toggle is Free.
            ['key' => 'ga4_measurement_id',         'tab' => 'analytics','label' => 'GA4 Measurement ID',                 'lock_reason' => 'pro', 'scope' => 'field'],
            ['key' => 'ga4_consent_mode',           'tab' => 'analytics','label' => 'GA4 GDPR Consent Mode',              'lock_reason' => 'pro', 'scope' => 'field'],
            ['key' => 'section:analytics.non_ga4',  'tab' => 'analytics','label' => 'Site Verification (GSC, Bing, Facebook)', 'lock_reason' => 'pro', 'scope' => 'section'],
            ['key' => 'section:analytics.gtm',      'tab' => 'analytics','label' => 'Google Tag Manager',                'lock_reason' => 'pro', 'scope' => 'section'],

            // ── Social — Task #473: Meta Pixel card is whole-section Pro ─
            ['key' => 'section:social.pixel',       'tab' => 'social',   'label' => 'Meta Pixel (Facebook Ads Tracking)','lock_reason' => 'pro', 'scope' => 'section'],

            // ── Custom Code tab — Task #473: whole tab Pro ──────────────
            ['key' => 'section:code',               'tab' => 'code',     'label' => 'Custom Code Injection',             'lock_reason' => 'pro', 'scope' => 'section'],

            // ── Debug tab — Task #473: whole tab Pro ────────────────────
            ['key' => 'section:debug',              'tab' => 'debug',    'label' => 'Debug & Diagnostics',               'lock_reason' => 'pro', 'scope' => 'section'],

            // ── Translations (cross-cutting) ────────────────────────────
            ['key' => 'section:translations.per_language', 'tab' => 'translations', 'label' => 'Per-language translations', 'lock_reason' => 'pro', 'scope' => 'section'],

            // ── v0.55.0 — page-level gates for whole Pro tabs. The SPA
            // routes are now reachable on Free; each page wraps its body
            // in <ProGate gate-key="page:*" mode="section"> so the
            // muted-preview + "Unlock Pro version" pill renders inline.
            ['key' => 'page:redirects',     'tab' => 'redirects',    'label' => 'Redirects',           'lock_reason' => 'pro', 'scope' => 'section'],
            ['key' => 'page:urlchecker',    'tab' => 'urlchecker',   'label' => 'URL Checker',         'lock_reason' => 'pro', 'scope' => 'section'],
            ['key' => 'page:integrations',  'tab' => 'integrations', 'label' => 'Integrations',        'lock_reason' => 'pro', 'scope' => 'section'],
            ['key' => 'page:analyzers',     'tab' => 'analyzers',    'label' => 'Analyzers',           'lock_reason' => 'pro', 'scope' => 'section'],
            ['key' => 'page:licenses',      'tab' => 'licenses',     'label' => 'Licenses',            'lock_reason' => 'pro', 'scope' => 'section'],
        ];
    }

    /**
     * Enum-gated fields: settings keys whose VALUE space is partitioned into a
     * Free subset and a Pro subset. The whole field stays editable on Free
     * (unlike `all()` entries which lock the entire field), but a Free admin
     * can only save values from the Free subset. Saving a Pro value gets
     * server-side rejected by `stripProOptions()` — same fail-closed pattern
     * as `stripLocked()`.
     *
     * Each entry: settings key → list of values that require Pro.
     * Free subset is whatever the SPA dropdown offers that isn't in this list.
     *
     * Why a separate map (not an `all()` entry):
     *  - `all()` entries gate ONE input via `<ProGate>` wrapping the entire
     *    control. The Schema Type dropdown is a single `<select>` whose
     *    OPTIONS need per-value gating — not the whole select. Wrapping the
     *    whole select in `<ProGate>` would lock Free users out of even the
     *    Free schema types, which is the opposite of what we want.
     *  - Keeping enum gating in its own map preserves the existing parity
     *    test (every `all()` key ↔ one `<ProGate>` wrapper) and lets the SPA
     *    render per-option lock indicators instead.
     *
     * Documented in CONVENTIONS.md §6a ("Enum-gated fields").
     *
     * @return array<string, array<int,string>>
     */
    public static function proOptions(): array
    {
        return [
            // Schema → Business / Organization Type → Schema Type dropdown.
            // Free set (implicit): Organization, LocalBusiness, FoodEstablishment,
            // EducationalOrganization.
            'schema_type' => [
                'LodgingBusiness',
                'MedicalClinic',
                'LegalService',
                'SportsActivityLocation',
                'Dentist',
                'RealEstateAgent',
                'Person',
                'NewsMediaOrganization',
            ],
        ];
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
     * Enforce enum gating on a save payload. For each enum-gated field:
     *   - if the new value is in the Pro set AND the install is Free,
     *     replace it with the existing row's value (if that value is Free),
     *     otherwise with the documented Free-tier default.
     *   - log the strip so a bypassed SPA is visible in the server log.
     *
     * Idempotent on Pro installs (returns payload unchanged).
     *
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $existing  previously-saved settings row
     */
    public static function stripProOptions(array $payload, array $existing, bool $isPro): array
    {
        if ($isPro) {
            return $payload;
        }

        $stripped = [];
        foreach (self::proOptions() as $field => $proValues) {
            $incoming = self::incomingProOption($payload, (string) $field, $proValues);
            if ($incoming === null) {
                continue;
            }

            $fallback = self::freeOptionFallback((string) $field, $existing, $proValues);
            $payload[$field] = $fallback;
            $stripped[] = $field . '=' . $incoming . '→' . $fallback;
        }

        self::logStrippedProOptions($stripped);
        return $payload;
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<int,string>   $proValues
     */
    private static function incomingProOption(array $payload, string $field, array $proValues): ?string
    {
        if (!array_key_exists($field, $payload)) {
            return null;
        }

        $incoming = (string) $payload[$field];
        return in_array($incoming, $proValues, true) ? $incoming : null;
    }

    /**
     * @param array<string,mixed> $existing
     * @param array<int,string>   $proValues
     */
    private static function freeOptionFallback(string $field, array $existing, array $proValues): string
    {
        $fallback = self::proOptionDefaults()[$field] ?? '';
        $previous = (string) ($existing[$field] ?? '');

        return $previous !== '' && !in_array($previous, $proValues, true)
            ? $previous
            : $fallback;
    }

    /** @param array<int,string> $stripped */
    private static function logStrippedProOptions(array $stripped): void
    {
        if ($stripped === []) {
            return;
        }

        error_log(
            '[AI Boost] ProFeatureRegistry::stripProOptions() rewrote Pro enum values on Free save: '
            . implode(', ', $stripped)
            . ' — the SPA dropdown should have disabled these options.'
        );
    }

    /**
     * Map of `section:*` registry keys → concrete underlying settings keys that
     * the section gates. Required because a `section:` registry entry has no
     * settings key of its own, so server-side `stripLocked()` would otherwise
     * miss every Pro field that lives inside a gated section. Add a row here
     * whenever a new `section:` entry is added to all().
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
            'section:schema.hours_advanced'       => self::advancedOpeningHoursFields(),
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
            'section:social.pixel'                => [
                'enable_meta_pixel', 'meta_pixel_id', 'meta_pixel_ids',
                'pixel_consent_mode',
            ],
            'section:social.pixel_events'         => ['meta_pixel_standard_events'],
            'section:social.pixel_custom_events'  => ['meta_custom_events'],
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
            'section:aeo.markdown'                => [
                'markdown_pages_enabled',
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

    /** @return array<int,string> */
    private static function advancedOpeningHoursFields(): array
    {
        $keys = ['schema_hours_temp_closed', 'schema_holiday_closed'];
        $dayAliases = [
            ['mon', 'mo', 'monday'],
            ['tue', 'tu', 'tuesday'],
            ['wed', 'we', 'wednesday'],
            ['thu', 'th', 'thursday'],
            ['fri', 'fr', 'friday'],
            ['sat', 'sa', 'saturday'],
            ['sun', 'su', 'sunday'],
        ];

        foreach ($dayAliases as $aliases) {
            foreach ($aliases as $day) {
                $keys[] = 'hours_' . $day . '_opens';
                $keys[] = 'hours_' . $day . '_closes';
                $keys[] = 'hours_' . $day . '_closed';
            }
        }

        return $keys;
    }

    /**
     * Flat list of settings keys (field entries + every key listed under a
     * section in sectionFields()) that must be stripped from a save payload
     * when the active license is Free.
     *
     * @return array<int, string>
     */
    public static function lockedSettingsKeys(): array
    {
        $out = [];
        foreach (self::all() as $entry) {
            array_push($out, ...self::lockedKeysForEntry($entry));
        }
        return array_values(array_unique($out));
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
     * Remove every Pro-gated key from $payload when the install is not Pro.
     * Idempotent on Pro installs (returns payload unchanged). Logs a warning
     * when something gets stripped so the SPA bug is visible in the log.
     *
     * @param array<string,mixed> $payload  settings being saved
     * @param bool                $isPro    license status
     * @return array<string,mixed>
     */
    public static function stripLocked(array $payload, bool $isPro): array
    {
        if ($isPro) {
            return $payload;
        }
        $stripped = [];
        foreach (self::lockedSettingsKeys() as $k) {
            if (array_key_exists($k, $payload)) {
                $stripped[] = $k;
                unset($payload[$k]);
            }
        }
        if (!empty($stripped)) {
            error_log(
                '[AI Boost] ProFeatureRegistry::stripLocked() removed Pro keys from Free save: '
                . implode(',', $stripped)
                . ' — the SPA should have prevented this; missing <ProGate> wrapper?'
            );
        }
        return $payload;
    }
}
