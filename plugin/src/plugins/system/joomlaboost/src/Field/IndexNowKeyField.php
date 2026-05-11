<?php

/**
 * JoomlaBoost - IndexNow Key Field
 *
 * Custom form field that renders a text input for the IndexNow API key
 * with a "Generate Key" button. The button is disabled when the field
 * already has a value, and enabled when the field is empty.
 *
 * @copyright   (C) 2024 emarket1ng.NET
 * @license     GNU General Public License version 2 or later
 */

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Field;

use Joomla\CMS\Form\Field\TextField;

defined('_JEXEC') or die;

/**
 * IndexNow API Key field with auto-generate button.
 */
class IndexNowKeyField extends TextField
{
    /** @var string Joomla field type identifier */
    protected $type = 'IndexNowKey';

    /**
     * Renders the text input + Generate Key button.
     *
     * @return string HTML output
     */
    protected function getInput(): string
    {
        // Render the standard text input
        $input = parent::getInput();

        $btnId    = 'jb-gen-' . $this->id;
        $hasValue = !empty(trim((string) $this->value));
        $disabled = $hasValue ? ' disabled="disabled"' : '';
        $hint     = $hasValue
            ? '<small class="text-muted ms-2">Clear the field to generate a new key.</small>'
            : '';

        $btn = <<<HTML
        <div class="d-flex align-items-center mt-2 gap-2">
            <button type="button"
                    id="{$btnId}"
                    class="btn btn-sm btn-outline-secondary"{$disabled}
                    title="Generate a random 32-character API key">
                &#128273; Generate API Key
            </button>
            {$hint}
        </div>
        HTML;

        // Inline script — no external file needed
        $fieldId = $this->id;
        $script  = <<<JS
        <script>
        (function () {
            var init = function () {
                var btn = document.getElementById('{$btnId}');
                var fld = document.getElementById('{$fieldId}');
                if (!btn || !fld) { return; }

                // Enable/disable button based on field value
                var sync = function () {
                    var empty = fld.value.trim().length === 0;
                    btn.disabled = !empty;
                    var hint = btn.nextElementSibling;
                    if (hint) {
                        hint.style.display = empty ? 'none' : 'inline';
                    }
                };

                fld.addEventListener('input', sync);
                sync();

                // Generate 32-char hex key on click
                btn.addEventListener('click', function () {
                    if (btn.disabled) { return; }
                    var key = '';
                    for (var i = 0; i < 32; i++) {
                        key += '0123456789abcdef'[Math.floor(Math.random() * 16)];
                    }
                    fld.value = key;
                    fld.dispatchEvent(new Event('input'));
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
        </script>
        JS;

        return $input . $btn . $script;
    }
}
