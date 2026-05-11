<?php

/**
 * JoomlaBoost - License Key Field
 *
 * Custom form field that renders a text input for the JoomlaBoost license key,
 * displays the current license status badge and tier, shows stored activation
 * details (buyer email, site, activation date, uses, remaining activations),
 * warns when a license is deactivated, and provides a "Verify License" button
 * that calls the AI Boost validation API via AJAX.
 *
 * The verify button is always rendered in the DOM so JavaScript can toggle its
 * visibility immediately as the admin types a valid UUID — no page reload needed.
 *
 * @copyright   (C) 2025 JoomlaBoost Team
 * @license     GNU General Public License version 2 or later
 */

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Field;

use Joomla\CMS\Form\Field\TextField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

defined('_JEXEC') or die;

/**
 * License Key field with status indicator, tier badge, activation details panel,
 * deactivation warning, and a Verify License button.
 */
class LicenseKeyField extends TextField
{
    /** @var string Joomla field type identifier */
    protected $type = 'LicenseKey';

    /**
     * Renders the license key text input with status badge, activation details,
     * and a Verify License button.
     *
     * @return string HTML output
     */
    protected function getInput(): string
    {
        $input    = parent::getInput();
        $rawValue = trim((string) $this->value);
        $hasKey   = $rawValue !== '';
        $isValid  = $this->isValidFormat($rawValue);

        $tier        = '';
        $email       = '';
        $activatedAt = '';
        $uses        = '';
        $maxUses     = '';
        $remaining   = '';
        $siteUrl     = '';
        $status      = '';

        if ($this->form) {
            $tier        = trim((string) $this->form->getValue('license_tier',                  'params'));
            $email       = trim((string) $this->form->getValue('license_email',                 'params'));
            $activatedAt = trim((string) $this->form->getValue('license_activated_at',          'params'));
            $uses        = trim((string) $this->form->getValue('license_uses',                  'params'));
            $maxUses     = trim((string) $this->form->getValue('license_max_uses',              'params'));
            $remaining   = trim((string) $this->form->getValue('license_remaining_activations', 'params'));
            $siteUrl     = trim((string) $this->form->getValue('license_site_url',              'params'));
            $status      = trim((string) $this->form->getValue('license_status',                'params'));
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

        $strVerifyBtn      = Text::_('PLG_SYSTEM_JOOMLABOOST_LICENSE_VERIFY_BTN');
        $strVerifying      = Text::_('PLG_SYSTEM_JOOMLABOOST_LICENSE_VERIFYING');
        $strActDetails     = Text::_('PLG_SYSTEM_JOOMLABOOST_LICENSE_ACTIVATION_DETAILS');
        $strEmailLabel     = Text::_('PLG_SYSTEM_JOOMLABOOST_LICENSE_EMAIL');
        $strActOn          = Text::_('PLG_SYSTEM_JOOMLABOOST_LICENSE_ACTIVATED_ON');
        $strSiteLabel      = Text::_('PLG_SYSTEM_JOOMLABOOST_LICENSE_SITE');
        $strUsesLabel      = Text::_('PLG_SYSTEM_JOOMLABOOST_LICENSE_USES');
        $strRemainingLabel = Text::_('PLG_SYSTEM_JOOMLABOOST_LICENSE_REMAINING');
        $strUnlimited      = Text::_('PLG_SYSTEM_JOOMLABOOST_LICENSE_UNLIMITED');
        $strVerifyOk       = Text::_('PLG_SYSTEM_JOOMLABOOST_LICENSE_VERIFY_SUCCESS');
        $strVerifyFail     = Text::_('PLG_SYSTEM_JOOMLABOOST_LICENSE_VERIFY_FAILED');
        $strVerifyErr      = Text::_('PLG_SYSTEM_JOOMLABOOST_LICENSE_VERIFY_ERROR');
        $strDeactivated    = Text::_('PLG_SYSTEM_JOOMLABOOST_LICENSE_DEACTIVATED');
        $strExpiringSoon   = Text::_('PLG_SYSTEM_JOOMLABOOST_LICENSE_EXPIRING_SOON');

        $siteRoot = rtrim(Uri::root(), '/');

        $jsLicensed       = json_encode('&#10003; ' . $strLicensed);
        $jsInvalid        = json_encode('&#9888; ' . $strInvalid);
        $jsEnterKey       = json_encode('&#x2713; ' . $strEnterKey);
        $jsHintBuy        = json_encode($strHintBuy);
        $jsHintInvalid    = json_encode($strHintInvalid);
        $jsVerifyBtn      = json_encode($strVerifyBtn);
        $jsVerifying      = json_encode($strVerifying);
        $jsActDetails     = json_encode($strActDetails);
        $jsEmailLabel     = json_encode($strEmailLabel);
        $jsActOn          = json_encode($strActOn);
        $jsSiteLabel      = json_encode($strSiteLabel);
        $jsUsesLabel      = json_encode($strUsesLabel);
        $jsRemainingLabel = json_encode($strRemainingLabel);
        $jsUnlimited      = json_encode($strUnlimited);
        $jsVerifyOk       = json_encode($strVerifyOk);
        $jsVerifyFail     = json_encode($strVerifyFail);
        $jsVerifyErr      = json_encode($strVerifyErr);
        $jsDeactivated    = json_encode($strDeactivated);
        $jsExpiringSoon   = json_encode($strExpiringSoon);
        $jsTierStarter    = json_encode(Text::_('PLG_SYSTEM_JOOMLABOOST_TIER_BADGE_STARTER'));
        $jsTierDeveloper  = json_encode(Text::_('PLG_SYSTEM_JOOMLABOOST_TIER_BADGE_DEVELOPER'));
        $jsTierAgency     = json_encode(Text::_('PLG_SYSTEM_JOOMLABOOST_TIER_BADGE_AGENCY'));
        $jsSiteRoot       = json_encode($siteRoot);

        $activationPanel = $this->renderActivationPanel(
            $statusId,
            $email,
            $activatedAt,
            $uses,
            $maxUses,
            $remaining,
            $siteUrl,
            $status,
            $strActDetails,
            $strEmailLabel,
            $strActOn,
            $strSiteLabel,
            $strUsesLabel,
            $strRemainingLabel,
            $strUnlimited,
            $strDeactivated,
            $strExpiringSoon
        );

        // The verify button is ALWAYS rendered so JS can toggle visibility right
        // after the admin types a valid UUID — no page reload needed.
        $escVerifyBtn      = htmlspecialchars($strVerifyBtn, ENT_QUOTES, 'UTF-8');
        $btnStyle          = ($hasKey && $isValid) ? '' : ' style="display:none;"';
        $verifyButton      = '<button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="' . $statusId . '_verify_btn"' . $btnStyle . '>'
            . $escVerifyBtn
            . '</button>';

        $script = <<<JS
        <script>
        (function () {
            var UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
            var STR = {
                licensed:       {$jsLicensed},
                invalid:        {$jsInvalid},
                enterKey:       {$jsEnterKey},
                hintBuy:        {$jsHintBuy},
                hintInvalid:    {$jsHintInvalid},
                verifyBtn:      {$jsVerifyBtn},
                verifying:      {$jsVerifying},
                actDetails:     {$jsActDetails},
                emailLabel:     {$jsEmailLabel},
                actOn:          {$jsActOn},
                siteLabel:      {$jsSiteLabel},
                usesLabel:      {$jsUsesLabel},
                remainingLabel: {$jsRemainingLabel},
                unlimited:      {$jsUnlimited},
                verifyOk:       {$jsVerifyOk},
                verifyFail:     {$jsVerifyFail},
                verifyErr:      {$jsVerifyErr},
                deactivated:    {$jsDeactivated},
                expiringSoon:   {$jsExpiringSoon}
            };
            var SITE_ROOT = {$jsSiteRoot};

            var init = function () {
                var fld        = document.getElementById('{$fieldId}');
                var badge      = document.getElementById('{$statusId}_badge');
                var hint       = document.getElementById('{$statusId}_hint');
                var verifyBtn  = document.getElementById('{$statusId}_verify_btn');
                var panel      = document.getElementById('{$statusId}_activation_panel');
                var noticeEl   = document.getElementById('{$statusId}_verify_notice');
                if (!fld || !badge) { return; }

                var tierMap = {
                    starter:   { label: {$jsTierStarter},   cls: 'bg-info text-dark' },
                    developer: { label: {$jsTierDeveloper}, cls: 'bg-primary' },
                    agency:    { label: {$jsTierAgency},    cls: 'bg-purple' }
                };

                var clearVerifiedState = function () {
                    var tierBadgeEl = document.getElementById('{$statusId}_tier_badge');
                    if (tierBadgeEl) { tierBadgeEl.className = ''; tierBadgeEl.textContent = ''; }
                    if (panel) { panel.style.display = 'none'; }
                    var deactWarn = document.getElementById('{$statusId}_deactivated_warn');
                    if (deactWarn) { deactWarn.style.display = 'none'; }
                    var expWarn = document.getElementById('{$statusId}_expiring_warn');
                    if (expWarn) { expWarn.style.display = 'none'; }
                };

                var update = function () {
                    var val = fld.value.trim();
                    if (val === '') {
                        badge.className = 'badge bg-secondary ms-2';
                        badge.style.fontSize = '0.85em';
                        badge.innerHTML = STR.enterKey;
                        if (hint) { hint.className = 'small text-muted mt-1'; hint.innerHTML = STR.hintBuy; }
                        if (verifyBtn) { verifyBtn.style.display = 'none'; }
                        clearVerifiedState();
                    } else if (UUID_RE.test(val)) {
                        badge.className = 'badge bg-success ms-2';
                        badge.style.fontSize = '0.85em';
                        badge.innerHTML = STR.licensed;
                        if (hint) { hint.className = 'd-none'; hint.innerHTML = ''; }
                        if (verifyBtn) { verifyBtn.style.display = ''; }
                        clearVerifiedState();
                    } else {
                        badge.className = 'badge bg-warning text-dark ms-2';
                        badge.style.fontSize = '0.85em';
                        badge.innerHTML = STR.invalid;
                        if (hint) { hint.className = 'small text-warning mt-1'; hint.innerHTML = STR.hintInvalid; }
                        if (verifyBtn) { verifyBtn.style.display = 'none'; }
                        clearVerifiedState();
                    }
                };

                fld.addEventListener('input', update);

                if (!verifyBtn) { return; }

                verifyBtn.addEventListener('click', function () {
                    var key = fld.value.trim();
                    if (!UUID_RE.test(key)) { return; }

                    verifyBtn.disabled = true;
                    verifyBtn.textContent = STR.verifying;

                    fetch(SITE_ROOT + '/api/license/validate', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ license_key: key, site_url: window.location.origin })
                    })
                    .then(function (r) {
                        if (!r.ok && r.status !== 400 && r.status !== 503) {
                            throw new Error('HTTP ' + r.status);
                        }
                        return r.json();
                    })
                    .then(function (data) {
                        verifyBtn.disabled = false;
                        verifyBtn.textContent = STR.verifyBtn;

                        if (data.valid) {
                            setHiddenParam('license_tier',                  data.tier         || '');
                            setHiddenParam('license_email',                 data.email        || '');
                            setHiddenParam('license_activated_at',          data.activated_at || '');
                            setHiddenParam('license_uses',                  String(data.uses  ?? ''));
                            setHiddenParam('license_max_uses',              String(data.max_uses ?? ''));
                            setHiddenParam('license_remaining_activations', String(data.remaining_activations ?? ''));
                            setHiddenParam('license_site_url',              data.site_url     || '');
                            setHiddenParam('license_status',                data.status       || 'active');

                            badge.className = 'badge bg-success ms-2';
                            badge.style.fontSize = '0.85em';
                            badge.innerHTML = STR.licensed;

                            var tierInfo = tierMap[data.tier] || null;
                            var tierBadgeEl = document.getElementById('{$statusId}_tier_badge');
                            if (!tierBadgeEl) {
                                tierBadgeEl = document.createElement('span');
                                tierBadgeEl.id = '{$statusId}_tier_badge';
                                tierBadgeEl.style.fontSize = '0.85em';
                                badge.parentNode.appendChild(tierBadgeEl);
                            }
                            if (tierInfo) {
                                tierBadgeEl.className = 'badge ' + tierInfo.cls + ' ms-1';
                                tierBadgeEl.textContent = tierInfo.label;
                            } else {
                                tierBadgeEl.className = '';
                                tierBadgeEl.textContent = '';
                            }

                            if (panel) {
                                renderPanel(panel, data);
                                panel.style.display = '';
                                var deactWarn = document.getElementById('{$statusId}_deactivated_warn');
                                if (deactWarn) { deactWarn.style.display = 'none'; }
                            }

                            var expWarn = document.getElementById('{$statusId}_expiring_warn');
                            if (expWarn) {
                                if (data.status === 'expiring_soon') {
                                    expWarn.textContent = STR.expiringSoon;
                                    expWarn.style.display = '';
                                } else {
                                    expWarn.style.display = 'none';
                                }
                            }

                            showNotice(noticeEl, STR.verifyOk, 'success');
                        } else {
                            setHiddenParam('license_status',                data.status || 'invalid');
                            setHiddenParam('license_tier',                  '');
                            setHiddenParam('license_email',                 '');
                            setHiddenParam('license_activated_at',          '');
                            setHiddenParam('license_uses',                  '');
                            setHiddenParam('license_max_uses',              '');
                            setHiddenParam('license_remaining_activations', '');
                            setHiddenParam('license_site_url',              '');

                            badge.className = 'badge bg-warning text-dark ms-2';
                            badge.style.fontSize = '0.85em';
                            badge.innerHTML = STR.invalid;
                            clearVerifiedState();

                            if (panel) { panel.style.display = 'none'; }

                            var deactWarn = document.getElementById('{$statusId}_deactivated_warn');
                            if (deactWarn) {
                                if (data.status === 'deactivated') {
                                    deactWarn.textContent = STR.deactivated;
                                    deactWarn.style.display = '';
                                } else {
                                    deactWarn.style.display = 'none';
                                }
                            }
                            var expWarn = document.getElementById('{$statusId}_expiring_warn');
                            if (expWarn) { expWarn.style.display = 'none'; }
                            showNotice(noticeEl, STR.verifyFail + (data.error ? ' \u2014 ' + data.error : ''), 'danger');
                        }
                    })
                    .catch(function () {
                        verifyBtn.disabled = false;
                        verifyBtn.textContent = STR.verifyBtn;
                        showNotice(noticeEl, STR.verifyErr, 'warning');
                    });
                });
            };

            function setHiddenParam(name, value) {
                var input = document.querySelector('[name="jform[params][' + name + ']"]');
                if (input) { input.value = value; }
            }

            function renderPanel(panel, data) {
                var maxLabel       = (data.max_uses === -1)              ? STR.unlimited : String(data.max_uses);
                var remainingLabel = (data.remaining_activations === -1) ? STR.unlimited : String(data.remaining_activations);
                var activatedDate  = '';
                if (data.activated_at) {
                    try {
                        activatedDate = new Date(data.activated_at).toLocaleDateString(undefined, {
                            year: 'numeric', month: 'long', day: 'numeric'
                        });
                    } catch(e) {
                        activatedDate = data.activated_at;
                    }
                }

                panel.innerHTML =
                    '<table class="table table-sm table-bordered mb-0" style="font-size:0.875em;">' +
                    '<tbody>' +
                    (data.email      ? '<tr><th>' + STR.emailLabel     + '</th><td>' + esc(data.email)       + '</td></tr>' : '') +
                    (activatedDate   ? '<tr><th>' + STR.actOn          + '</th><td>' + esc(activatedDate)    + '</td></tr>' : '') +
                    (data.site_url   ? '<tr><th>' + STR.siteLabel      + '</th><td>' + esc(data.site_url)    + '</td></tr>' : '') +
                    '<tr><th>' + STR.usesLabel      + '</th><td>' + String(data.uses ?? 0) + ' / ' + maxLabel       + '</td></tr>' +
                    '<tr><th>' + STR.remainingLabel + '</th><td>' + remainingLabel + '</td></tr>' +
                    '</tbody></table>';
            }

            function esc(str) {
                var d = document.createElement('div');
                d.textContent = str;
                return d.innerHTML;
            }

            function showNotice(el, msg, type) {
                if (!el) { return; }
                el.className = 'alert alert-' + type + ' mt-2 py-1 px-2 small';
                el.textContent = msg;
                el.style.display = '';
                setTimeout(function () {
                    el.style.display = 'none';
                }, 7000);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
        </script>
        JS;

        $statusHtml = '<div class="d-flex align-items-center mt-1 flex-wrap gap-1">'
            . '<span id="' . $statusId . '_badge">' . $badge . '</span>'
            . '</div>'
            . '<div id="' . $statusId . '_hint">' . $hint . '</div>'
            . $activationPanel
            . $verifyButton
            . '<div id="' . $statusId . '_verify_notice" style="display:none;"></div>';

        return $input . $statusHtml . $script;
    }

    /**
     * Renders the readonly activation details panel from stored params.
     *
     * Shows: buyer email, activation date, active site, activations used (X of Y),
     * remaining activations.  If license_status is 'deactivated', shows a warning
     * banner instead of (or in addition to) the details table.
     */
    private function renderActivationPanel(
        string $statusId,
        string $email,
        string $activatedAt,
        string $uses,
        string $maxUses,
        string $remaining,
        string $siteUrl,
        string $status,
        string $strActDetails,
        string $strEmailLabel,
        string $strActOn,
        string $strSiteLabel,
        string $strUsesLabel,
        string $strRemainingLabel,
        string $strUnlimited,
        string $strDeactivated,
        string $strExpiringSoon
    ): string {
        $hasData = ($email !== '' || $activatedAt !== '' || $uses !== '');

        // Deactivated warning — shown on page load only when status is explicitly 'deactivated'.
        // JS also sets textContent and toggles visibility when a live verify returns status:'deactivated'.
        if ($status === 'deactivated') {
            $escDeactivated     = htmlspecialchars($strDeactivated, ENT_QUOTES, 'UTF-8');
            $deactivatedWarning = '<div id="' . $statusId . '_deactivated_warn" class="alert alert-danger mt-2 py-1 px-2 small">'
                . $escDeactivated . '</div>';
        } else {
            $deactivatedWarning = '<div id="' . $statusId . '_deactivated_warn" class="alert alert-danger mt-2 py-1 px-2 small" style="display:none;"></div>';
        }

        // Expiring-soon warning — shown when status is 'expiring_soon' (subscription licenses only).
        if ($status === 'expiring_soon') {
            $escExpiringSoon    = htmlspecialchars($strExpiringSoon, ENT_QUOTES, 'UTF-8');
            $expiringWarning    = '<div id="' . $statusId . '_expiring_warn" class="alert alert-warning mt-2 py-1 px-2 small">'
                . $escExpiringSoon . '</div>';
        } else {
            $expiringWarning    = '<div id="' . $statusId . '_expiring_warn" class="alert alert-warning mt-2 py-1 px-2 small" style="display:none;"></div>';
        }

        if (!$hasData) {
            return $deactivatedWarning
                . $expiringWarning
                . '<div id="' . $statusId . '_activation_panel" style="display:none;" class="mt-2"></div>';
        }

        $maxLabel       = ($maxUses === '-1'   || $maxUses === '')   ? htmlspecialchars($strUnlimited, ENT_QUOTES, 'UTF-8') : htmlspecialchars($maxUses,   ENT_QUOTES, 'UTF-8');
        $remainingLabel = ($remaining === '-1' || $remaining === '') ? htmlspecialchars($strUnlimited, ENT_QUOTES, 'UTF-8') : htmlspecialchars($remaining, ENT_QUOTES, 'UTF-8');
        $usesNum        = $uses !== '' ? htmlspecialchars($uses, ENT_QUOTES, 'UTF-8') : '0';

        $activatedFormatted = '';
        if ($activatedAt !== '') {
            $ts = strtotime($activatedAt);
            $activatedFormatted = $ts ? date('d M Y', $ts) : htmlspecialchars($activatedAt, ENT_QUOTES, 'UTF-8');
        }

        $escActDetails     = htmlspecialchars($strActDetails,     ENT_QUOTES, 'UTF-8');
        $escEmailLabel     = htmlspecialchars($strEmailLabel,     ENT_QUOTES, 'UTF-8');
        $escActOn          = htmlspecialchars($strActOn,          ENT_QUOTES, 'UTF-8');
        $escSiteLabel      = htmlspecialchars($strSiteLabel,      ENT_QUOTES, 'UTF-8');
        $escUsesLabel      = htmlspecialchars($strUsesLabel,      ENT_QUOTES, 'UTF-8');
        $escRemainingLabel = htmlspecialchars($strRemainingLabel, ENT_QUOTES, 'UTF-8');
        $escEmail          = htmlspecialchars($email,             ENT_QUOTES, 'UTF-8');
        $escSiteUrl        = htmlspecialchars($siteUrl,           ENT_QUOTES, 'UTF-8');

        $rows = '';
        if ($email !== '') {
            $rows .= '<tr><th style="width:40%;">' . $escEmailLabel . '</th><td>' . $escEmail . '</td></tr>';
        }
        if ($activatedFormatted !== '') {
            $rows .= '<tr><th>' . $escActOn . '</th><td>' . $activatedFormatted . '</td></tr>';
        }
        if ($siteUrl !== '') {
            $rows .= '<tr><th>' . $escSiteLabel . '</th><td>' . $escSiteUrl
                . '<br><span class="text-muted" style="font-size:0.8em;font-style:italic;">(' . htmlspecialchars(Text::_('PLG_SYSTEM_JOOMLABOOST_LICENSE_SITE_DISPLAY_ONLY'), ENT_QUOTES, 'UTF-8') . ')</span>'
                . '</td></tr>';
        }
        $rows .= '<tr><th>' . $escUsesLabel      . '</th><td>' . $usesNum . ' / ' . $maxLabel . '</td></tr>';
        $rows .= '<tr><th>' . $escRemainingLabel . '</th><td>' . $remainingLabel . '</td></tr>';

        return $deactivatedWarning
            . $expiringWarning
            . '<div id="' . $statusId . '_activation_panel" class="mt-2">'
            . '<p class="small fw-semibold text-muted mb-1">' . $escActDetails . '</p>'
            . '<table class="table table-sm table-bordered mb-0" style="font-size:0.875em;"><tbody>'
            . $rows
            . '</tbody></table>'
            . '</div>';
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

        return '<span id="' . htmlspecialchars($this->id . '_status_tier_badge', ENT_QUOTES, 'UTF-8') . '" class="badge ' . $class . ' ms-1" style="font-size:0.85em;">' . $label . '</span>';
    }

    /**
     * Validates the license key format (UUID pattern).
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
