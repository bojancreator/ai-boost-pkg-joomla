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
use AiBoost\Lib\SettingsSaveDefinition;
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

            $fields = SettingsSaveDefinition::acceptedKeys();

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
            // 'conflict_setup_done' is the first-run Conflict Manager flag. It is
            // owned by the Conflict Manager wizard/page, not the Settings tabs, so
            // preserve it here the same way as dismissed_checks — a Settings save
            // that doesn't post it must never reset the wizard.
            foreach (['dismissed_checks', 'conflict_setup_done'] as $preservedKey) {
                if (!isset($settings[$preservedKey]) && isset($existingForMerge[$preservedKey])) {
                    $settings[$preservedKey] = $existingForMerge[$preservedKey];
                }
            }

            // Integration master switches (`integration_<key>_enabled`) are
            // owned by the Integrations page via IntegrationsController::saveToggle,
            // not by this form, so they are never posted here. Carry forward any
            // that already exist in the stored blob — otherwise this full-blob
            // rewrite would silently turn every integration back on. Pattern-based
            // so future bridges need no change here.
            foreach ($existingForMerge as $exKey => $exVal) {
                if (!array_key_exists($exKey, $settings)
                    && is_string($exKey)
                    && str_starts_with($exKey, 'integration_')
                    && str_ends_with($exKey, '_enabled')) {
                    $settings[$exKey] = $exVal;
                }
            }

            // Legacy compatibility: keep historical license state available to
            // old integrations without using it to strip manifest-backed saves.
            $isProForSave = $this->isProSetting($existingForMerge);

            // License/activation state, per-site identity and dev overrides are
            // NEVER writable from the Settings save endpoint — they belong to
            // the license activation / heartbeat / debug-controls endpoints.
            // Carry every SYSTEM_PRESERVED_KEYS entry forward from the existing
            // row (fail-closed both ways): a Free admin cannot promote
            // themselves to Pro by posting `pro_activated=1` (or
            // `license_tier=pro`) inside an ordinary settings save, and a save
            // can never wipe a paying customer's perpetual activation or
            // stored licence key.
            $settings = SettingsSaveDefinition::mergeSystemPreservedKeys($settings, $existingForMerge);

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
            // the count when the controller carries them forward — including
            // every system-preserved key (license/activation state, install
            // identity, dev overrides), which never changes through this save.
            $changeBookkeepingKeys = array_merge(
                ['change_counter', 'last_changed_at', 'dismissed_checks', 'conflict_setup_done'],
                SettingsSaveDefinition::SYSTEM_PRESERVED_KEYS
            );
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

            // Include #__aiboost_translations rows so the backup actually
            // restores per-language values (Pro flagship) — emitted in the
            // exact row shape ImportController::upload() consumes on import:
            // [{field_key, lang_code, field_value}, ...]. Best-effort: a
            // missing table (pre-translations install) must not block export.
            $translations = [];
            try {
                $tq = $db->getQuery(true)
                    ->select([
                        $db->quoteName('field_key'),
                        $db->quoteName('lang_code'),
                        $db->quoteName('field_value'),
                    ])
                    ->from($db->quoteName('#__aiboost_translations'))
                    ->order($db->quoteName('field_key') . ' ASC, ' . $db->quoteName('lang_code') . ' ASC');
                $translations = (array) $db->setQuery($tq)->loadAssocList();
            } catch (\Throwable $e) {
                \AiBoost\Lib\Logger::warning('[AiBoost] Settings export: translations read failed: ' . $e->getMessage());
            }

            // buildExportPayload() strips every SYSTEM_PRESERVED_KEYS entry
            // (plaintext licence key, activation flags, install identity, dev
            // overrides) from the downloadable file; $settings itself stays
            // intact for the last_backup_at bookkeeping write below.
            $export = self::buildExportPayload(
                $settings,
                $translations,
                Factory::getDate()->toISO8601(),
                JVERSION
            );

            $json     = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $filename = 'aiboost-settings-export-' . date('Y-m-d') . '.json';

            // Task #500 — record server-side last backup timestamp so the
            // Health check can warn admins when no backup has been taken in
            // 30 days. We update in-place on the existing settings row.
            //
            // Only for a token-bearing (genuine SPA-initiated) export: the
            // download link is intentionally token-less so it can be a plain
            // <a href>, but the side-effecting timestamp write must not be
            // forgeable via a cross-site GET, which would silently reset the
            // backup reminder. The SPA appends the CSRF token to the export URL.
            if ((Session::checkToken('get') || Session::checkToken()) && $row) {
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

    /**
     * Build the downloadable export payload from the raw settings blob and
     * the translation rows.
     *
     * Static and side-effect free so the redaction contract is unit-testable:
     * every SYSTEM_PRESERVED_KEYS entry (licence key, license/heartbeat
     * state, perpetual-activation flags, install identity, dev overrides) is
     * stripped from `params` — a backup file must never carry the customer's
     * plaintext licence key, and the import side denylists those keys anyway
     * (ImportController::IMPORT_DENYLIST), so exporting them had no
     * round-trip value. Translations are passed through in the row shape
     * ImportController::upload() consumes, so export → import restores them.
     *
     * @param array<string,mixed>            $settings      Decoded settings row.
     * @param array<int,array<string,mixed>> $translations  #__aiboost_translations rows.
     * @param string                         $exportedAt    ISO8601 export timestamp.
     * @param string                         $joomlaVersion JVERSION of the exporting site.
     * @return array<string,mixed>
     */
    public static function buildExportPayload(
        array $settings,
        array $translations,
        string $exportedAt,
        string $joomlaVersion
    ): array {
        foreach (SettingsSaveDefinition::SYSTEM_PRESERVED_KEYS as $strippedKey) {
            unset($settings[$strippedKey]);
        }

        return [
            'meta' => [
                'version'     => '1.0',
                'plugin'      => 'pkg_aiboost',
                'exported_at' => $exportedAt,
                'joomla'      => $joomlaVersion,
            ],
            'params'       => $settings,
            'translations' => $translations,
        ];
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

            // Strict whitelist — only our own add-on plugins.
            // Plan 1 (2026-06): the SDK YOOtheme bridge (aiboost_int_yootheme)
            // stores its settings in the AI Boost blob via onAiBoostRegisterFields,
            // not in plugin params, so the legacy 'aiboost_yootheme' element is gone.
            $allowedPlugins = ['aiboost_falang'];

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

    /**
     * AJAX: one-click create the 5 author Joomla user custom fields that the
     * Author Entity (Person schema) feature reads. Idempotent — existing fields
     * are left untouched (the user may have customised them).
     * URL: index.php?option=com_aiboost&task=settings.createAuthorFields&format=json
     */
    public function createAuthorFields(): void
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
            $context = 'com_users.user';
            $now     = Factory::getDate()->toSql();
            $userId  = (int) ($this->app->getIdentity()->id ?? 0);

            // Detect optional columns (Joomla version differences)
            $prefix    = $db->getPrefix();
            $fieldCols = array_map(
                static fn($c) => (string) $c->Field,
                $db->setQuery('SHOW COLUMNS FROM `' . $prefix . 'fields`')->loadObjectList() ?: []
            );
            $hasFieldparams = in_array('fieldparams', $fieldCols, true);
            $hasOnlySubform = in_array('only_use_in_subform', $fieldCols, true);
            // #__fields audit columns are NOT NULL without a default on strict MySQL.
            $fieldAudit = [
                'created_time'    => $db->quote($now),
                'created_user_id' => (string) $userId,
                'modified_time'   => $db->quote($now),
                'modified_by'     => (string) $userId,
            ];

            // Ensure field group
            $groupId = $this->ensureFieldGroupInline(
                $db,
                $context,
                'AI Boost — Author',
                'AI Boost author identity fields, read into the Person entity in Article schema (E-E-A-T).'
            );

            // MUST match the names SchemaProBuilder::loadAuthorCustomFields() reads.
            $fieldDefs = [
                ['name' => 'aiboost_job_title', 'title' => 'AI Boost: Job Title',     'type' => 'text',     'description' => 'Author job title / role — emitted as Person.jobTitle. Multilingual sites can add aiboost_job_title_en, _de, etc.', 'fieldparams' => '{}',                          'ordering' => 1],
                ['name' => 'aiboost_bio',       'title' => 'AI Boost: Bio',           'type' => 'textarea', 'description' => 'Short author biography — emitted as Person.description.',                                                            'fieldparams' => '{"rows":"4","cols":""}',      'ordering' => 2],
                ['name' => 'aiboost_website',   'title' => 'AI Boost: Website URL',   'type' => 'url',      'description' => 'Author website — emitted as Person.url and added to Person.sameAs.',                                                'fieldparams' => '{}',                          'ordering' => 3],
                ['name' => 'aiboost_linkedin',  'title' => 'AI Boost: LinkedIn URL',  'type' => 'url',      'description' => 'Author LinkedIn profile — added to Person.sameAs.',                                                                 'fieldparams' => '{}',                          'ordering' => 4],
                ['name' => 'aiboost_wikipedia', 'title' => 'AI Boost: Wikipedia URL', 'type' => 'url',      'description' => 'Author Wikipedia page — strong entity disambiguation signal in Person.sameAs.',                                     'fieldparams' => '{}',                          'ordering' => 5],
            ];

            $note    = 'aiboost_version:author-' . date('Ymd');
            $created = $skipped = 0;

            foreach ($fieldDefs as $def) {
                $query = $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from('#__fields')
                    ->where($db->quoteName('name')    . '=' . $db->quote($def['name']))
                    ->where($db->quoteName('context') . '=' . $db->quote($context));
                $db->setQuery($query, 0, 1);

                // Idempotent: never overwrite a field the user may already have.
                if ((int) ($db->loadResult() ?? 0) > 0) {
                    $skipped++;
                    continue;
                }

                $columns = ['asset_id', 'context', 'group_id', 'title', 'name', 'label', 'default_value', 'type', 'note', 'description', 'state', 'language', 'ordering', 'access', 'params'];
                $values  = ['0', $db->quote($context), (string) (int) $groupId, $db->quote($def['title']), $db->quote($def['name']), $db->quote($def['title']), $db->quote(''), $db->quote($def['type']), $db->quote($note), $db->quote($def['description']), '1', $db->quote('*'), (string) (int) $def['ordering'], '1', $db->quote('{}')];
                if ($hasFieldparams) { $columns[] = 'fieldparams';         $values[] = $db->quote($def['fieldparams']); }
                if ($hasOnlySubform) { $columns[] = 'only_use_in_subform'; $values[] = '0'; }
                foreach ($fieldAudit as $auditCol => $auditVal) {
                    if (in_array($auditCol, $fieldCols, true)) { $columns[] = $auditCol; $values[] = $auditVal; }
                }
                $db->setQuery($db->getQuery(true)->insert('#__fields')->columns($columns)->values(implode(',', $values)))->execute();
                $created++;
            }

            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'created' => $created,
                'skipped' => $skipped,
                'message' => "Author fields: {$created} created, {$skipped} already existed. Find them under Users → Manage → (a user) → group \"AI Boost — Author\".",
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
        return $this->ensureFieldGroupInline(
            $db,
            $context,
            $title,
            'AI Boost per-article OpenGraph override fields'
        );
    }

    /**
     * Generic "ensure a custom-field group exists" for any context, with a
     * caller-supplied description. Mirrors ensureOgFieldGroupInline().
     */
    private function ensureFieldGroupInline(
        \Joomla\Database\DatabaseInterface $db,
        string $context,
        string $title,
        string $description
    ): int {
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from('#__fields_groups')
            ->where($db->quoteName('context') . '=' . $db->quote($context))
            ->where($db->quoteName('title')   . '=' . $db->quote($title));
        $db->setQuery($query, 0, 1);
        $id = (int) ($db->loadResult() ?? 0);

        if ($id > 0) {
            return $id;
        }

        // Detect columns to avoid issues with Joomla version differences
        $prefix    = $db->getPrefix();
        $groupCols = array_map(
            static fn($c) => (string) $c->Field,
            $db->setQuery('SHOW COLUMNS FROM `' . $prefix . 'fields_groups`')->loadObjectList() ?: []
        );

        $columns = ['asset_id', 'context', 'title', 'description', 'state', 'language', 'ordering', 'params'];
        $values  = ['0', $db->quote($context), $db->quote($title), $db->quote($description), '1', $db->quote('*'), '1', $db->quote('{}')];

        if (in_array('note', $groupCols, true)) {
            $columns[] = 'note';
            $values[]  = $db->quote('');
        }
        if (in_array('access', $groupCols, true)) {
            $columns[] = 'access';
            $values[]  = '1';
        }

        // #__fields_groups audit columns are NOT NULL without a default on strict MySQL.
        $now    = Factory::getDate()->toSql();
        $userId = (int) ($this->app->getIdentity()->id ?? 0);
        foreach ([
            'created'     => $db->quote($now),
            'created_by'  => (string) $userId,
            'modified'    => $db->quote($now),
            'modified_by' => (string) $userId,
        ] as $auditCol => $auditVal) {
            if (in_array($auditCol, $groupCols, true)) { $columns[] = $auditCol; $values[] = $auditVal; }
        }

        $db->setQuery(
            $db->getQuery(true)
                ->insert('#__fields_groups')
                ->columns($columns)
                ->values(implode(',', $values))
        )->execute();

        return (int) $db->insertid();
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
    /**
     * AJAX: search published articles for the Event Schema visual picker.
     * Pass `ids` (comma-separated) to resolve specific IDs to titles, or `q`
     * to search by title. Read-only, admin-gated (no token — same as getSettings).
     * URL: index.php?option=com_aiboost&task=settings.searchArticles&format=json
     */
    public function searchArticles(): void
    {
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJsonResponse(false, 'Access denied.');
            return;
        }
        try {
            $input = $this->app->getInput();
            $db    = Factory::getDbo();

            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('c.id'),
                    $db->quoteName('c.title'),
                    $db->quoteName('cat.title', 'category'),
                ])
                ->from($db->quoteName('#__content', 'c'))
                ->join(
                    'LEFT',
                    $db->quoteName('#__categories', 'cat') . ' ON ' . $db->quoteName('cat.id') . ' = ' . $db->quoteName('c.catid')
                )
                ->where($db->quoteName('c.state') . ' = 1');

            $idsRaw = (string) $input->get('ids', '', 'string');
            if ($idsRaw !== '') {
                $ids = array_values(array_filter(
                    array_map('intval', explode(',', $idsRaw)),
                    static fn($n) => $n > 0
                ));
                if (empty($ids)) {
                    $this->jsonArticles([]);
                    return;
                }
                $query->where($db->quoteName('c.id') . ' IN (' . implode(',', $ids) . ')');
            } else {
                $q = trim((string) $input->get('q', '', 'string'));
                if ($q !== '') {
                    $query->where($db->quoteName('c.title') . ' LIKE ' . $db->quote('%' . $db->escape($q, true) . '%', false));
                }
                $query->order($db->quoteName('c.modified') . ' DESC');
            }

            $db->setQuery($query, 0, 30);
            $rows = $db->loadObjectList() ?: [];

            $articles = array_map(static fn($r) => [
                'id'       => (int) $r->id,
                'title'    => (string) $r->title,
                'category' => (string) ($r->category ?? ''),
            ], $rows);

            $this->jsonArticles($articles);
        } catch (\Throwable $e) {
            $this->sendJsonResponse(false, 'Error: ' . $e->getMessage());
        }
    }

    /** @param array<int,array<string,mixed>> $articles */
    private function jsonArticles(array $articles): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo json_encode(['success' => true, 'articles' => $articles]);
        $this->app->close();
    }

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
     * enforcement uses the SAME perpetual-activation signal as the admin
     * bootstrap and runtime gates — a Pro install behaves exactly like Free
     * until a key is verified active once. The previous `license_tier`-only
     * check was DRIFT: it leaked Pro saving in the lapsed-license window.
     *
     * @param array<string,mixed> $settings The existing settings row (pre-merge).
     */
    private function isProSetting(array $settings): bool
    {
        if (class_exists('AiBoost\\Lib\\PluginRegistry')) {
            return \AiBoost\Lib\PluginRegistry::isProActive($settings);
        }
        // Fail-closed fallback if the lib is somehow unavailable.
        return false;
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
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_USERAGENT      => 'AIBoost-Admin-Preview/1.0',
                ]);
                $body  = (string) curl_exec($ch);
                $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($body === '' || $body === false) {
                    $error = (string) curl_error($ch);
                }
                // curl_close() omitted — deprecated no-op since PHP 8.0.
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
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_USERAGENT      => 'AIBoost-Admin-Preview/1.0',
                ]);
                $xml  = (string) curl_exec($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($xml === '' || $xml === false) {
                    $error = (string) curl_error($ch);
                }
                // curl_close() omitted — deprecated no-op since PHP 8.0.
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
            $w[] = 'More than 50,000 URLs in a single sitemap — Google rejects this. Enable Sitemap Index to split into chunks.';
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
                'success'      => true,
                'states'       => (object) $states,
                'integrations' => $this->installedSellableIntegrations(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $this->app->close();
        } catch (\Throwable $e) {
            $this->sendJsonResponse(false, 'licenseStateGet failed: ' . $e->getMessage());
        }
    }

    /**
     * Sellable integration products surfaced in the Licenses UI, keyed by license
     * SKU. `detect` is the third-party extension element whose presence makes the
     * integration relevant to this site (so a license row appears — even before
     * any verification — once the customer has the integration installed), and
     * `label` is the customer-facing product name.
     */
    private const SELLABLE_INTEGRATIONS = [
        'int_falang'   => ['label' => 'AI Boost for Multilang', 'detect' => 'falang'],
        'int_yootheme' => ['label' => 'AI Boost for YOOtheme',  'detect' => 'yootheme'],
    ];

    /**
     * The sellable integrations whose third-party dependency is installed on this
     * site (via the shared BridgeDetector). Drives the Licenses UI so a customer
     * can paste the key before any license_state row exists for that SKU.
     *
     * @return array<int,array{sku:string,label:string,installed:bool}>
     */
    private function installedSellableIntegrations(): array
    {
        $out = [];
        try {
            \AiBoost\Lib\BridgeDetector::init(Factory::getDbo());
            foreach (self::SELLABLE_INTEGRATIONS as $sku => $meta) {
                if (\AiBoost\Lib\BridgeDetector::isInstalled($meta['detect'])) {
                    $out[] = ['sku' => $sku, 'label' => $meta['label'], 'installed' => true];
                }
            }
        } catch (\Throwable $e) {
            // Non-fatal: the Licenses page still renders core + any stored states.
        }
        return $out;
    }

    /**
     * Licensable SKUs accepted by verifyLicense()/deactivateLicense().
     *
     * Core/bundle SKUs unlock core Pro via perpetual activation; the two
     * `int_*` SKUs are the independently-sold integration products (Plan 2a),
     * each product-pinned in LicenseValidator. NOTE: detection-only integrations
     * (e.g. Admin Tools) are deliberately NOT here — they are not for sale.
     */
    private const LICENSE_SKUS = ['schema', 'og', 'hreflang', 'code', 'aeo', 'bundle', 'int_yootheme', 'int_falang'];

    /**
     * Verify a license key for a single SKU. POST params:
     *   sku         — schema|og|hreflang|code|aeo|bundle|int_yootheme|int_falang
     *   license_key — raw key, validated against the live Lemon Squeezy API
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

            if (!in_array($sku, self::LICENSE_SKUS, true)) {
                $this->sendJsonResponse(false, 'Unknown SKU.');
                return;
            }
            if ($key === '') {
                $this->sendJsonResponse(false, 'License key is required.');
                return;
            }

            // Plan 2a — integration SKUs are product-pinned (fail closed). An
            // integration key cannot be verified until its Lemon Squeezy product
            // ID is configured, so a same-store key for another product can never
            // activate this integration.
            if (
                str_starts_with($sku, 'int_')
                && \AiBoost\Lib\LicenseValidator::expectedProductId($sku) === null
            ) {
                $this->sendJsonResponse(false, 'Integration licensing is not configured yet (product pinning missing). '
                    . 'This integration becomes purchasable once its product ID is set.');
                return;
            }

            $state = $this->resolveLicenseState($sku, $key);

            PluginRegistry::saveLicenseState($sku, $state);
            // Seamless updates: push the key into Joomla's update-site Download
            // Key so the native updater sends it to our update server (dlid).
            if (($state['status'] ?? '') === 'active') {
                $this->fillUpdateDownloadKey($sku, $key);
            }

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
            if (!in_array($sku, self::LICENSE_SKUS, true)) {
                $this->sendJsonResponse(false, 'Unknown SKU.');
                return;
            }

            $state = [
                'key'         => '',
                'status'      => 'deactivated',
                'expires_at'  => null,
                'verified_at' => gmdate('c'),
                'activations_remaining' => null,
                'mock'        => false,
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
     * Write the licence key into Joomla's #__update_sites.extra_query (dlid=<key>)
     * for the matching update site, so the native one-click updater sends the key
     * to our update server automatically (the customer never re-types it). Mapped by
     * feed path: int_falang → /multilang/, int_yootheme → /yootheme/, core → the
     * pkg_aiboost feed. Best-effort: on failure the Download Key can still be pasted
     * manually under System → Update Sites.
     */
    private function fillUpdateDownloadKey(string $sku, string $key): void
    {
        if ($key === '') {
            return;
        }
        try {
            $db   = Factory::getDbo();
            $rows = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['update_site_id', 'location']))
                    ->from($db->quoteName('#__update_sites'))
                    ->where($db->quoteName('location') . ' LIKE ' . $db->quote('%updates.aiboostnow.com%'))
            )->loadObjectList();
            foreach ($rows ?: [] as $row) {
                $loc      = (string) $row->location;
                $isFalang = strpos($loc, '/multilang/') !== false;
                $isYoo    = strpos($loc, '/yootheme/') !== false;
                $match    = ($sku === 'int_falang' && $isFalang)
                    || ($sku === 'int_yootheme' && $isYoo)
                    || (!str_starts_with($sku, 'int_') && !$isFalang && !$isYoo);
                if (!$match) {
                    continue;
                }
                $db->setQuery(
                    $db->getQuery(true)
                        ->update($db->quoteName('#__update_sites'))
                        ->set($db->quoteName('extra_query') . ' = ' . $db->quote('dlid=' . $key))
                        ->where($db->quoteName('update_site_id') . ' = ' . (int) $row->update_site_id)
                )->execute();
            }
        } catch (\Throwable $e) {
            // best-effort — the Download Key can be entered manually
        }
    }

    /**
     * Resolve the license state for a verify request.
     *
     * A real Lemon Squeezy validate/activate call via LicenseValidator::verify()
     * — Pro only unlocks on a confirmed live, active license (fail-closed on any
     * error).
     *
     * @return array<string,mixed>
     */
    private function resolveLicenseState(string $sku, string $key): array
    {
        $existing     = PluginRegistry::loadLicenseStates()[$sku] ?? [];
        $instanceId   = is_array($existing) ? (string) ($existing['instance_id'] ?? '') : '';
        $instanceName = rtrim(\Joomla\CMS\Uri\Uri::root(), '/');

        // Integration SKUs are pinned to their single product; core SKUs are
        // pinned to the SET of core tier products (3/10/unlimited) so a cheap
        // same-store add-on key cannot unlock the core bundle.
        $expectedProductId = str_starts_with($sku, 'int_')
            ? \AiBoost\Lib\LicenseValidator::expectedProductId($sku)
            : \AiBoost\Lib\LicenseValidator::expectedCoreProductIds();

        return \AiBoost\Lib\LicenseValidator::verify($key, $instanceName, $instanceId, $expectedProductId);
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
     * Permission + CSRF guard for the License endpoints (verifyLicense,
     * deactivateLicense, heartbeatRun, licenseStateGet) — available in production.
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
}
