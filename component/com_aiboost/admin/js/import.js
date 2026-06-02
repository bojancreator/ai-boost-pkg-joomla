/**
 * AI Boost — Import Wizard JS
 * @package AiBoost
 */
(function () {
    'use strict';

    var form   = document.getElementById('ab-import-form');
    var btn    = document.getElementById('ab-import-btn');
    var result = document.getElementById('ab-import-result');

    if (!form || !btn) { return; }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var fileInput = document.getElementById('ab-import-file');
        if (!fileInput || !fileInput.files.length) {
            showResult('error', 'Please select a JSON file to import.');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Importing…';

        var fd = new FormData(form);

        fetch('index.php?option=com_aiboost&task=import.upload&format=json', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            showResult(data.success ? 'success' : 'error', data.message || 'Done.');
        })
        .catch(function () {
            showResult('error', 'Network error. Please try again.');
        })
        .finally(function () {
            btn.disabled = false;
            btn.textContent = 'Import';
        });
    });

    function showResult(type, msg) {
        if (!result) { return; }
        result.className = 'alert alert-' + (type === 'success' ? 'success' : 'danger') + ' mt-3';
        result.textContent = msg;
        result.style.display = 'block';
    }
})();
