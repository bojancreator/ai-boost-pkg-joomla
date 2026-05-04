<?php

/**
 * Multi-Language Textarea Field for JoomlaBoost
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System.Field
 * @since       0.6.0
 * @author      JoomlaBoost Team
 * @copyright   (C) 2026 JoomlaBoost
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Field;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Factory;
use JoomlaBoost\Plugin\System\JoomlaBoost\Services\TranslationService;

\defined('_JEXEC') or die;

/**
 * Multi-Language Textarea Field
 *
 * Renders language selector dropdown + textareas for each installed language
 */
class MultiLangTextarea extends FormField
{
    /**
     * The form field type
     *
     * @var string
     */
    protected $type = 'MultiLangTextarea';

    /**
     * Layout to render the field
     *
     * @return string HTML output
     */
    protected function getInput(): string
    {
        // Get field configuration
        $fieldKey = $this->element['field_key'] ?? 'undefined';
        $hint = $this->element['hint'] ?? '';
        $rows = $this->element['rows'] ?? '3';
        $class = $this->element['class'] ?? '';

        // Get installed languages
        $languages = $this->getInstalledLanguages();

        // Get existing translations
        $translations = $this->getTranslations($fieldKey);

        // Generate unique field ID
        $fieldId = $this->id;
        $selectorId = "lang-selector-{$fieldId}";

        // Start output
        $html = [];
        $html[] = '<div class="multilang-field-wrapper">';

        // Language selector dropdown
        $html[] = '<div class="multilang-selector">';
        $html[] = '<select id="' . $selectorId . '" class="form-select multilang-dropdown">';
        $html[] = '<option value="all">🌍 All Languages</option>';

        foreach ($languages as $i => $lang) {
            $selected = ($i === 0) ? ' selected' : '';
            $html[] = sprintf(
                '<option value="%s"%s>%s %s</option>',
                $lang['code'],
                $selected,
                $this->getLanguageFlag($lang['code']),
                $lang['name']
            );
        }

        $html[] = '</select>';
        $html[] = '</div>';

        // Language textarea fields
        $html[] = '<div class="multilang-inputs">';

        foreach ($languages as $i => $lang) {
            $langCode = $lang['code'];
            $value = $translations[$langCode] ?? '';
            $display = ($i === 0) ? 'block' : 'none';

            $html[] = sprintf('<div class="multilang-input" data-lang="%s" style="display: %s;">', $langCode, $display);
            $html[] = sprintf(
                '<label class="multilang-label"><span class="lang-flag">%s</span> %s</label>',
                $this->getLanguageFlag($langCode),
                $lang['name']
            );

            // Hidden textarea to send data to server
            $inputName = sprintf('jform[translations][%s][%s]', $fieldKey, $langCode);

            $html[] = sprintf(
                '<textarea name="%s" rows="%s" class="form-control %s" placeholder="%s">%s</textarea>',
                $inputName,
                $rows,
                $class,
                htmlspecialchars($hint, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
            );

            $html[] = '</div>';
        }

        $html[] = '</div>'; // .multilang-inputs
        $html[] = '</div>'; // .multilang-field-wrapper

        // Add JavaScript for language switching
        $html[] = $this->getJavaScript($selectorId);

        return implode("\n", $html);
    }

    /**
     * Get JavaScript for language selector
     *
     * @param string $selectorId Selector dropdown ID
     * @return string JavaScript code
     */
    private function getJavaScript(string $selectorId): string
    {
        return <<<JS
<script>
(function() {
    const selector = document.getElementById('{$selectorId}');
    if (selector) {
        selector.addEventListener('change', function(e) {
            const selectedLang = e.target.value;
            const container = e.target.closest('.multilang-field-wrapper');
            const inputs = container.querySelectorAll('.multilang-input[data-lang]');

            inputs.forEach(function(input) {
                if (selectedLang === 'all') {
                    input.style.display = 'block';
                } else {
                    input.style.display = input.dataset.lang === selectedLang ? 'block' : 'none';
                }
            });
        });
    }
})();
</script>
JS;
    }

    /**
     * Get installed site languages
     *
     * @return array<int, array{code: string, name: string, tag: string}>
     */
    private function getInstalledLanguages(): array
    {
        $languages = LanguageHelper::getInstalledLanguages(0); // 0 = site languages
        $result = [];

        foreach ($languages as $lang) {
            // Extract 2-letter code from tag (e.g., 'en-GB' → 'en')
            $code = strtolower(substr($lang->lang_code, 0, 2));

            $result[] = [
                'code' => $code,
                'name' => $lang->name,
                'tag' => $lang->lang_code
            ];
        }

        return $result;
    }

    /**
     * Get existing translations for field key
     *
     * @param string $fieldKey Field identifier
     * @return array<string, string> Translations array
     */
    private function getTranslations(string $fieldKey): array
    {
        try {
            // Get plugin params to initialize service
            $plugin = \Joomla\CMS\Plugin\PluginHelper::getPlugin('system', 'joomlaboost');
            $params = new \Joomla\Registry\Registry($plugin->params ?? '{}');

            $app = Factory::getApplication();
            $service = new TranslationService($app, $params);

            return $service->getAll($fieldKey);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get language flag emoji
     *
     * @param string $code Language code
     * @return string Flag emoji
     */
    private function getLanguageFlag(string $code): string
    {
        $flags = [
            'en' => '🇬🇧',
            'sr' => '🇷🇸',
            'ru' => '🇷🇺',
            'fr' => '🇫🇷',
            'de' => '🇩🇪',
            'it' => '🇮🇹',
            'es' => '🇪🇸',
            'pt' => '🇵🇹',
            'nl' => '🇳🇱',
            'pl' => '🇵🇱',
            'cs' => '🇨🇿',
            'sk' => '🇸🇰',
            'hu' => '🇭🇺',
            'ro' => '🇷🇴',
            'bg' => '🇧🇬',
            'hr' => '🇭🇷',
            'sl' => '🇸🇮',
            'uk' => '🇺🇦',
        ];

        return $flags[$code] ?? '🏳️';
    }
}
