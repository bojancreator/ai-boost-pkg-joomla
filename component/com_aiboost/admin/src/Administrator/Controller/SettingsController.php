<?php
/**
 * @package     AiBoost\Component\AiBoost\Administrator\Controller
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Component\AiBoost\Administrator\Controller;

defined('_JEXEC') or die;

use AiBoost\Lib\JoomlaAppContext;
use AiBoost\Lib\LanguageService;
use AiBoost\Lib\PluginRegistry;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

class SettingsController extends BaseController
{
    public function save(): void
    {
        if (!Session::checkToken()) {
            $this->sendJsonResponse(false, 'Invalid security token.');
            return;
        }

        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJsonResponse(false, 'Access denied.');
            return;
        }

        try {
            $input    = $this->app->getInput();
            $settings = [];

            $fields = [
                // General
                'license_key', 'license_tier',
                'show_advanced_options',
                'auto_domain_detection', 'manual_domain',
                'enable_robots', 'robots_auto_sync',
                'conflict_mode',
                'crawler_rules',

                // Organization — new Vue keys (v0.8.3+)
                'org_name', 'org_description', 'org_logo', 'org_url', 'org_email', 'org_phone',
                'social_facebook', 'social_instagram', 'social_youtube', 'social_twitter',
                'social_linkedin', 'social_tiktok', 'social_pinterest',
                'org_address_street', 'org_address_city', 'org_address_state',
                'org_address_zip', 'org_address_country',
                'org_latitude', 'org_longitude',
                'rating_value', 'rating_count', 'rating_best', 'rating_worst', 'rating_source',

                // Organization — legacy keys (kept for backward compat with pre-v0.8.3 DB data)
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

                // Schema.org — core
                'enable_schema', 'schema_type', 'page_type_auto_detect',
                // Schema — new Vue keys (v0.8.3+)
                'specific_price_range', 'specific_star_rating',
                'specific_checkin_time', 'specific_checkout_time',
                'specific_available_service', 'specific_area_served', 'specific_serves_cuisine',
                'specific_pets_allowed',
                'enable_search_action',
                'faq_items',
                'events_enabled', 'events_category_id',
                // Schema — legacy keys
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
                // Opening Hours — new flat keys (v0.8.3+)
                'hours_monday_opens',    'hours_monday_closes',    'hours_monday_closed',
                'hours_tuesday_opens',   'hours_tuesday_closes',   'hours_tuesday_closed',
                'hours_wednesday_opens', 'hours_wednesday_closes', 'hours_wednesday_closed',
                'hours_thursday_opens',  'hours_thursday_closes',  'hours_thursday_closed',
                'hours_friday_opens',    'hours_friday_closes',    'hours_friday_closed',
                'hours_saturday_opens',  'hours_saturday_closes',  'hours_saturday_closed',
                'hours_sunday_opens',    'hours_sunday_closes',    'hours_sunday_closed',
                // Opening Hours — legacy keys
                'schema_hours_mode', 'schema_opening_hours',
                'schema_hours_appointment_only', 'schema_hours_temp_closed',
                'schema_business_hours',
                'schema_season_from', 'schema_season_to', 'schema_holiday_closed',
                // FAQ
                'faq_auto_detect', 'enable_manual_faqs', 'manual_faq_scope', 'manual_faqs_en',
                // Events — legacy
                'schema_events_enabled', 'schema_events_en',

                // Sitemap & Canonical — new Vue keys (v0.8.3+)
                'include_articles', 'include_categories', 'include_menu_items', 'include_tags',
                'sitemap_limit',
                'default_changefreq', 'default_priority',
                'priority_homepage', 'priority_articles', 'priority_categories', 'priority_tags',
                'exclude_category_ids', 'exclude_menu_ids',
                'enable_sitemap_index', 'enable_image_sitemap',
                'enable_news_sitemap', 'news_category_id', 'news_publication_name',
                'ping_google', 'ping_bing', 'ping_on_publish',
                // Sitemap — legacy keys
                'enable_canonical', 'canonical_url_map',
                'enable_hreflang',
                'enable_sitemap',
                'sitemap_include_articles', 'sitemap_include_categories', 'sitemap_include_menus',
                'sitemap_max_articles', 'sitemap_exclude_ids', 'sitemap_exclude_urls',
                'sitemap_hreflang',
                'sitemap_priority_articles', 'sitemap_priority_categories', 'sitemap_priority_menu',
                'sitemap_changefreq_articles', 'sitemap_changefreq_categories', 'sitemap_changefreq_menu',

                // Social & Meta — new Vue keys (v0.8.3+)
                'site_name', 'default_og_image',
                'og_description_override',
                'enable_per_article_fields', 'enable_article_og_type',
                'enable_og_locale', 'fb_app_id',
                'twitter_site_handle', 'og_image_width', 'og_image_height',
                // Social & Meta — existing + legacy keys
                'enable_opengraph', 'og_site_name', 'og_default_image',
                'enable_twitter_cards',
                'enable_meta_pixel',
                'meta_pixel_id',       // legacy single-ID field — kept for backward compat
                'meta_pixel_ids',      // JSON array of IDs (new chip UI)
                'pixel_consent_mode',
                'fb_domain_verification',
                'meta_pixel_standard_events',  // JSON object of enabled events
                'meta_custom_events',          // JSON array of {name, url}

                // Analytics
                'enable_google_verification',
                'gsc_codes',           // JSON array (built from gsc_codes_rows[])
                'gsc_verification_code',
                'gsc_additional_html',
                'enable_ga4', 'ga4_measurement_id', 'ga4_consent_mode',
                'enable_gtm', 'gtm_container_id',
                'indexnow_enabled', 'indexnow_api_key',
                'indexnow_auto_submit',
                'llmstxt_enabled', 'llmstxt_custom_pages_en',
                'llms_full_txt_enabled',
                // AEO — Vue admin fields
                'llmstxt_description', 'llmstxt_faq_items', 'llmstxt_recent_articles', 'llmstxt_faq_auto_detect',
                'llms_full_max_articles',
                'llmstxt_custom_pages',
                'robots_block_scrapers', 'robots_custom_rules', 'robots_custom_scrapers',
                // Per-bot scraper toggles (canonical model — supersedes robots_block_scrapers aggregate)
                'scraper_ahrefsbot', 'scraper_semrushbot', 'scraper_dotbot',
                'scraper_mj12bot', 'scraper_blexbot', 'scraper_rogerbot',
                'scraper_screamingfrog', 'scraper_sitebulb', 'scraper_siteauditor',
                'scraper_serpstatbot', 'scraper_bytespider', 'scraper_petalbot',
                'crawler_bot_rules',
                'aeo_ai_meta_enabled',
                'enable_x_robots_header',
                'markdown_pages_enabled',

                // Article & WebSite Schema (v0.7.0+)
                'article_schema_enabled', 'article_schema_type_auto',
                'website_schema_enabled', 'website_schema_search_enabled',
                // Author entity — v0.38.0: single toggle, reads Joomla User Custom Fields per article author
                'schema_author_entity_enabled',
                // HowTo schema (v0.8.16)
                'schema_howto',
                // FAQ/QAPage output type: 'faqpage' | 'qapage' | 'both' (v0.8.16)
                'schema_faq_output_type',

                // Title Templates (v0.7.0) — global + per-content-type
                'title_template', 'title_separator',
                'title_template_home', 'title_template_article',
                'title_template_category', 'title_template_search',
                'title_template_tag', 'title_template_default',
                'title_template_maxlen',
                'meta_desc_template', 'meta_desc_template_article',
                'meta_desc_template_category', 'meta_desc_template_default',
                'meta_desc_maxlen',

                // Redirect Manager & 404 (v0.7.0)
                'redirect_enabled', 'redirect_404_log_enabled',

                // Crawlers
                'ai_crawlers_enabled',
                'aeo_crawler_default_policy',

                // Custom Code
                'enable_custom_code',
                'custom_code_head', 'custom_code_head_scope', 'custom_code_head_menu_ids',
                'custom_code_body', 'custom_code_body_scope', 'custom_code_body_menu_ids',
                'custom_code_footer', 'custom_code_footer_scope', 'custom_code_footer_menu_ids',
                // Custom Code — legacy shared scope (kept for backward compat)
                'custom_code_scope', 'custom_code_menu_ids',

                // Debug
                'debug_mode', 'hide_comments',
                'dev_license_preview', 'dev_force_free_tier', 'staging_mode',
                // Debug — Error logging (Task #511)
                'error_log_enabled', 'error_log_min_severity',
            ];

            foreach ($fields as $field) {
                $value = $input->get($field, null, 'raw');
                if ($value !== null) {
                    $settings[$field] = htmlspecialchars_decode((string) $value, ENT_QUOTES);
                }
            }

            /* ── Repeatable rows → JSON arrays ──────────────────────────────── */

            // Meta Pixel IDs: meta_pixel_ids_rows[] → meta_pixel_ids (JSON)
            $pixelRows = $input->get('meta_pixel_ids_rows', [], 'array');
            $pixelIds  = array_values(array_filter(array_map('trim', $pixelRows)));
            if (!empty($pixelIds)) {
                $settings['meta_pixel_ids'] = json_encode($pixelIds, JSON_UNESCAPED_UNICODE);
                // backward-compat: keep first ID in legacy field
                $settings['meta_pixel_id'] = $pixelIds[0];
            }

            // GSC codes: gsc_codes_rows[] → gsc_codes (JSON)
            $gscRows  = $input->get('gsc_codes_rows', [], 'array');
            $gscCodes = array_values(array_filter(array_map('trim', $gscRows)));
            if (!empty($gscCodes)) {
                $settings['gsc_codes'] = json_encode($gscCodes, JSON_UNESCAPED_UNICODE);
                // backward-compat
                $settings['gsc_verification_code'] = $gscCodes[0];
            }

            // Meta Pixel Standard Events: sent as JSON string from hidden input (f-pixel-events).
            // The field 'meta_pixel_standard_events' is already in the $fields list above and
            // is read automatically as a raw string — no special handling needed here.

            // Custom Events: custom_event_name[] + custom_event_url[] → JSON array
            $ceNames = $input->get('custom_event_name', [], 'array');
            $ceUrls  = $input->get('custom_event_url', [], 'array');
            if (!empty($ceNames)) {
                $customEvents = [];
                foreach ($ceNames as $idx => $ceName) {
                    $name = trim((string) $ceName);
                    $url  = trim((string) ($ceUrls[$idx] ?? ''));
                    if ($name !== '') {
                        $customEvents[] = ['name' => $name, 'url' => $url];
                    }
                }
                $settings['meta_custom_events'] = json_encode($customEvents, JSON_UNESCAPED_UNICODE);
            }

            $db  = Factory::getDbo();
            $now = Factory::getDate()->toSql();

            // Carry forward keys that are managed outside the Settings form so that
            // a normal settings save does not destroy them.
            $existingForMerge = [];
            try {
                $q   = $db->getQuery(true)
                    ->select($db->quoteName('settings_json'))
                    ->from('#__aiboost_settings')
                    ->where($db->quoteName('setting_key') . '=' . $db->quote('main'));
                $raw = (string) $db->setQuery($q)->loadResult();
                $existingForMerge = json_decode($raw, true) ?: [];
            } catch (\Throwable $e) {
            }
            foreach (['dismissed_checks'] as $preservedKey) {
                if (!isset($settings[$preservedKey]) && isset($existingForMerge[$preservedKey])) {
                    $settings[$preservedKey] = $existingForMerge[$preservedKey];
                }
            }

            // ── Pro gating: server-side enforcement ───────────────────────
            // Strip any Pro-only keys from the save payload when the install is Free,
            // even if the SPA failed to gate them. License tier is read from the
            // EXISTING settings row (before merge) so a Free install cannot promote
            // itself to Pro inside the same save call.
            $isProForSave = $this->isProSetting($existingForMerge);

            // License tier and debug-tier overrides are NEVER writable from the
            // Settings save endpoint — they belong to the license activation /
            // debug-controls endpoints. Carry-forward from the existing row so a
            // Free admin cannot promote themselves to Pro by posting
            // `license_tier=pro` (or flipping `dev_license_preview`) inside an
            // ordinary settings save.
            foreach (['license_tier', 'dev_license_preview', 'dev_force_free_tier'] as $tierKey) {
                if (array_key_exists($tierKey, $existingForMerge)) {
                    $settings[$tierKey] = $existingForMerge[$tierKey];
                } else {
                    unset($settings[$tierKey]);
                }
            }

            if (class_exists('AiBoost\\Lib\\ProFeatureRegistry')) {
                $settings = \AiBoost\Lib\ProFeatureRegistry::stripLocked($settings, $isProForSave);
                // Preserve any previously-saved Pro values: stripping locked keys
                // from the payload should not wipe values an admin set while
                // running Pro, so we merge them back from the existing row.
                if (!$isProForSave) {
                    foreach (\AiBoost\Lib\ProFeatureRegistry::lockedSettingsKeys() as $lockedKey) {
                        if (array_key_exists($lockedKey, $existingForMerge)) {
                            $settings[$lockedKey] = $existingForMerge[$lockedKey];
                        }
                    }
                }
                // Enum-gated fields (e.g. schema_type): the field stays
                // editable on Free but Pro values get rewritten to a safe
                // Free fallback. Same fail-closed pattern as stripLocked.
                $settings = \AiBoost\Lib\ProFeatureRegistry::stripProOptions(
                    $settings, $existingForMerge, $isProForSave
                );
            }

            // ── Task #497 — Change-based backup-reminder counter ─────────
            // Count how many top-level setting keys actually changed value in
            // this save, and bump a monotonic counter stored in the JSON blob.
            // The Vue dashboard snapshots this counter at backup time, so
            // (currentCounter − snapshotAtBackup) = "settings changed since
            // last backup" — the signal that triggers the change-based
            // reminder banner alongside the existing time-based one.
            //
            // Internal bookkeeping keys are excluded so they don't inflate
            // the count when the controller carries them forward.
            $changeBookkeepingKeys = [
                'change_counter', 'last_changed_at',
                'license_tier', 'dev_license_preview', 'dev_force_free_tier',
                'dismissed_checks',
            ];
            $changeCount = 0;
            $allKeys = array_unique(array_merge(array_keys($settings), array_keys($existingForMerge)));
            // Some stored keys hold arrays (e.g. license_state, dismissed_checks),
            // so a bare (string) cast would emit "Array to string conversion"
            // warnings — which corrupt this endpoint's JSON output on hosts with
            // display_errors enabled. Normalise array values to their JSON form
            // for a stable, warning-free change comparison.
            $scalarize = static function ($v): ?string {
                if ($v === null) {
                    return null;
                }
                if (is_array($v)) {
                    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                return (string) $v;
            };
            foreach ($allKeys as $k) {
                if (in_array($k, $changeBookkeepingKeys, true)) {
                    continue;
                }
                $newV = array_key_exists($k, $settings)          ? $scalarize($settings[$k])          : null;
                $oldV = array_key_exists($k, $existingForMerge) ? $scalarize($existingForMerge[$k]) : null;
                if ($newV !== $oldV) {
                    $changeCount++;
                }
            }
            $prevCounter = (int) ($existingForMerge['change_counter'] ?? 0);
            $settings['change_counter'] = $prevCounter + $changeCount;
            if ($changeCount > 0) {
                $settings['last_changed_at'] = $now;
            } elseif (isset($existingForMerge['last_changed_at'])) {
                $settings['last_changed_at'] = $existingForMerge['last_changed_at'];
            }

            $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            $query = $db->getQuery(true)
                ->select('id')
                ->from('#__aiboost_settings')
                ->where($db->quoteName('setting_key') . '=' . $db->quote('main'));
            $existingId = (int) $db->setQuery($query)->loadResult();

            if ($existingId) {
                $query = $db->getQuery(true)
                    ->update('#__aiboost_settings')
                    ->set($db->quoteName('settings_json') . '=' . $db->quote($json))
                    ->set($db->quoteName('updated_at') . '=' . $db->quote($now))
                    ->where($db->quoteName('id') . '=' . $existingId);
            } else {
                $query = $db->getQuery(true)
                    ->insert('#__aiboost_settings')
                    ->columns(['setting_key', 'settings_json', 'created_at', 'updated_at'])
                    ->values($db->quote('main') . ',' . $db->quote($json) . ',' . $db->quote($now) . ',' . $db->quote($now));
            }

            $db->setQuery($query)->execute();

            // Save per-language translations (Pro feature — server-side gate)
            // UI lock in TranslationExpander is not sufficient; always verify Pro here.
            $translationsRaw = $input->get('translations', '', 'raw');
            if ($translationsRaw !== '' && $isProForSave) {
                $this->saveTranslations($db, $translationsRaw, $now);
            }

            // Save add-on plugin params (yootheme, falang) if provided by the Settings form
            $addonParamsRaw = $input->get('addon_params', '', 'string');
            if ($addonParamsRaw) {
                $this->saveAddonPluginParams($db, $addonParamsRaw);
            }

            // Regenerate physical robots.txt — web servers (LiteSpeed/Apache) serve it
            // directly from disk; standard Joomla .htaccess excludes it from PHP rewriting.
            $this->regenerateRobotsTxt($settings);

            // Format the save timestamp in the admin's timezone so the JS can display
            // the canonical server value without relying on browser-local time.
            try {
                $identity = $this->app->getIdentity();
                $tz       = $identity ? $identity->getParam('timezone', $this->app->get('offset', 'UTC')) : 'UTC';
                $savedDate = Factory::getDate($now, 'UTC');
                $savedDate->setTimezone(new \DateTimeZone($tz));
                $savedAt = $savedDate->format('d M Y \a\t H:i', true);
            } catch (\Throwable $e) {
                $savedAt = null;
            }

            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode(['success' => true, 'message' => 'Settings saved successfully.', 'saved_at' => $savedAt]);
            $this->app->close();
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] Settings save error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->sendJsonResponse(false, 'An error occurred while saving settings. Check the server error log for details.');
        }
    }

    /**
     * Export current AI Boost settings as a downloadable JSON file.
     * URL: index.php?option=com_aiboost&task=settings.export
     */
    public function export(): void
    {
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            throw new \RuntimeException('Access denied', 403);
        }

        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select(['settings_json', 'updated_at'])
                ->from('#__aiboost_settings')
                ->where($db->quoteName('setting_key') . '=' . $db->quote('main'));
            $row = $db->setQuery($query)->loadObject();

            $settings = [];
            if ($row && !empty($row->settings_json)) {
                $decoded = json_decode($row->settings_json, true);
                if (is_array($decoded)) {
                    $settings = $decoded;
                }
            }

            $export = [
                'meta' => [
                    'version'     => '1.0',
                    'plugin'      => 'pkg_aiboost',
                    'exported_at' => Factory::getDate()->toISO8601(),
                    'joomla'      => JVERSION,
                ],
                'params' => $settings,
            ];

            $json     = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $filename = 'aiboost-settings-export-' . date('Y-m-d') . '.json';

            // Task #500 — record server-side last backup timestamp so the
            // Health check can warn admins when no backup has been taken in
            // 30 days. We update in-place on the existing settings row.
            try {
                $settings['last_backup_at'] = Factory::getDate()->toISO8601();
                $update = $db->getQuery(true)
                    ->update($db->quoteName('#__aiboost_settings'))
                    ->set($db->quoteName('settings_json') . '=' . $db->quote(
                        json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    ))
                    ->set($db->quoteName('updated_at') . '=' . $db->quote(Factory::getDate()->toSql()))
                    ->where($db->quoteName('setting_key') . '=' . $db->quote('main'));
                $db->setQuery($update)->execute();
            } catch (\Throwable $e) {
                // Persisting the timestamp is best-effort; never block the export.
                \AiBoost\Lib\Logger::warning('[AiBoost] last_backup_at persist failed: ' . $e->getMessage());
            }

            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            $this->app->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $this->app->setHeader('Pragma', 'no-cache');
            $this->app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
            $this->app->sendHeaders();

            echo $json;
            $this->app->close();
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] Settings export error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function debugsettings(): void
    {
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJsonResponse(false, 'Access denied.');
            return;
        }
        try {
            $db     = Factory::getDbo();
            $q      = $db->getQuery(true)->select(['setting_key','settings_json','updated_at'])->from('#__aiboost_settings');
            $rows   = $db->setQuery($q)->loadObjectList();
            $out    = [];
            foreach ($rows as $row) {
                $decoded = json_decode($row->settings_json ?? '{}', true);
                $out[] = [
                    'key'      => $row->setting_key,
                    'updated'  => $row->updated_at,
                    'count'    => count($decoded),
                    'sample'   => array_slice($decoded, 0, 8, true),
                    'org_name' => $decoded['org_name_en'] ?? '(missing)',
                    'enable_schema' => $decoded['enable_schema'] ?? '(missing)',
                    'schema_type'   => $decoded['schema_type'] ?? '(missing)',
                ];
            }
            // Also check plugin status
            $q2  = $db->getQuery(true)->select(['element','enabled','ordering'])->from('#__extensions')
                      ->where($db->quoteName('type') . '=' . $db->quote('plugin'))
                      ->where($db->quoteName('folder') . '=' . $db->quote('system'))
                      ->where($db->quoteName('element') . ' LIKE ' . $db->quote('aiboost_%'));
            $plgs = $db->setQuery($q2)->loadObjectList();
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode(['settings_rows' => $out, 'plugins' => $plgs], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $this->app->close();
        } catch (\Throwable $e) {
            $this->sendJsonResponse(false, $e->getMessage());
        }
    }

    public function enableplugins(): void
    {
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJsonResponse(false, 'Access denied.');
            return;
        }

        try {
            $db = Factory::getDbo();

            // Enable all aiboost system plugins
            $query = $db->getQuery(true)
                ->update('#__extensions')
                ->set($db->quoteName('enabled') . '=1')
                ->where($db->quoteName('type')   . '=' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . '=' . $db->quote('system'))
                ->where($db->quoteName('element') . ' LIKE ' . $db->quote('aiboost_%'));
            $db->setQuery($query)->execute();
            $affected = $db->getAffectedRows();

            // Query what got enabled
            $q2 = $db->getQuery(true)
                ->select(['element', 'enabled'])
                ->from('#__extensions')
                ->where($db->quoteName('type')   . '=' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . '=' . $db->quote('system'))
                ->where($db->quoteName('element') . ' LIKE ' . $db->quote('aiboost_%'));
            $rows = $db->setQuery($q2)->loadObjectList();

            $list = array_map(fn($r) => $r->element . '=' . $r->enabled, $rows);

            $this->sendJsonResponse(true, "Enabled {$affected} plugins. Status: " . implode(', ', $list));
        } catch (\Throwable $e) {
            $this->sendJsonResponse(false, 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Write robots.txt to disk (no backup — direct overwrite).
     * Called after every settings save so changes take effect immediately.
     *
     * Order of sections written:
     *   1. Header + Joomla system paths (always)
     *   2. Sitemap line (unless `enable_sitemap=0`)
     *   3. Per-bot SEO scraper blocks (`scraper_*` keys from AEO tab)
     *   4. Custom scraper rules (`robots_custom_scrapers` textarea)
     *   5. AI Crawler Rules per-bot Allow/Block (`crawler_bot_rules` JSON map)
     *      when `ai_crawlers_enabled=1`
     *   6. Custom AI crawler rules (`crawler_rules` textarea) — appended
     *      verbatim after the per-bot section so admins can target bots
     *      not in the matrix (CCBot, Wayback, social previews, …).
     */
    private function regenerateRobotsTxt(array $settings): void
    {
        $robotsFile = JPATH_ROOT . '/robots.txt';

        $host    = \Joomla\CMS\Uri\Uri::getInstance();
        $baseUrl = $host->getScheme() . '://' . $host->getHost();

        $existing = is_file($robotsFile) ? (string) @file_get_contents($robotsFile) : '';

        // Task #566 — AI Boost manages ONLY a fenced block inside the user's
        // robots.txt (ForSEO model). We never overwrite the whole file: we
        // inject/refresh our block when management is on, and strip just our
        // block when it is off, always preserving any user-authored rules.
        if ((int) ($settings['enable_robots'] ?? 1) === 1) {
            @file_put_contents(
                $robotsFile,
                \AiBoost\Lib\RobotsTxtBuilder::injectManagedBlock($existing, $settings, $baseUrl)
            );
            return;
        }

        // Management disabled — remove our block, keep everything else.
        if ($existing === '') {
            return;
        }
        $stripped = \AiBoost\Lib\RobotsTxtBuilder::stripManagedBlock($existing);
        if (trim($stripped) === '') {
            // The file was entirely ours — remove it rather than leave it empty.
            @unlink($robotsFile);
        } else {
            @file_put_contents($robotsFile, rtrim($stripped) . "\n");
        }
    }

    /**
     * Return a JSON list of images from the Joomla /images folder.
     * Used by the admin media picker modal — bypasses JCE entirely.
     *
     * URL: index.php?option=com_aiboost&task=settings.mediaImages&format=json
     *
     * Query params:
     *   path  (string) — relative path inside /images/, e.g. "" or "subdir"
     *   q     (string) — optional filename search filter
     *
     * Returns:
     *   { success: true, path: "...", dirs: [...], files: [{name,url,thumb,size},...] }
     */
    public function mediaImages(): void
    {
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            $this->app->close();
            return;
        }

        $input    = $this->app->getInput();
        $relPath  = trim($input->getString('path', ''), '/\\');
        $query    = strtolower(trim($input->getString('q', '')));

        // Sanitise: only allow [a-zA-Z0-9/_-]
        $relPath = preg_replace('#[^a-zA-Z0-9/_\-]#', '', $relPath);
        $absBase = JPATH_ROOT . '/images';
        $absPath = $relPath ? $absBase . '/' . $relPath : $absBase;

        $siteUrl = \Joomla\CMS\Uri\Uri::root(true) ?: '';

        $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif'];
        $dirs      = [];
        $files     = [];

        if (!is_dir($absPath)) {
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Directory not found.']);
            $this->app->close();
            return;
        }

        foreach (scandir($absPath) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $absPath . '/' . $entry;
            if (is_dir($full)) {
                $dirs[] = ['name' => $entry, 'path' => ($relPath ? $relPath . '/' : '') . $entry];
                continue;
            }
            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!in_array($ext, $imageExts, true)) {
                continue;
            }
            if ($query && strpos(strtolower($entry), $query) === false) {
                continue;
            }
            $rel  = ($relPath ? $relPath . '/' : '') . $entry;
            $url  = $siteUrl . '/images/' . $rel;
            $size = @filesize($full) ?: 0;
            $files[] = [
                'name'  => $entry,
                'url'   => $url,
                'thumb' => $url,
                'size'  => round($size / 1024, 1) . ' KB',
                'rel'   => 'images/' . $rel,
            ];
        }

        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'path'    => $relPath,
            'dirs'    => $dirs,
            'files'   => $files,
        ]);
        $this->app->close();
    }

    /**
     * Persist add-on plugin params (YooTheme, Falang) into #__extensions.params.
     *
     * Called at the end of save() when the Settings form includes an `addon_params`
     * JSON field populated by settings.js from [data-addon-plugin] containers.
     *
     * Only whitelisted plugin elements are processed; unknown keys are silently skipped.
     * Existing params are deep-merged so that fields absent from the form are preserved.
     *
     * Failures are silenced — add-on param save must never break the main settings save.
     *
     * @param \Joomla\Database\DatabaseInterface $db
     * @param string $addonParamsJson  JSON: {"aiboost_yootheme": {...}, "aiboost_falang": {...}}
     */
    private function saveAddonPluginParams(
        \Joomla\Database\DatabaseInterface $db,
        string $addonParamsJson
    ): void {
        try {
            $allAddonParams = json_decode($addonParamsJson, true);
            if (!is_array($allAddonParams) || empty($allAddonParams)) {
                return;
            }

            // Strict whitelist — only our own add-on plugins
            $allowedPlugins = ['aiboost_yootheme', 'aiboost_falang'];

            foreach ($allAddonParams as $pluginElement => $params) {
                if (!in_array($pluginElement, $allowedPlugins, true)) {
                    continue;
                }
                if (!is_array($params) || empty($params)) {
                    continue;
                }

                // Load existing params from #__extensions so we can deep-merge
                $query = $db->getQuery(true)
                    ->select($db->quoteName('params'))
                    ->from($db->quoteName('#__extensions'))
                    ->where($db->quoteName('type')    . ' = ' . $db->quote('plugin'))
                    ->where($db->quoteName('folder')  . ' = ' . $db->quote('system'))
                    ->where($db->quoteName('element') . ' = ' . $db->quote($pluginElement));

                $db->setQuery($query);
                $existingJson = $db->loadResult();

                $existing = [];
                if ($existingJson) {
                    $decoded = json_decode($existingJson, true);
                    if (is_array($decoded)) {
                        $existing = $decoded;
                    }
                }

                // Falang: collapse per-lang checkboxes (falang_lang_sel_*) into JSON array.
                // Key suffix is sanitised SEF (hyphens→underscores); restore on save.
                if ($pluginElement === 'aiboost_falang') {
                    $enabledSefs = [];
                    $selKeys     = [];
                    foreach ($params as $k => $v) {
                        if (str_starts_with($k, 'falang_lang_sel_')) {
                            $selKeys[] = $k;
                            if ($v === '1') {
                                $enabledSefs[] = str_replace(
                                    '_', '-',
                                    substr($k, strlen('falang_lang_sel_'))
                                );
                            }
                        }
                    }
                    foreach ($selKeys as $selKey) {
                        unset($params[$selKey]);
                    }
                    if (!empty($selKeys)) {
                        $params['falang_enabled_languages'] = json_encode($enabledSefs, JSON_UNESCAPED_UNICODE);
                    }
                }

                $merged = array_merge($existing, $params);
                foreach (array_keys($merged) as $k) {
                    if (str_starts_with((string) $k, 'falang_lang_sel_')) {
                        unset($merged[$k]);
                    }
                }
                $mergedJson = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                $updateQuery = $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('params') . ' = ' . $db->quote($mergedJson))
                    ->where($db->quoteName('type')    . ' = ' . $db->quote('plugin'))
                    ->where($db->quoteName('folder')  . ' = ' . $db->quote('system'))
                    ->where($db->quoteName('element') . ' = ' . $db->quote($pluginElement));

                $db->setQuery($updateQuery)->execute();
            }
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] saveAddonPluginParams failed: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: manually create / repair OG custom fields.
     * URL: index.php?option=com_aiboost&task=settings.repairOgFields&format=json
     */
    public function repairOgFields(): void
    {
        if (!Session::checkToken('get') && !Session::checkToken()) {
            $this->sendJsonResponse(false, 'Invalid security token.');
            return;
        }

        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJsonResponse(false, 'Access denied.');
            return;
        }

        try {
            $db      = Factory::getDbo();
            $context = 'com_content.article';
            $now     = Factory::getDate()->toSql();

            // Detect optional columns
            $prefix    = $db->getPrefix();
            $fieldCols = array_map(
                static fn($c) => (string) $c->Field,
                $db->setQuery('SHOW COLUMNS FROM `' . $prefix . 'fields`')->loadObjectList() ?: []
            );
            $hasFieldparams = in_array('fieldparams', $fieldCols, true);
            $hasOnlySubform = in_array('only_use_in_subform', $fieldCols, true);

            // Ensure field group
            $groupId = $this->ensureOgFieldGroupInline($db, $context, 'AI Boost — OpenGraph');

            $listOgTypeParams = json_encode(['options' => [
                ['name' => '— default (article) —', 'value' => ''],
                ['name' => 'Article',               'value' => 'article'],
                ['name' => 'Website',               'value' => 'website'],
                ['name' => 'Video',                 'value' => 'video.movie'],
                ['name' => 'Music',                 'value' => 'music.song'],
                ['name' => 'Product',               'value' => 'product'],
            ]]);
            $listTwitterCardParams = json_encode(['options' => [
                ['name' => '— default (summary_large_image) —', 'value' => ''],
                ['name' => 'Summary Large Image',               'value' => 'summary_large_image'],
                ['name' => 'Summary',                           'value' => 'summary'],
            ]]);

            $fieldDefs = [
                ['name' => 'aiboost_og_title',       'title' => 'AI Boost — OG Title',       'type' => 'text',    'description' => 'Override the og:title meta tag. Leave empty to use the article title.',            'fieldparams' => '{}',                               'ordering' => 1],
                ['name' => 'aiboost_og_description', 'title' => 'AI Boost — OG Description', 'type' => 'textarea','description' => 'Override the og:description meta tag for this article.',                           'fieldparams' => '{"rows":"3","cols":""}',            'ordering' => 2],
                ['name' => 'aiboost_og_image',       'title' => 'AI Boost — OG Image',       'type' => 'media',   'description' => 'Override og:image. Recommended size: 1200×630 px.',                              'fieldparams' => '{"directory":"","preview":"true"}','ordering' => 3],
                ['name' => 'aiboost_og_type',        'title' => 'AI Boost — OG Type',        'type' => 'list',    'description' => 'Override the og:type meta tag. Defaults to "article" for article pages.',          'fieldparams' => $listOgTypeParams,                  'ordering' => 4],
                ['name' => 'aiboost_og_video',       'title' => 'AI Boost — OG Video URL',   'type' => 'url',     'description' => 'Optional og:video URL. Enables video preview cards on Facebook and LinkedIn.',    'fieldparams' => '{}',                               'ordering' => 5],
                ['name' => 'aiboost_twitter_card',   'title' => 'AI Boost — Twitter Card',   'type' => 'list',    'description' => 'Override the twitter:card type. Defaults to summary_large_image.',                 'fieldparams' => $listTwitterCardParams,             'ordering' => 6],
            ];

            $version = 'manual-' . date('Ymd');
            $note    = 'aiboost_version:' . $version;
            $created = $updated = $skipped = 0;

            foreach ($fieldDefs as $def) {
                $query = $db->getQuery(true)
                    ->select([$db->quoteName('id'), $db->quoteName('note')])
                    ->from('#__fields')
                    ->where($db->quoteName('name')    . '=' . $db->quote($def['name']))
                    ->where($db->quoteName('context') . '=' . $db->quote($context));
                $db->setQuery($query, 0, 1);
                $existing = $db->loadObject();

                if ($existing === null) {
                    $columns = ['asset_id', 'context', 'group_id', 'title', 'name', 'label', 'default_value', 'type', 'note', 'description', 'state', 'language', 'ordering', 'access', 'params'];
                    $values  = ['0', $db->quote($context), (string)(int)$groupId, $db->quote($def['title']), $db->quote($def['name']), $db->quote($def['title']), $db->quote(''), $db->quote($def['type']), $db->quote($note), $db->quote($def['description']), '1', $db->quote('*'), (string)(int)$def['ordering'], '1', $db->quote('{}')];
                    if ($hasFieldparams) { $columns[] = 'fieldparams'; $values[] = $db->quote($def['fieldparams']); }
                    if ($hasOnlySubform) { $columns[] = 'only_use_in_subform'; $values[] = '0'; }
                    $db->setQuery($db->getQuery(true)->insert('#__fields')->columns($columns)->values(implode(',', $values)))->execute();
                    $created++;
                } else {
                    // Force re-create: note marker uses 'manual-YYYYMMDD' so it never matches a real version
                    $updateQuery = $db->getQuery(true)
                        ->update('#__fields')
                        ->set($db->quoteName('group_id')    . '=' . (int)$groupId)
                        ->set($db->quoteName('title')       . '=' . $db->quote($def['title']))
                        ->set($db->quoteName('label')       . '=' . $db->quote($def['title']))
                        ->set($db->quoteName('description') . '=' . $db->quote($def['description']))
                        ->set($db->quoteName('type')        . '=' . $db->quote($def['type']))
                        ->set($db->quoteName('ordering')    . '=' . (int)$def['ordering'])
                        ->set($db->quoteName('state')       . '=1')
                        ->set($db->quoteName('note')        . '=' . $db->quote($note))
                        ->where($db->quoteName('id') . '=' . (int)$existing->id);
                    if ($hasFieldparams) {
                        $updateQuery->set($db->quoteName('fieldparams') . '=' . $db->quote($def['fieldparams']));
                    }
                    $db->setQuery($updateQuery)->execute();
                    $updated++;
                }
            }

            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'message' => "OG fields: {$created} created, {$updated} updated, {$skipped} skipped. Check Content → Fields → Articles.",
            ]);
            $this->app->close();
        } catch (\Throwable $e) {
            $this->sendJsonResponse(false, 'Error: ' . $e->getMessage());
        }
    }

    private function ensureOgFieldGroupInline(
        \Joomla\Database\DatabaseInterface $db,
        string $context,
        string $title
    ): int {
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from('#__fields_groups')
            ->where($db->quoteName('context') . '=' . $db->quote($context))
            ->where($db->quoteName('title')   . '=' . $db->quote($title));
        $db->setQuery($query, 0, 1);
        $id = (int)($db->loadResult() ?? 0);

        if ($id > 0) {
            return $id;
        }

        // Detect columns to avoid issues with Joomla version differences
        $prefix    = $db->getPrefix();
        $groupCols = array_map(
            static fn($c) => (string)$c->Field,
            $db->setQuery('SHOW COLUMNS FROM `' . $prefix . 'fields_groups`')->loadObjectList() ?: []
        );

        $columns = ['asset_id', 'context', 'title', 'description', 'state', 'language', 'ordering', 'params'];
        $values  = ['0', $db->quote($context), $db->quote($title), $db->quote('AI Boost per-article OpenGraph override fields'), '1', $db->quote('*'), '1', $db->quote('{}')];

        if (in_array('note', $groupCols, true)) {
            $columns[] = 'note';
            $values[]  = $db->quote('');
        }
        if (in_array('access', $groupCols, true)) {
            $columns[] = 'access';
            $values[]  = '1';
        }

        $db->setQuery(
            $db->getQuery(true)
                ->insert('#__fields_groups')
                ->columns($columns)
                ->values(implode(',', $values))
        )->execute();

        return (int)$db->insertid();
    }

    /**
     * Return a JSON list of all published Joomla languages.
     * URL: index.php?option=com_aiboost&task=settings.getLanguages&format=json
     */
    public function getLanguages(): void
    {
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJsonResponse(false, 'Access denied.');
            return;
        }
        try {
            $langService = new LanguageService(new JoomlaAppContext(), Factory::getDbo());
            // Use getInstalledLanguages() — shows all installed language packs
            // regardless of whether Joomla Multilanguage routing is active.
            $rawLangs    = $langService->getInstalledLanguages();
            // Convert stdObject list to plain assoc arrays for JSON output
            $languages   = array_map(static fn($l) => [
                'lang_code' => $l->lang_code,
                'title'     => $l->title,
                'sef'       => $l->sef,
                'image'     => $l->image ?? '',
            ], $rawLangs);
            $defaultLang = (string) $this->app->get('language', 'en-GB');

            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode(['success' => true, 'languages' => $languages, 'default_lang' => $defaultLang]);
            $this->app->close();
        } catch (\Throwable $e) {
            $this->sendJsonResponse(false, 'Error loading languages: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: return the merged manifest + plugin capabilities map.
     * Used by the Vue SPA to render locked Pro/integration placeholders and
     * decide whether a given field should be editable.
     *
     * Response shape:
     *   { success: true, capabilities: {...}, fields: [...] }
     */
    public function capabilities(): void
    {
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJsonResponse(false, 'Access denied.');
            return;
        }
        try {
            // Force fresh scan in case a plugin was installed/enabled mid-session
            \AiBoost\Lib\PluginRegistry::reset();
            \AiBoost\Lib\Manifest\Registry::reset();

            $payload = \AiBoost\Lib\Manifest\Registry::payload();
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode(array_merge(['success' => true], $payload));
            $this->app->close();
        } catch (\Throwable $e) {
            $this->sendJsonResponse(false, 'Error loading capabilities: ' . $e->getMessage());
        }
    }

    /**
     * Return current settings + all stored translations as JSON.
     * URL: index.php?option=com_aiboost&task=settings.getSettings&format=json
     */
    public function getSettings(): void
    {
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJsonResponse(false, 'Access denied.');
            return;
        }
        try {
            $db = Factory::getDbo();

            $q        = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from($db->quoteName('#__aiboost_settings'))
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
            $raw      = (string) $db->setQuery($q)->loadResult();
            $settings = json_decode($raw, true) ?: [];

            $q2   = $db->getQuery(true)
                ->select([$db->quoteName('field_key'), $db->quoteName('lang_code'), $db->quoteName('field_value')])
                ->from($db->quoteName('#__aiboost_translations'))
                ->order($db->quoteName('field_key') . ' ASC, ' . $db->quoteName('lang_code') . ' ASC');
            $rows = $db->setQuery($q2)->loadObjectList() ?: [];

            $translations = [];
            foreach ($rows as $row) {
                $fk = (string) $row->field_key;
                $lc = (string) $row->lang_code;
                if (!isset($translations[$fk])) {
                    $translations[$fk] = [];
                }
                $translations[$fk][$lc] = (string) $row->field_value;
            }

            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode(['success' => true, 'settings' => $settings, 'translations' => $translations]);
            $this->app->close();
        } catch (\Throwable $e) {
            $this->sendJsonResponse(false, 'Error loading settings: ' . $e->getMessage());
        }
    }

    /**
     * Server-side Pro check for the settings save endpoint.
     *
     * Delegates to the canonical PluginRegistry::isProActive() so save
     * enforcement uses the SAME verified-license signal as the admin
     * bootstrap and runtime gates — a Pro install behaves exactly like Free
     * until a key is verified, and Pro saving is hard-disabled on license
     * expiry / failed heartbeat. The previous `license_tier`-only check was
     * DRIFT: it leaked Pro saving in the lapsed-license window and ignored
     * `dev_force_free_tier`.
     *
     * @param array<string,mixed> $settings The existing settings row (pre-merge).
     */
    private function isProSetting(array $settings): bool
    {
        if (class_exists('AiBoost\\Lib\\PluginRegistry')) {
            return \AiBoost\Lib\PluginRegistry::isProActive($settings);
        }
        // Fail-closed fallback if the lib is somehow unavailable.
        return (string) ($settings['dev_license_preview'] ?? '0') === '1';
    }

    /**
     * Persist per-language translations from the Vue admin settings save.
     *
     * Payload format: JSON blob {fieldKey: {langCode: value, ...}, ...}
     * Empty-string values delete the row; non-empty values upsert.
     */
    private function saveTranslations(\Joomla\Database\DatabaseInterface $db, string $rawJson, string $now): void
    {
        $data = json_decode($rawJson, true);
        if (!is_array($data)) {
            return;
        }

        foreach ($data as $fieldKey => $langMap) {
            if (!is_array($langMap)) {
                continue;
            }
            $fieldKey = substr((string) preg_replace('/[^a-z0-9_]/', '', strtolower((string) $fieldKey)), 0, 100);
            if ($fieldKey === '') {
                continue;
            }
            foreach ($langMap as $langCode => $value) {
                $langCode = substr((string) preg_replace('/[^a-zA-Z0-9\-]/', '', (string) $langCode), 0, 10);
                $value    = trim((string) $value);
                if ($langCode === '') {
                    continue;
                }

                if ($value === '') {
                    $db->setQuery(
                        $db->getQuery(true)
                            ->delete($db->quoteName('#__aiboost_translations'))
                            ->where($db->quoteName('field_key') . ' = ' . $db->quote($fieldKey))
                            ->where($db->quoteName('lang_code') . ' = ' . $db->quote($langCode))
                    )->execute();
                } else {
                    $existId = (int) $db->setQuery(
                        $db->getQuery(true)
                            ->select($db->quoteName('id'))
                            ->from($db->quoteName('#__aiboost_translations'))
                            ->where($db->quoteName('field_key') . ' = ' . $db->quote($fieldKey))
                            ->where($db->quoteName('lang_code') . ' = ' . $db->quote($langCode))
                    )->loadResult();

                    if ($existId > 0) {
                        $db->setQuery(
                            $db->getQuery(true)
                                ->update($db->quoteName('#__aiboost_translations'))
                                ->set($db->quoteName('field_value') . ' = ' . $db->quote($value))
                                ->set($db->quoteName('updated_at') . ' = ' . $db->quote($now))
                                ->where($db->quoteName('id') . ' = ' . $existId)
                        )->execute();
                    } else {
                        $db->setQuery(
                            $db->getQuery(true)
                                ->insert($db->quoteName('#__aiboost_translations'))
                                ->columns([
                                    $db->quoteName('field_key'),
                                    $db->quoteName('lang_code'),
                                    $db->quoteName('field_value'),
                                    $db->quoteName('created_at'),
                                    $db->quoteName('updated_at'),
                                ])
                                ->values(
                                    $db->quote($fieldKey) . ', ' .
                                    $db->quote($langCode) . ', ' .
                                    $db->quote($value)    . ', ' .
                                    $db->quote($now)      . ', ' .
                                    $db->quote($now)
                                )
                        )->execute();
                    }
                }
            }
        }
    }

    private function sendJsonResponse(bool $success, string $message): void
    {
        $this->emitJson(['success' => $success, 'message' => $message]);
    }

    /**
     * Emit a JSON payload as the complete response for an AJAX endpoint.
     *
     * Discards any stray output that may already sit in the output buffer —
     * PHP warnings/notices/deprecations rendered as HTML (`<br /> <b>…`),
     * or echoes from a system plugin firing during the request lifecycle.
     * Such pre-output would otherwise be prepended to the JSON body and make
     * the client fail with "Unexpected token '<', "<br /> <b>"… is not valid
     * JSON". Cleaning the buffer guarantees a parseable response.
     *
     * @param array<string,mixed> $payload
     */
    private function emitJson(array $payload, int $flags = 0): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo json_encode($payload, $flags);
        $this->app->close();
    }

    /**
     * Preview the currently served robots.txt and return body + line-by-line analysis.
     *
     * URL: index.php?option=com_aiboost&task=settings.previewRobots&format=json
     */
    public function previewRobots(): void
    {
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->emitJson(['success' => false, 'message' => 'Access denied.']);
            return;
        }

        try {
            $robotsUrl = rtrim((string) \Joomla\CMS\Uri\Uri::root(), '/') . '/robots.txt';

            $body  = '';
            $code  = 0;
            $error = '';

            if (\function_exists('curl_init')) {
                $ch = curl_init($robotsUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT        => 12,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_USERAGENT      => 'AIBoost-Admin-Preview/1.0',
                ]);
                $body  = (string) curl_exec($ch);
                $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($body === '' || $body === false) {
                    $error = (string) curl_error($ch);
                }
                curl_close($ch);
            } else {
                $ctx  = stream_context_create(['http' => ['timeout' => 12, 'user_agent' => 'AIBoost-Admin-Preview/1.0']]);
                $body = (string) @file_get_contents($robotsUrl, false, $ctx);
            }

            // Fallback: read on-disk robots.txt directly.
            $source = 'http';
            if ($body === '' || ($code !== 0 && $code >= 400)) {
                $file = JPATH_ROOT . '/robots.txt';
                if (is_readable($file)) {
                    $body   = (string) @file_get_contents($file);
                    $source = 'disk';
                    $code   = 200;
                }
            }

            if ($body === '') {
                $this->emitJson([
                    'success' => false,
                    'message' => 'Could not fetch robots.txt (HTTP ' . $code . '). ' . $error
                              . ' Make sure robots.txt management is enabled or that a physical robots.txt file exists at the site root.',
                    'url'     => $robotsUrl,
                ]);
                return;
            }

            $sitemapUrl = rtrim((string) \Joomla\CMS\Uri\Uri::root(), '/') . '/sitemap.xml';
            $analysis   = $this->analyzeRobotsTxt($body, $sitemapUrl);

            $this->emitJson([
                'success'      => true,
                'url'          => $robotsUrl,
                'source'       => $source,
                'body'         => $body,
                'size_bytes'   => strlen($body),
                'sitemap_url'  => $sitemapUrl,
                'lines'        => $analysis['lines'],
                'summary'      => $analysis['summary'],
                'issues'       => $analysis['issues'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] previewRobots error: ' . $e->getMessage());
            $this->emitJson(['success' => false, 'message' => 'Preview failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Annotate every line of robots.txt and surface common issues.
     *
     * @return array{lines: array<int, array<string,mixed>>, summary: array<string,mixed>, issues: array<int,array<string,string>>}
     */
    private function analyzeRobotsTxt(string $body, string $expectedSitemapUrl): array
    {
        $rawLines = preg_split("/\r\n|\n|\r/", $body) ?: [];
        $lines    = [];

        $userAgents      = [];
        $sitemaps        = [];
        $globalAllowAll  = false;
        $hasUserAgent    = false;
        $currentAgent    = null;
        $perAgentBlockAll = []; // ['*' => true, 'GPTBot' => true]
        $hasAnyRule      = false;
        $hasCrawlDelay   = false;

        foreach ($rawLines as $i => $raw) {
            $trim    = trim($raw);
            $annot   = '';
            $kind    = 'blank';
            $level   = 'info';

            if ($trim === '') {
                $kind  = 'blank';
                $annot = '';
            } elseif (str_starts_with($trim, '#')) {
                $kind  = 'comment';
                $annot = 'Comment — ignored by crawlers.';
            } else {
                // Split "Directive: value" (case-insensitive directive).
                if (!preg_match('/^([A-Za-z\-]+)\s*:\s*(.*)$/', $trim, $m)) {
                    $kind  = 'invalid';
                    $level = 'warn';
                    $annot = 'Not a valid directive (expected "Name: value"). Crawlers will ignore this line.';
                } else {
                    $directive = strtolower($m[1]);
                    $value     = trim($m[2]);

                    switch ($directive) {
                        case 'user-agent':
                            $kind         = 'user-agent';
                            $hasUserAgent = true;
                            $currentAgent = $value;
                            $userAgents[] = $value;
                            $annot = $value === '*'
                                ? 'Applies to all crawlers that do not have a more specific block below.'
                                : 'Begins a rule block that applies only to the "' . $value . '" crawler.';
                            break;

                        case 'allow':
                            $kind  = 'allow';
                            $hasAnyRule = true;
                            $annot = $value === ''
                                ? '(empty Allow) — has no effect.'
                                : 'Explicitly permits crawling of paths starting with "' . $value . '".';
                            break;

                        case 'disallow':
                            $kind  = 'disallow';
                            $hasAnyRule = true;
                            if ($value === '') {
                                $annot = '(empty Disallow) — equivalent to "allow everything" for this user-agent.';
                                if ($currentAgent === '*') {
                                    $globalAllowAll = true;
                                }
                            } elseif ($value === '/') {
                                $annot = '⛔ Blocks the ENTIRE site for the current user-agent.';
                                $level = $currentAgent === '*' ? 'danger' : 'warn';
                                if ($currentAgent !== null) {
                                    $perAgentBlockAll[$currentAgent] = true;
                                }
                            } else {
                                $annot = 'Asks well-behaved crawlers not to fetch URLs starting with "' . $value . '".';
                            }
                            break;

                        case 'sitemap':
                            $kind = 'sitemap';
                            $sitemaps[] = $value;
                            $annot = $value === ''
                                ? '(empty Sitemap) — has no effect. Remove or fill in the URL.'
                                : 'Tells search engines where to find your XML sitemap. Picked up by Google and Bing on every robots.txt fetch.';
                            if ($value === '') { $level = 'warn'; }
                            break;

                        case 'crawl-delay':
                            $kind = 'crawl-delay';
                            $hasCrawlDelay = true;
                            $annot = 'Asks crawlers to wait ' . $value . 's between requests. Ignored by Google; honored by Bing, Yandex, Seznam.';
                            $level = 'info';
                            break;

                        case 'host':
                            $kind = 'host';
                            $annot = 'Non-standard Yandex directive that sets the preferred host. Ignored by Google.';
                            break;

                        case 'clean-param':
                        case 'noindex':
                            $kind = 'nonstandard';
                            $level = 'warn';
                            $annot = '"' . ucfirst($directive) . '" is non-standard / unsupported by Google. Use a meta robots tag or X-Robots-Tag header instead.';
                            break;

                        default:
                            $kind = 'unknown';
                            $level = 'warn';
                            $annot = 'Unknown directive "' . $m[1] . '" — most crawlers will ignore this line.';
                    }
                }
            }

            $lines[] = [
                'n'     => $i + 1,
                'text'  => $raw,
                'kind'  => $kind,
                'level' => $level,
                'note'  => $annot,
            ];
        }

        /* ── Issue detection ─────────────────────────────────────────── */

        $issues = [];

        if (!$hasUserAgent) {
            $issues[] = [
                'id'     => 'no-user-agent',
                'level'  => 'danger',
                'title'  => 'No User-agent directive',
                'detail' => 'robots.txt has no User-agent line. Crawlers will treat every line as a syntax error and may default to allow-all.',
                'fix'    => 'add-user-agent-star',
            ];
        }

        if (isset($perAgentBlockAll['*'])) {
            $issues[] = [
                'id'     => 'block-all',
                'level'  => 'danger',
                'title'  => 'Site is fully blocked from search engines',
                'detail' => 'A "Disallow: /" rule applies to User-agent: *. Search engines will stop crawling your entire site. Remove the rule unless this is intentional (staging site).',
                'fix'    => 'remove-block-all',
            ];
        }

        if (empty($sitemaps)) {
            $issues[] = [
                'id'     => 'no-sitemap',
                'level'  => 'warn',
                'title'  => 'No Sitemap directive',
                'detail' => 'Add a Sitemap line so Google and Bing discover your XML sitemap automatically on every robots.txt fetch.',
                'fix'    => 'add-sitemap',
            ];
        } else {
            $hasExpected = false;
            foreach ($sitemaps as $sm) {
                if ($sm === $expectedSitemapUrl) {
                    $hasExpected = true;
                    break;
                }
            }
            if (!$hasExpected) {
                $issues[] = [
                    'id'     => 'sitemap-mismatch',
                    'level'  => 'warn',
                    'title'  => 'Sitemap URL does not match this site',
                    'detail' => 'The Sitemap line(s) (' . implode(', ', $sitemaps) . ') do not include the AI Boost sitemap URL (' . $expectedSitemapUrl . ').',
                    'fix'    => 'add-sitemap',
                ];
            }
        }

        if (!$hasAnyRule && $hasUserAgent) {
            $issues[] = [
                'id'     => 'empty-block',
                'level'  => 'info',
                'title'  => 'User-agent declared but no rules',
                'detail' => 'A User-agent block is present but contains no Allow / Disallow lines — it has no effect on crawling.',
                'fix'    => null,
            ];
        }

        $summary = [
            'user_agents'      => array_values(array_unique($userAgents)),
            'sitemaps'         => $sitemaps,
            'rule_count'       => $hasAnyRule ? 1 : 0,
            'block_all_agents' => array_keys($perAgentBlockAll),
            'has_crawl_delay'  => $hasCrawlDelay,
            'line_count'       => count($lines),
        ];

        return ['lines' => $lines, 'summary' => $summary, 'issues' => $issues];
    }

    /**
     * Preview the currently served sitemap.xml and return XML body + stats.
     *
     * URL: index.php?option=com_aiboost&task=settings.previewSitemap&format=json
     *
     * Performs a server-side HTTP GET against the site's own /sitemap.xml so the
     * response is exactly what search engines see. Falls back with a clear error
     * if the sitemap is disabled or unreachable.
     */
    public function previewSitemap(): void
    {
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->emitJson(['success' => false, 'message' => 'Access denied.']);
            return;
        }

        try {
            $sitemapUrl = rtrim((string) \Joomla\CMS\Uri\Uri::root(), '/') . '/sitemap.xml';

            $xml   = '';
            $error = '';
            $code  = 0;

            if (\function_exists('curl_init')) {
                $ch = curl_init($sitemapUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT        => 12,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_USERAGENT      => 'AIBoost-Admin-Preview/1.0',
                ]);
                $xml  = (string) curl_exec($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($xml === '' || $xml === false) {
                    $error = (string) curl_error($ch);
                }
                curl_close($ch);
            } else {
                $ctx = stream_context_create(['http' => ['timeout' => 12, 'user_agent' => 'AIBoost-Admin-Preview/1.0']]);
                $xml = @file_get_contents($sitemapUrl, false, $ctx);
                if ($xml === false) {
                    $error = 'file_get_contents failed';
                    $xml   = '';
                }
            }

            if ($xml === '' || ($code !== 0 && $code >= 400)) {
                $this->emitJson([
                    'success' => false,
                    'message' => 'Could not fetch sitemap (HTTP ' . $code . '). ' . $error
                              . ' Make sure XML Sitemap is enabled and that the server can reach itself at ' . $sitemapUrl,
                    'url'     => $sitemapUrl,
                ]);
                return;
            }

            $stats    = $this->analyzeSitemapXml($xml);
            $warnings = $this->buildSitemapWarnings($stats, $xml);

            $this->emitJson([
                'success'  => true,
                'url'      => $sitemapUrl,
                'xml'      => $xml,
                'size_kb'  => round(strlen($xml) / 1024, 1),
                'stats'    => $stats,
                'warnings' => $warnings,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] previewSitemap error: ' . $e->getMessage());
            $this->emitJson(['success' => false, 'message' => 'Preview failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Parse a sitemap XML string and compute admin-facing statistics.
     *
     * @return array<string,mixed>
     */
    private function analyzeSitemapXml(string $xml): array
    {
        $stats = [
            'url_count'        => 0,
            'image_count'      => 0,
            'hreflang_groups'  => 0,
            'hreflang_links'   => 0,
            'latest_lastmod'   => null,
            'oldest_lastmod'   => null,
            'is_index'         => false,
            'sitemap_count'    => 0,
            'languages'        => [],
            'changefreq_dist'  => [],
            'top_paths'        => [],
        ];

        if ($xml === '') {
            return $stats;
        }

        $prev = libxml_use_internal_errors(true);
        $sx   = @simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if ($sx === false) {
            return $stats;
        }

        $root = strtolower($sx->getName());

        if ($root === 'sitemapindex') {
            $stats['is_index']      = true;
            $stats['sitemap_count'] = isset($sx->sitemap) ? count($sx->sitemap) : 0;
            return $stats;
        }

        if ($root !== 'urlset') {
            return $stats;
        }

        $stats['image_count'] = substr_count($xml, '<image:image');

        $langs   = [];
        $changes = [];
        $paths   = [];

        foreach ($sx->url as $url) {
            $stats['url_count']++;

            $loc = (string) $url->loc;
            if ($loc !== '') {
                $pathPart = parse_url($loc, PHP_URL_PATH) ?: '/';
                $first    = '/' . explode('/', trim($pathPart, '/'))[0];
                $paths[$first === '/' ? '(homepage)' : $first] =
                    (int) ($paths[$first === '/' ? '(homepage)' : $first] ?? 0) + 1;
            }

            $lastmod = (string) $url->lastmod;
            if ($lastmod !== '') {
                if ($stats['latest_lastmod'] === null || $lastmod > $stats['latest_lastmod']) {
                    $stats['latest_lastmod'] = $lastmod;
                }
                if ($stats['oldest_lastmod'] === null || $lastmod < $stats['oldest_lastmod']) {
                    $stats['oldest_lastmod'] = $lastmod;
                }
            }

            $cf = (string) $url->changefreq;
            if ($cf !== '') {
                $changes[$cf] = (int) ($changes[$cf] ?? 0) + 1;
            }

            $xhtml = $url->children('xhtml', true);
            if (isset($xhtml->link) && count($xhtml->link) > 0) {
                $stats['hreflang_groups']++;
                foreach ($xhtml->link as $lnk) {
                    $stats['hreflang_links']++;
                    $lang = (string) $lnk->attributes()->hreflang;
                    if ($lang !== '') {
                        $langs[$lang] = (int) ($langs[$lang] ?? 0) + 1;
                    }
                }
            }
        }

        ksort($langs);
        ksort($changes);
        arsort($paths);
        $stats['languages']       = $langs;
        $stats['changefreq_dist'] = $changes;
        $stats['top_paths']       = array_slice($paths, 0, 8, true);

        return $stats;
    }

    /**
     * @param array<string,mixed> $stats
     * @return string[]
     */
    private function buildSitemapWarnings(array $stats, string $xml): array
    {
        $w = [];

        if (!empty($stats['is_index'])) {
            if ((int) $stats['sitemap_count'] === 0) {
                $w[] = 'Sitemap index is empty — no child sitemaps listed.';
            }
            return $w;
        }

        if ((int) $stats['url_count'] === 0) {
            $w[] = 'Sitemap contains zero URLs. Check Content to Include toggles, exclusion lists, and that articles are published.';
        }
        if ((int) $stats['url_count'] > 50000) {
            $w[] = 'More than 50,000 URLs in a single sitemap — Google rejects this. Enable Sitemap Index (Pro) to split into chunks.';
        }
        if (strlen($xml) > 50 * 1024 * 1024) {
            $w[] = 'Sitemap exceeds 50 MB — Google rejects this size. Enable Sitemap Index to split.';
        }
        if ($stats['latest_lastmod'] !== null) {
            $ageDays = (int) floor((time() - strtotime((string) $stats['latest_lastmod'])) / 86400);
            if ($ageDays > 180) {
                $w[] = 'Latest lastmod is ' . $ageDays . ' days old — Google may consider this sitemap stale.';
            }
        }
        if (stripos($xml, 'noindex') !== false) {
            $w[] = 'Sitemap contains the string "noindex" — review your URLs, noindex pages should not be listed.';
        }
        if (empty($stats['hreflang_groups']) && count($stats['languages']) === 0) {
            // Not a warning — most sites are single-language. Just informational, skip.
        }

        return $w;
    }

    // ─────────────────────────────────────────────────────────────────────
    // LICENSE SIMULATOR (Task #432) — dev-only, gated on JDEBUG
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Return the current license_simulation map + the resolved capabilities
     * so the Dashboard simulator card can render its toggle state.
     * URL: index.php?option=com_aiboost&task=settings.simulatorGet&format=json
     * Method: POST (Session::checkToken() expects the form token in the body).
     */
    public function simulatorGet(): void
    {
        if (!$this->guardSimulator()) {
            return;
        }

        try {
            $payload = [
                'success'      => true,
                'states'       => PluginRegistry::SIM_STATES,
                'skus'         => PluginRegistry::SIM_SKUS,
                'simulation'   => PluginRegistry::loadSimulation(),
                'capabilities' => PluginRegistry::capabilities(),
            ];
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $this->app->close();
        } catch (\Throwable $e) {
            $this->sendJsonResponse(false, 'simulatorGet failed: ' . $e->getMessage());
        }
    }

    /**
     * Persist the license_simulation map. Body params:
     *   simulation[<sku>] = active|expired|disabled|not_licensed
     *   simulation[_domain_override] = string
     * URL: index.php?option=com_aiboost&task=settings.simulatorSave&format=json
     */
    public function simulatorSave(): void
    {
        if (!$this->guardSimulator()) {
            return;
        }

        try {
            $input = $this->app->getInput();
            $raw   = $input->get('simulation', [], 'array');
            $map   = [];
            foreach ($raw as $k => $v) {
                $map[(string) $k] = is_string($v) ? trim($v) : '';
            }
            PluginRegistry::saveSimulation($map);

            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode([
                'success'      => true,
                'message'      => 'Simulator saved.',
                'simulation'   => PluginRegistry::loadSimulation(),
                'capabilities' => PluginRegistry::capabilities(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $this->app->close();
        } catch (\Throwable $e) {
            $this->sendJsonResponse(false, 'simulatorSave failed: ' . $e->getMessage());
        }
    }

    // ── Task #429 — License Key Verify (per-SKU) ───────────────────────────

    /**
     * Return current per-SKU license_state map.
     * URL: index.php?option=com_aiboost&task=settings.licenseStateGet&format=json
     */
    public function licenseStateGet(): void
    {
        if (!$this->guardLicense()) {
            return;
        }

        try {
            $states = PluginRegistry::loadLicenseStates();
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'states'  => (object) $states,
                'mock'    => true,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $this->app->close();
        } catch (\Throwable $e) {
            $this->sendJsonResponse(false, 'licenseStateGet failed: ' . $e->getMessage());
        }
    }

    /**
     * Verify a license key for a single SKU. POST params:
     *   sku         — schema|og|hreflang|code|aeo|bundle
     *   license_key — raw key (mock prefixes: AB-VALID / AB-EXPIRED / AB-LIMIT / AB-DEACT)
     * URL: index.php?option=com_aiboost&task=settings.verifyLicense&format=json
     */
    public function verifyLicense(): void
    {
        if (!$this->guardLicense()) {
            return;
        }

        try {
            $input = $this->app->getInput();
            $sku   = strtolower(trim((string) $input->getString('sku', '')));
            $key   = trim((string) $input->getString('license_key', ''));

            if (!in_array($sku, ['schema', 'og', 'hreflang', 'code', 'aeo', 'bundle'], true)) {
                $this->sendJsonResponse(false, 'Unknown SKU.');
                return;
            }
            if ($key === '') {
                $this->sendJsonResponse(false, 'License key is required.');
                return;
            }

            $state = $this->mockValidateLicense($sku, $key);
            PluginRegistry::saveLicenseState($sku, $state);

            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'state'   => $state,
                'message' => $state['message'] ?? '',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $this->app->close();
        } catch (\Throwable $e) {
            $this->sendJsonResponse(false, 'verifyLicense failed: ' . $e->getMessage());
        }
    }

    /**
     * Release the license for a single SKU (sets status=deactivated).
     * URL: index.php?option=com_aiboost&task=settings.deactivateLicense&format=json
     */
    public function deactivateLicense(): void
    {
        if (!$this->guardLicense()) {
            return;
        }

        try {
            $input = $this->app->getInput();
            $sku   = strtolower(trim((string) $input->getString('sku', '')));
            if (!in_array($sku, ['schema', 'og', 'hreflang', 'code', 'aeo', 'bundle'], true)) {
                $this->sendJsonResponse(false, 'Unknown SKU.');
                return;
            }

            $state = [
                'key'         => '',
                'status'      => 'deactivated',
                'expires_at'  => null,
                'verified_at' => gmdate('c'),
                'activations_remaining' => null,
                'mock'        => true,
                'message'     => 'License released from this site.',
            ];
            PluginRegistry::saveLicenseState($sku, $state);

            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'state'   => $state,
                'message' => 'License released.',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $this->app->close();
        } catch (\Throwable $e) {
            $this->sendJsonResponse(false, 'deactivateLicense failed: ' . $e->getMessage());
        }
    }

    /**
     * Mock Lemon Squeezy validator — mirrors artifacts/api-server/src/routes/license.ts.
     * Prefix conventions:
     *   AB-VALID-*    → active (1y expiry, 4 activations remaining)
     *   AB-EXPIRED-*  → expired (30d ago)
     *   AB-LIMIT-*    → limit_reached (0 activations remaining)
     *   AB-DEACT-*    → deactivated
     *   anything else → invalid
     *
     * @return array<string,mixed>
     */
    private function mockValidateLicense(string $sku, string $key): array
    {
        $upper = strtoupper($key);
        $base  = [
            'key'                    => $key,
            'mock'                   => true,
            'verified_at'            => gmdate('c'),
            'expires_at'             => null,
            'activations_remaining'  => null,
            'status'                 => 'invalid',
            'message'                => '',
        ];

        if (str_starts_with($upper, 'AB-VALID')) {
            $base['status']                = 'active';
            $base['expires_at']            = gmdate('c', time() + 365 * 86400);
            $base['activations_remaining'] = 4;
            $base['message']               = 'License is active. Pro features for "' . $sku . '" are now unlocked.';
        } elseif (str_starts_with($upper, 'AB-EXPIRED')) {
            $base['status']     = 'expired';
            $base['expires_at'] = gmdate('c', time() - 30 * 86400);
            $base['message']    = 'License expired 30 days ago. Renew at aiboostnow.com/account.';
        } elseif (str_starts_with($upper, 'AB-LIMIT')) {
            $base['status']                = 'limit_reached';
            $base['expires_at']            = gmdate('c', time() + 365 * 86400);
            $base['activations_remaining'] = 0;
            $base['message']               = 'Activation limit reached for this license. Release a site at aiboostnow.com/account first.';
        } elseif (str_starts_with($upper, 'AB-DEACT')) {
            $base['status']  = 'deactivated';
            $base['message'] = 'License was deactivated. Re-activate from aiboostnow.com/account.';
        } else {
            $base['status']  = 'invalid';
            $base['message'] = 'License key not recognised. Mock prefixes: AB-VALID, AB-EXPIRED, AB-LIMIT, AB-DEACT.';
        }
        return $base;
    }

    /**
     * Manually trigger a license heartbeat — used by the "Verify now" button
     * on the Licenses tab. Bypasses the 7-day shouldRun() throttle so the
     * admin can force a fresh check after re-entering a key.
     * URL: index.php?option=com_aiboost&task=settings.heartbeatRun&format=json
     */
    public function heartbeatRun(): void
    {
        if (!$this->guardLicense()) {
            return;
        }

        try {
            $db    = $this->app->getDocument() ? \Joomla\CMS\Factory::getDbo() : \Joomla\CMS\Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from($db->quoteName('#__aiboost_settings'))
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
            $row   = $db->setQuery($query)->loadResult();
            $settings = $row ? (json_decode((string) $row, true) ?? []) : [];

            $verdict = \AiBoost\Lib\LicenseHeartbeat::execute($settings);

            // Re-load to get the freshly persisted license_heartbeat blob.
            // Task #565 — display only: just the next-check countdown. The
            // verdict/status/expiry drive the renewal notice; Pro stays
            // unlocked regardless (perpetual activation).
            $row2 = $db->setQuery($query)->loadResult();
            $settings2 = $row2 ? (json_decode((string) $row2, true) ?? []) : [];
            $hb = is_array($settings2['license_heartbeat'] ?? null) ? $settings2['license_heartbeat'] : [];
            $hb['days_until_next_check']   = \AiBoost\Lib\LicenseHeartbeat::daysUntilNextCheck($settings2);

            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode([
                'success'   => true,
                'verdict'   => $verdict ?: null,
                'heartbeat' => $hb,
                'message'   => $verdict
                    ? ($verdict['message'] ?? 'Heartbeat completed.')
                    : 'Heartbeat failed (network error or no active license key). See debug log.',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $this->app->close();
        } catch (\Throwable $e) {
            $this->sendJsonResponse(false, 'heartbeatRun failed: ' . $e->getMessage());
        }
    }

    /**
     * Permission + CSRF guard for the License endpoints. Unlike the
     * simulator, this is available in production (not gated on JDEBUG).
     */
    private function guardLicense(): bool
    {
        // Accept token from both GET (used by licenseStateGet on page load)
        // and POST (verifyLicense, deactivateLicense, heartbeatRun).
        if (!Session::checkToken('get') && !Session::checkToken()) {
            $this->sendJsonResponse(false, 'Invalid security token.');
            return false;
        }
        $identity = $this->app->getIdentity();
        if (!$identity || !$identity->authorise('core.manage', 'com_aiboost')) {
            $this->sendJsonResponse(false, 'Access denied.');
            return false;
        }
        return true;
    }

    /**
     * Common guard for simulator endpoints. Returns true when the request
     * may proceed, false (and emits a JSON error) otherwise.
     */
    private function guardSimulator(): bool
    {
        // Hard gate: only when Joomla debug mode is on.
        if (!(defined('JDEBUG') && JDEBUG === true)) {
            $this->sendJsonResponse(false, 'License Simulator is only available when Joomla debug mode is on.');
            return false;
        }
        if (!Session::checkToken()) {
            $this->sendJsonResponse(false, 'Invalid security token.');
            return false;
        }
        $identity = $this->app->getIdentity();
        if (!$identity || !$identity->authorise('core.manage', 'com_aiboost')) {
            $this->sendJsonResponse(false, 'Access denied.');
            return false;
        }
        return true;
    }
}
