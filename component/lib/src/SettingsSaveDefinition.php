<?php
/**
 * AI Boost settings save definition.
 *
 * Central source for settings keys accepted by the admin save endpoint.
 * Keeps the existing controller-compatible payload surface while allowing
 * manifest-backed Free/runtime fields to become writable without opening
 * ungated Pro fields on Free installs.
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or die;

use AiBoost\Lib\Cms\AdapterRegistry;
use AiBoost\Lib\Manifest\Registry as ManifestRegistry;

final class SettingsSaveDefinition
{
    /**
     * Keys that the Settings save endpoint NEVER accepts from the client and
     * that export/import must never transfer between installs.
     *
     * License/activation state, per-site identity and dev overrides are
     * managed exclusively by their own endpoints (license activation,
     * heartbeat, debug controls). On an ordinary settings save they are
     * carried forward from the existing row via mergeSystemPreservedKeys(),
     * which is fail-closed in both directions: a client posting
     * `pro_activated=1` cannot self-promote a Free install, and a save can
     * never wipe a paying customer's perpetual activation or stored licence
     * key. ImportController::IMPORT_DENYLIST builds on this constant so the
     * two lists cannot drift.
     *
     * @var array<int,string>
     */
    public const SYSTEM_PRESERVED_KEYS = [
        // License / perpetual activation — written only by
        // PluginRegistry::saveLicenseState() and the heartbeat/reconcile jobs.
        'license_key',
        'license_tier',
        'license_state',
        'license_heartbeat',
        'license_reconcile',
        'license_simulation',
        'pro_activated',
        'pro_activated_at',
        'pro_activated_version',
        'pro_skus',
        'pro_installed', // edition marker — written only by the package install script, never via form/import
        // Per-site identity + server-side bookkeeping.
        'install_id',
        'last_backup_at',
        // Dev/QA overrides — DB-only by design, never writable from the form.
        'dev_license_preview',
        'dev_force_free_tier',
    ];

    /**
     * Historical settings payload accepted by SettingsController::save().
     *
     * This list intentionally includes modern Vue keys, legacy database aliases,
     * repeatable-row JSON targets, debug carry-forward keys, and section-gated
     * Pro keys. It is the compatibility floor for the first manifest-driven
     * settings save slice.
     *
     * @var array<int,string>
     */
    private const COMPATIBILITY_KEYS = [
        'license_key', 'license_tier',
        'show_advanced_options',
        'auto_domain_detection', 'manual_domain',
        'enable_robots', 'robots_auto_sync',
        'conflict_mode',
        'crawler_rules',
        'org_name', 'org_description', 'org_logo', 'org_url', 'org_email', 'org_phone',
        'social_facebook', 'social_instagram', 'social_youtube', 'social_twitter',
        'social_linkedin', 'social_tiktok', 'social_pinterest',
        'org_address_street', 'org_address_city', 'org_address_state',
        'org_address_zip', 'org_address_country',
        'org_latitude', 'org_longitude',
        'rating_value', 'rating_count', 'rating_best', 'rating_worst', 'rating_source',
        'org_name_en', 'org_description_en', 'schema_logo_url',
        'schema_url', 'schema_email', 'schema_phone',
        'schema_social_facebook', 'schema_social_instagram',
        'schema_social_youtube', 'schema_social_twitter', 'schema_social_linkedin',
        'schema_social_tiktok', 'schema_social_pinterest',
        'schema_address_street_en', 'schema_address_locality_en',
        'schema_address_zip', 'schema_address_country',
        'schema_latitude', 'schema_longitude',
        'schema_rating_value', 'schema_rating_count',
        'schema_rating_best', 'schema_rating_worst', 'schema_rating_source',
        'enable_schema', 'schema_type', 'page_type_auto_detect',
        'specific_price_range', 'specific_star_rating',
        'specific_checkin_time', 'specific_checkout_time',
        'specific_available_service', 'specific_area_served', 'specific_serves_cuisine',
        'specific_pets_allowed',
        'schema_services',
        // Faza 2b — per-type Pro detail fields
        'specific_accepting_patients', 'specific_credentials', 'specific_languages',
        'specific_diets', 'specific_number_of_rooms',
        'specific_return_category', 'specific_return_days', 'specific_return_country',
        'specific_number_of_employees', 'specific_slogan', 'specific_award',
        'specific_smoking_allowed', 'specific_drive_through', 'specific_accessible_free',
        'specific_audience', 'specific_brand',
        'enable_search_action',
        'faq_items',
        'events_enabled', 'events_category_id',
        'schema_hotel_star_rating', 'schema_hotel_checkin_time',
        'schema_hotel_checkout_time', 'schema_hotel_pets_allowed',
        'schema_hotel_image',
        'schema_org_image',
        'schema_price_range',
        'schema_medical_specialty',
        'schema_legal_area',
        'schema_edu_level',
        'schema_job_title', 'schema_portfolio_url',
        'schema_dental_specialty',
        'schema_realestate_area_served', 'schema_realestate_property_types',
        'schema_gym_sport', 'schema_gym_amenities',
        'schema_news_topics', 'schema_news_principles',
        'hours_monday_opens', 'hours_monday_closes', 'hours_monday_closed',
        'hours_tuesday_opens', 'hours_tuesday_closes', 'hours_tuesday_closed',
        'hours_wednesday_opens', 'hours_wednesday_closes', 'hours_wednesday_closed',
        'hours_thursday_opens', 'hours_thursday_closes', 'hours_thursday_closed',
        'hours_friday_opens', 'hours_friday_closes', 'hours_friday_closed',
        'hours_saturday_opens', 'hours_saturday_closes', 'hours_saturday_closed',
        'hours_sunday_opens', 'hours_sunday_closes', 'hours_sunday_closed',
        'schema_hours_mode', 'schema_opening_hours',
        'schema_hours_appointment_only',
        'schema_business_hours',
        'schema_season_from', 'schema_season_to',
        'faq_auto_detect', 'enable_manual_faqs', 'manual_faq_scope', 'manual_faqs_en',
        'schema_events_enabled', 'schema_events_en',
        'include_articles', 'include_categories', 'include_menu_items', 'include_tags',
        'sitemap_limit',
        'default_changefreq', 'default_priority',
        'priority_homepage', 'priority_articles', 'priority_categories', 'priority_tags',
        'exclude_category_ids', 'exclude_menu_ids',
        'enable_sitemap_index', 'enable_image_sitemap',
        'enable_news_sitemap', 'news_category_id', 'news_publication_name',
        'ping_google', 'ping_bing', 'ping_on_publish',
        'enable_canonical', 'canonical_url_map',
        'enable_hreflang',
        'enable_sitemap',
        'sitemap_include_articles', 'sitemap_include_categories', 'sitemap_include_menus',
        'sitemap_max_articles', 'sitemap_exclude_ids', 'sitemap_exclude_urls',
        'sitemap_hreflang',
        'sitemap_priority_articles', 'sitemap_priority_categories', 'sitemap_priority_menu',
        'sitemap_changefreq_articles', 'sitemap_changefreq_categories', 'sitemap_changefreq_menu',
        'site_name', 'default_og_image',
        'og_description_override',
        'enable_per_article_fields', 'enable_article_og_type',
        'enable_og_locale', 'fb_app_id',
        'twitter_site_handle', 'og_image_width', 'og_image_height',
        'enable_opengraph', 'og_site_name', 'og_default_image',
        'enable_twitter_cards',
        'enable_meta_pixel',
        'meta_pixel_id',
        'meta_pixel_ids',
        'pixel_consent_mode',
        'fb_domain_verification',
        'meta_pixel_standard_events',
        'meta_custom_events',
        'enable_google_verification',
        'gsc_codes',
        'gsc_verification_code',
        'gsc_additional_html',
        'enable_ga4', 'ga4_measurement_id', 'ga4_consent_mode',
        'enable_gtm', 'gtm_container_id',
        'indexnow_enabled', 'indexnow_api_key',
        'indexnow_auto_submit',
        'llmstxt_enabled', 'llmstxt_custom_pages_en',
        'llms_full_txt_enabled',
        'llmstxt_description', 'llmstxt_faq_items', 'llmstxt_recent_articles', 'llmstxt_faq_auto_detect',
        'llms_full_max_articles',
        'llmstxt_custom_pages',
        'robots_block_scrapers', 'robots_custom_rules', 'robots_custom_scrapers',
        'scraper_ahrefsbot', 'scraper_semrushbot', 'scraper_dotbot',
        'scraper_mj12bot', 'scraper_blexbot', 'scraper_rogerbot',
        'scraper_screamingfrog', 'scraper_sitebulb', 'scraper_siteauditor',
        'scraper_serpstatbot', 'scraper_bytespider', 'scraper_petalbot',
        'crawler_bot_rules',
        'aeo_ai_meta_enabled',
        'enable_x_robots_header',
        'markdown_pages_enabled',
        'article_schema_enabled', 'article_schema_type_auto',
        'website_schema_enabled', 'website_schema_search_enabled',
        'schema_author_entity_enabled',
        'schema_howto',
        'schema_faq_output_type',
        'title_template', 'title_separator',
        'title_template_home', 'title_template_article',
        'title_template_category', 'title_template_search',
        'title_template_tag', 'title_template_default',
        'title_template_maxlen',
        'meta_desc_template', 'meta_desc_template_article',
        'meta_desc_template_category', 'meta_desc_template_default',
        'meta_desc_maxlen',
        'redirect_enabled', 'redirect_404_log_enabled',
        'ai_crawlers_enabled',
        'aeo_crawler_default_policy',
        'enable_custom_code',
        'custom_code_head', 'custom_code_head_scope', 'custom_code_head_menu_ids',
        'custom_code_body', 'custom_code_body_scope', 'custom_code_body_menu_ids',
        'custom_code_footer', 'custom_code_footer_scope', 'custom_code_footer_menu_ids',
        'custom_code_scope', 'custom_code_menu_ids',
        'debug_mode', 'hide_comments', 'staging_mode',
        'error_log_enabled', 'error_log_min_severity',
    ];

    /** @return array<int,string> */
    public static function acceptedKeys(): array
    {
        return self::unique(array_merge(self::legacyKeys(), self::safeManifestKeys()));
    }

    /**
     * Carry every SYSTEM_PRESERVED_KEYS entry forward from the existing
     * settings row into the save payload.
     *
     * Fail-closed in both directions: a value present in the existing row
     * always overwrites whatever the client posted (so a posted
     * `pro_activated=1` cannot self-promote a Free install), and a key absent
     * from the existing row is removed from the payload entirely (so it can
     * never be introduced through the Settings save endpoint).
     *
     * Pure and side-effect free so SettingsController::save() and the unit
     * tests share the exact same merge contract.
     *
     * @param array<string,mixed> $settings Posted payload (already whitelisted).
     * @param array<string,mixed> $existing Decoded existing settings row.
     * @return array<string,mixed>
     */
    public static function mergeSystemPreservedKeys(array $settings, array $existing): array
    {
        foreach (self::SYSTEM_PRESERVED_KEYS as $preservedKey) {
            if (array_key_exists($preservedKey, $existing)) {
                $settings[$preservedKey] = $existing[$preservedKey];
            } else {
                unset($settings[$preservedKey]);
            }
        }
        return $settings;
    }

    /** @return array<int,string> */
    public static function manifestKeys(): array
    {
        return array_keys(self::manifestFieldMap());
    }

    /** @return array<int,string> */
    public static function legacyKeys(): array
    {
        return self::unique(array_merge(self::COMPATIBILITY_KEYS, self::advancedOpeningHoursKeys()));
    }

    /** @return array<int,string> */
    public static function saveOnlyKeys(): array
    {
        return [
            'meta_pixel_id',
            'meta_pixel_ids',
            'gsc_verification_code',
            'gsc_codes',
            'meta_custom_events',
        ];
    }

    /** @return array<string,mixed> */
    public static function defaults(): array
    {
        $defaults = [];
        foreach (self::fields() as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '' || !array_key_exists('default', $field)) {
                continue;
            }
            $defaults[$key] = $field['default'];
        }
        return $defaults;
    }

    /** @return array<string,mixed>|null */
    public static function field(string $key): ?array
    {
        foreach (self::fields() as $field) {
            if (($field['key'] ?? null) === $key) {
                return $field;
            }
        }
        return null;
    }

    /** @return array<int, array<string,mixed>> */
    public static function fields(): array
    {
        $manifestFields = self::manifestFieldMap();
        $fields = [];
        foreach (self::acceptedKeys() as $key) {
            if (isset($manifestFields[$key])) {
                $fields[] = $manifestFields[$key] + ['source' => 'manifest'];
                continue;
            }
            $fields[] = ['key' => $key, 'source' => 'compatibility'];
        }
        return $fields;
    }

    /** @return array<int,string> */
    private static function safeManifestKeys(): array
    {
        $keys = [];
        foreach (self::manifestFieldMap() as $field) {
            if (self::canAcceptManifestField($field)) {
                $keys[] = (string) $field['key'];
            }
        }
        return self::unique($keys);
    }

    /** @param array<string,mixed> $field */
    private static function canAcceptManifestField(array $field): bool
    {
        $key = (string) ($field['key'] ?? '');
        if ($key === '') {
            return false;
        }
        if (in_array($key, self::legacyKeys(), true)) {
            return true;
        }
        return true;
    }

    /** @return array<string, array<string,mixed>> */
    private static function manifestFieldMap(): array
    {
        $fields = [];
        foreach (array_merge(ManifestRegistry::staticOnly(), self::runtimeManifestFields()) as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $fields[$key] = self::normalizeField($field);
        }
        return $fields;
    }

    /** @return array<int, array<string,mixed>> */
    private static function runtimeManifestFields(): array
    {
        $fields = [];
        try {
            foreach (AdapterRegistry::events()->trigger('onAiBoostRegisterFields', []) as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                foreach ($entry as $field) {
                    if (is_array($field) && !empty($field['key'])) {
                        $fields[] = $field;
                    }
                }
            }
        } catch (\Throwable) {
            return [];
        }
        return $fields;
    }

    /**
     * @param array<string,mixed> $field
     * @return array<string,mixed>
     */
    private static function normalizeField(array $field): array
    {
        return array_merge([
            'key'           => '',
            'tab'           => 'core',
            'section'       => 'general',
            'label'         => '',
            'type'          => 'toggle',
            'default'       => '',
            'tier'          => 'free',
            'sku'           => 'core',
            'integration'   => null,
            'description'   => '',
            'dependsOn'     => null,
            'options'       => null,
            'locked'        => false,
            'lock_reason'   => null,
            'feature_class' => null,
            'health'        => null,
            'i18n'          => null,
        ], $field);
    }

    /** @return array<int,string> */
    private static function advancedOpeningHoursKeys(): array
    {
        $keys = [];
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
     * @param array<int,string> $keys
     * @return array<int,string>
     */
    private static function unique(array $keys): array
    {
        return array_values(array_unique($keys));
    }
}