<?php

/**
 * JoomlaBoost - License Key Field
 *
 * Custom form field that renders a text input for the JoomlaBoost license key
 * and displays the current license status (Licensed ✅ / Enter your license key)
 * together with the active license tier badge (Starter / Developer / Agency).
 *
 * @copyright   (C) 2025 JoomlaBoost Team
 * @license     GNU General Public License version 2 or later
 */

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Field;

use Joomla\CMS\Form\Field\TextField;
use Joomla\CMS\Language\Text;

defined('_JEXEC') or die;

/**
 * License Key field with status indicator and tier badge.
 */
class LicenseKeyField extends TextField
{
    /** @var string Joomla field type identifier */
    protected $type = 'LicenseKey';

    /**
     * Renders the license key text input with status badge and tier indicator.
     *
     * @return string HTML output
     */
    protected function getInput(): string
    {
        $input    = parent::getInput();
        $rawValue = trim((string) $this->value);
        $hasKey   = $rawValue !== '';
        $isValid  = $this->isValidFormat($rawValue);

        $tier = '';
        if ($this->form) {
            // Plugin params are stored under the 'params' group in com_plugins forms
            $tier = trim((string) $this->form->getValue('license_tier', 'params'));
        }

        $strLicensed    = Text::_('PLG_SYSTEM_JOOMLABOOST_LICENSE_STATUS_LICENSED');
        $strInvalid     = Text::_('PLG_SYSTEM_JOOMLABOOST_LICENSE_STATUS_INVALID_FORMAT');
        $strEnterKey    = Text::_('PLG_SYSTEM_JOOMLABOOST_LICENSE_STATUS_ENTER_KEY');
        $strHintBuy     = Text::_('PLG_SYSTEM_JOOMLABOOST_LICENSE_HINT_BUY');
        $strHintInvalid = Text::_('PLG_SYSTEM_JOOMLABOOST_LICENSE_HINT_INVALID');

        $escLicensed    = htmlspecialchars($strLicensed, ENT_QUOTES, 'UTF-8');
        $escInvalid     = htmlspecialchars($strInvalid, ENT_QUOTES, 'UTF-8');
        $escEnterKey    = htmlspecialchars($strEnterKey, ENT_QUOTES, 'UTF-8');
        $escHintBuy     = htmlspecialchars($strHintBuy, ENT_QUOTES, 'UTF-8');
        $escHintInvalid = htmlspecialchars($strHintInvalid, ENT_QUOTES, 'UTF-8');

        if ($hasKey && $isValid) {
            $badge = '<span class="badge bg-success ms-2" style="font-size:0.85em;">&#10003; ' . $escLicensed . '</span>';
            $badge .= $this->renderTierBadge($tier);
            $hint  = '';
        } elseif ($hasKey && !$isValid) {
            $badge = '<span class="badge bg-warning text-dark ms-2" style="font-size:0.85em;">&#9888; ' . $escInvalid . '</span>';
            $hint  = '<div class="small text-warning mt-1">' . $escHintInvalid . '</div>';
        } else {
            $badge = '<span class="badge bg-secondary ms-2" style="font-size:0.85em;">&#x2713; ' . $escEnterKey . '</span>';
            $hint  = '<div class="small text-muted mt-1">' . $escHintBuy . '</div>';
        }

        $fieldId  = $this->id;
        $statusId = $this->id . '_status';

        $jsLicensed    = json_encode('&#10003; ' . $strLicensed);
        $jsInvalid     = json_encode('&#9888; ' . $strInvalid);
        $jsEnterKey    = json_encode('&#x2713; ' . $strEnterKey);
        $jsHintBuy     = json_encode($strHintBuy);
        $jsHintInvalid = json_encode($strHintInvalid);

        $script = <<<JS
        <script>
        (function () {
            var UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
            var STR = {
                licensed:    {$jsLicensed},
                invalid:     {$jsInvalid},
                enterKey:    {$jsEnterKey},
                hintBuy:     {$jsHintBuy},
                hintInvalid: {$jsHintInvalid}
            };
            var init = function () {
                var fld   = document.getElementById('{$fieldId}');
                var badge = document.getElementById('{$statusId}_badge');
                var hint  = document.getElementById('{$statusId}_hint');
                if (!fld || !badge) { return; }

                var update = function () {
                    var val = fld.value.trim();
                    if (val === '') {
                        badge.className = 'badge bg-secondary ms-2';
                        badge.style.fontSize = '0.85em';
                        badge.innerHTML = STR.enterKey;
                        if (hint) { hint.className = 'small text-muted mt-1'; hint.innerHTML = STR.hintBuy; }
                    } else if (UUID_RE.test(val)) {
                        badge.className = 'badge bg-success ms-2';
                        badge.style.fontSize = '0.85em';
                        badge.innerHTML = STR.licensed;
                        if (hint) { hint.className = 'd-none'; hint.innerHTML = ''; }
                    } else {
                        badge.className = 'badge bg-warning text-dark ms-2';
                        badge.style.fontSize = '0.85em';
                        badge.innerHTML = STR.invalid;
                        if (hint) { hint.className = 'small text-warning mt-1'; hint.innerHTML = STR.hintInvalid; }
                    }
                };

                fld.addEventListener('input', update);
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
        </script>
        JS;

        $statusHtml = '<div class="d-flex align-items-center mt-1">'
            . '<span id="' . $statusId . '_badge">' . $badge . '</span>'
            . '</div>'
            . '<div id="' . $statusId . '_hint">' . $hint . '</div>';

        return $input . $statusHtml . $script;
    }

    /**
     * Renders a tier badge for valid licensed keys.
     *
     * @param   string  $tier  One of: 'starter', 'developer', 'agency', or empty
     *
     * @return  string  HTML badge or empty string
     */
    private function renderTierBadge(string $tier): string
    {
        $map = [
            'starter'   => ['label' => Text::_('PLG_SYSTEM_JOOMLABOOST_TIER_BADGE_STARTER'),   'class' => 'bg-info text-dark'],
            'developer' => ['label' => Text::_('PLG_SYSTEM_JOOMLABOOST_TIER_BADGE_DEVELOPER'), 'class' => 'bg-primary'],
            'agency'    => ['label' => Text::_('PLG_SYSTEM_JOOMLABOOST_TIER_BADGE_AGENCY'),    'class' => 'bg-purple'],
        ];

        if (!isset($map[$tier])) {
            return '';
        }

        $label = htmlspecialchars($map[$tier]['label'], ENT_QUOTES, 'UTF-8');
        $class = htmlspecialchars($map[$tier]['class'], ENT_QUOTES, 'UTF-8');

        return '<span class="badge ' . $class . ' ms-1" style="font-size:0.85em;">' . $label . '</span>';
    }

    /**
     * Validates the license key format (UUID pattern).
     * Phase 2 will add server-side Gumroad API validation.
     *
     * @param   string  $value  Raw key value
     *
     * @return  bool
     */
    private function isValidFormat(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $value
        );
    }
}
