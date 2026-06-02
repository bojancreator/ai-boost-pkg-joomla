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

            // ── Sitemap tab ─────────────────────────────────────────────
            ['key' => 'include_tags',               'tab' => 'sitemap',  'label' => 'Tag URLs in sitemap',               'lock_reason' => 'pro', 'scope' => 'field'],
            ['key' => 'section:sitemap.priority_pertype', 'tab' => 'sitemap', 'label' => 'Per-type sitemap priority',    'lock_reason' => 'pro', 'scope' => 'section'],
            ['key' => 'section:sitemap.advanced',   'tab' => 'sitemap',  'label' => 'Sitemap advanced (index, image, hreflang)', 'lock_reason' => 'pro', 'scope' => 'section'],
            ['key' => 'section:sitemap.news',       'tab' => 'sitemap',  'label' => 'Google News Sitemap',               'lock_reason' => 'pro', 'scope' => 'section'],
            ['key' => 'ping_on_publish',            'tab' => 'sitemap',  'label' => 'Ping search engines on publish',    'lock_reason' => 'pro', 'scope' => 'field'],

            // ── Social tab ──────────────────────────────────────────────
            // Task #473 — og_description_override is Free; kept in the
            // sectionFields() map below under section:social.locale so it is
            // NOT stripped on Free saves.
            ['key' => 'enable_og_locale',           'tab' => 'social',   'label' => 'og:locale tag',                     'lock_reason' => 'pro', 'scope' => 'field'],
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
            if (!array_key_exists($field, $payload)) {
                continue;
            }
            $incoming = (string) $payload[$field];
            if (!in_array($incoming, $proValues, true)) {
                continue;
            }
            // Fall back to the previously-saved value if it was Free-safe,
            // otherwise to the documented default.
            $fallback = self::proOptionDefaults()[$field] ?? '';
            $prev     = (string) ($existing[$field] ?? '');
            if ($prev !== '' && !in_array($prev, $proValues, true)) {
                $fallback = $prev;
            }
            $payload[$field] = $fallback;
            $stripped[] = $field . '=' . $incoming . '→' . $fallback;
        }
        if (!empty($stripped)) {
            error_log(
                '[AI Boost] ProFeatureRegistry::stripProOptions() rewrote Pro enum values on Free save: '
                . implode(', ', $stripped)
                . ' — the SPA dropdown should have disabled these options.'
            );
        }
        return $payload;
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
        return [
            // Task #473 — whole FAQ card is Pro; gates auto_detect + manual + items + output type.
            'section:schema.faq'                  => [
                'faq_auto_detect', 'enable_manual_faqs', 'faq_items',
                'schema_faq_output_type', 'manual_faq_scope',
            ],
            // Day-by-day schedule grid (rendered only when schema_hours_mode=advanced).
            'section:schema.hours_advanced'       => [
                'schema_hours_temp_closed', 'schema_holiday_closed',
                'hours_mo_opens', 'hours_mo_closes', 'hours_tu_opens', 'hours_tu_closes',
                'hours_we_opens', 'hours_we_closes', 'hours_th_opens', 'hours_th_closes',
                'hours_fr_opens', 'hours_fr_closes', 'hours_sa_opens', 'hours_sa_closes',
                'hours_su_opens', 'hours_su_closes',
                'hours_mo_closed', 'hours_tu_closed', 'hours_we_closed', 'hours_th_closed',
                'hours_fr_closed', 'hours_sa_closed', 'hours_su_closed',
            ],
            'section:schema.author_entity'        => ['schema_author_entity_enabled'],
            'section:schema.howto'                => ['schema_howto'],
            'section:schema.event'                => [
                'events_enabled', 'events_category_id', 'schema_event_article_ids',
                'schema_events_enabled', 'schema_events_en',
            ],
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
            // Task #473 — whole Meta Pixel card is Pro on Free.
            'section:social.pixel'                => [
                'enable_meta_pixel', 'meta_pixel_id', 'meta_pixel_ids',
                'pixel_consent_mode',
            ],
            'section:social.pixel_events'         => ['meta_pixel_standard_events'],
            'section:social.pixel_custom_events'  => ['meta_custom_events'],

            // Task #473 — Analytics re-tiering. GA4 keys stay Free; everything else Pro.
            'section:analytics.non_ga4'           => [
                'enable_google_verification', 'gsc_verification_code', 'gsc_codes',
                'gsc_additional_html', 'fb_domain_verification',
            ],
            'section:analytics.gtm'               => [
                'enable_gtm', 'gtm_container_id',
            ],

            // Task #473 — Custom Code is whole-tab Pro. Mirror EVERY key the
            // SettingsController accepts for this tab (head/body/footer scopes
            // + menu_ids + legacy aliases) so a crafted Free POST can't
            // persist Pro-only injection settings via stripLocked() bypass.
            'section:code'                        => [
                'enable_custom_code',
                'custom_code_head', 'custom_code_head_scope', 'custom_code_head_menu_ids',
                'custom_code_body', 'custom_code_body_scope', 'custom_code_body_menu_ids',
                'custom_code_footer', 'custom_code_footer_scope', 'custom_code_footer_menu_ids',
                // Legacy single-bucket aliases (still writable by older payloads).
                'custom_code_scope', 'custom_code_menu_ids',
            ],

            // Task #473 — Debug tab is whole-tab Pro. Strip the toggles the
            // SettingsController accepts; dev_license_preview / dev_force_free_tier
            // stay DB-only QA overrides and are filtered separately by the
            // controller itself, so we deliberately do NOT list them here.
            'section:debug'                       => [
                'debug_mode', 'hide_comments', 'staging_mode',
            ],

            // AEO Pro sections — every key in here MUST appear in the SPA's
            // payload list so stripLocked() actually drops it on a Free save.
            'section:aeo.llms_full'               => [
                'llms_full_txt_enabled', 'llms_full_max_articles',
            ],
            // Task #463: AI Crawler Rules is now a Free-only consolidated
            // card in AeoTab.vue. `crawler_bot_rules`, `ai_crawlers_enabled`,
            // and `crawler_rules` pass through stripLocked() unchanged.
            'section:aeo.indexnow'                => [
                'indexnow_enabled', 'indexnow_api_key', 'indexnow_auto_submit',
            ],
            'section:aeo.markdown'                => [
                'markdown_pages_enabled',
            ],

            // section:translations.per_language gates the per-language values
            // saved through the dedicated saveTranslations endpoint (which has
            // its own isPro guard in SettingsController). It deliberately has
            // no settings-payload keys to strip here.
            'section:translations.per_language'   => [],
        ];
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
            $key = (string) ($entry['key'] ?? '');
            if ($key === '') {
                continue;
            }
            if (strpos($key, 'section:') === 0) {
                foreach (self::sectionFields()[$key] ?? [] as $sectionKey) {
                    $out[] = $sectionKey;
                }
            } else {
                $out[] = $key;
            }
        }
        return array_values(array_unique($out));
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
