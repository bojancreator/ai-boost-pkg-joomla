<?php

/**
 * Multi-Language Params Textarea Field for JoomlaBoost
 *
 * Same as MultiLangParamsTextField but renders <textarea> elements.
 * Saves directly as plugin params: {fieldName}_{2charcode}.
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System.Field
 * @since       0.9.6
 */

declare(strict_types=1);

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Field;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * MultiLangParamsTextarea
 *
 * Renders one <textarea> per installed Joomla site language.
 * Each maps to a plugin param: {fieldName}_{2charcode}.
 */
class MultiLangParamsTextarea extends FormField
{
    use MultiLangFieldTrait;

    /** @var string */
    protected $type = 'MultiLangParamsTextarea';

    protected function getInput(): string
    {
        $languages  = $this->getInstalledLanguages();
        $params     = $this->getPluginParams();
        $hint       = (string) ($this->element['hint'] ?? '');
        $rows       = (string) ($this->element['rows'] ?? '3');
        $class      = (string) ($this->element['class'] ?? 'form-control');
        $fieldId    = $this->id;
        $selectorId = 'jb-mlta-sel-' . $fieldId;

        if (empty($languages)) {
            return '<p class="alert alert-warning">No site languages detected.</p>';
        }

        $html   = [];
        $html[] = '<div class="jb-multilang-params-field">';

        // ── Language selector ─────────────────────────────────────────────────
        if (count($languages) > 1) {
            $html[] = '<div style="margin-bottom:6px">';
            $html[] = '<select id="' . $selectorId . '" class="form-select" style="max-width:240px;display:inline-block">';
            $html[] = '<option value="all">🌍 All</option>';
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
        }

        // ── Textareas ─────────────────────────────────────────────────────────
        $html[] = '<div class="jb-mlt-inputs">';
        foreach ($languages as $i => $lang) {
            $code      = $lang['code'];
            $paramName = $this->name . '_' . $code;
            $value     = (string) $params->get($paramName, '');
            $display   = (count($languages) === 1 || $i === 0) ? 'block' : 'none';
            $inputName = 'jform[params][' . $paramName . ']';
            $inputId   = $fieldId . '_' . $code;

            $html[] = sprintf('<div class="jb-mlt-block" data-lang="%s" style="display:%s;margin-bottom:6px">', $code, $display);
            $html[] = sprintf(
                '<label style="font-size:.85em;color:#666;margin-bottom:2px;display:block">%s %s</label>',
                $this->getFlag($code),
                htmlspecialchars($lang['name'], ENT_QUOTES, 'UTF-8')
            );
            $html[] = sprintf(
                '<textarea name="%s" id="%s" rows="%s" placeholder="%s" class="%s">%s</textarea>',
                htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($rows, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($hint, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($class, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
            );
            $html[] = '</div>';
        }
        $html[] = '</div>'; // .jb-mlt-inputs
        $html[] = '</div>'; // .jb-multilang-params-field

        if (count($languages) > 1) {
            $html[] = $this->getSwitcherJs($selectorId);
        }

        return implode("\n", $html);
    }

    // Helpers via MultiLangFieldTrait
}
