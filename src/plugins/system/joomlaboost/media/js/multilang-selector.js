/**
 * Multi-Language Field Selector
 * Adds dropdown to switch between language-specific fields
 * 
 * @package     JoomlaBoost
 * @since       0.6.0
 */

(function () {
    'use strict';

    // Language configuration
    const LANGUAGES = {
        en: { name: 'English', flag: '🇬🇧' },
        sr: { name: 'Српски', flag: '🇷🇸' },
        ru: { name: 'Русский', flag: '🇷🇺' }
    };

    // Field groups to enhance
    const FIELD_GROUPS = ['org_name', 'schema_description'];

    /**
     * Initialize language selectors for all field groups
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
     * Find all language-specific fields for a group
     */
    function findLanguageFields(fieldGroup) {
        const fields = [];

        Object.keys(LANGUAGES).forEach(langCode => {
            const fieldName = `jform[${fieldGroup}_${langCode}]`;
            const input = document.querySelector(`[name="${fieldName}"]`);

            if (input) {
                const controlGroup = input.closest('.control-group');
                if (controlGroup) {
                    fields.push({
                        langCode: langCode,
                        input: input,
                        controlGroup: controlGroup
                    });
                }
            }
        });

        return fields;
    }

    /**
     * Create language selector dropdown for a field group
     */
    function createSelectorForGroup(fieldGroup, fields) {
        if (fields.length === 0) return;

        // Create selector container
        const selectorDiv = document.createElement('div');
        selectorDiv.className = 'multilang-selector-wrapper';
        selectorDiv.innerHTML = `
            <div class="control-group multilang-selector-group">
                <div class="control-label">
                    <label>
                        <span class="icon-language" aria-hidden="true"></span>
                        Language Selector
                    </label>
                </div>
                <div class="controls">
                    <select class="form-select multilang-selector" data-field-group="${fieldGroup}">
                        <option value="all">🌍 All Languages</option>
                        ${Object.keys(LANGUAGES).map(code => `
                            <option value="${code}">
                                ${LANGUAGES[code].flag} ${LANGUAGES[code].name}
                            </option>
                        `).join('')}
                    </select>
                    <small class="form-text text-muted">
                        Select a language to edit, or view all fields at once
                    </small>
                </div>
            </div>
        `;

        // Insert selector before first field
        fields[0].controlGroup.parentNode.insertBefore(selectorDiv, fields[0].controlGroup);

        // Add change event listener
        const select = selectorDiv.querySelector('select');
        select.addEventListener('change', function (e) {
            toggleFieldVisibility(fields, e.target.value);
        });

        // Initial state: show only first language
        toggleFieldVisibility(fields, fields[0].langCode);
        select.value = fields[0].langCode;
    }

    /**
     * Show/hide fields based on selected language
     */
    function toggleFieldVisibility(fields, selectedLang) {
        fields.forEach(field => {
            if (selectedLang === 'all') {
                // Show all fields
                field.controlGroup.style.display = '';
            } else {
                // Show only selected language
                field.controlGroup.style.display =
                    field.langCode === selectedLang ? '' : 'none';
            }
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLanguageSelectors);
    } else {
        initLanguageSelectors();
    }

})();
