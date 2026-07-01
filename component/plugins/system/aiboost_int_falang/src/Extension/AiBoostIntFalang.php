<?php
/**
 * AI Boost — Multilang Integration Plugin (single plugin, pure Pro)
 *
 * ONE plugin element for the whole multilingual integration (Plan 2a Workstream
 * C, single-plugin model). "Multilang" is a PURE Pro add-on — there is no free
 * floor. The element ships in two builds of the SAME plugin:
 *
 *   - FREE (Pro-stripped): a discovery/upsell SHELL only. It contributes the
 *     descriptor tile, the Integrations master toggle, and registers its six
 *     settings keys (so a plain Settings save never drops them) — but every
 *     runtime emission method is stripped, so it emits NOTHING.
 *   - PRO (the "AI Boost — Multilang" Lemon Squeezy product, full/unstripped):
 *     installing it UPGRADES this plugin in place (same id / settings / enabled
 *     state). All hreflang + per-language data registration runs, gated on an
 *     active Multilang licence.
 *
 * Scope: this element owns the HEAD hreflang tags + the Falang sitemap-language
 * registration, gated on the master toggle AND an active Multilang licence —
 * with NO Falang host requirement, so it serves native-Joomla-multilingual
 * sites too (Falang, when present, only enriches the data). The XML sitemap's
 * own hreflang (native #__associations + Falang) is re-tiered onto the same
 * Multilang licence in aiboost_sitemap. Translated Schema.org / OpenGraph live
 * in the core schema_pro / social_pro decorators and are gated there on the
 * same Multilang licence (see those plugins).
 *
 * Anti-piracy: every Pro-only section is fenced with the build's Pro-strip
 * markers, so the Free distribution ZIP physically lacks the emission code
 * (verified by verify-no-pro-leakage STRICT). Field registration stays OUTSIDE
 * the fence so Save keeps the keys in both builds.
 *
 * @package     AiBoost\Plugin\System\AiBoostIntFalang
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostIntFalang\Extension;

defined('_JEXEC') or die;

use AiBoost\Lib\ConflictManager;
use AiBoost\Lib\Integration\AbstractIntegrationPlugin;
use AiBoost\Lib\Integration\IntegrationDescriptor;
use AiBoost\Lib\Integration\Sdk;
// @pro:start
use AiBoost\Lib\BridgeDetector;
use AiBoost\Lib\Cms\AdapterRegistry;
use AiBoost\Lib\HeadBlockBuilder;
use AiBoost\Lib\PluginRegistry;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
// @pro:end

class AiBoostIntFalang extends AbstractIntegrationPlugin
{
    private ?array $languages = null;

    /** Cached result of libReady() — null until first probed. */
    private ?bool $libReady = null;

    protected function describe(): IntegrationDescriptor
    {
        return new IntegrationDescriptor(
            key:            'falang',
            pluginElement:  'aiboost_int_falang',
            label:          'Multilingual',
            vendor:         'Native Joomla & Falang',
            category:       'Multilingual',
            description:    'Per-language hreflang, Schema.org and OpenGraph for multilingual Joomla sites — works with native Joomla language associations and, when present, Falang. Pro: no multilingual output without an active licence.',
            hostType:       'component',
            hostElement:    'com_falang',
            sdkVersion:     Sdk::SDK_VERSION,
            minCoreVersion: '0.58.0',
            version:        '0.76.0',
            learnUrl:       'https://www.falang.net/',
            addonUrl:       'https://aiboostnow.com/integrations/multilang',
            icon:           'icon-language',
            claimsSlots:    [
                ConflictManager::SLOT_HREFLANG,
            ],
        );
    }

    // ── onAiBoostRegisterFields (manifest contributions) ───────────────────

    /**
     * Field registration runs regardless of licence/host state so a plain
     * Settings save never drops these keys (the save whitelist is built from a
     * LIVE dispatch of this method). All six fields are tier='pro',
     * sku='int_falang' — Multilang is pure Pro — so Manifest\Registry locks them
     * in the UI (lock_reason 'integration_pro:falang') until the Multilang
     * licence is active. This method MUST stay OUTSIDE the Pro-strip fence.
     */
    public function onAiBoostRegisterFields(): array
    {
        if (!$this->libReady()) {
            return [];
        }

        return [
            $this->manifestField('falang_hreflang_head', 'social', 'hreflang', 'Multilang: hreflang in <head>', 'toggle', '1', [
                'description' => 'Generate <link rel="alternate" hreflang> tags from the published language list.',
            ]),
            $this->manifestField('falang_hreflang_sitemap', 'sitemap', 'hreflang', 'Multilang: hreflang in sitemap'),
            $this->manifestField('falang_hreflang_mode', 'sitemap', 'hreflang', 'Hreflang source mode', 'select', 'auto', [
                'options'     => [
                    'auto'          => 'Auto: Joomla native first, Falang fallback',
                    'joomla_native' => 'Joomla native only',
                    'falang'        => 'Falang only',
                ],
                'description' => 'Choose how sitemap hreflang alternates are sourced when Joomla multilingual associations and Falang are both present.',
            ]),
            $this->manifestField('falang_schema_translate', 'schema', 'translation', 'Multilang: translate Schema.org per language'),
            $this->manifestField('falang_og_translate', 'social', 'og', 'Multilang: translate OpenGraph per language'),
            $this->manifestField('falang_primary_language', 'general', 'multilingual', 'Multilang: primary language SEF', 'text', 'en', [
                'description' => 'SEF code used as x-default in hreflang alternates.',
            ]),
        ];
    }

    /**
     * Multilang is pure Pro, so every field defaults to tier='pro',
     * sku='int_falang'. The signature still accepts overrides for symmetry with
     * the other integrations.
     *
     * @param array<string,mixed> $extra
     */
    private function manifestField(
        string $key,
        string $tab,
        string $section,
        string $label,
        string $type = 'toggle',
        string $default = '1',
        array $extra = [],
        string $tier = 'pro',
        string $sku = 'int_falang'
    ): array {
        return array_merge([
            'key'         => $key,
            'tab'         => $tab,
            'section'     => $section,
            'label'       => $label,
            'type'        => $type,
            'default'     => $default,
            'tier'        => $tier,
            'sku'         => $sku,
            'integration' => 'falang',
        ], $extra);
    }

    /**
     * Whether the shared AiBoost\Lib library is fully loadable.
     *
     * This class can only be defined when AbstractIntegrationPlugin resolved,
     * but a partial base-package uninstall can leave lib/autoload.php (and a
     * few lib files) on disk while others — BridgeDetector, ConflictManager —
     * are gone, and the first reference then fatals on every page. Probing
     * two core lib classes detects that state so every event handler can
     * no-op instead. This is a tripwire, not an exhaustive integrity check.
     * The try/catch matters: under JDEBUG Joomla's debug class loader THROWS
     * on a missing class file instead of returning false. Used by
     * onAiBoostRegisterFields, so it stays OUTSIDE the Pro-strip fence.
     */
    private function libReady(): bool
    {
        if ($this->libReady !== null) {
            return $this->libReady;
        }
        try {
            $this->libReady = class_exists('AiBoost\\Lib\\PluginRegistry')
                && class_exists('AiBoost\\Lib\\Logger');
        } catch (\Throwable $e) {
            $this->libReady = false;
        }
        return $this->libReady;
    }

    // @pro:start
    // ── Runtime gate (PRO) ─────────────────────────────────────────────────

    /**
     * The single runtime gate for all Multilang emission: the lib is loadable,
     * the admin master toggle is on (isAdminEnabled — the
     * integration_falang_enabled switch), AND an active Multilang licence is
     * present (per-integration licensing, independent of the core bundle). The
     * whole method is stripped from the Free build.
     *
     * Deliberately does NOT require Falang host detection (the isDetected half of
     * isActive): Multilang is a Pro product for ANY multilingual Joomla site,
     * native or Falang. The multilingual precondition is enforced where it
     * matters (onBeforeCompileHead bails below 2 published languages); Falang,
     * when present, only enriches the data (the alias map). So HEAD hreflang
     * serves native-Joomla-multilingual sites too — gated solely on the master
     * toggle + the Multilang licence.
     */
    private function proOn(): bool
    {
        if (!$this->libReady() || !$this->isAdminEnabled()) {
            return false;
        }
        try {
            return PluginRegistry::hasPro('int_falang');
        } catch (\Throwable) {
            return false;
        }
    }

    // ── Bridge: register translation data with BridgeDetector ──────────────

    public function onAiBoostBeforeSitemapBuild(): void
    {
        if (!$this->proOn()) {
            return;
        }
        if (!(int) $this->aiBoostSetting('falang_hreflang_sitemap', $this->params->get('falang_hreflang_sitemap', 1))) {
            return;
        }
        if (!class_exists(BridgeDetector::class)) {
            return;
        }

        BridgeDetector::registerHreflangMode($this->hreflangMode());

        $langs = $this->getLanguages();
        if (!empty($langs)) {
            BridgeDetector::registerSitemapLanguages($langs);
        }

        $primary = $this->primaryLanguageSef();
        if ($primary !== '') {
            BridgeDetector::registerPrimaryLanguageSef($primary);
        }

        $aliasMap = $this->loadFalangAliasMap();
        if (!empty($aliasMap)) {
            BridgeDetector::registerFalangAliasMap($aliasMap);
        }
    }

    private function hreflangMode(): string
    {
        $mode = (string) $this->aiBoostSetting('falang_hreflang_mode', $this->params->get('falang_hreflang_mode', 'auto'));
        return in_array($mode, ['auto', 'joomla_native', 'falang'], true) ? $mode : 'auto';
    }

    /**
     * SEF of the language that x-default must point at — the SITE'S DEFAULT
     * language, resolved DYNAMICALLY so it is correct on ANY site whatever its
     * default (the default can change over time and is NOT necessarily English).
     *
     * B7 (order 0006): the old code used the `falang_primary_language` setting,
     * whose manifest default is 'en' — so on a site whose default language was
     * e.g. sr-YU, x-default pointed at the (wrong) /en/ URL, which in the head
     * also collided with and overwrote the en-gb self-alternate. Now x-default
     * follows the native Joomla SITE DEFAULT CONTENT language (Language Manager →
     * Installed → Site → Default = com_languages params['site']) mapped to its
     * published SEF — NOT the global config default and NOT the per-request
     * active language — falling back to the legacy setting then 'en' only when it
     * cannot be resolved. See [[joomla-site-default-vs-global-language]].
     */
    private function primaryLanguageSef(): string
    {
        $fallback = trim((string) $this->aiBoostSetting(
            'falang_primary_language',
            $this->params->get('falang_primary_language', 'en')
        ));

        // The frontend / SEO default = the SITE DEFAULT CONTENT language
        // (Language Manager → Installed Languages → Site → "Default" flag). In
        // Joomla this is a DISTINCT setting from the global Default Language
        // (Global Configuration / configuration.php $language): it is stored in
        // the com_languages component params under the per-client key 'site'.
        // (See administrator/components/com_languages InstalledModel: the Default
        // flag reads/writes ComponentHelper::getParams('com_languages')->get(
        // ApplicationHelper::getClientInfo($clientId)->name).) This is native
        // Joomla — correct for ANY multilingual site, Falang or not — and is NOT
        // the per-request active language (which Falang swaps) nor the global
        // config default. For frontend/hreflang/SEO the SITE default always wins.
        $defaultLangCode = $this->resolveSiteDefaultLanguage();

        return BridgeDetector::resolvePrimaryLanguageSef($defaultLangCode, $this->getLanguages(), $fallback);
    }

    /**
     * T1·S6 — the SITE default CONTENT language, now sourced from the ONE resolver
     * field `PageContext::siteDefaultLanguage` instead of an ad-hoc
     * `com_languages 'site'` read.
     *
     * PRIMARY source is unchanged — the resolver's `siteDefaultLanguage` IS the
     * `com_languages 'site'` value — so on every functional multilingual site
     * (where x-default applies and `site` is always set) the value is identical to
     * before (golden-diff identical, e.g. staging `sr-YU`).
     *
     * Empty-`site` EDGE (per Bojan's fallback-direction rule, a FRONT-END signal
     * falls back to a FRONT-END default): the resolver falls back to the STABLE
     * front-end config default language (`$app->get('language')` on the SITE app —
     * NOT the per-request active language, NOT a hardcoded 'en'). resolvePrimary­
     * LanguageSef() then maps whatever code this is to a PUBLISHED front-end
     * language's SEF, so a value that is not a published front-end language can
     * never leak into x-default; the legacy `falang_primary_language` setting
     * remains only as the very-last-resort when nothing matches. See
     * [[joomla-site-default-vs-global-language]].
     *
     * Guarded: if the Page classes are absent (partial install) the legacy
     * `com_languages 'site'` read is used, byte-identical to the pre-S6 behaviour.
     */
    private function resolveSiteDefaultLanguage(): string
    {
        // class_exists is INSIDE the try: under JDEBUG the debug class loader THROWS
        // on a missing class file (partial uninstall) instead of returning false, so
        // probing outside try/catch could fatal before this intended legacy read.
        try {
            if (class_exists('AiBoost\\Lib\\Page\\PageResolver')) {
                return trim((string) AdapterRegistry::pageResolver()->resolve()->siteDefaultLanguage);
            }
        } catch (\Throwable) {
            // fall through to the legacy read
        }
        try {
            return trim((string) ComponentHelper::getParams('com_languages')->get('site', ''));
        } catch (\Throwable) {
            return '';
        }
    }

    private function aiBoostSetting(string $key, mixed $default = null): mixed
    {
        static $settings = null;

        if ($settings === null) {
            $settings = $this->loadAiBoostSettings();
        }

        return $settings[$key] ?? $default;
    }

    /** @return array<string,mixed> */
    private function loadAiBoostSettings(): array
    {
        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from($db->quoteName('#__aiboost_settings'))
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
            $json = $db->setQuery($query)->loadResult();
            $data = $json ? json_decode((string) $json, true) : [];

            return is_array($data) ? $data : [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function onBeforeCompileHead(): void
    {
        if (!$this->proOn()) {
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

        // Per-feature head toggle. Canonical source is the manifest-backed
        // `falang_hreflang_head` setting (the field the admin actually sees);
        // it falls back to the legacy `falang_hreflang_enabled` plugin param so
        // sites that disabled head hreflang the old way stay disabled until they
        // touch the new field.
        $legacyDefault = $this->params->get('falang_hreflang_enabled', 1) ? '1' : '0';
        if ((string) $this->aiBoostSetting('falang_hreflang_head', $legacyDefault) === '0') {
            return;
        }

        // Multilingual precondition — hreflang is meaningless with one language.
        $languages = $this->getLanguages();
        if (count($languages) < 2) {
            return;
        }

        $this->injectHreflangTags($document, $languages);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /** @return array<int, array<string,string>> */
    private function getLanguages(): array
    {
        if ($this->languages !== null) {
            return $this->languages;
        }
        $out = [];
        try {
            $db = Factory::getDbo();
            $q  = $db->getQuery(true)
                ->select(['lang_id', 'lang_code', 'sef', 'title'])
                ->from($db->quoteName('#__languages'))
                ->where($db->quoteName('published') . ' = 1')
                ->order('ordering ASC');
            $db->setQuery($q);
            foreach ((array) $db->loadAssocList() as $row) {
                $out[] = [
                    'lang_id'   => (string) ($row['lang_id']   ?? ''),
                    'lang_code' => (string) ($row['lang_code'] ?? ''),
                    'sef'       => (string) ($row['sef']       ?? ''),
                    'title'     => (string) ($row['title']     ?? ''),
                ];
            }
        } catch (\Throwable) { /* silent */ }
        return $this->languages = $out;
    }

    /**
     * Build [orig_alias => [sef => translated_alias]] from #__falang_content
     * for menu, articles, and categories. Falang Pro schema:
     *   reference_table, reference_field='alias', reference_id, language_id, value
     * Returns an empty map when Falang is absent (native-only sites) — the head
     * links then fall back to native routing.
     *
     * @return array<string, array<string,string>>
     */
    private function loadFalangAliasMap(): array
    {
        $map = [];

        // Falang misconfiguration / absent (native-only site): the translation
        // table may not exist. Bail out cleanly instead of letting a failed
        // query surface a DB warning into the head/sitemap output.
        if (class_exists(BridgeDetector::class) && !BridgeDetector::tableExists('#__falang_content')) {
            return $map;
        }

        try {
            $db    = Factory::getDbo();
            $fetch = function (string $refTable, string $joinTable, string $joinAlias) use ($db, &$map): void {
                try {
                    $q = $db->getQuery(true)
                        ->select([
                            $db->quoteName($joinAlias . '.alias', 'orig'),
                            $db->quoteName('fc.value', 'translated'),
                            $db->quoteName('l.sef', 'sef'),
                        ])
                        ->from($db->quoteName('#__falang_content', 'fc'))
                        ->join('INNER', $db->quoteName($joinTable, $joinAlias)
                            . ' ON ' . $db->quoteName($joinAlias . '.id') . ' = ' . $db->quoteName('fc.reference_id'))
                        ->join('INNER', $db->quoteName('#__languages', 'l')
                            . ' ON ' . $db->quoteName('l.lang_id') . ' = ' . $db->quoteName('fc.language_id'))
                        ->where($db->quoteName('fc.reference_table') . ' = ' . $db->quote($refTable))
                        ->where($db->quoteName('fc.reference_field') . ' = ' . $db->quote('alias'))
                        ->where($db->quoteName('fc.published') . ' = 1')
                        ->where($db->quoteName('l.published') . ' = 1');
                    $db->setQuery($q);
                    foreach ($db->loadObjectList() as $row) {
                        $o = trim((string) ($row->orig ?? ''));
                        $t = trim((string) ($row->translated ?? ''));
                        $s = trim((string) ($row->sef ?? ''));
                        if ($o !== '' && $t !== '' && $s !== '') {
                            $map[$o][$s] = $t;
                        }
                    }
                } catch (\Throwable) { /* skip this entity type */ }
            };

            $fetch('menu', '#__menu', 'm');
            $fetch('#__content', '#__content', 'a');
            $fetch('#__categories', '#__categories', 'c');
        } catch (\Throwable) { /* silent */ }
        return $map;
    }

    /** @param array<int, array<string,string>> $languages */
    private function injectHreflangTags(object $document, array $languages): void
    {
        try {
            $app        = Factory::getApplication();
            $menu       = $app->getMenu()->getActive();
            $currentUri = Uri::getInstance();
            $baseUrl    = $currentUri->getScheme() . '://' . $currentUri->getHost();
            // x-default follows the SITE'S DEFAULT language, resolved dynamically
            // (see primaryLanguageSef) — identical source as the sitemap path so
            // head and sitemap x-default stay consistent. Pointing it at the real
            // default (e.g. /sr/ on a Serbian-default site) also stops it from
            // colliding with — and overwriting — the en-gb self-alternate.
            $primarySef = $this->primaryLanguageSef();
            $aliasMap   = $this->loadFalangAliasMap();
            [$defaultUrl] = $this->addLanguageHeadLinks(
                $document,
                $languages,
                $menu,
                $baseUrl,
                $currentUri,
                $aliasMap,
                $primarySef
            );

            // x-default points at the primary language's URL, but ONLY when the
            // configured primary SEF resolved to a REAL published language
            // ($defaultUrl was set while iterating the languages above). The old
            // code fabricated $baseUrl . '/' . $primarySef . '/' with $primarySef
            // defaulting to 'en' — so on a non-English-default site (e.g. es-ES,
            // English not installed) it advertised a non-existent /en/ home as the
            // x-default. When the primary cannot be resolved to a published
            // language we now skip x-default entirely (search engines fall back,
            // and the sitemap still emits the correct site-default x-default).
            if ($defaultUrl !== null) {
                $this->addCustomHreflangLink($document, 'x-default', $defaultUrl);
            }

            // Front-end discipline: hreflang alternates are emitted via Joomla's
            // native head stream (addHeadLink), so register the slot as natively
            // owned in the consolidated header so no other plugin double-emits.
            if (class_exists(HeadBlockBuilder::class)) {
                HeadBlockBuilder::noteNative('hreflang');
            }
        } catch (\Throwable) { /* graceful degradation */ }
    }

    /**
     * @param array<int, array<string,string>> $languages
     * @param array<string,array<string,string>> $aliasMap
     * @return array{0:?string,1:bool}
     */
    private function addLanguageHeadLinks(
        object $document,
        array $languages,
        ?object $menu,
        string $baseUrl,
        Uri $currentUri,
        array $aliasMap,
        string $primarySef
    ): array {
        $defaultUrl = null;
        $primaryEmitted = false;
        $langSefs = $this->languageSefs($languages);

        foreach ($languages as $lang) {
            $linkData = $this->normalizeLanguageLinkData($lang);
            if ($linkData === null) {
                continue;
            }

            [$sef, $hreflang] = $linkData;
            $url = $this->buildLanguageUrl($menu, $sef, $baseUrl, $currentUri, $langSefs, $aliasMap);
            $document->addHeadLink($url, 'alternate', 'rel', ['hreflang' => $hreflang]);

            if ($sef === $primarySef) {
                $defaultUrl = $url;
                $primaryEmitted = true;
            }
        }

        return [$defaultUrl, $primaryEmitted];
    }

    /**
     * Emit a single hreflang alternate via Joomla's native head stream.
     * Front-end discipline: NEVER addCustomTag() for head content — use
     * addHeadLink so the tag flows through the sanctioned channel.
     */
    private function addCustomHreflangLink(object $document, string $hreflang, string $href): void
    {
        $document->addHeadLink($href, 'alternate', 'rel', ['hreflang' => $hreflang]);
    }

    /** @param array<int, array<string,string>> $languages */
    private function languageSefs(array $languages): array
    {
        return array_values(array_filter(array_map(
            static fn (array $lang): string => (string) ($lang['sef'] ?? ''),
            $languages
        )));
    }

    /** @param array<string,string> $lang */
    private function normalizeLanguageLinkData(array $lang): ?array
    {
        $sef      = trim((string) ($lang['sef'] ?? ''));
        $langCode = trim((string) ($lang['lang_code'] ?? ''));

        return $sef !== '' && $langCode !== ''
            ? [$sef, strtolower(str_replace('_', '-', $langCode))]
            : null;
    }

    /**
     * @param array<int,string> $allSefs
     * @param array<string,array<string,string>> $aliasMap
     */
    private function buildLanguageUrl(
        ?object $menu,
        string $sef,
        string $baseUrl,
        Uri $currentUri,
        array $allSefs,
        array $aliasMap = []
    ): string {
        $aliasUrl = $this->buildAliasLanguageUrl($menu, $sef, $baseUrl, $aliasMap);
        if ($aliasUrl !== '') {
            return $aliasUrl;
        }

        $routeUrl = $this->buildRoutedLanguageUrl($menu, $sef, $baseUrl);
        return $routeUrl !== ''
            ? $routeUrl
            : $baseUrl . '/' . $sef . $this->stripLanguagePrefix($currentUri->getPath(), $allSefs);
    }

    /** @param array<string,array<string,string>> $aliasMap */
    private function buildAliasLanguageUrl(?object $menu, string $sef, string $baseUrl, array $aliasMap): string
    {
        $menuAlias = $menu ? trim((string) ($menu->alias ?? '')) : '';
        if ($menuAlias === '' || empty($aliasMap[$menuAlias][$sef])) {
            return '';
        }

        return $baseUrl . '/' . $sef . '/' . $aliasMap[$menuAlias][$sef];
    }

    private function buildRoutedLanguageUrl(?object $menu, string $sef, string $baseUrl): string
    {
        if (!$menu) {
            return '';
        }

        try {
            $routed = Route::_('index.php?Itemid=' . (int) $menu->id . '&lang=' . $sef, false, Route::TLS_IGNORE, true);
            return str_starts_with($routed, 'http') ? $routed : $baseUrl . $routed;
        } catch (\Throwable) {
            return '';
        }
    }

    /** @param array<int,string> $allSefs */
    private function stripLanguagePrefix(string $path, array $allSefs): string
    {
        $cleanPath = '/' . ltrim($path, '/');
        foreach ($allSefs as $existingSef) {
            if ($existingSef === '') {
                continue;
            }
            if (str_starts_with($cleanPath, '/' . $existingSef . '/')) {
                return substr($cleanPath, strlen('/' . $existingSef)) ?: '/';
            }
            if ($cleanPath === '/' . $existingSef) {
                return '/';
            }
        }

        return $cleanPath;
    }
    // @pro:end
}
