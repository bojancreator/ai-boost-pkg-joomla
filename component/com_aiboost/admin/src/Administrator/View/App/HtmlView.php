<?php
/**
 * AI Boost — SPA Shell View
 *
 * Renders the empty SPA mount point + window.aiBoostBootstrap. All actual UI
 * is rendered by Vue (AppShell.vue + vue-router). Each individual view
 * (?view=dashboard, ?view=settings, …) is still available as legacy fallback.
 *
 * @package     AiBoost\Component\AiBoost\Administrator\View\App
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Component\AiBoost\Administrator\View\App;

defined('_JEXEC') or die;

use AiBoost\Component\AiBoost\Administrator\Controller\ErrorsController;
use AiBoost\Version;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public string $version          = '';
    public array  $bootstrap        = [];
    public string $bootstrapJson    = '{}';

    public function display($tpl = null): void
    {
        $this->version       = Version::VERSION;
        $this->bootstrap     = $this->buildBootstrap();
        $this->bootstrapJson = json_encode(
            $this->bootstrap,
            JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        // Tell the SPA which content language is the SITE DEFAULT (the language
        // the admin types into the main fields). TranslationExpander excludes it
        // from the per-language editors. Without this, the Vue useTranslations
        // composable falls back to a hardcoded 'en-GB' until an async
        // settings.getLanguages call returns, briefly mis-rendering the real
        // default language as a "translation" row on a non-English-default site.
        // In the admin app $app->get('language') is the configured site default
        // (languagefilter does not run here). Mirrors View/Settings/HtmlView.php.
        $defaultLangCode = (string) Factory::getApplication()->get('language', 'en-GB');
        $this->getDocument()->addScriptDeclaration(
            'window.aiBoostDefaultLang=' . json_encode(
                $defaultLangCode,
                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT
            ) . ';'
        );

        $this->addToolbar();
        parent::display($tpl);
    }

    private function addToolbar(): void
    {
        $edition = $this->detectProInstall() ? 'PRO' : 'FREE';
        ToolbarHelper::title('AI Boost ' . $edition . ' <small>v' . Version::VERSION . '</small>', 'bolt');
    }

    /**
     * Static bootstrap data the SPA needs immediately on first paint.
     *
     * Per-view data (dashboard plugin status, health checks, etc.) is fetched
     * lazily by the SPA via the legacy tmpl=component URLs — see
     * vue-admin/src/composables/useLegacyGlobals.js.
     */
    /**
    * Returns true when the legacy AI Boost add-on package (or any of its
    * add-on plugins) is installed and enabled. Independent of license key
    * status so the Licenses page can be reached to enter that key.
     */
    private function detectProInstall(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        // Phase 5a — single source of truth: the install marker (survives the
        // single-plugin collapse) OR a live activation OR a legacy split layout.
        // The Licenses page must stay reachable on any Pro install — even one
        // whose key was Released or whose Pro plugins were toggled off — so the
        // user always has a path back to entering a key (Bojan's bug).
        $cached = \AiBoost\Lib\PluginRegistry::isProInstall();
        return $cached;
    }

    /**
     * Render native Joomla media fields (joomla-field-media web component) for
     * every settings key the SPA exposes a MediaPicker for. Calling getInput()
     * queues the field.media assets + media-picker options AND lets JCE (if
     * installed) swap its own browser URL into the field — so the Vue picker's
     * "Browse" opens the real, configured Joomla/JCE media manager.
     *
     * @param  array<string,mixed> $settings
     * @return array<string,string>
     */
    private function buildMediaFields(array $settings): array
    {
        $keys = [
            'org_logo'         => $settings['org_logo']         ?? ($settings['schema_logo_url'] ?? ''),
            'org_image'        => $settings['org_image']        ?? '',
            'default_og_image' => $settings['default_og_image'] ?? ($settings['og_default_image'] ?? ''),
            'schema_org_image' => $settings['schema_org_image'] ?? '',
        ];

        $out = [];
        foreach ($keys as $name => $value) {
            $out[$name] = $this->buildMediaField($name, (string) $value);
        }

        return $out;
    }

    /**
     * Build a single native Joomla media Form field (type="media"). Returns the
     * rendered joomla-field-media markup; getInput() side-effects queue the
     * required web-component assets and the com_media / JCE picker options.
     */
    private function buildMediaField(string $name, string $value): string
    {
        try {
            $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $xml = '<?xml version="1.0" encoding="UTF-8"?><form>'
                 . '<field name="' . $safeName . '" type="media"'
                 . ' preview="tooltip" class="input-xlarge" hiddenLabel="true" />'
                 . '</form>';

            $form = \Joomla\CMS\Form\Form::getInstance(
                'ab_media_' . $name,
                $xml,
                ['control' => ''],
                false,
                false
            );
            $form->setValue($name, null, $value);

            // Let form plugins decorate the field. JCE (when installed and
            // configured to replace media fields) hooks onContentPrepareForm to
            // swap its own browser URL into the field — so "Browse" opens the
            // JCE media manager instead of native com_media, honouring whatever
            // the site has configured as its default. A bare getInput() skips
            // this event, which is why the field otherwise always falls back to
            // native com_media.
            try {
                Factory::getApplication()->triggerEvent('onContentPrepareForm', [$form, []]);
            } catch (\Throwable $e) {
                // Non-fatal: fall back to the native field if a plugin errors.
            }

            return $form->getInput($name);
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function buildBootstrap(): array
    {
        $tokenName = Session::getFormToken();

        $licenseTier       = '';
        $isPro             = false;
        $proActivated      = false;
        $proActivatedAt    = null;
        $licenseHeartbeat  = new \stdClass();
        $settings          = [];
        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from($db->quoteName('#__aiboost_settings'))
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
            $db->setQuery($query);
            $raw = $db->loadResult();
            if ($raw) {
                $settings = json_decode($raw, true) ?: [];
                if (is_array($settings)) {
                    $licenseTier = (string) ($settings['license_tier'] ?? '');

                    // v0.54.2 — Pro UI is gated on a VERIFIED license key, not
                    // on `license_tier` alone. A Pro install with no entered
                    // license code must look identical to Free; only an active
                    // license key (or the dev_license_preview QA flag) unlocks
                    // the UI. This matches Bojan's directive: "Pro verzija se
                    // isto ponaša kao Free dok se ne unese licence kod i
                    // provjeri".
                    //
                    // v0.69.1 — delegate to the single canonical gate so the
                    // admin UI, the settings-save endpoint and the sitemap
                    // runtime all derive `isPro` from the SAME signal (no
                    // gating-source drift).
                    //
                    // Task #565 — PERPETUAL ACTIVATION. isProActive() now resolves
                    // from the permanent `pro_activated` flag: once a key verifies
                    // active, Pro stays unlocked forever. An expired licence only
                    // pauses updates + support (shown as a renewal notice), it
                    // never relocks the UI. The dev_force_free_tier QA override
                    // (render Pro as Free for screenshots) still wins.
                    $isPro            = \AiBoost\Lib\PluginRegistry::isProActive($settings);
                    $proActivated     = (string) ($settings['pro_activated'] ?? '0') === '1';
                    $proActivatedAt   = $settings['pro_activated_at'] ?? null;
                    if (isset($settings['license_heartbeat']) && is_array($settings['license_heartbeat'])) {
                        $hb = $settings['license_heartbeat'];
                        // Days until the next informational re-validate (display only).
                        $hb['days_until_next_check'] = \AiBoost\Lib\LicenseHeartbeat::daysUntilNextCheck($settings);
                        $licenseHeartbeat = $hb;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Settings table may not exist on first run — ignore.
        }

        // Legacy feature registry payload kept for compatibility during the
        // one-product transition.
        $proFeatures = [];
        try {
            if (class_exists('AiBoost\\Lib\\ProFeatureRegistry')) {
                $proFeatures = \AiBoost\Lib\ProFeatureRegistry::all();
            }
        } catch (\Throwable $e) {
            // Silent: SPA falls back to inline (non-gated) render.
        }

        // Conflict Manager — seed the first-run wizard + nav badge from the CHEAP,
        // DB-only named-competitor scan (no HTTP). `conflict_setup_done` gates the
        // one-time wizard; the SPA lazy-loads the heavier HTTP generic scan
        // (DuplicateTagScanner) via ConflictsController::scan when the page opens.
        $conflictSetupDone = (string) ($settings['conflict_setup_done'] ?? '0') === '1';
        $detectedConflicts = [];
        try {
            if (class_exists('AiBoost\\Lib\\ConflictDetector')) {
                $dismissed = json_decode((string) ($settings['dismissed_checks'] ?? '[]'), true);
                $dismissed = is_array($dismissed) ? $dismissed : [];
                $detectedConflicts = (new \AiBoost\Lib\ConflictDetector(Factory::getDbo(), $settings, $dismissed))->scan();
            }
        } catch (\Throwable $e) {
            // Detection must never break the SPA shell.
        }

        return [
            'version'          => Version::VERSION,
            // item 12a — lets the global CriticalBar gate the "no backup yet"
            // notice (don't nag a fresh install with nothing to back up).
            'hasSettings'      => !empty($settings),
            // True only under Joomla debug mode — the Licenses UI uses this to
            // show the offline mock-validation hint. Production verifies keys
            // against the real Lemon Squeezy licence API.
            'debug'            => (defined('JDEBUG') && JDEBUG === true),
            'tokenName'        => $tokenName,
            'csrfToken'        => $tokenName,
            'baseUrl'          => Route::_('index.php?option=com_aiboost', false),
            'ajaxBase'         => 'index.php?option=com_aiboost',
            'isPro'            => $isPro,
            // v0.55.1 — "Pro package installed" detection via #__extensions.
            // `license_tier` is materialised by saveLicenseState() and ends
            // up 'free' until a key is actually verified, so we cannot use
            // it to tell "Pro pkg installed" from "Free pkg installed".
            // Look up the Pro package + Pro plugins in the extensions
            // table instead — that is the unambiguous signal.
            // Used only by the Licenses page so a fresh Pro install (no
            // key entered yet) stays usable and the user can paste a key.
            'isProInstall'     => $this->detectProInstall(),
            'proFeatures'      => $proFeatures,
            // Server-rendered native Joomla media fields (joomla-field-media web
            // component) per settings key. Calling getInput() also queues the
            // field.media assets + media-picker options AND lets JCE (if
            // installed) swap in its own browser URL. The Vue MediaPicker renders
            // this markup so "Browse" opens the real Joomla/JCE media manager.
            'mediaFields'      => $this->buildMediaFields($settings),
            'licenseHeartbeat' => $licenseHeartbeat,
            'license'          => [
                'tier'          => $licenseTier,
                'isPro'         => $isPro,
                'isProInstall'  => $this->detectProInstall(),
                // Task #565 — perpetual activation status for the Licenses tab.
                'proActivated'  => $proActivated,
                'proActivatedAt' => $proActivatedAt,
            ],
            'urls'         => [
                'app'           => Route::_('index.php?option=com_aiboost&view=app', false),
                'settingsSave'  => Route::_('index.php?option=com_aiboost&task=settings.save&format=json', false),
                'pluginManager' => Route::_('index.php?option=com_plugins&filter[folder]=system&filter[search]=ai+boost', false),
                'conflictsScan' => Route::_('index.php?option=com_aiboost&task=conflicts.scan&format=json', false),
            ],
            'legacyUrls' => [
                'dashboard'    => Route::_('index.php?option=com_aiboost&view=dashboard',    false),
                'settings'     => Route::_('index.php?option=com_aiboost&view=settings',     false),
                'health'       => Route::_('index.php?option=com_aiboost&view=health',       false),
                'redirects'    => Route::_('index.php?option=com_aiboost&view=redirects',    false),
                'urlchecker'   => Route::_('index.php?option=com_aiboost&view=urlchecker',   false),
                'import'       => Route::_('index.php?option=com_aiboost&view=import',      false),
                'integrations' => Route::_('index.php?option=com_aiboost&view=integrations', false),
                'analyzer'     => Route::_('index.php?option=com_aiboost&view=analyzer',     false),
                'help'         => Route::_('index.php?option=com_aiboost&view=help',         false),
            ],
            'labels' => [
                'dashboard'    => Text::_('COM_AIBOOST_NAV_DASHBOARD'),
                'autopilot'    => Text::_('COM_AIBOOST_NAV_AUTOPILOT'),
                'settings'     => Text::_('COM_AIBOOST_NAV_SETTINGS'),
                'health'       => Text::_('COM_AIBOOST_NAV_HEALTH'),
                'redirects'    => Text::_('COM_AIBOOST_NAV_REDIRECTS'),
                'urlchecker'   => Text::_('COM_AIBOOST_NAV_URLCHECKER'),
                'import'       => Text::_('COM_AIBOOST_NAV_IMPORT'),
                'integrations' => Text::_('COM_AIBOOST_NAV_INTEGRATIONS'),
                'analyzers'    => Text::_('COM_AIBOOST_NAV_ANALYZERS'),
                'errors'       => Text::_('COM_AIBOOST_NAV_ERRORS'),
                'conflicts'    => Text::_('COM_AIBOOST_NAV_CONFLICTS'),
                'help'         => Text::_('COM_AIBOOST_NAV_HELP'),
            ],
            // Task #512 — seed the nav badge + Errors page so the user
            // sees the count without an extra round-trip on first paint.
            'errorsSummary' => ErrorsController::buildSummary(),
            // Conflict Manager — first-run wizard gate + detected named conflicts
            // (cheap DB scan) for the nav badge, both on first paint.
            'conflictSetupDone' => $conflictSetupDone,
            'conflicts'         => [
                'setupDone' => $conflictSetupDone,
                'detected'  => $detectedConflicts,
            ],
        ];
    }
}
