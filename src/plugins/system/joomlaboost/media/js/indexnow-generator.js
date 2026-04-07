/**
 * JoomlaBoost - IndexNow API Key Manager
 *
 * Manages the IndexNow API key UI with two states:
 *
 * State A (no key): text input visible + "Generate API Key" button active
 * State B (has key): input hidden, key displayed as masked text + trash button
 *
 * Transitions:
 *   Generate click  → generates key → switches to State B
 *   Trash click     → clears key   → switches to State A
 */
(function () {
    'use strict';

    // Inject admin input width limit — CSS approach failed (delivery issue),
    // JS injection is guaranteed since this file is confirmed to load.
    var s = document.createElement('style');
    s.textContent = [
        '#style-form .controls input[type="text"],',
        '#style-form .controls input[type="url"],',
        '#style-form .controls input[type="email"],',
        '#style-form .controls input[type="number"],',
        '#style-form .controls textarea {',
        '  max-width: 800px !important;',
        '  width: 100% !important;',
        '  box-sizing: border-box !important;',
        '}'
    ].join('\n');
    document.head.appendChild(s);

    document.addEventListener('DOMContentLoaded', function () {
        var genBtn = document.querySelector('[data-jb-indexnow-gen]');
        var field  = document.getElementById('jform_params_indexnow_api_key');

        if (!genBtn || !field) {
            return;
        }

        // Find Joomla's .control-group wrapper for the text field
        var fieldRow = field.closest('.control-group') || field.parentElement.parentElement;

        // --- Build the "key is set" display row ---
        var displayRow  = document.createElement('div');
        displayRow.style.cssText = 'display:none;align-items:center;gap:10px;margin:4px 0 8px;';

        var keyCode = document.createElement('code');
        keyCode.id  = 'jb-key-display';
        keyCode.style.cssText = [
            'flex:1',
            'background:#0d1117',
            'color:#58d68d',
            'border:1px solid #2d4a3e',
            'border-radius:5px',
            'padding:8px 14px',
            'font-size:0.88em',
            'letter-spacing:0.07em',
            'word-break:break-all'
        ].join(';');

        var trashBtn = document.createElement('button');
        trashBtn.type      = 'button';
        trashBtn.id        = 'jb-key-delete';
        trashBtn.className = 'btn btn-sm btn-danger';
        trashBtn.title     = 'Delete API key — you will be able to generate a new one';
        trashBtn.innerHTML = '&#128465;';

        displayRow.appendChild(keyCode);
        displayRow.appendChild(trashBtn);

        // Insert display row in the same parent as fieldRow
        fieldRow.parentElement.insertBefore(displayRow, fieldRow);

        // --- State transitions ---

        function showKeyState(keyValue) {
            keyCode.textContent     = keyValue;
            displayRow.style.display = 'flex';
            fieldRow.style.display   = 'none';
            genBtn.disabled          = true;
            genBtn.classList.add('disabled');
        }

        function showEmptyState() {
            displayRow.style.display = 'none';
            fieldRow.style.display   = '';
            field.value              = '';
            genBtn.disabled          = false;
            genBtn.classList.remove('disabled');
        }

        // --- Event listeners ---

        // Generate button: set key, switch to State B
        genBtn.addEventListener('click', function () {
            if (genBtn.disabled) { return; }
            var key = Array(32).fill(0).map(function () {
                return '0123456789abcdef'[Math.floor(Math.random() * 16)];
            }).join('');
            field.value = key;
            showKeyState(key);
        });

        // Trash button: clear key, switch to State A
        trashBtn.addEventListener('click', function () {
            showEmptyState();
            field.focus();
        });

        // --- Initial render ---
        var existingKey = field.value.trim();
        if (existingKey.length > 0) {
            showKeyState(existingKey);
        } else {
            showEmptyState();
        }
    });
})();
