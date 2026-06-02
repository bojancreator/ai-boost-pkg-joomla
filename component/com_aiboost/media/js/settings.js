/**
 * AI Boost — Settings AJAX Save
 * Tab switching is handled entirely by Bootstrap 5 (data-bs-toggle="pill").
 * This file only handles the AJAX save action.
 * @package AiBoost
 */
(function () {
    'use strict';

    var saveBtn = document.getElementById('ab-save-btn');
    var saveMsg = document.getElementById('ab-save-msg');

    if (!saveBtn) { return; }

    saveBtn.addEventListener('click', function () {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving\u2026';

        var form = document.getElementById('ab-settings-form');
        var formData = new FormData(form);

        /* ── Meta Pixel IDs backward-compat sync ───────────────────────
           meta_pixel_ids is a JSON array stored in a hidden field.
           Legacy field meta_pixel_id must equal the first element so
           older plugin versions and controller code keep working.
        ─────────────────────────────────────────────────────────────── */
        (function () {
            var pixelHidden = document.getElementById('f-pixel-ids-hidden');
            if (!pixelHidden) { return; }
            try {
                var ids = JSON.parse(pixelHidden.value || '[]');
                var first = Array.isArray(ids) && ids.length ? String(ids[0]).trim() : '';
                formData.set('meta_pixel_id', first);
            } catch (e) { /* leave meta_pixel_id as-is */ }
        }());

        /* Bootstrap switch inputs are type="checkbox" — include unchecked as '0'.
           Array checkboxes (name ends with []) must never use set() because that
           would overwrite all previously appended values for that array field.
           Unchecked array checkboxes are simply absent from FormData — correct. */
        form.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
            if (!cb.checked && !cb.name.endsWith('[]')) {
                formData.set(cb.name, '0');
            }
        });

        /* ── Add-on plugin params ───────────────────────────────────────────
           Collect editable fields from add-on tab panes (data-addon-plugin containers).
           Each field must carry [data-addon-param="param_name"].
           Radios: only the checked one is included.
           Checkboxes: unchecked = '0'.
           Sent as JSON string in `addon_params` field.
        ─────────────────────────────────────────────────────────────────── */
        (function () {
            var addonParams = {};
            document.querySelectorAll('[data-addon-plugin]').forEach(function (container) {
                var plugin = container.getAttribute('data-addon-plugin');
                if (!plugin) { return; }
                addonParams[plugin] = addonParams[plugin] || {};

                container.querySelectorAll('[data-addon-param]').forEach(function (el) {
                    var paramName = el.getAttribute('data-addon-param');
                    if (!paramName) { return; }
                    var value;
                    if (el.type === 'radio') {
                        if (!el.checked) { return; }
                        value = el.value;
                    } else if (el.type === 'checkbox') {
                        value = el.checked ? '1' : '0';
                    } else {
                        value = el.value;
                    }
                    addonParams[plugin][paramName] = value;
                });
            });
            if (Object.keys(addonParams).length) {
                formData.set('addon_params', JSON.stringify(addonParams));
            }
        }());

        fetch('index.php?option=com_aiboost&task=settings.save&format=json', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            saveMsg.className = 'ab-save-msg show ' + (data.success ? 'success' : 'error');
            saveMsg.textContent = data.message || (data.success ? 'Settings saved.' : 'Error saving.');

            if (data.success) {
                var lastSavedEl     = document.getElementById('ab-last-saved');
                var lastSavedTimeEl = document.getElementById('ab-last-saved-time');
                if (lastSavedEl && lastSavedTimeEl) {
                    var label = data.saved_at || (function () {
                        var now    = new Date();
                        var day    = String(now.getDate()).padStart(2, '0');
                        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                        var time   = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
                        return day + ' ' + months[now.getMonth()] + ' ' + now.getFullYear() + ' at ' + time;
                    }());
                    lastSavedTimeEl.textContent = label;
                    lastSavedEl.style.display = '';
                }
            }
        })
        .catch(function () {
            saveMsg.className = 'ab-save-msg show error';
            saveMsg.textContent = 'Network error. Please try again.';
        })
        .finally(function () {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Settings';
            setTimeout(function () { saveMsg.className = 'ab-save-msg'; }, 4000);
        });
    });

    /* ── Health tab deep-link: activate the correct pill tab on page load ──
       health.js stores a tab btn ID in sessionStorage when a Fix-it link is clicked.
       We read it here and trigger a click so Bootstrap switches to the right tab.
    ─────────────────────────────────────────────────────────────────────────── */
    (function () {
        var tabId = sessionStorage.getItem('ab_settings_tab');
        if (!tabId) { return; }
        sessionStorage.removeItem('ab_settings_tab');
        var btn = document.getElementById(tabId);
        if (btn && btn.getAttribute('data-bs-toggle') === 'pill') {
            btn.click();
            setTimeout(function () {
                btn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 100);
        }
    }());

})();
