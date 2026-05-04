<?php

/**
 * Multi-Language FAQ Field for JoomlaBoost
 *
 * Dynamically renders a FAQ JSON textarea for every installed Joomla site language.
 * Saves as plugin params: manual_faqs_en, manual_faqs_sr, etc.
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System.Field
 * @since       0.9.5
 * @author      JoomlaBoost Team
 * @copyright   (C) 2026 JoomlaBoost. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Field;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * MultiLangFaqField
 *
 * Renders one JSON textarea per installed Joomla site language.
 * Each textarea maps to a plugin param: manual_faqs_{2charcode}.
 * The field reads existing values directly from plugin params.
 */
class MultiLangFaqField extends FormField
{
    use MultiLangFieldTrait;

    /** @var string */
    protected $type = 'MultiLangFaqField';

    /**
     * Render the field HTML
     */
    protected function getInput(): string
    {
        $languages  = $this->getInstalledLanguages();
        $params     = $this->getPluginParams();
        $rows       = (string) ($this->element['rows'] ?? '8');
        $fieldId    = $this->id;
        $selectorId = 'jb-faq-lang-selector-' . $fieldId;

        // ── TEMP DEBUG ───────────────────────────────────────────────────────
        $debugInfo = 'Languages found: ' . count($languages) . ' | ';
        foreach ($languages as $l) {
            $debugInfo .= $l['code'] . '(' . $l['tag'] . ') ';
        }
        try {
            $db = \Joomla\CMS\Factory::getDbo();
            $db->setQuery('SELECT id, lang_code, published FROM ' . $db->quoteName('#__falang_languages'));
            $raw = $db->loadObjectList();
            $debugInfo .= '| Falang rows: ';
            foreach ($raw as $r) {
                $debugInfo .= $r->lang_code . '(pub=' . $r->published . ') ';
            }
        } catch (\Throwable $e) {
            $debugInfo .= '| Falang ERR: ' . $e->getMessage();
        }
        $debugHtml = '<div style="background:#fff3cd;border:1px solid #ffc107;padding:8px;margin-bottom:8px;font-size:11px;font-family:monospace">'
            . '🔍 JB Debug: ' . htmlspecialchars($debugInfo, ENT_QUOTES, 'UTF-8')
            . '</div>';
        // ── END DEBUG ────────────────────────────────────────────────────────

        if (empty($languages)) {
            return $debugHtml . '<p class="alert alert-warning">No site languages detected.</p>';
        }

        $html = [];
        $html[] = $debugHtml; // TEMP DEBUG
        $html[] = '<div class="jb-multilang-faq-wrapper">';

        // ── Language selector ─────────────────────────────────────────────────
        $html[] = '<div class="jb-faq-lang-selector-wrap" style="margin-bottom:8px">';
        $html[] = '<select id="' . $selectorId . '" class="form-select" style="max-width:280px">';
        $html[] = '<option value="all">🌍 All Languages</option>';

        foreach ($languages as $lang) {
            $html[] = sprintf(
                '<option value="%s">%s %s</option>',
                htmlspecialchars($lang['code'], ENT_QUOTES, 'UTF-8'),
                $this->getFlag($lang['code']),
                htmlspecialchars($lang['name'], ENT_QUOTES, 'UTF-8')
            );
        }

        $html[] = '</select>';
        $html[] = '</div>';

        // ── One textarea per language ──────────────────────────────────────────
        $html[] = '<div class="jb-faq-inputs">';

        foreach ($languages as $i => $lang) {
            $code      = $lang['code'];
            $paramName = 'manual_faqs_' . $code;
            $value     = (string) $params->get($paramName, '');
            $display   = ($i === 0) ? 'block' : 'none';

            // Input name must match plugin param structure: jform[params][manual_faqs_en]
            $inputName = 'jform[params][' . $paramName . ']';

            $html[] = sprintf('<div class="jb-faq-lang-block" data-lang="%s" style="display:%s;">', $code, $display);
            $html[] = sprintf(
                '<label style="font-weight:600;margin-bottom:4px;display:block">%s %s</label>',
                $this->getFlag($code),
                htmlspecialchars($lang['name'], ENT_QUOTES, 'UTF-8')
            );
            $html[] = sprintf(
                '<textarea name="%s" id="%s_%s" rows="%s" class="form-control" '
                . 'placeholder=\'[{"question":"...","answer":"..."}]\' '
                . 'style="font-family:monospace;font-size:12px">%s</textarea>',
                htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($code, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($rows, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
            );
            $html[] = '</div>';
        }

        $html[] = '</div>'; // .jb-faq-inputs
        $html[] = '</div>'; // .jb-multilang-faq-wrapper

        // ── Language switcher JS ───────────────────────────────────────────────
        $html[] = <<<JS
<script>
(function() {
    var sel = document.getElementById('{$selectorId}');
    if (!sel) return;
    sel.addEventListener('change', function() {
        var val = this.value;
        var blocks = this.closest('.jb-multilang-faq-wrapper')
                         .querySelectorAll('.jb-faq-lang-block[data-lang]');
        blocks.forEach(function(b) {
            b.style.display = (val === 'all' || b.dataset.lang === val) ? 'block' : 'none';
        });
    });
})();
</script>
JS;

        return implode("\n", $html);
    }

    /**
     * Get label — use parent default
     */
    protected function getLabel(): string
    {
        return parent::getLabel();
    }

    // ── Helpers ─────────────────────────────────────── (from MultiLangFieldTrait) ──
}
