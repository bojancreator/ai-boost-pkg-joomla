<?php

/**
 * MultiLang Field Trait for JoomlaBoost
 *
 * Shared helpers for dynamic language-aware form fields.
 * Reads active site languages from #__languages DB table instead of filesystem,
 * ensuring Falang / multilingual setups are fully supported.
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System.Field
 * @since       0.9.7
 */

declare(strict_types=1);

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Field;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Shared functionality for multilingual plugin param fields.
 *
 * @since 0.9.7
 */
trait MultiLangFieldTrait
{
    /**
     * Get site languages for form field rendering.
     *
     * Merges results from BOTH sources:
     *   1. #__falang_languages (Falang - if installed)
     *   2. #__languages        (native Joomla multilingual)
     *
     * Both are always queried and deduplicated by 2-char code so that
     * sites using Falang + native Joomla both see all configured languages.
     * Falls back to English if neither source returns any data.
     *
     * @return array<int, array{code: string, name: string, tag: string}>
     */
    private function getInstalledLanguages(): array
    {
        $db     = Factory::getDbo();
        $seen   = [];
        $result = [];

        // ── 1. Falang ─────────────────────────────────────────────────────────
        try {
            $db->setQuery(
                'SELECT fl.lang_code,'
                . ' COALESCE(l.title, fl.lang_code) AS name'
                . ' FROM ' . $db->quoteName('#__falang_languages') . ' AS fl'
                . ' LEFT JOIN ' . $db->quoteName('#__languages') . ' AS l'
                . '   ON l.lang_id = fl.joomla_lang_id'
                . ' ORDER BY fl.id ASC'
            );
            foreach ((array) $db->loadObjectList() as $row) {
                $code = strtolower(substr((string) $row->lang_code, 0, 2));
                if ($code !== '' && !isset($seen[$code])) {
                    $seen[$code] = true;
                    $result[]    = [
                        'code' => $code,
                        'name' => (string) $row->name,
                        'tag'  => (string) $row->lang_code,
                    ];
                }
            }
        } catch (\Throwable $e) {
            // #__falang_languages does not exist — not a Falang site
        }

        // ── 2. Native Joomla #__languages ────────────────────────────────────
        // Always query, even when Falang returned results, so native-only
        // languages are included too (e.g. a language in #__languages that is
        // not yet in Falang, or a non-Falang site).
        try {
            $db->setQuery(
                'SELECT lang_code, title AS name'
                . ' FROM ' . $db->quoteName('#__languages')
                . ' WHERE published = 1'
                . ' ORDER BY ordering ASC'
            );
            foreach ((array) $db->loadObjectList() as $row) {
                $code = strtolower(substr((string) $row->lang_code, 0, 2));
                if ($code !== '' && !isset($seen[$code])) {
                    $seen[$code] = true;
                    $result[]    = [
                        'code' => $code,
                        'name' => (string) $row->name,
                        'tag'  => (string) $row->lang_code,
                    ];
                }
            }
        } catch (\Throwable $e) {
            // fall through
        }

        // ── 3. Fallback ───────────────────────────────────────────────────────
        return !empty($result)
            ? $result
            : [['code' => 'en', 'name' => 'English', 'tag' => 'en-GB']];
    }

    /**
     * Read plugin params from DB for reading existing values.
     */
    private function getPluginParams(): Registry
    {
        try {
            $plugin = PluginHelper::getPlugin('system', 'joomlaboost');
            return new Registry($plugin->params ?? '{}');
        } catch (\Throwable $e) {
            return new Registry();
        }
    }

    /**
     * Language flag emoji map.
     */
    private function getFlag(string $code): string
    {
        // Using text country codes instead of emoji to avoid encoding issues
        // across different server/editor environments.
        $map = [
            'en' => '[EN]', 'sr' => '[SR]', 'me' => '[ME]', 'hr' => '[HR]',
            'bs' => '[BS]', 'ru' => '[RU]', 'de' => '[DE]', 'fr' => '[FR]',
            'it' => '[IT]', 'es' => '[ES]', 'pt' => '[PT]', 'nl' => '[NL]',
            'pl' => '[PL]', 'cs' => '[CS]', 'sk' => '[SK]', 'hu' => '[HU]',
            'ro' => '[RO]', 'bg' => '[BG]', 'sl' => '[SL]', 'uk' => '[UK]',
            'tr' => '[TR]', 'ar' => '[AR]', 'zh' => '[ZH]', 'ja' => '[JA]',
        ];
        return $map[$code] ?? '[' . strtoupper($code) . ']';
    }

    /**
     * Inline JS that wires the language selector dropdown to
     * show/hide the corresponding input rows (data-lang attribute).
     *
     * Used by MultiLangParamsTextField and MultiLangParamsTextarea.
     *
     * @param  string $selectorId  The <select> element ID
     * @return string              <script> HTML block
     */
    private function getSwitcherJs(string $selectorId): string
    {
        return <<<JS
<script>
(function () {
    var sel = document.getElementById('{$selectorId}');
    if (!sel) { return; }
    function applyLang(lang) {
        var wrap = sel.closest('.jb-multilang-params-field');
        if (!wrap) { return; }
        wrap.querySelectorAll('[data-lang]').forEach(function (el) {
            el.style.display = (lang === 'all' || el.dataset.lang === lang) ? '' : 'none';
        });
    }
    sel.addEventListener('change', function () { applyLang(sel.value); });
    // Apply initial state (first language)
    if (sel.options.length > 1) { applyLang(sel.options[1].value); sel.value = sel.options[1].value; }
})();
</script>
JS;
    }
}
