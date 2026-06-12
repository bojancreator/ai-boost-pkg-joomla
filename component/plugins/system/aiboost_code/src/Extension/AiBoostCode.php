<?php
/**
 * AI Boost — Custom Code Plugin Extension Class.
 *
 * Reads custom_code_head / custom_code_body / custom_code_footer from
 * #__aiboost_settings and injects them into the page on every request
 * (or on specific menu items when scope = 'specific').
 *
 * Hooks used:
 *   onBeforeCompileHead — injects <head> snippets via Document::addCustomTag()
 *   onAfterRender       — injects <body> and <footer> snippets via string-replace
 *
 * Settings keys read from DB:
 *   enable_custom_code         (0|1)          — master toggle
 *   custom_code_head           (string)       — raw HTML to inject before </head>
 *   custom_code_head_scope     (all|specific) — injection scope for head code
 *   custom_code_head_menu_ids  (JSON array)   — menu item IDs for head scope = specific
 *   custom_code_body           (string)       — raw HTML to inject after <body>
 *   custom_code_body_scope     (all|specific) — injection scope for body code
 *   custom_code_body_menu_ids  (JSON array)   — menu item IDs for body scope = specific
 *   custom_code_footer         (string)       — raw HTML to inject before </body>
 *   custom_code_footer_scope   (all|specific) — injection scope for footer code
 *   custom_code_footer_menu_ids (JSON array)  — menu item IDs for footer scope = specific
 *   staging_mode               (0|1)          — skip injection on staging
 *   debug_mode                 (0|1)          — write error_log entries
 *
 * Legacy fallback: if a field-specific scope key is absent, the plugin falls
 * back to the shared custom_code_scope / custom_code_menu_ids keys so that
 * settings saved before v0.8.x continue to work without migration.
 *
 * @package     AiBoost\Plugin\System\AiBoostCode
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Plugin\System\AiBoostCode\Extension;

defined('_JEXEC') or die;

use AiBoost\Lib\BodyBlockBuilder;
use AiBoost\Lib\HeadBlockBuilder;
use AiBoost\Version;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

class AiBoostCode extends CMSPlugin
{
    protected $autoloadLanguage = true;

    /** Cached result of libReady() — null until first probed. */
    private ?bool $libReady = null;

    // ─────────────────────────────────────────────────────────────────────────
    // Settings loader (cached per request)
    // ─────────────────────────────────────────────────────────────────────────

    private function getSettings(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from($db->quoteName('#__aiboost_settings'))
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
            $json  = $db->setQuery($query)->loadResult();
            $cache = $json ? (json_decode($json, true) ?? []) : [];
        } catch (\Throwable $e) {
            $cache = [];
        }
        return $cache;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Global guard (master toggle + staging)
    // ─────────────────────────────────────────────────────────────────────────

    private function isEnabled(array $settings): bool
    {
        if (empty($settings['enable_custom_code']) || (string) ($settings['enable_custom_code'] ?? '0') === '0') {
            return false;
        }

        if (!empty($settings['staging_mode'])) {
            error_log('[AI Boost: aiboost_code] STAGING MODE ON — custom head/body/footer code injection is suppressed. Disable staging_mode in Debug tab to see output.');
            return false;
        }

        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Per-field scope check
    //
    // Reads the field-specific scope key (e.g. custom_code_head_scope).
    // Falls back to the legacy shared key (custom_code_scope) when the
    // field-specific key is not present, preserving backward compatibility.
    // ─────────────────────────────────────────────────────────────────────────

    private function isFieldApplicable(array $settings, string $field): bool
    {
        $scopeKey   = 'custom_code_' . $field . '_scope';
        $menuIdsKey = 'custom_code_' . $field . '_menu_ids';

        // Prefer field-specific scope; fall back to legacy shared scope key
        if (isset($settings[$scopeKey])) {
            $scope = (string) $settings[$scopeKey];
        } else {
            $scope = (string) ($settings['custom_code_scope'] ?? 'all');
        }

        if ($scope !== 'specific') {
            return true;
        }

        // Prefer field-specific menu IDs; fall back to legacy shared menu IDs
        if (isset($settings[$menuIdsKey])) {
            $rawIds = (string) $settings[$menuIdsKey];
        } else {
            $rawIds = (string) ($settings['custom_code_menu_ids'] ?? '[]');
        }

        $menuIds = json_decode($rawIds, true);
        if (!is_array($menuIds) || empty($menuIds)) {
            return false;
        }

        try {
            $active = Factory::getApplication()->getMenu()->getActive();
            if (!$active) {
                return false;
            }
            return in_array((int) $active->id, array_map('intval', $menuIds), true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Hooks
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Inject custom <head> code before Joomla compiles the <head> section.
     * Uses Document::addCustomTag() — the same API other AI Boost plugins use
     * for injecting raw HTML into the <head>.
     */
    public function onBeforeCompileHead(): void
    {
        if (!$this->libReady()) {
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

        $settings = $this->getSettings();

        // Set hide-comments flag FIRST — before any early-return paths (#384).
        $hide = !empty($settings['hide_comments']);
        HeadBlockBuilder::setHideComments($hide);
        BodyBlockBuilder::setHideComments($hide);

        if (!$this->isEnabled($settings)) {
            return;
        }

        $debug = !empty($settings['debug_mode']);

        // ── <head> snippet ──────────────────────────────────────────────────
        if ($this->isFieldApplicable($settings, 'head')) {
            $headCode = trim((string) ($settings['custom_code_head'] ?? ''));
            if ($headCode !== '') {
                if ($debug) {
                    error_log('[AI Boost: aiboost_code] onBeforeCompileHead — pushing ' . strlen($headCode) . ' chars into <head>');
                }
                // Custom head HTML is the last sub-section of the consolidated
                // AI Boost head block — user-supplied code goes last so it can
                // override / append to anything AI Boost emitted above it.
                HeadBlockBuilder::pushSection(HeadBlockBuilder::SECTION_CODE, $headCode);
            }
        }

        // ── <body> snippet — queued for the consolidated body wrapper (#384) ─
        if ($this->isFieldApplicable($settings, 'body')) {
            $bodyCode = trim((string) ($settings['custom_code_body'] ?? ''));
            if ($bodyCode !== '') {
                if ($debug) {
                    error_log('[AI Boost: aiboost_code] onBeforeCompileHead — queueing ' . strlen($bodyCode) . ' chars after <body>');
                }
                BodyBlockBuilder::pushBody('Custom Body Code', $bodyCode);
            }
        }

        // ── footer snippet — queued for the consolidated footer wrapper ─────
        if ($this->isFieldApplicable($settings, 'footer')) {
            $footerCode = trim((string) ($settings['custom_code_footer'] ?? ''));
            if ($footerCode !== '') {
                if ($debug) {
                    error_log('[AI Boost: aiboost_code] onBeforeCompileHead — queueing ' . strlen($footerCode) . ' chars before </body>');
                }
                BodyBlockBuilder::pushFooter('Custom Footer Code', $footerCode);
            }
        }
    }

    /**
     * Finalize the consolidated AI Boost head + body blocks. Idempotent —
     * first AI Boost plugin to run wins; subsequent calls no-op. All custom
     * <head>, <body>, and footer HTML was queued in onBeforeCompileHead.
     */
    public function onAfterRender(): void
    {
        if (!$this->libReady()) {
            return;
        }

        $app = Factory::getApplication();
        HeadBlockBuilder::finalize($app, Version::VERSION);
        BodyBlockBuilder::finalize($app);
    }

    /**
     * Whether the shared AiBoost\Lib library is fully loadable.
     *
     * The plugin entry file only checks that lib/autoload.php exists — not
     * enough: a partial base-package uninstall can leave autoload.php on disk
     * while individual lib/src class files are gone, and the first lib
     * reference then fatals on every page. Probing two core lib classes
     * detects that state so every lib-touching event handler can no-op
     * instead. This is a tripwire, not an exhaustive integrity check. The
     * try/catch matters: under JDEBUG Joomla's debug class loader THROWS on
     * a missing class file instead of returning false.
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
}
