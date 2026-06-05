<?php
/**
 * @package     AiBoost\Component\AiBoost\Administrator\Controller
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Component\AiBoost\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

class ImportController extends BaseController
{
    private const FORMAT_VERSION = '1.0';
    private const MAX_FILE_SIZE  = 5 * 1024 * 1024;

    /**
     * Keys that are NEVER imported from an uploaded file.
     *
     * License state + per-site identity + dev overrides must always come from
     * the destination install's own verified state, never from a JSON file.
     * Without this denylist a hand-crafted export could set
    * license_state[*].status=active and forge entitlement state, or clobber
    * the unique per-site install_id. The destination's
     * existing values for these keys are preserved on import.
     *
     * @var string[]
     */
    private const IMPORT_DENYLIST = [
        'license_key',
        'license_tier',
        'license_state',
        'license_simulation',
        'pro_skus',
        'dev_license_preview',
        'dev_force_free_tier',
        'install_id',
        'last_backup_at',
    ];

    public function upload(): void
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
            $input = $this->app->getInput();
            $files = $input->files->get('ab_import_file', [], 'array');

            if (empty($files) || empty($files['tmp_name']) || !is_uploaded_file($files['tmp_name'])) {
                $this->sendJsonResponse(false, 'No file uploaded.');
                return;
            }

            if ((int) ($files['size'] ?? 0) > self::MAX_FILE_SIZE) {
                $this->sendJsonResponse(false, 'File too large (max 5 MB).');
                return;
            }

            $json = file_get_contents($files['tmp_name']);
            if ($json === false || $json === '') {
                $this->sendJsonResponse(false, 'Could not read uploaded file.');
                return;
            }

            $data = json_decode($json, true);
            if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
                $this->sendJsonResponse(false, 'Invalid JSON file. Please use the export from AI Boost.');
                return;
            }

            // --- Read export metadata ---
            $meta          = isset($data['meta']) && is_array($data['meta']) ? $data['meta'] : [];
            $formatVersion = trim((string) ($meta['version'] ?? ''));
            $exportPlugin  = trim((string) ($meta['plugin'] ?? 'unknown'));
            $isModern      = ($exportPlugin === 'pkg_aiboost');

            // meta.version is the EXPORT-FORMAT version (currently "1.0"), not
            // the plugin version. Only warn if a modern export declares a
            // newer format than this build understands; never block on it.
            $versionWarning = '';
            if ($isModern && $formatVersion !== ''
                && version_compare($formatVersion, self::FORMAT_VERSION, '>')) {
                $versionWarning = sprintf(
                    ' (Note: file uses a newer export format %s — some keys may be ignored.)',
                    $formatVersion
                );
            }

            // --- Locate and normalise the settings payload ---
            $settings = $this->extractSettings($data, $isModern);

            if ($settings === null || $settings === []) {
                $this->sendJsonResponse(false, 'Invalid export format: settings payload not found or empty.');
                return;
            }

            // --- Strip license / identity / dev-override keys (security) ---
            // These must come from the destination's own verified state, never
            // from an uploaded file. See self::IMPORT_DENYLIST.
            $skipped = [];
            foreach (self::IMPORT_DENYLIST as $denied) {
                if (array_key_exists($denied, $settings)) {
                    unset($settings[$denied]);
                    $skipped[] = $denied;
                }
            }

            if ($settings === []) {
                $this->sendJsonResponse(
                    false,
                    'Nothing to import: the file contained only license/identity keys, which are never imported.'
                );
                return;
            }

            // --- Persist settings (merge over existing, never blind-overwrite) ---
            // Merging preserves destination-only keys (e.g. install_id,
            // license_state) and lets settings added after the source export
            // keep their runtime defaults.
            $db  = Factory::getDbo();
            $now = Factory::getDate()->toSql();

            $existingRow = $db->setQuery(
                $db->getQuery(true)->select(['id', 'settings_json'])->from('#__aiboost_settings')
                    ->where($db->quoteName('setting_key') . '=' . $db->quote('main'))
            )->loadObject();

            $existing = [];
            if ($existingRow && !empty($existingRow->settings_json)) {
                $decoded = json_decode($existingRow->settings_json, true);
                if (is_array($decoded)) {
                    $existing = $decoded;
                }
            }

            $merged       = array_merge($existing, $settings);
            $settingsJson = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if ($existingRow && (int) $existingRow->id) {
                $db->setQuery(
                    $db->getQuery(true)
                        ->update('#__aiboost_settings')
                        ->set($db->quoteName('settings_json') . '=' . $db->quote($settingsJson))
                        ->set($db->quoteName('updated_at') . '=' . $db->quote($now))
                        ->where($db->quoteName('id') . '=' . (int) $existingRow->id)
                )->execute();
            } else {
                $db->setQuery(
                    $db->getQuery(true)
                        ->insert('#__aiboost_settings')
                        ->columns(['setting_key', 'settings_json', 'created_at', 'updated_at'])
                        ->values(
                            $db->quote('main') . ',' . $db->quote($settingsJson) . ','
                            . $db->quote($now) . ',' . $db->quote($now)
                        )
                )->execute();
            }

            // --- Persist translations (upsert per field_key + lang_code) ---
            $translations     = isset($data['translations']) && is_array($data['translations'])
                ? $data['translations'] : [];
            $translationCount = 0;

            foreach ($translations as $row) {
                if (empty($row['field_key']) || empty($row['lang_code'])) {
                    continue;
                }
                $tid = (int) $db->setQuery(
                    $db->getQuery(true)->select('id')->from('#__aiboost_translations')
                        ->where($db->quoteName('field_key') . '=' . $db->quote($row['field_key']))
                        ->where($db->quoteName('lang_code') . '=' . $db->quote($row['lang_code']))
                )->loadResult();

                if ($tid) {
                    $db->setQuery(
                        $db->getQuery(true)
                            ->update('#__aiboost_translations')
                            ->set($db->quoteName('field_value') . '=' . $db->quote($row['field_value'] ?? ''))
                            ->set($db->quoteName('updated_at') . '=' . $db->quote($now))
                            ->where($db->quoteName('id') . '=' . $tid)
                    )->execute();
                } else {
                    $db->setQuery(
                        $db->getQuery(true)
                            ->insert('#__aiboost_translations')
                            ->columns(['field_key', 'lang_code', 'field_value', 'created_at', 'updated_at'])
                            ->values(
                                $db->quote($row['field_key']) . ',' .
                                $db->quote($row['lang_code']) . ',' .
                                $db->quote($row['field_value'] ?? '') . ',' .
                                $db->quote($now) . ',' . $db->quote($now)
                            )
                    )->execute();
                }
                $translationCount++;
            }

            $skippedNote = $skipped
                ? sprintf(' Skipped %d license/identity key(s) for safety.', count($skipped))
                : '';
            $this->sendJsonResponse(
                true,
                sprintf(
                    'Settings imported successfully%s: %d configuration keys merged, %d translations loaded.%s%s',
                    $formatVersion ? " (format v{$formatVersion})" : '',
                    count($settings),
                    $translationCount,
                    $skippedNote,
                    $versionWarning
                )
            );
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] Import error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->sendJsonResponse(false, 'An error occurred during import. Check the server error log for details.');
        }
    }

    /**
     * Locate and normalise the settings payload from any supported export shape.
     *
     * Supported shapes:
     *  1. Modern export:   { "meta": {"plugin":"pkg_aiboost"}, "params": { ...canonical keys... } }
     *  2. Flat settings:   { "settings": { "key": "value", ... } }
     *  3. Multi-row dump:  { "settings": [ {"setting_key":"main","settings_json":"{...}"}, ... ] }
     *  4. Legacy joomlaboost params blob: { "params": { ...old keys... } }  (no/old meta.plugin)
     *
     * Modern pkg_aiboost exports already use canonical keys, so their `params`
     * are used verbatim; only genuine legacy params (older plg_system_joomlaboost)
     * are run through mapLegacyParams().
     *
     * @param  array<string, mixed> $data     Decoded export file.
     * @param  bool                 $isModern True when meta.plugin === 'pkg_aiboost'.
     * @return array<string, mixed>|null      Normalised settings, or null if none found.
     */
    private function extractSettings(array $data, bool $isModern): ?array
    {
        if (isset($data['settings'])) {
            $raw = $data['settings'];

            if (is_array($raw) && array_is_list($raw)) {
                // Multi-row format: array of DB row objects/arrays.
                $merged = [];
                foreach ($raw as $row) {
                    $json = $row['settings_json'] ?? ($row[1] ?? '{}');

                    if (is_string($json)) {
                        $decoded = json_decode($json, true);
                        if (is_array($decoded)) {
                            $merged = array_merge($merged, $decoded);
                        }
                    } elseif (is_array($json)) {
                        $merged = array_merge($merged, $json);
                    }
                }
                return $merged ?: null;
            }

            if (is_array($raw)) {
                // Standard flat key→value map (canonical keys).
                return $raw;
            }
        }

        if (isset($data['params'])) {
            $params = is_array($data['params'])
                ? $data['params']
                : json_decode((string) $data['params'], true);

            if (!is_array($params)) {
                return null;
            }

            return $isModern ? $params : $this->mapLegacyParams($params);
        }

        return null;
    }

    /**
     * Map legacy plg_system_joomlaboost params keys to pkg_aiboost settings keys.
     *
     * Legacy params used different key names in the Joomla #__extensions.params column.
     * This ensures old exports are importable without manual field remapping.
     *
     * @param  array<string, mixed> $params Raw params array from legacy export.
     * @return array<string, mixed>         Mapped settings array.
     */
    private function mapLegacyParams(array $params): array
    {
        // Direct renames: legacy_key => new_key
        $keyMap = [
            'schema_org_name'            => 'org_name_en',
            'schema_org_description'     => 'org_description_en',
            'schema_org_url'             => 'schema_url',
            'schema_org_email'           => 'schema_email',
            'schema_org_phone'           => 'schema_phone',
            'schema_org_type'            => 'schema_type',
            'schema_social_fb'           => 'schema_social_facebook',
            'schema_social_ig'           => 'schema_social_instagram',
            'schema_social_yt'           => 'schema_social_youtube',
            'schema_social_tw'           => 'schema_social_twitter',
            'schema_social_li'           => 'schema_social_linkedin',
            'schema_address_postcode'    => 'schema_address_zip',
            'analytics_ga4_id'           => 'ga4_measurement_id',
            'analytics_gtm_id'           => 'gtm_container_id',
            'analytics_gsc_code'         => 'gsc_verification_code',
            'analytics_pixel_id'         => 'meta_pixel_id',
            'analytics_enabled'          => 'enable_analytics',
            'sitemap_enabled'            => 'enable_sitemap',
            'sitemap_articles'           => 'sitemap_include_articles',
            'sitemap_categories'         => 'sitemap_include_categories',
            'sitemap_menus'              => 'sitemap_include_menus',
            'schema_enabled'             => 'enable_schema',
            'opengraph_enabled'          => 'enable_opengraph',
            'opengraph_site_name'        => 'og_site_name',
            'opengraph_default_image'    => 'og_default_image',
            'twitter_cards_enabled'      => 'enable_twitter_cards',
            'hreflang_enabled'           => 'enable_hreflang',
            'canonical_enabled'          => 'enable_canonical',
            'indexnow_enabled'           => 'enable_indexnow',
            'llmstxt_enabled'            => 'enable_llms_txt',
            'llmstxt_content'            => 'llms_txt_content',
            'debug_enabled'              => 'debug_mode',
            'jb_is_paid'                 => 'license_tier',
            'license_key_value'          => 'license_key',
        ];

        $mapped = [];
        foreach ($params as $key => $value) {
            $newKey          = $keyMap[$key] ?? $key;
            $mapped[$newKey] = $value;
        }

        return $mapped;
    }

    private function sendJsonResponse(bool $success, string $message): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo json_encode(['success' => $success, 'message' => $message]);
        $this->app->close();
    }
}
