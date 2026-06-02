<?php
/**
 * AI Boost — AEO — Custom field: Generate Key
 *
 * Renders a text input alongside a "Generate Key" button.
 * The button uses the Web Crypto API to create a cryptographically
 * random 32-character hex string and populate the input.
 *
 * @package  AiBoost\Plugin\System\AiBoostAeo\Field
 * @version  1.1.0
 */

namespace AiBoost\Plugin\System\AiBoostAeo\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Form\FormField;

class GenerateKeyField extends FormField
{
    /** @var string Joomla form field type identifier */
    public $type = 'Generatekey';

    protected function getInput(): string
    {
        $value = htmlspecialchars((string) ($this->value ?? ''), ENT_QUOTES, 'UTF-8');
        $id    = $this->id;
        $name  = $this->name;
        $size  = (int) ($this->element['size'] ?? 50);

        $html  = '<div class="input-group flex-nowrap" style="max-width:600px;">';
        $html .= '<input type="text"'
               . ' id="' . $id . '"'
               . ' name="' . $name . '"'
               . ' value="' . $value . '"'
               . ' size="' . $size . '"'
               . ' class="form-control"'
               . ' autocomplete="off"'
               . ' spellcheck="false"'
               . ' placeholder="Click &quot;Generate Key&quot; or paste your own key"'
               . ' />';
        $html .= '<button type="button"'
               . ' class="btn btn-secondary"'
               . ' onclick="aiboostGenerateKey(\'' . $id . '\')"'
               . ' title="Generate a cryptographically random 32-character IndexNow key">';
        $html .= '<span class="icon-refresh" aria-hidden="true"></span>&nbsp;Generate Key';
        $html .= '</button>';
        $html .= '</div>';

        // Inline JS — only output once per page (guard via window flag)
        $html .= <<<JS
<script>
if (!window._aiboostGenKeyLoaded) {
    window._aiboostGenKeyLoaded = true;
    window.aiboostGenerateKey = function(fieldId) {
        var arr = new Uint8Array(16);
        window.crypto.getRandomValues(arr);
        var hex = Array.from(arr).map(function(b) {
            return b.toString(16).padStart(2, '0');
        }).join('');
        var el = document.getElementById(fieldId);
        if (el) {
            el.value = hex;
            // Trigger change event so Joomla form detects the modification
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };
}
</script>
JS;

        return $html;
    }
}
