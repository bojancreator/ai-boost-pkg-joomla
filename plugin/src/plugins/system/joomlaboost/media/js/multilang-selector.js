/**
 * Multi-Language Field Selector
 * Adds dropdown to switch between language-specific fields.
 * Languages are detected dynamically from the DOM — no hardcoded list.
 *
 * @package     JoomlaBoost
 * @since       0.6.0
 */

(function () {
    'use strict';

    /**
     * Display names and flags for known language codes.
     * Used as a fallback for the dropdown label.
     * Any language not listed here will show only its code (e.g. "ZH").
     */
    const LANG_DISPLAY = {
        en: { name: 'English',    flag: '🇬🇧' },
        sr: { name: 'Srpski',     flag: '🇷🇸' },
        de: { name: 'Deutsch',    flag: '🇩🇪' },
        fr: { name: 'Français',   flag: '🇫🇷' },
        es: { name: 'Español',    flag: '🇪🇸' },
        it: { name: 'Italiano',   flag: '🇮🇹' },
        ru: { name: 'Русский',    flag: '🇷🇺' },
        pl: { name: 'Polski',     flag: '🇵🇱' },
        tr: { name: 'Türkçe',     flag: '🇹🇷' },
        nl: { name: 'Nederlands', flag: '🇳🇱' },
        pt: { name: 'Português',  flag: '🇵🇹' },
        cs: { name: 'Čeština',    flag: '🇨🇿' },
        hu: { name: 'Magyar',     flag: '🇭🇺' },
        ro: { name: 'Română',     flag: '🇷🇴' },
        uk: { name: 'Українська', flag: '🇺🇦' },
        ar: { name: 'العربية',    flag: '🇸🇦' },
        zh: { name: '中文',        flag: '🇨🇳' },
        ja: { name: '日本語',       flag: '🇯🇵' },
        ko: { name: '한국어',        flag: '🇰🇷' }
    };

    /**
     * Field group prefixes to enhance with a language selector.
     * Each entry must match the prefix used in jform[prefix_langcode] field names.
     */
    const FIELD_GROUPS = [
        'org_name',
        'org_description',
        'org_logo',
        'schema_address_locality',
        'schema_address_street',
        'og_site_name',
        'og_image',
        'manual_faqs',
        'schema_events',
        'llmstxt_custom_pages'
    ];

    /**
     * Initialize language selectors for all known field groups.
     */
    function initLanguageSelectors() {
        FIELD_GROUPS.forEach(fieldGroup => {
            const fields = findLanguageFields(fieldGroup);
            if (fields.length > 1) {
                createSelectorForGroup(fieldGroup, fields);
            }
        });
    }

    /**
     * Scan the DOM for all fields matching jform[{fieldGroup}_{langCode}].
     * Language codes are discovered dynamically — no hardcoded list required.
     *
     * @param {string} fieldGroup
     * @returns {Array}
     */
    function findLanguageFields(fieldGroup) {
        const fields = [];
        const pattern = new RegExp('^jform\\[' + fieldGroup + '_([a-z]{2})\\]$');

        document.querySelectorAll('input, textarea, select').forEach(input => {
            const match = (input.name || '').match(pattern);
            if (!match) return;

            const langCode = match[1];
            const controlGroup = input.closest('.control-group');
            if (controlGroup) {
                fields.push({ langCode, input, controlGroup });
            }
        });

        return fields;
    }

    /**
     * Return display label for a language code.
     * Falls back to uppercased code if not in LANG_DISPLAY.
     *
     * @param {string} code
     * @returns {string}
     */
    function langLabel(code) {
        const d = LANG_DISPLAY[code];
        return d ? d.flag + ' ' + d.name : code.toUpperCase();
    }

    /**
     * Build and insert the language selector dropdown before the first field.
     *
     * @param {string} fieldGroup
     * @param {Array}  fields
     */
    function createSelectorForGroup(fieldGroup, fields) {
        const options = fields.map(f =>
            '<option value="' + f.langCode + '">' + langLabel(f.langCode) + '</option>'
        ).join('');

        const selectorDiv = document.createElement('div');
        selectorDiv.className = 'multilang-selector-wrapper';
        selectorDiv.innerHTML =
            '<div class="control-group multilang-selector-group">' +
                '<div class="control-label">' +
                    '<label>' +
                        '<span class="icon-language" aria-hidden="true"></span> ' +
                        'Language' +
                    '</label>' +
                '</div>' +
                '<div class="controls">' +
                    '<select class="form-select multilang-selector" data-field-group="' + fieldGroup + '">' +
                        '<option value="all">🌍 All Languages</option>' +
                        options +
                    '</select>' +
                    '<small class="form-text text-muted">' +
                        'Select a language to edit, or view all at once.' +
                    '</small>' +
                '</div>' +
            '</div>';

        fields[0].controlGroup.parentNode.insertBefore(selectorDiv, fields[0].controlGroup);

        const select = selectorDiv.querySelector('select');
        select.addEventListener('change', function (e) {
            toggleFieldVisibility(fields, e.target.value);
        });

        // Default: show first language only
        toggleFieldVisibility(fields, fields[0].langCode);
        select.value = fields[0].langCode;
    }

    /**
     * Show or hide field control-groups based on selected language.
     *
     * @param {Array}  fields
     * @param {string} selectedLang  language code or "all"
     */
    function toggleFieldVisibility(fields, selectedLang) {
        fields.forEach(field => {
            field.controlGroup.style.display =
                (selectedLang === 'all' || field.langCode === selectedLang) ? '' : 'none';
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLanguageSelectors);
    } else {
        initLanguageSelectors();
    }

})();
