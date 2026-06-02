/**
 * AI Boost — Joomla Native Media Picker
 * Opens Joomla's built-in com_media popup and wires the result back to the
 * source input + thumbnail preview.
 *
 * PHP side: buildMediaField() generates an <input> with data-preview-id and a
 * <button onclick="abOpenMedia(inputId)"> that triggers this.
 *
 * Joomla calls window.jSelectMedia(fieldId, type, path, name, uri) when the
 * user picks a file in the popup — this is the standard Joomla 4/5/6 callback.
 *
 * @package AiBoost
 */
(function () {
    'use strict';

    /**
     * Standard Joomla callback — invoked by com_media popup on file select.
     * fieldId  = the input element's id attribute
     * uri      = root-relative or absolute URL of the chosen file
     */
    window.jSelectMedia = function (fieldId, mediaType, path, name, uri) {
        var input = document.getElementById(fieldId);
        if (!input) { return; }

        input.value = uri;
        input.dispatchEvent(new Event('change', { bubbles: true }));

        var previewId = input.getAttribute('data-preview-id');
        if (!previewId) { return; }
        var img = document.getElementById(previewId);
        if (!img) { return; }
        img.src = uri;
        img.style.display = '';
        var wrap = img.closest('.ab-img-preview-wrap');
        if (wrap) { wrap.style.display = ''; }
    };

    /**
     * Open Joomla's native com_media popup.
     * inputId = ID of the target <input> element.
     */
    window.abOpenMedia = function (inputId) {
        var token = '';
        try { token = Joomla.getOptions('csrf.token', ''); } catch (e) {}
        var url = 'index.php?option=com_media&view=media&tmpl=component'
                + '&mediatypes=0'
                + '&fieldid=' + encodeURIComponent(inputId)
                + (token ? '&' + encodeURIComponent(token) + '=1' : '');
        window.open(
            url,
            'jSelectMedia',
            'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,' +
            'resizable=yes,width=900,height=600,directories=no,location=no'
        );
    };

    /* Wire up preview refresh on manual text edit (typing a URL directly) */
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-preview-id]').forEach(function (input) {
            input.addEventListener('change', function () {
                var previewId = input.getAttribute('data-preview-id');
                if (!previewId) { return; }
                var img  = document.getElementById(previewId);
                if (!img) { return; }
                var url  = (input.value || '').trim();
                if (url) {
                    img.src = url;
                    img.style.display = '';
                    var wrap = img.closest('.ab-img-preview-wrap');
                    if (wrap) { wrap.style.display = ''; }
                } else {
                    img.style.display = 'none';
                    var wrap2 = img.closest('.ab-img-preview-wrap');
                    if (wrap2) { wrap2.style.display = 'none'; }
                }
            });
        });
    });
}());
