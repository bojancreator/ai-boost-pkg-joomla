/**
 * AI Boost — Dashboard plugin toggle
 * Inline Enable / Disable buttons on each module card.
 * Disable requires a two-step confirmation; Enable fires immediately.
 * @package AiBoost
 */
(function () {
    'use strict';

    var token     = document.getElementById('ab-csrf-token');
    var tokenName = token ? token.getAttribute('data-token-name') : null;

    if (!tokenName) { return; }

    var CONFIRM_TIMEOUT = 3000; // ms before pending confirmation auto-cancels

    function resetDisableBtn(btn) {
        btn.disabled      = false;
        btn.textContent   = 'Disable';
        btn.setAttribute('data-ab-pending', '0');
        btn.classList.remove('btn-danger', 'ab-btn-confirming');

        var cancelLink = btn.parentNode && btn.parentNode.querySelector('.ab-cancel-disable');
        if (cancelLink) { cancelLink.remove(); }

        if (btn._abConfirmTimer) {
            clearTimeout(btn._abConfirmTimer);
            btn._abConfirmTimer = null;
        }
    }

    function enterConfirmState(btn) {
        btn.setAttribute('data-ab-pending', '1');
        btn.classList.add('btn-danger', 'ab-btn-confirming');
        btn.textContent = 'Confirm Disable';

        var cancel = document.createElement('a');
        cancel.href           = '#';
        cancel.className      = 'ab-cancel-disable';
        cancel.textContent    = 'Cancel';
        cancel.addEventListener('click', function (e) {
            e.preventDefault();
            resetDisableBtn(btn);
        });
        btn.parentNode.appendChild(cancel);

        btn._abConfirmTimer = setTimeout(function () {
            resetDisableBtn(btn);
        }, CONFIRM_TIMEOUT);
    }

    function doToggle(btn, card, extensionId, newState) {
        btn.disabled    = true;
        btn.textContent = newState ? 'Enabling\u2026' : 'Disabling\u2026';

        var cancelLink = btn.parentNode && btn.parentNode.querySelector('.ab-cancel-disable');
        if (cancelLink) { cancelLink.remove(); }
        if (btn._abConfirmTimer) {
            clearTimeout(btn._abConfirmTimer);
            btn._abConfirmTimer = null;
        }

        var body = new URLSearchParams();
        body.append('extension_id', extensionId);
        body.append('state', newState);
        body.append(tokenName, '1');

        fetch('index.php?option=com_aiboost&task=dashboard.togglePlugin&format=json', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body.toString()
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) {
                Joomla.renderMessages({ error: ['AI Boost: ' + (data.message || 'Unknown error')] });
                btn.disabled    = false;
                btn.textContent = newState ? 'Enable' : 'Disable';
                btn.setAttribute('data-ab-pending', '0');
                return;
            }

            // Update badge
            var badge = card.querySelector('[data-ab-badge]');
            if (badge) {
                if (newState === 1) {
                    badge.textContent = 'Enabled';
                    badge.className   = 'badge bg-success';
                } else {
                    badge.textContent = 'Disabled';
                    badge.className   = 'badge bg-danger';
                }
                badge.setAttribute('data-ab-badge', newState);
            }

            // Swap Enable ↔ Disable buttons
            var enableBtn  = card.querySelector('[data-ab-toggle][data-state="1"]');
            var disableBtn = card.querySelector('[data-ab-toggle][data-state="0"]');

            if (newState === 1) {
                // Just enabled: hide Enable, show Disable
                if (enableBtn)  { enableBtn.classList.add('d-none');    enableBtn.disabled  = false; }
                if (disableBtn) {
                    disableBtn.classList.remove('d-none');
                    disableBtn.disabled    = false;
                    disableBtn.textContent = 'Disable';
                    disableBtn.setAttribute('data-ab-pending', '0');
                    disableBtn.classList.remove('btn-danger', 'ab-btn-confirming');
                }
                Joomla.renderMessages({ message: [data.message || 'Plugin enabled successfully.'] });
            } else {
                // Just disabled: show Enable, hide Disable
                if (disableBtn) { disableBtn.classList.add('d-none');   disableBtn.disabled  = false; }
                if (enableBtn)  { enableBtn.classList.remove('d-none'); enableBtn.disabled   = false; enableBtn.textContent = 'Enable'; }
                Joomla.renderMessages({ message: [data.message || 'Plugin disabled successfully.'] });
            }
        })
        .catch(function () {
            Joomla.renderMessages({ error: ['AI Boost: Network error. Please try again.'] });
            btn.disabled    = false;
            btn.textContent = newState ? 'Enable' : 'Disable';
            btn.setAttribute('data-ab-pending', '0');
        });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-ab-toggle]');
        if (!btn) { return; }

        e.preventDefault();
        e.stopPropagation();

        var card        = btn.closest('[data-ab-plugin-card]');
        var extensionId = btn.getAttribute('data-extension-id');
        var newState    = parseInt(btn.getAttribute('data-state'), 10);

        if (!card || !extensionId) { return; }

        // Enable fires immediately; Disable requires confirmation
        if (newState === 1) {
            doToggle(btn, card, extensionId, newState);
            return;
        }

        // Disable: first click → enter confirm state; second click → proceed
        var pending = btn.getAttribute('data-ab-pending');
        if (pending === '1') {
            btn.setAttribute('data-ab-pending', '0');
            doToggle(btn, card, extensionId, newState);
        } else {
            enterConfirmState(btn);
        }
    });

})();
