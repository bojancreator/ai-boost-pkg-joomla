/**
 * JoomlaBoost - IndexNow API Key Generator
 *
 * Manages the "Generate API Key" button in the plugin admin.
 * - On page load: disables button if API key field already has a value
 * - On field input: re-evaluates button state in real time
 * - Key generation remains in the inline onclick (no < operator needed)
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var button = document.querySelector('[data-jb-indexnow-gen]');
        var field  = document.getElementById('jform_params_indexnow_api_key');

        if (!button || !field) {
            return;
        }

        function syncButton() {
            var hasKey = field.value.trim().length > 0;
            button.disabled = hasKey;
            button.title = hasKey
                ? 'Clear the API key field to generate a new one'
                : 'Generate a random 32-character API key';

            if (hasKey) {
                button.classList.add('disabled');
            } else {
                button.classList.remove('disabled');
            }
        }

        field.addEventListener('input', syncButton);
        syncButton(); // Run immediately on page load
    });
})();
