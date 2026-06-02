<?php
/**
 * AI Boost — Falang Pro Bridge Plugin
 *
 * Detects Falang Pro and enhances AI Boost SEO/AEO output:
 *  - hreflang <link> tags in <head> from Joomla language table
 *  - Per-language hreflang alternates (<xhtml:link>) in XML sitemap
 *    → registered with BridgeDetector for aiboost_sitemap (onAiBoostBeforeSitemapBuild)
 *  - Per-language page title/description override → aiboost_social OG pipeline
 *  - Per-language Schema.org Organisation (name, description, inLanguage)
 *  - onAiBoostGetSettingsTabs: actionable "Falang Pro" tab in AI Boost Settings
 *    with editable toggles for primary/fallback language + sitemap hreflang;
 *    persisted via SettingsController::saveAddonPluginParams()
 *
 * Graceful degradation: absent Falang Pro or Free license = silent boot.
 *
 * Requires: AI Boost for Joomla (Basic or Professional license) + Falang Pro.
 *
 * @package     AiBoost\Plugin\System\AiBoostFalang
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostFalang\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

class AiBoostFalang extends CMSPlugin
{
    protected $autoloadLanguage = true;

    private bool   $detected       = false;
    private bool   $licensed       = false;
    private array  $abSettings     = [];
    private bool   $settingsLoaded = false;

    /** @var array<array{lang_id:string,lang_code:string,sef:string,title:string}>|null */
    private ?array $languages = null;

    // ── Bootstrap ───────────────────────────────────────────────────────────

    public function onAfterInitialise(): void
    {
        $this->bootLib();
        $this->detected = $this->detectFalang();
        if (!$this->detected) {
            return;
        }
        $this->licensed = $this->isLicensed();
    }

    private function bootLib(): void
    {
        static $booted = false;
        if ($booted) {
            return;
        }
        $boot = JPATH_ADMINISTRATOR . '/components/com_aiboost/lib/autoload.php';
        if (file_exists($boot)) {
            require_once $boot;
        }
        $booted = true;
    }

    // ── Detection ───────────────────────────────────────────────────────────

    private function detectFalang(): bool
    {
        if (!class_exists('AiBoost\\Lib\\BridgeDetector')) {
            return false;
        }

        // Use OR-chain of independent signals to be robust across Falang Pro versions
        // and installation variants. Any ONE signal is sufficient for detection.
        //
        // Signal priority (most reliable first):
        // 1. #__falang_content table — created exclusively by Falang Pro on install.
        //    This is the strongest signal; version and element-name independent.
        // 2. isInstalled('falang') — searches plugin/system, component, template in
        //    #__extensions for element name 'falang' (common in Falang Pro v4+).
        // 3. isInstalled('com_falang') — some Falang versions register as 'com_falang'.
        // 4. isExtensionEnabled('com_falang', 'component') — explicit component check.
        // 5. classExists signals — Falang Pro registers these when loaded.
        return \AiBoost\Lib\BridgeDetector::tableExists('#__falang_content')
            || \AiBoost\Lib\BridgeDetector::isInstalled('falang')
            || \AiBoost\Lib\BridgeDetector::isInstalled('com_falang')
            || \AiBoost\Lib\BridgeDetector::isExtensionEnabled('com_falang', 'component', '')
            || \AiBoost\Lib\BridgeDetector::classExists('FalangPro\\Core\\Application')
            || \AiBoost\Lib\BridgeDetector::classExists('Falang\\Helper\\FalangHelper');
    }

    // ── License gate ────────────────────────────────────────────────────────

    private function isLicensed(): bool
    {
        $s = $this->loadAiBoostSettings();

        if (!empty($s['dev_license_preview'])) {
            return true;
        }
        if (!empty($s['dev_force_free_tier'])) {
            return false;
        }

        $key  = trim((string) ($s['license_key']  ?? ''));
        $tier = strtolower(trim((string) ($s['license_tier'] ?? '')));

        if (!$key || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $key)) {
            return false;
        }

        return in_array($tier, ['basic', 'professional', 'starter', 'developer', 'agency'], true);
    }

    private function loadAiBoostSettings(): array
    {
        if ($this->settingsLoaded) {
            return $this->abSettings;
        }
        $this->settingsLoaded = true;
        if (!class_exists('AiBoost\\Lib\\PluginSettings', false)) {
            return $this->abSettings = [];
        }
        return $this->abSettings = \AiBoost\Lib\PluginSettings::all();
    }

    // ── onAiBoostBeforeSitemapBuild ─────────────────────────────────────────

    /**
     * Called ONCE by aiboost_sitemap (via event dispatcher) before building the
     * sitemap URL list. Registers two data sets with BridgeDetector:
     *
     * 1. Language list → registerSitemapLanguages()
     *    Used to generate <xhtml:link hreflang> alternate tags for every URL.
     *
     * 2. Falang alias map → registerFalangAliasMap()
     *    Maps original aliases to translated aliases per language SEF code.
     *    Used by buildSitemapAlternateUrl() to produce accurate translated URLs
     *    (e.g. /sr/o-nama) instead of naively prefixing (/sr/about-us).
     *
     * Both registrations require falang_hreflang_sitemap = 1 (default ON).
     */
    public function onAiBoostBeforeSitemapBuild(): void
    {
        if (!$this->detected || !$this->licensed) {
            return;
        }

        if (!(int) $this->params->get('falang_hreflang_sitemap', 1)) {
            return;
        }

        if (!class_exists('AiBoost\\Lib\\BridgeDetector')) {
            return;
        }

        $langs = $this->getLanguages();

        // Filter by user language selection (falang_enabled_languages); empty = all.
        $enabledLangs = json_decode(
            (string) $this->params->get('falang_enabled_languages', '[]'),
            true
        );
        if (is_array($enabledLangs) && !empty($enabledLangs)) {
            $langs = array_values(array_filter(
                $langs,
                fn($l) => in_array($l['sef'], $enabledLangs, true)
            ));
        }

        if (!empty($langs)) {
            \AiBoost\Lib\BridgeDetector::registerSitemapLanguages($langs);
        }

        // Register primary language SEF for sitemap x-default generation
        $primarySef = trim((string) $this->params->get('falang_primary_language', 'en'));
        if ($primarySef !== '') {
            \AiBoost\Lib\BridgeDetector::registerPrimaryLanguageSef($primarySef);
        }

        $aliasMap = $this->loadFalangAliasMap();
        if (!empty($aliasMap)) {
            \AiBoost\Lib\BridgeDetector::registerFalangAliasMap($aliasMap);
        }
    }

    /**
     * Query #__falang_content for translated aliases of menu items, articles,
     * and categories. Builds a map: [original_alias => [sef => translated_alias]].
     *
     * This map allows aiboost_sitemap to generate accurate per-language URLs in
     * <xhtml:link hreflang> alternates by substituting the actual Falang-translated
     * alias rather than just prepending the language SEF prefix.
     *
     * Falang Pro schema used:
     *   reference_table: 'menu' | '#__content' | '#__categories'
     *   reference_field: 'alias'
     *   reference_id:    entity PK
     *   language_id:     Joomla #__languages.lang_id
     *   value:           translated alias string
     *   published:       1
     *
     * @return array<string,array<string,string>>  [original_alias => [sef => translated_alias]]
     */
    private function loadFalangAliasMap(): array
    {
        try {
            $db  = Factory::getDbo();
            $map = [];

            // Helper: run a query joining #__falang_content with a source table,
            // selecting original alias + translated alias + language SEF.
            $fetch = function (string $refTable, string $joinTable, string $joinAlias) use ($db, &$map): void {
                try {
                    $q = $db->getQuery(true)
                        ->select([
                            $db->quoteName($joinAlias . '.alias', 'orig'),
                            $db->quoteName('fc.value', 'translated'),
                            $db->quoteName('l.sef', 'sef'),
                        ])
                        ->from($db->quoteName('#__falang_content', 'fc'))
                        ->join(
                            'INNER',
                            $db->quoteName($joinTable, $joinAlias)
                            . ' ON ' . $db->quoteName($joinAlias . '.id') . ' = ' . $db->quoteName('fc.reference_id')
                        )
                        ->join(
                            'INNER',
                            $db->quoteName('#__languages', 'l')
                            . ' ON ' . $db->quoteName('l.lang_id') . ' = ' . $db->quoteName('fc.language_id')
                        )
                        ->where($db->quoteName('fc.reference_table') . ' = ' . $db->quote($refTable))
                        ->where($db->quoteName('fc.reference_field') . ' = ' . $db->quote('alias'))
                        ->where($db->quoteName('fc.published') . ' = 1')
                        ->where($db->quoteName('l.published') . ' = 1');

                    $db->setQuery($q);
                    foreach ($db->loadObjectList() as $row) {
                        $orig       = trim((string) ($row->orig ?? ''));
                        $translated = trim((string) ($row->translated ?? ''));
                        $sef        = trim((string) ($row->sef ?? ''));
                        if ($orig !== '' && $translated !== '' && $sef !== '') {
                            $map[$orig][$sef] = $translated;
                        }
                    }
                } catch (\Throwable) {
                    // Table may not exist or query fails — silently skip this entity type
                }
            };

            // Menu items (reference_table = 'menu')
            $fetch('menu', '#__menu', 'm');

            // Articles (reference_table = '#__content')
            $fetch('#__content', '#__content', 'a');

            // Categories (reference_table = '#__categories')
            $fetch('#__categories', '#__categories', 'c');

            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    // ── onAiBoostGetSettingsTabs ────────────────────────────────────────────

    /**
     * Inject an actionable "Falang Pro" tab into AI Boost Settings.
     *
     * Contains editable toggles and primary/fallback language text inputs
     * with [data-addon-plugin] / [data-addon-param] attributes so that
     * settings.js collects them and SettingsController::saveAddonPluginParams()
     * persists them into #__extensions.params for the aiboost_falang plugin.
     *
     * Returns null when Falang is not detected (tab is not shown at all).
     */
    public function onAiBoostGetSettingsTabs(): ?array
    {
        if (!$this->detected) {
            return null;
        }

        $licOk    = $this->licensed;
        $licBadge = $licOk
            ? '<span class="badge bg-success">Licensed</span>'
            : '<span class="badge bg-warning text-dark">Free — upgrade to Basic or Professional</span>';

        // Current param values
        $hreflangHead    = (int) $this->params->get('falang_hreflang_enabled', 1);
        $hreflangSitemap = (int) $this->params->get('falang_hreflang_sitemap', 1);
        $schemaTranslate = (int) $this->params->get('falang_schema_translate', 1);
        $ogTranslate     = (int) $this->params->get('falang_og_translate', 1);
        $primaryLang     = htmlspecialchars(
            (string) $this->params->get('falang_primary_language', 'en'),
            ENT_QUOTES, 'UTF-8'
        );
        $fallbackLang    = htmlspecialchars(
            (string) $this->params->get('falang_fallback_language', 'en'),
            ENT_QUOTES, 'UTF-8'
        );

        $pluginMgrUrl = 'index.php?option=com_plugins&filter[folder]=system&filter[search]=aiboost_falang';

        // Helper: toggle row with Yes/No radios
        $toggle = function (
            string $label,
            string $paramName,
            int    $value
        ) use ($licOk): string {
            $disabled = $licOk ? '' : ' disabled';
            $yesChk   = $value ? ' checked' : '';
            $noChk    = $value ? '' : ' checked';
            $dimCls   = $licOk ? '' : ' text-muted';
            return '<tr class="' . $dimCls . '">'
                . '<td class="pe-3 align-middle">' . $label . '</td>'
                . '<td class="align-middle">'
                . '<div class="btn-group btn-group-sm" role="group">'
                . '<input type="radio" class="btn-check" id="' . $paramName . '_yes" '
                . 'data-addon-param="' . $paramName . '" value="1"' . $yesChk . $disabled . '>'
                . '<label class="btn btn-outline-success" for="' . $paramName . '_yes">Yes</label>'
                . '<input type="radio" class="btn-check" id="' . $paramName . '_no" '
                . 'data-addon-param="' . $paramName . '" value="0"' . $noChk . $disabled . '>'
                . '<label class="btn btn-outline-secondary" for="' . $paramName . '_no">No</label>'
                . '</div></td></tr>';
        };

        // Load enabled-languages selection (JSON array of SEF codes, empty = all)
        $enabledLangsRaw = (string) $this->params->get('falang_enabled_languages', '[]');
        $enabledLangs    = json_decode($enabledLangsRaw, true);
        $enabledLangs    = is_array($enabledLangs) ? $enabledLangs : [];

        // Detected languages — selectable checkboxes for hreflang inclusion
        $langs       = $this->getLanguages();
        $langRows    = '';
        foreach ($langs as $lang) {
            $sef      = htmlspecialchars((string) $lang['sef'], ENT_QUOTES, 'UTF-8');
            $langCode = htmlspecialchars((string) $lang['lang_code'], ENT_QUOTES, 'UTF-8');
            $title    = htmlspecialchars((string) $lang['title'], ENT_QUOTES, 'UTF-8');
            // Empty enabledLangs = all languages are included (default)
            $checked  = (empty($enabledLangs) || in_array($lang['sef'], $enabledLangs, true))
                ? ' checked'
                : '';
            $dis      = $licOk ? '' : ' disabled';
            $paramKey = 'falang_lang_sel_' . preg_replace('/[^a-z0-9]/', '_', strtolower((string) $lang['sef']));
            $langRows .= '<tr>'
                . '<td class="align-middle">'
                . '<input type="checkbox" class="form-check-input me-2 falang-lang-checkbox" '
                . 'id="' . $paramKey . '" '
                . 'data-addon-param="' . $paramKey . '" '
                . 'data-lang-sef="' . $sef . '" '
                . 'value="1"' . $checked . $dis . '>'
                . '<label for="' . $paramKey . '" class="mb-0">'
                . '<code>' . $sef . '</code> — ' . $langCode . ' — ' . $title
                . '</label>'
                . '</td>'
                . '</tr>';
        }
        $langTable = empty($langs)
            ? '<p class="text-muted small">No active languages found in Joomla.</p>'
            : '<table class="table table-sm table-bordered mb-1" style="max-width:560px;">'
              . '<thead><tr><th>Include in hreflang alternates</th></tr></thead>'
              . '<tbody>' . $langRows . '</tbody></table>'
              . '<p class="form-text text-muted mb-0">Check = included in <code>&lt;link rel="alternate" hreflang&gt;</code> '
              . 'head tags and sitemap <code>&lt;xhtml:link&gt;</code>. Uncheck to exclude a language. '
              . 'Selection is saved as <code>falang_enabled_languages</code> JSON. Leave all checked to include all.</p>';

        $html = '<div data-addon-plugin="aiboost_falang">'
            . '<div class="ab-section-title">Falang Pro — Settings</div>'
            . '<p><strong>Falang Pro detected:</strong> <span class="badge bg-success">Yes</span>'
            . ' &nbsp; <strong>License:</strong> ' . $licBadge . '</p>'
            . (!$licOk
                ? '<div class="alert alert-warning py-2 mb-3">Upgrade to Basic or Professional to activate these features. '
                  . '<a href="https://aiboostnow.com/pricing" target="_blank" rel="noopener" class="alert-link">View plans</a></div>'
                : '')
            . '<table class="table table-sm table-bordered mb-3" style="max-width:560px;">'
            . '<thead><tr><th>Feature</th><th style="width:160px;">Setting</th></tr></thead>'
            . '<tbody>'
            . $toggle('hreflang &lt;link&gt; tags in &lt;head&gt;', 'falang_hreflang_enabled', $hreflangHead)
            . $toggle('hreflang alternates in XML sitemap', 'falang_hreflang_sitemap', $hreflangSitemap)
            . $toggle('Per-language page meta (og:title, og:description)', 'falang_og_translate', $ogTranslate)
            . $toggle('Per-language Schema.org Organisation', 'falang_schema_translate', $schemaTranslate)
            . '</tbody></table>'
            . '<div class="row g-3 mb-3" style="max-width:460px;">'
            . '<div class="col-6">'
            . '<label class="form-label fw-semibold" for="falang_primary_language">Primary language SEF</label>'
            . '<input type="text" class="form-control form-control-sm" id="falang_primary_language" '
            . 'data-addon-param="falang_primary_language" value="' . $primaryLang . '"'
            . ($licOk ? '' : ' disabled') . '>'
            . '<div class="form-text text-muted">e.g. <code>en</code> — used for <code>x-default</code> hreflang</div>'
            . '</div>'
            . '<div class="col-6">'
            . '<label class="form-label fw-semibold" for="falang_fallback_language">Fallback language SEF</label>'
            . '<input type="text" class="form-control form-control-sm" id="falang_fallback_language" '
            . 'data-addon-param="falang_fallback_language" value="' . $fallbackLang . '"'
            . ($licOk ? '' : ' disabled') . '>'
            . '<div class="form-text text-muted">Fallback when translation is missing</div>'
            . '</div>'
            . '</div>'
            . '<div class="ab-section-title" style="font-size:.8rem;">Active Languages — hreflang Selection</div>'
            . $langTable
            . '<p class="text-muted small mt-3 mb-0">Settings are saved when you click <strong>Save Settings</strong> above. '
            . 'Advanced options: <a href="' . $pluginMgrUrl . '" class="alert-link" target="_blank">AI Boost for Falang Pro plugin</a>.</p>'
            . '</div>';

        return [
            'id'    => 'tab-falang',
            'label' => 'Falang Pro',
            'svg'   => '<svg class="ab-ti" width="13" height="13" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M4.545 6.714 4.11 8H3l1.862-5h1.284L8 8H6.833l-.435-1.286H4.545zm1.634-.736L5.5 3.956h-.049l-.679 2.022H6.18z"/><path d="M0 2a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v3h3a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-3H2a2 2 0 0 1-2-2V2zm2-1a1 1 0 0 0-1 1v7a1 1 0 0 0 1 1h7a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H2zm7.138 9.995c.193.301.402.583.63.846-.748.575-1.673 1.001-2.768 1.292.178.217.451.635.555.867 1.125-.359 2.08-.844 2.886-1.494.777.665 1.739 1.165 2.93 1.472.133-.254.414-.673.629-.89-1.125-.253-2.057-.694-2.82-1.284.681-.747 1.222-1.651 1.621-2.757H14v-.75h-3v-.75h-.75v.75H7v.75h1.019c-.714 1.54-1.479 2.548-2.546 3.215.228.239.514.671.665.925.293-.161.576-.348.844-.548-.148.221-.311.432-.489.633.189.237.494.722.656.967.25-.254.486-.52.708-.799Z"/></svg>',
            'html'  => $html,
        ];
    }

    // ── onAfterRoute — per-language meta override ────────────────────────────

    /**
     * Set document title/description from Falang translated fields.
     * aiboost_social reads these in onBeforeCompileHead for og:title, og:description.
     */
    public function onAfterRoute(): void
    {
        if (!$this->detected || !$this->licensed) {
            return;
        }

        $app = Factory::getApplication();
        if (!$app->isClient('site')) {
            return;
        }

        // Schema translation coordination — must happen in onAfterRoute (before
        // onBeforeCompileHead) so aiboost_schema can read BridgeDetector data
        // regardless of plugin ordering, and without creating a duplicate Organization.
        if ((int) $this->params->get('falang_schema_translate', 1)) {
            $this->registerSchemaTranslation();
        }

        if (!(int) $this->params->get('falang_og_translate', 1)) {
            return;
        }

        try {
            $langs      = $this->getLanguages();
            $currentTag = Factory::getLanguage()->getTag();
            $activeLang = $this->findLanguageByTag($langs, $currentTag);
            if (!$activeLang) {
                return;
            }

            $translation = $this->loadFalangTranslation((int) ($activeLang['lang_id'] ?? 0));
            if (empty($translation['org_name']) && empty($translation['org_desc'])) {
                return;
            }

            $doc = $app->getDocument();
            if (!$doc) {
                return;
            }

            if (!empty($translation['org_name'])) {
                $doc->setTitle($translation['org_name'] . ' — ' . $doc->getTitle());
            }
            if (!empty($translation['org_desc'])) {
                $doc->setDescription($translation['org_desc']);
            }
        } catch (\Throwable) {
            // Graceful degradation
        }
    }

    /**
     * Register Falang schema translation coordination with BridgeDetector.
     *
     * Called in onAfterRoute() — before onBeforeCompileHead() fires — so that
     * aiboost_schema plugin can read these values regardless of plugin ordering.
     *
     * Effect: aiboost_schema will substitute translated name/description for
     * EN defaults on non-EN pages (adding inLanguage), instead of Falang
     * outputting a separate Organization block that conflicts with the main one.
     */
    private function registerSchemaTranslation(): void
    {
        if (!class_exists('AiBoost\\Lib\\BridgeDetector')) {
            return;
        }

        try {
            $currentTag = Factory::getLanguage()->getTag();
            $langs      = $this->getLanguages();
            $activeLang = $this->findLanguageByTag($langs, $currentTag);
            if (!$activeLang) {
                return;
            }

            // Signal aiboost_schema that Falang is coordinating Organization output.
            // This prevents duplicate JSON-LD Organization blocks on non-EN pages.
            \AiBoost\Lib\BridgeDetector::setSchemaTranslationActive(true);

            $translation = $this->loadFalangTranslation((int) ($activeLang['lang_id'] ?? 0));

            // Register translated field values — aiboost_schema reads these and
            // replaces EN defaults + adds inLanguage to the single Organization block.
            if (!empty($translation['org_name'])) {
                \AiBoost\Lib\BridgeDetector::registerTranslation('org_name_en', $translation['org_name']);
            }
            if (!empty($translation['org_desc'])) {
                \AiBoost\Lib\BridgeDetector::registerTranslation('org_description_en', $translation['org_desc']);
            }
        } catch (\Throwable) {
            // Graceful degradation
        }
    }

    // ── onBeforeCompileHead — hreflang + schema ──────────────────────────────

    public function onBeforeCompileHead(): void
    {
        if (!$this->detected || !$this->licensed) {
            return;
        }

        $app = Factory::getApplication();
        if (!$app->isClient('site')) {
            return;
        }

        $document = $app->getDocument();
        if (!$document || $document->getType() !== 'html') {
            return;
        }

        $languages = $this->getLanguages();
        if (empty($languages)) {
            return;
        }

        if ((int) $this->params->get('falang_hreflang_enabled', 1)) {
            $this->injectHreflangTags($document, $languages);
        }

        if ((int) $this->params->get('falang_schema_translate', 1)) {
            $this->injectPerLanguageSchema($document, $languages);
        }
    }

    // ── hreflang <link> tags ─────────────────────────────────────────────────

    private function injectHreflangTags($document, array $languages): void
    {
        try {
            // Filter by falang_enabled_languages (JSON array of SEF codes); empty = all.
            $enabledLangs = json_decode(
                (string) $this->params->get('falang_enabled_languages', '[]'),
                true
            );
            if (is_array($enabledLangs) && !empty($enabledLangs)) {
                $languages = array_values(array_filter(
                    $languages,
                    fn($l) => in_array($l['sef'], $enabledLangs, true)
                ));
            }
            if (empty($languages)) {
                return;
            }

            $app        = Factory::getApplication();
            $menu       = $app->getMenu()->getActive();
            $primarySef = trim((string) $this->params->get('falang_primary_language', 'en'));

            $currentUri = Uri::getInstance();
            $baseUrl    = $currentUri->getScheme() . '://' . $currentUri->getHost();
            $langSefs   = array_column($languages, 'sef');

            // Load Falang alias map for URL-aware hreflang generation.
            // Prefer already-registered map (e.g. loaded for sitemap request);
            // otherwise load on demand and register for potential reuse.
            $aliasMap = class_exists('AiBoost\\Lib\\BridgeDetector')
                ? \AiBoost\Lib\BridgeDetector::getFalangAliasMap()
                : [];
            if (empty($aliasMap)) {
                $aliasMap = $this->loadFalangAliasMap();
                if (!empty($aliasMap) && class_exists('AiBoost\\Lib\\BridgeDetector')) {
                    \AiBoost\Lib\BridgeDetector::registerFalangAliasMap($aliasMap);
                }
            }

            $defaultUrl = null;

            foreach ($languages as $lang) {
                $sef      = (string) $lang['sef'];
                $hreflang = htmlspecialchars(
                    strtolower(str_replace('_', '-', (string) $lang['lang_code'])),
                    ENT_QUOTES, 'UTF-8'
                );

                $url = $this->buildLanguageUrl($menu, $sef, $baseUrl, $currentUri, $langSefs, $aliasMap);

                $document->addHeadLink(
                    htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
                    'alternate',
                    'rel',
                    ['hreflang' => $hreflang]
                );

                if ($sef === $primarySef) {
                    $defaultUrl = $url;
                }
            }

            // x-default → primary language URL
            $xDefault = $defaultUrl ?? ($baseUrl . '/' . $primarySef . '/');
            $document->addHeadLink(
                htmlspecialchars($xDefault, ENT_QUOTES, 'UTF-8'),
                'alternate',
                'rel',
                ['hreflang' => 'x-default']
            );
        } catch (\Throwable) {
            // Graceful degradation
        }
    }

    /**
     * Build the URL for the current page in a specific language.
     *
     * Strategy (in order of precedence):
     *
     * 1. Falang alias map lookup — if current menu item has a translated alias
     *    for the target language, construct URL from translated alias directly.
     *    Produces accurate translated URLs (e.g. /sr/o-nama not /sr/about-us).
     *
     * 2. Joomla Route::_ with lang= parameter — Language Filter plugin aware;
     *    produces the canonical Joomla SEF URL for the menu item in that language.
     *
     * 3. SEF prefix swap — strip existing language prefix, prepend target.
     *    Fallback when menu is unavailable (non-component pages, etc.).
     *
     * @param array<string,array<string,string>> $aliasMap  [original_alias=>[sef=>translated]]
     */
    private function buildLanguageUrl(
        ?object $menu,
        string  $sef,
        string  $baseUrl,
        Uri     $currentUri,
        array   $allSefs,
        array   $aliasMap = []
    ): string {
        // 1. Falang alias map — check if menu item has a translated alias
        if ($menu && !empty($aliasMap)) {
            $menuAlias = trim((string) ($menu->alias ?? ''));
            if ($menuAlias !== '' && isset($aliasMap[$menuAlias][$sef])) {
                return $baseUrl . '/' . $sef . '/' . $aliasMap[$menuAlias][$sef];
            }
        }

        // 2. Joomla Route::_ with lang= parameter
        try {
            if ($menu) {
                $link   = 'index.php?Itemid=' . (int) $menu->id . '&lang=' . $sef;
                $routed = Route::_($link, false, Route::TLS_IGNORE, true);
                if (str_starts_with($routed, 'http')) {
                    return $routed;
                }
                return $baseUrl . $routed;
            }
        } catch (\Throwable) {
            // Fall through to prefix approach
        }

        // 3. SEF prefix swap fallback
        $path      = $currentUri->getPath();
        $cleanPath = '/' . ltrim($path, '/');
        foreach ($allSefs as $existingSef) {
            if (!$existingSef) {
                continue;
            }
            if (str_starts_with($cleanPath, '/' . $existingSef . '/')) {
                $cleanPath = substr($cleanPath, strlen('/' . $existingSef)) ?: '/';
                break;
            }
            if ($cleanPath === '/' . $existingSef) {
                $cleanPath = '/';
                break;
            }
        }

        return $baseUrl . '/' . $sef . $cleanPath;
    }

    // ── Per-language Schema.org Organisation ─────────────────────────────────

    /**
     * Schema output coordination is handled via BridgeDetector — NOT direct output here.
     *
     * In previous versions this method injected a separate Organization JSON-LD block,
     * which conflicted with the block already output by aiboost_schema (duplicate entities).
     *
     * Current approach (onAfterRoute → registerSchemaTranslation):
     *   1. aiboost_falang registers translated name/description + setSchemaTranslationActive(true)
     *      in onAfterRoute(), which runs before any onBeforeCompileHead() handler.
     *   2. aiboost_schema reads these values from BridgeDetector and outputs ONE Organization
     *      block — with translated content and inLanguage — for the current language.
     *   3. This method is now a no-op; it is kept for backwards compatibility with any
     *      callers that may reference it.
     *
     * @deprecated Use registerSchemaTranslation() (called from onAfterRoute) instead.
     */
    private function injectPerLanguageSchema($document, array $languages): void
    {
        // No-op: schema output is coordinated via BridgeDetector::registerSchemaTranslation().
        // See onAfterRoute() → registerSchemaTranslation() for the implementation.
    }

    // ── Falang language data ─────────────────────────────────────────────────

    private function getLanguages(): array
    {
        if ($this->languages !== null) {
            return $this->languages;
        }

        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select(['lang_id', 'lang_code', 'sef', 'title'])
                ->from($db->quoteName('#__languages'))
                ->where($db->quoteName('published') . ' = 1')
                ->order($db->quoteName('ordering') . ' ASC');
            $db->setQuery($query);
            $this->languages = $db->loadAssocList() ?: [];
        } catch (\Throwable) {
            $this->languages = [];
        }

        return $this->languages;
    }

    private function findLanguageByTag(array $languages, string $tag): ?array
    {
        foreach ($languages as $lang) {
            if (strcasecmp((string) ($lang['lang_code'] ?? ''), $tag) === 0) {
                return $lang;
            }
        }
        return null;
    }

    /**
     * Load Falang Pro translations for AI Boost org fields from #__falang_content.
     * reference_table = 'com_aiboost', published = 1, language_id = Joomla lang ID.
     */
    private function loadFalangTranslation(int $langId): array
    {
        if (!$langId) {
            return [];
        }

        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select([$db->quoteName('reference_field'), $db->quoteName('value')])
                ->from($db->quoteName('#__falang_content'))
                ->where($db->quoteName('reference_table') . ' = ' . $db->quote('com_aiboost'))
                ->where($db->quoteName('language_id')     . ' = ' . (int) $langId)
                ->where($db->quoteName('published')       . ' = 1');

            $db->setQuery($query);
            $rows = $db->loadAssocList('reference_field') ?: [];

            return [
                'org_name' => trim((string) ($rows['org_name_en']['value']        ?? '')),
                'org_desc' => trim((string) ($rows['org_description_en']['value'] ?? '')),
            ];
        } catch (\Throwable) {
            return [];
        }
    }
}
