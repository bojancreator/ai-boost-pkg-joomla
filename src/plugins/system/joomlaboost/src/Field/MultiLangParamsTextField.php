<?php

/**
 * Multi-Language Params Text Field for JoomlaBoost
 *
 * Dynamically renders a text input for every installed Joomla site language.
 * Saves directly as plugin params: {fieldName}_{2charcode} (e.g. org_name_en, org_name_sr).
 * Reading side: getLocalizedParam() in SchemaService already handles this pattern.
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
 * MultiLangParamsTextField
 *
 * Renders one <input type="text"> per installed Joomla site language.
 * Each input maps to a plugin param: {fieldName}_{2charcode}.
 */
class MultiLangParamsTextField extends FormField
{
    use MultiLangFieldTrait;

    /** @var string */
    protected $type = 'MultiLangParamsTextField';

    protected function getInput(): string
    {
        $languages  = $this->getInstalledLanguages();
        $params     = $this->getPluginParams();
        $hint       = (string) ($this->element['hint'] ?? '');
        $class      = (string) ($this->element['class'] ?? 'form-control');
        $fieldId    = $this->id;
        $selectorId = 'jb-mltf-sel-' . $fieldId;

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

        // ── Inputs ─────────────────────────────────────────────────────────────
        $html[] = '<div class="jb-mlt-inputs">';
        foreach ($languages as $i => $lang) {
            $code      = $lang['code'];
            $paramName = $this->name . '_' . $code;  // e.g. org_name_en
            $value     = (string) $params->get($paramName, '');
            $display   = (count($languages) === 1 || $i === 0) ? 'flex' : 'none';
            $inputName = 'jform[params][' . $paramName . ']';
            $inputId   = $fieldId . '_' . $code;

            $html[] = sprintf('<div class="jb-mlt-row" data-lang="%s" style="display:%s;align-items:center;gap:6px;margin-bottom:4px">', $code, $display);
            $html[] = sprintf('<span title="%s" style="font-size:1.2em;min-width:24px">%s</span>', htmlspecialchars($lang['name'], ENT_QUOTES, 'UTF-8'), $this->getFlag($code));
            $html[] = sprintf(
                '<input type="text" name="%s" id="%s" value="%s" placeholder="%s" class="%s" style="flex:1">',
                htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($value, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($hint, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($class, ENT_QUOTES, 'UTF-8')
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
