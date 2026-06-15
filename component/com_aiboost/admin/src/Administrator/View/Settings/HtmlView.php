<?php
/**
 * @package     AiBoost\Component\AiBoost\Administrator\View\Settings
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Component\AiBoost\Administrator\View\Settings;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public array  $settings      = [];
    public string $token         = '';
    public string $ogImageField  = '';
    public string $logoField     = '';
    public array  $menuItems     = [];

    /**
     * Dynamic tabs contributed by installed AI Boost add-on plugins via the
     * onAiBoostGetSettingsTabs event. Each entry is an array with keys:
     *   id     (string) — unique tab ID, e.g. 'tab-yootheme'
     *   label  (string) — human-readable tab label
     *   svg    (string) — optional inline SVG icon HTML
     *   html   (string) — full tab pane content HTML
     */
    public array $addonTabs = [];

    public function display($tpl = null): void
    {
        $this->settings   = $this->loadSettings();
        $this->token      = Session::getFormToken();
        $this->menuItems  = $this->loadMenuItems();
        // NOTE: onAiBoostRegisterFields is dispatched by
        // AiBoost\Lib\Manifest\Registry::payload() — calling it here too
        // would double-fire and risk stateful listener side effects.
        $this->addonTabs  = $this->collectAddonTabs();

        $this->ogImageField = $this->buildMediaField(
            'default_og_image',
            $this->settings['default_og_image'] ?? ($this->settings['og_default_image'] ?? '')
        );
        $this->logoField = $this->buildMediaField(
            'org_logo',
            $this->settings['org_logo'] ?? ($this->settings['schema_logo_url'] ?? '')
        );
        $this->schemaOrgImageField = $this->buildMediaField(
            'schema_org_image',
            $this->settings['schema_org_image'] ?? ''
        );
        $this->schemaHotelImageField = $this->buildMediaField(
            'schema_hotel_image',
            $this->settings['schema_hotel_image'] ?? ''
        );

        $this->addToolbar();
        $this->injectScripts();

        parent::display($tpl);
    }

    /**
     * Build a media picker using the Joomla native MediaField (type="media").
     * This renders the standard Joomla media manager modal — not a popup window.
     * The field name matches the form-save key so settings are saved correctly.
     *
     * @param string $name  Field name / form save key (e.g. "logo_url")
     * @param string $value Current URL value
     */
    private function buildMediaField(string $name, string $value): string
    {
        try {
            $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $xml = '<?xml version="1.0" encoding="UTF-8"?><form>'
                 . '<field name="' . $safeName . '" type="media"'
                 . ' preview="tooltip" class="input-xlarge" hiddenLabel="true" />'
                 . '</form>';

            $form = \Joomla\CMS\Form\Form::getInstance(
                'ab_media_' . $name,
                $xml,
                ['control' => ''],
                false,
                false
            );
            $form->setValue($name, null, $value);

            return $form->getInput($name);
        } catch (\Throwable $e) {
            // Fallback: plain text input
            $nm  = htmlspecialchars($name,  ENT_QUOTES, 'UTF-8');
            $val = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            return '<input type="text" name="' . $nm . '" id="f-' . $nm . '"'
                 . ' class="form-control" value="' . $val . '"'
                 . ' placeholder="/images/logo.png">';
        }
    }

    /**
     * Load all published front-end menu items for the Custom Code tab picker.
     * Returns a flat list of stdClass objects with: id, title, level, menutype.
     */
    private function loadMenuItems(): array
    {
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select(['id', 'title', 'level', 'menutype', 'type', 'lft'])
                ->from('#__menu')
                ->where('published = 1')
                ->where('client_id = 0')
                ->where($db->quoteName('type') . ' != ' . $db->quote('heading'))
                ->where($db->quoteName('type') . ' != ' . $db->quote('root'))
                ->where($db->quoteName('level') . ' > 0')
                ->order('menutype ASC, lft ASC');
            $db->setQuery($query);
            return $db->loadObjectList() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Dispatch the onAiBoostGetSettingsTabs event to all installed system plugins.
     * Add-on bridge plugins that are active and licensed return their tab data here.
     * Each response item must be an array with: id, label, html (and optionally svg).
     */
    private function collectAddonTabs(): array
    {
        try {
            $app    = Factory::getApplication();
            $result = $app->triggerEvent('onAiBoostGetSettingsTabs', []);
            $tabs   = [];
            foreach ($result as $item) {
                if (
                    is_array($item)
                    && !empty($item['id'])
                    && !empty($item['label'])
                    && !empty($item['html'])
                ) {
                    $tabs[] = $item;
                }
            }
            return $tabs;
        } catch (\Throwable) {
            return [];
        }
    }

    private function addToolbar(): void
    {
        ToolbarHelper::title('AI Boost &mdash; Settings', 'cog');
    }

    /**
     * Register all JS inline so Joomla 6 CSP nonce is applied automatically.
     * External defer scripts are unreliable in Joomla 6.1 (Atum).
     */
    private function injectScripts(): void
    {
        $doc = Factory::getApplication()->getDocument();

        /* ── Vue.js globals ────────────────────────────────────────────── */
        $saveUrl      = 'index.php?option=com_aiboost&task=settings.save&format=json';
        $settingsJson = json_encode($this->settings, \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_UNESCAPED_SLASHES);
        $tokenJson    = json_encode($this->token);
        $saveUrlJson  = json_encode($saveUrl);
        $menuItemsJson = json_encode(
            array_values(array_map(static function ($mi) {
                return [
                    'id'       => (int) $mi->id,
                    'title'    => (string) $mi->title,
                    'level'    => (int) $mi->level,
                    'menutype' => (string) $mi->menutype,
                ];
            }, $this->menuItems)),
            \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_UNESCAPED_SLASHES
        );

        // Inject per-language translations for the Pro multilingual feature
        $translationsData = $this->loadTranslationsData();
        $translationsJson = json_encode(
            $translationsData,
            \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_UNESCAPED_SLASHES
        );

        $defaultLangCode = (string) Factory::getApplication()->get('language', 'en-GB');
        $defaultLangJson = json_encode($defaultLangCode, \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_QUOT);

        // Joomla global debug flag — used by the Debug tab to gate the
        // "Developer-only" section so it stays hidden on production sites
        // unless the admin has explicitly enabled Joomla debug mode.
        $joomlaDebug     = (bool) Factory::getApplication()->get('debug', false);
        $joomlaDebugJson = json_encode($joomlaDebug ? 1 : 0);

        // Inject manifest + capabilities so the Vue SPA can render locked
        // Pro/integration placeholders without a separate AJAX round-trip.
        $manifestPayload = ['capabilities' => [], 'fields' => []];
        try {
            if (class_exists('AiBoost\\Lib\\Manifest\\Registry')) {
                $manifestPayload = \AiBoost\Lib\Manifest\Registry::payload();
            }
        } catch (\Throwable $e) {
            // Silent: SPA falls back to legacy non-manifest render.
        }
        $manifestJson = json_encode(
            $manifestPayload,
            \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_UNESCAPED_SLASHES
        );

        // Pro feature registry — same payload the SPA bootstrap exposes,
        // duplicated here so the legacy Settings entry point also gates Pro UI.
        $proFeaturesData = [];
        try {
            if (class_exists('AiBoost\\Lib\\ProFeatureRegistry')) {
                $proFeaturesData = \AiBoost\Lib\ProFeatureRegistry::all();
            }
        } catch (\Throwable $e) {
            // Silent: SPA falls back to non-gated render.
        }
        $proFeaturesJson = json_encode(
            $proFeaturesData,
            \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_UNESCAPED_SLASHES
        );

        // Minimal bootstrap so the Vue admin's ProGate unlocks correctly in this
        // LEGACY standalone mount (view=settings). Without it, window.aiBoostBootstrap
        // is undefined here and isProInstalled() reads false → every Pro card shows
        // locked even on a Pro install (Bojan's bug #8). Only isProInstall drives
        // ProGate; the full bootstrap is still owned by the SPA (view=app).
        $bootstrapMinJson = json_encode([
            'isProInstall' => $this->detectProInstall(),
            'isPro'        => false,
            'tokenName'    => $this->token,
            'legacy'       => true,
        ], \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_QUOT);

        $doc->addScriptDeclaration(
            "window.aiBoostSettings={$settingsJson};" .
            "window.aiBoostToken={$tokenJson};" .
            "window.aiBoostSaveUrl={$saveUrlJson};" .
            "window.aiBoostMenuItems={$menuItemsJson};" .
            "window.aiBoostTranslations={$translationsJson};" .
            "window.aiBoostDefaultLang={$defaultLangJson};" .
            "window.aiBoostJoomlaDebug={$joomlaDebugJson};" .
            "window.aiBoostManifest={$manifestJson};" .
            "window.aiBoostProFeatures={$proFeaturesJson};" .
            "window.aiBoostBootstrap=Object.assign(window.aiBoostBootstrap||{},{$bootstrapMinJson});"
        );

        /* ── Tab switching ─────────────────────────────────────────────── */
        $doc->addScriptDeclaration(<<<'JS'
(function () {
    function initTabs() {
        var btns  = document.querySelectorAll('[data-bs-toggle="pill"]');
        var panes = document.querySelectorAll('.tab-pane');
        if (!btns.length) { return; }

        panes.forEach(function (p) {
            if (!p.classList.contains('active')) { p.style.display = 'none'; }
        });

        btns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = btn.getAttribute('data-bs-target');

                btns.forEach(function (b) {
                    b.classList.remove('active');
                    b.setAttribute('aria-selected', 'false');
                });
                btn.classList.add('active');
                btn.setAttribute('aria-selected', 'true');

                panes.forEach(function (p) {
                    p.classList.remove('active');
                    p.style.display = 'none';
                });

                var pane = document.querySelector(targetId);
                if (pane) {
                    pane.classList.add('active');
                    pane.style.display = 'block';
                }
            });
        });
    }

    function activateDeepLink() {
        var tabId = sessionStorage.getItem('ab_settings_tab');
        if (!tabId) { return; }
        sessionStorage.removeItem('ab_settings_tab');
        var btn = document.getElementById(tabId);
        if (btn) { btn.click(); }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { initTabs(); activateDeepLink(); });
    } else {
        initTabs();
        activateDeepLink();
    }
}());
JS
        );

        /* ── Schema type conditional fields + Advanced Options + Business Hours + Widgets ── */
        $doc->addScriptDeclaration(<<<'JS'
(function () {

    /* ── 1. Schema type → show/hide niche & hours sections ── */
    function initSchemaConditional() {
        var typeSelect = document.getElementById('f-schema-type');
        if (!typeSelect) { return; }

        var hoursMode  = document.getElementById('f-hours-mode');

        var typeMap = {
            'hotel':       ['ab-hotel-fields', 'ab-price-range-group', 'ab-hours-section'],
            'restaurant':  ['ab-price-range-group', 'ab-hours-section'],
            'localbusiness':['ab-price-range-group', 'ab-hours-section'],
            'ecommerce':   ['ab-price-range-group', 'ab-hours-section'],
            'medical':     ['ab-medical-fields', 'ab-hours-section'],
            'lawyer':      ['ab-lawyer-fields', 'ab-hours-section'],
            'school':      ['ab-school-fields', 'ab-hours-section'],
            'portfolio':   ['ab-portfolio-fields'],
            'dentist':     ['ab-dentist-fields', 'ab-price-range-group', 'ab-hours-section'],
            'realestate':  ['ab-realestate-fields', 'ab-hours-section'],
            'gym':         ['ab-gym-fields', 'ab-price-range-group', 'ab-hours-section'],
            'news':        ['ab-news-fields'],
        };
        var allSections = ['ab-hotel-fields','ab-price-range-group','ab-hours-section',
                           'ab-medical-fields','ab-lawyer-fields','ab-school-fields',
                           'ab-portfolio-fields','ab-dentist-fields','ab-realestate-fields',
                           'ab-gym-fields','ab-news-fields'];

        function applyType() {
            var val = typeSelect.value;
            allSections.forEach(function(id) {
                var el = document.getElementById(id);
                if (el) { el.style.display = 'none'; }
            });
            var show = typeMap[val] || [];
            show.forEach(function(id) {
                var el = document.getElementById(id);
                if (el) { el.style.display = 'block'; }
            });
            applyHoursMode();
        }

        function applyHoursMode() {
            if (!hoursMode) { return; }
            var mode    = hoursMode.value;
            var simple  = document.getElementById('ab-hours-simple');
            var advanced= document.getElementById('ab-hours-advanced');
            if (simple)   { simple.style.display   = mode === 'simple'   ? 'block' : 'none'; }
            if (advanced) { advanced.style.display  = mode === 'advanced' ? 'block' : 'none'; }
        }

        typeSelect.addEventListener('change', applyType);
        if (hoursMode) { hoursMode.addEventListener('change', applyHoursMode); }
        applyType();
    }

    /* ── 2. Show Advanced Options → toggle .ab-advanced-section ── */
    function initAdvancedOptions() {
        var toggle = document.querySelector('input[name="show_advanced_options"]');
        if (!toggle) { return; }

        function applyAdv() {
            var vis = toggle.checked ? 'block' : 'none';
            document.querySelectorAll('.ab-advanced-section').forEach(function(el) {
                el.style.display = vis;
            });
        }
        toggle.addEventListener('change', applyAdv);
        applyAdv();
    }

    /* ── 3. Business Hours widget — sync table → hidden JSON field ── */
    function initBusinessHours() {
        var hiddenField = document.getElementById('f-business-hours');
        var table       = document.getElementById('ab-bh-table');
        if (!hiddenField || !table) { return; }

        function serialize() {
            var data = {};
            table.querySelectorAll('.ab-bh-row').forEach(function(row) {
                var day    = row.dataset.day;
                var toggle = row.querySelector('.ab-bh-toggle');
                var isOpen = toggle && toggle.checked;
                var slots  = [];
                row.querySelectorAll('.ab-bh-slot').forEach(function(slot) {
                    slots.push({
                        from: slot.querySelector('.ab-bh-from').value || '09:00',
                        to:   slot.querySelector('.ab-bh-to').value   || '17:00'
                    });
                });
                data[day] = { open: isOpen, slots: slots.length ? slots : [{ from:'09:00', to:'17:00' }] };
            });
            hiddenField.value = JSON.stringify(data);
        }

        /* Toggle open/closed */
        table.addEventListener('change', function(e) {
            if (e.target.classList.contains('ab-bh-toggle')) {
                var row    = e.target.closest('.ab-bh-row');
                var slots  = row.querySelector('.ab-bh-slots');
                var lbl    = row.querySelector('.form-check-label');
                if (slots) { slots.classList.toggle('d-none', !e.target.checked); }
                if (lbl)   { lbl.textContent = e.target.checked ? 'Open' : 'Closed'; }
                serialize();
            }
            if (e.target.classList.contains('ab-bh-from') || e.target.classList.contains('ab-bh-to')) {
                serialize();
            }
        });

        /* Add split slot */
        table.addEventListener('click', function(e) {
            if (e.target.classList.contains('ab-bh-add-slot')) {
                var slotsDiv = e.target.closest('.ab-bh-slots');
                var newSlot  = document.createElement('div');
                newSlot.className = 'ab-bh-slot';
                newSlot.innerHTML =
                    '<input type="time" class="form-control form-control-sm ab-bh-from" value="09:00">' +
                    '<span class="ab-bh-sep">\u2013</span>' +
                    '<input type="time" class="form-control form-control-sm ab-bh-to" value="17:00">' +
                    '<button type="button" class="btn btn-sm btn-outline-danger ab-bh-rm-slot" title="Remove">\u00d7</button>';
                slotsDiv.insertBefore(newSlot, e.target);
                serialize();
            }
            if (e.target.classList.contains('ab-bh-rm-slot')) {
                e.target.closest('.ab-bh-slot').remove();
                serialize();
            }
        });

        serialize(); /* initial serialize */
    }

    /* ── 4. Repeatable rows (Pixel IDs, GSC codes) ── */
    function initRepeatableRows() {
        function setupAdd(btnId, listId, templateFn, max) {
            var btn  = document.getElementById(btnId);
            var list = document.getElementById(listId);
            if (!btn || !list) { return; }
            btn.addEventListener('click', function() {
                if (list.querySelectorAll('.ab-repeatable-row').length >= max) { return; }
                var row = document.createElement('div');
                row.className = 'ab-repeatable-row mb-2';
                row.innerHTML = templateFn();
                list.appendChild(row);
            });
        }

        /* Pixel IDs now use ab-chip input (initChipInputs) — no setupAdd needed */

        setupAdd('ab-add-gsc-code', 'ab-gsc-codes-list', function() {
            return '<div class="input-group" style="max-width:500px;">' +
                   '<input type="text" name="gsc_codes_rows[]" class="form-control font-monospace" placeholder="Paste content= value">' +
                   '<button type="button" class="btn btn-outline-danger ab-remove-row">\u00d7</button></div>';
        }, 10);

        setupAdd('ab-add-custom-event', 'ab-custom-events-list', function() {
            return '<div class="row g-2 mb-2" style="max-width:700px;">' +
                   '<div class="col-4"><input type="text" name="custom_event_name[]" class="form-control form-control-sm" placeholder="EventName"></div>' +
                   '<div class="col-5"><input type="text" name="custom_event_url[]" class="form-control form-control-sm" placeholder="URL pattern"></div>' +
                   '<div class="col-3"><button type="button" class="btn btn-sm btn-outline-danger ab-remove-row w-100">Remove</button></div></div>';
        }, 20);

        /* Remove delegate */
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('ab-remove-row')) {
                e.target.closest('.ab-repeatable-row, .row').remove();
            }
        });
    }

    /* ── 5. IndexNow key generator ── */
    function initIndexNowGen() {
        var btn = document.getElementById('ab-indexnow-gen');
        var fld = document.getElementById('f-indexnow-key');
        if (!btn || !fld) { return; }
        btn.addEventListener('click', function() {
            var chars = 'abcdef0123456789';
            var key   = '';
            for (var i = 0; i < 32; i++) { key += chars[Math.floor(Math.random() * chars.length)]; }
            fld.value = key;
        });
    }

    /* ── 6. Crawler list → serialize to hidden JSON field ── */
    function initCrawlerList() {
        var hidden  = document.getElementById('f-crawler-disabled-bots');
        var chkAll  = document.getElementById('ab-crawlers-allow-all');
        var chkNone = document.getElementById('ab-crawlers-block-all');
        var wrap    = document.getElementById('ab-crawler-list-wrap');
        if (!hidden || !wrap) { return; }

        function serialize() {
            var disabled = [];
            wrap.querySelectorAll('.ab-crawler-chk').forEach(function(cb) {
                if (!cb.checked) { disabled.push(cb.dataset.bot); }
            });
            hidden.value = JSON.stringify(disabled);
        }

        function refreshBoxes() {
            wrap.querySelectorAll('.ab-crawler-chk').forEach(function(cb) {
                var box = cb.closest('.ab-crawler-box');
                if (box) {
                    if (cb.checked) { box.classList.remove('blocked'); }
                    else            { box.classList.add('blocked'); }
                }
            });
        }

        wrap.addEventListener('change', function(e) {
            if (e.target.classList.contains('ab-crawler-chk')) {
                serialize();
                refreshBoxes();
            }
        });

        if (chkAll) {
            chkAll.addEventListener('click', function() {
                wrap.querySelectorAll('.ab-crawler-chk').forEach(function(cb) { cb.checked = true; });
                hidden.value = '[]';
                refreshBoxes();
            });
        }
        if (chkNone) {
            chkNone.addEventListener('click', function() {
                wrap.querySelectorAll('.ab-crawler-chk').forEach(function(cb) { cb.checked = false; });
                serialize();
                refreshBoxes();
            });
        }
    }

    /* ── 7. Media Manager — popup window (Joomla 5/6 compatible) ── */
    /*
     * Joomla 6 tmpl=component returns JSON error inside an iframe.
     * Solution: open media manager as a popup window (window.open).
     * Joomla media manager calls window.opener.jInsertFieldValue(url, fieldId)
     * when the user clicks Insert. We listen for that callback here.
     */
    function initMediaManager() {
        var activeFieldId = null;
        var mediaPopup    = null;

        /* ── Apply URL to the target input + inline preview ── */
        function applyUrl(url, fieldId) {
            fieldId = fieldId || activeFieldId;
            if (!fieldId || !url) { return; }
            var input = document.getElementById(fieldId);
            if (input) { input.value = url; }
            var prev = document.getElementById(fieldId + '_preview');
            var wrap = document.getElementById(fieldId + '_preview_wrap');
            if (prev) {
                prev.src = url;
                if (wrap) { wrap.style.display = url ? 'block' : 'none'; }
            }
            if (mediaPopup && !mediaPopup.closed) { mediaPopup.close(); }
            mediaPopup    = null;
            activeFieldId = null;
        }

        /* ── Joomla media manager callback (called from popup window) ── */
        window.jInsertFieldValue = function(value, fieldId) {
            applyUrl(value, fieldId || activeFieldId);
        };

        /* ── postMessage from Joomla media manager Vue SPA (J5/6) ── */
        window.addEventListener('message', function(event) {
            if (!event.data) { return; }
            var d = event.data, url = '';
            if (d.type === 'mediaSelected') {
                var sel = d.selectedData || d.data || d;
                url = sel.url || sel.path || '';
            }
            if (!url && d.messageType === 'joomla:file:selected') {
                var sel2 = d.selectedData || d.data || {};
                url = sel2.url || sel2.path || d.url || '';
            }
            if (!url && typeof d.url  === 'string') { url = d.url; }
            if (!url && typeof d.path === 'string') { url = d.path; }
            if (url) { applyUrl(url); }
        });

        /* ── Open popup ── */
        function openPopup(fieldId) {
            activeFieldId = fieldId;
            var url = 'index.php?option=com_media&view=media&tmpl=component'
                    + '&asset=com_content'
                    + '&mediatypes=0'
                    + '&fieldid=' + encodeURIComponent(fieldId)
                    + '&author=1';
            var w = 1020, h = 700;
            var left = Math.max(0, Math.round((screen.width  - w) / 2));
            var top  = Math.max(0, Math.round((screen.height - h) / 2));
            mediaPopup = window.open(
                url, 'abMediaBrowser',
                'width=' + w + ',height=' + h
                + ',left=' + left + ',top=' + top
                + ',resizable=yes,scrollbars=yes,toolbar=no,menubar=no,location=no,status=no'
            );
            if (mediaPopup) { mediaPopup.focus(); }
        }

        /* ── Browse button click → open popup ── */
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('button[data-fieldid]');
            if (!btn) { return; }
            e.preventDefault();
            openPopup(btn.dataset.fieldid);
        });

        /* ── Sync manual URL typing → preview ── */
        document.querySelectorAll('button[data-fieldid]').forEach(function(btn) {
            var fid   = btn.dataset.fieldid;
            var input = document.getElementById(fid);
            if (!input) { return; }
            input.addEventListener('input', function() { applyUrl(input.value, fid); });
        });
    }

    /* ── 8. Meta Pixel Standard Events — hidden JSON + toggle switches ── */
    function initPixelEvents() {
        var hidden = document.getElementById('f-pixel-events');
        if (!hidden) { return; }

        var KEYS = [
            'Purchase','Lead','ViewContent','Search','AddToCart','AddToWishlist',
            'InitiateCheckout','AddPaymentInfo','CompleteRegistration','Contact',
            'FindLocation','Schedule','StartTrial','SubmitApplication','Subscribe'
        ];

        function save() {
            var out = {};
            KEYS.forEach(function(k) {
                var el = document.getElementById('pxev-' + k.toLowerCase());
                if (el && el.checked) { out[k] = true; }
            });
            hidden.value = JSON.stringify(out);
        }

        document.querySelectorAll('.ab-pse-chk').forEach(function(chk) {
            chk.addEventListener('change', save);
        });
    }

    /* ── 9. Field Hints — collapsible example panels ── */
    function initHints() {
        document.querySelectorAll('.ab-hint-toggle').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var body = btn.nextElementSibling;
                var open = !body.hidden;
                body.hidden = open;
                btn.setAttribute('aria-expanded', String(!open));
                btn.classList.toggle('ab-hint-open', !open);
            });
        });
    }

    /* ── 10. Custom Code — scope toggle + menu IDs serializer ── */
    function initCustomCodeScope() {
        var radios  = document.querySelectorAll('input[name="custom_code_scope"]');
        var wrap    = document.getElementById('ab-code-menu-wrap');
        var hidden  = document.getElementById('f-custom-code-menu-ids');
        if (!radios.length || !wrap || !hidden) { return; }

        function applyScope() {
            var specific = document.getElementById('f-code-scope-specific');
            wrap.style.display = (specific && specific.checked) ? 'block' : 'none';
        }

        radios.forEach(function(r) { r.addEventListener('change', applyScope); });
        applyScope();

        /* Serialize checked menu item IDs → hidden JSON field */
        function serializeMenuIds() {
            var ids = [];
            wrap.querySelectorAll('.ab-code-menu-chk:checked').forEach(function(chk) {
                ids.push(parseInt(chk.value, 10));
            });
            hidden.value = JSON.stringify(ids);
        }

        wrap.addEventListener('change', function(e) {
            if (e.target.classList.contains('ab-code-menu-chk')) {
                serializeMenuIds();
            }
        });
    }

    function runAll() {
        var inits = [
            initSchemaConditional,
            initAdvancedOptions,
            initBusinessHours,
            initRepeatableRows,
            initIndexNowGen,
            initCrawlerList,
            initMediaManager,
            initPixelEvents,
            initHints,
            initCustomCodeScope,
        ];
        inits.forEach(function(fn) {
            try { fn(); } catch(e) { console.error('[AI Boost] init error in ' + fn.name + ':', e); }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', runAll);
    } else {
        runAll();
    }
}());
JS
        );

        /* ── UX Enhancements v0.6.44 ───────────────────────────────────── */
        $doc->addScriptDeclaration(<<<'JS'
(function () {

    /* ── 1. Priority range sliders — live value display ── */
    function initPrioritySliders() {
        document.querySelectorAll('.ab-priority-range').forEach(function(range) {
            var val = range.nextElementSibling;
            if (!val) { return; }
            range.addEventListener('input', function() {
                val.textContent = parseFloat(range.value).toFixed(1);
            });
        });
    }

    /* ── 2. Max Articles stepper (+/-) ── */
    function initSteppers() {
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.ab-step-dec, .ab-step-inc');
            if (!btn) { return; }
            var input = btn.closest('.ab-stepper').querySelector('input[type="number"]');
            if (!input) { return; }
            var step = parseInt(btn.dataset.step || input.step || '1', 10);
            var cur  = parseInt(input.value, 10) || 0;
            var min  = parseInt(input.min, 10);
            var max  = parseInt(input.max, 10);
            var next = btn.classList.contains('ab-step-inc') ? cur + step : cur - step;
            if (!isNaN(min)) { next = Math.max(min, next); }
            if (!isNaN(max)) { next = Math.min(max, next); }
            input.value = next;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    /* ── 3. GA4 / GTM inline validation ── */
    function initIdValidation() {
        var rules = [
            { id: 'f-ga4-id',  indId: 'ab-ga4-indicator',  rx: /^G-[A-Z0-9]{4,20}$/,  ok: 'Valid GA4 ID',  bad: 'Expect G-XXXXXXXX' },
            { id: 'f-gtm-id',  indId: 'ab-gtm-indicator',  rx: /^GTM-[A-Z0-9]{5,12}$/, ok: 'Valid GTM ID', bad: 'Expect GTM-XXXXXXX' },
        ];
        rules.forEach(function(r) {
            var input = document.getElementById(r.id);
            var ind   = document.getElementById(r.indId);
            if (!input || !ind) { return; }
            function validate() {
                var v = input.value.trim();
                if (!v) { ind.textContent = ''; ind.className = 'ab-id-indicator'; return; }
                var valid = r.rx.test(v);
                ind.textContent = valid ? r.ok : r.bad;
                ind.className   = 'ab-id-indicator ' + (valid ? 'ok' : 'bad');
            }
            input.addEventListener('input', validate);
            validate(); /* run on load for pre-filled values */
        });
    }

    /* ── 4. Star rating live preview ── */
    function initStarRating() {
        var input   = document.getElementById('f-rating-value');
        var bestFld = document.querySelector('input[name="schema_rating_best"]');
        var preview = document.getElementById('ab-stars-preview');
        if (!input || !preview) { return; }

        function update() {
            var rv     = parseFloat(input.value) || 0;
            var rb     = Math.max(1, parseFloat(bestFld ? bestFld.value : 5) || 5);
            var filled = rv > 0 ? Math.round(Math.min(5, rv / rb * 5)) : 0;
            var stars  = preview.querySelectorAll('.ab-star');
            var txt    = preview.querySelector('.ab-rating-txt');
            stars.forEach(function(s, i) {
                s.classList.toggle('empty', i + 1 > filled);
            });
            if (txt) {
                txt.textContent = rv > 0 ? rv : '';
                txt.style.display = rv > 0 ? '' : 'none';
            } else if (rv > 0) {
                var span = document.createElement('span');
                span.className = 'ab-rating-txt';
                span.textContent = rv;
                preview.appendChild(span);
            }
        }

        input.addEventListener('input', update);
        if (bestFld) { bestFld.addEventListener('input', update); }
    }

    /* ── 5. Smooth show/hide for .ab-type-conditional sections ── */
    function initSmoothConditional() {
        /* MutationObserver: when display changes to non-none, fire slide-in animation */
        if (!window.MutationObserver) { return; }
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(m) {
                if (m.type !== 'attributes' || m.attributeName !== 'style') { return; }
                var el = m.target;
                if (!el.classList.contains('ab-type-conditional')) { return; }
                if (el.style.display !== 'none' && el.style.display !== '') {
                    el.classList.remove('ab-visible');
                    void el.offsetWidth; /* force reflow to restart animation */
                    el.classList.add('ab-visible');
                    setTimeout(function() { el.classList.remove('ab-visible'); }, 280);
                }
            });
        });
        document.querySelectorAll('.ab-type-conditional').forEach(function(el) {
            observer.observe(el, { attributes: true });
        });
    }

    /* ── 6. OSM Geo Picker ── */
    function initOsmPicker() {
        var openBtn   = document.getElementById('ab-osm-open');
        var modal     = document.getElementById('ab-osm-modal');
        var closeBtn  = document.getElementById('ab-osm-close');
        var confirmBtn= document.getElementById('ab-osm-confirm');
        var coordsEl  = document.getElementById('ab-osm-coords');
        var latInput  = document.getElementById('f-lat');
        var lngInput  = document.getElementById('f-lng');
        if (!openBtn || !modal) { return; }

        var map = null, marker = null, pendingLat = null, pendingLng = null;
        var leafletLoaded = false;

        function initMap() {
            if (map) { return; }
            var initLat = parseFloat(latInput ? latInput.value : '') || 44.8178;
            var initLng = parseFloat(lngInput ? lngInput.value : '') || 20.4569;
            map = window.L.map('ab-osm-map').setView([initLat, initLng], 10);
            window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);
            if (latInput && latInput.value && lngInput && lngInput.value) {
                marker = window.L.marker([initLat, initLng]).addTo(map);
                pendingLat = initLat.toFixed(7);
                pendingLng = initLng.toFixed(7);
                if (coordsEl) { coordsEl.textContent = pendingLat + ', ' + pendingLng; }
                if (confirmBtn) { confirmBtn.disabled = false; }
            }
            map.on('click', function(e) {
                pendingLat = e.latlng.lat.toFixed(7);
                pendingLng = e.latlng.lng.toFixed(7);
                if (marker) { marker.setLatLng(e.latlng); }
                else { marker = window.L.marker(e.latlng).addTo(map); }
                if (coordsEl)   { coordsEl.textContent = pendingLat + ', ' + pendingLng; }
                if (confirmBtn) { confirmBtn.disabled = false; }
            });
        }

        function loadLeaflet(cb) {
            if (leafletLoaded || window.L) { leafletLoaded = true; cb(); return; }
            var cssEl = document.getElementById('ab-leaflet-css');
            var jsEl  = document.getElementById('ab-leaflet-js');
            if (cssEl) { cssEl.href = cssEl.dataset.src; }
            if (!jsEl) { cb(); return; }
            var s = document.createElement('script');
            s.src = jsEl.dataset.src;
            s.crossOrigin = '';
            s.onload = function() { leafletLoaded = true; cb(); };
            s.onerror = function() {
                if (coordsEl) { coordsEl.textContent = 'Could not load map library. Check internet connection.'; }
            };
            document.head.appendChild(s);
            jsEl.remove();
        }

        openBtn.addEventListener('click', function() {
            modal.classList.remove('ab-modal-hidden');
            document.body.style.overflow = 'hidden';
            loadLeaflet(function() {
                setTimeout(function() {
                    initMap();
                    if (map) { map.invalidateSize(); }
                }, 50);
            });
        });

        function closeModal() {
            modal.classList.add('ab-modal-hidden');
            document.body.style.overflow = '';
        }

        if (closeBtn) { closeBtn.addEventListener('click', closeModal); }
        modal.addEventListener('click', function(e) {
            if (e.target === modal) { closeModal(); }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !modal.classList.contains('ab-modal-hidden')) { closeModal(); }
        });

        if (confirmBtn) {
            confirmBtn.addEventListener('click', function() {
                if (pendingLat !== null && latInput) { latInput.value = pendingLat; }
                if (pendingLng !== null && lngInput) { lngInput.value = pendingLng; }
                closeModal();
            });
        }
    }

    /* ── 7. CodeMirror for Custom Code tab ── */
    function initCodeMirror() {
        if (typeof window.CodeMirror === 'undefined') { return; }
        function attachCm(ta, mode) {
            if (!ta || ta._cmAttached) { return; }
            ta._cmAttached = true;
            var cm = window.CodeMirror.fromTextArea(ta, {
                mode:          mode,
                lineNumbers:   true,
                lineWrapping:  true,
                indentWithTabs:false,
                tabSize:       2,
                theme:         'default',
                extraKeys:     { Tab: function(c) { c.replaceSelection('  ', 'end'); } },
            });
            cm.on('change', function() {
                cm.save();
                /* JSON inline validation */
                if (mode === 'application/json') {
                    var val = cm.getValue().trim();
                    var wrap = ta.closest('.ab-cm-json');
                    if (!wrap) { return; }
                    var badge = wrap.querySelector('.ab-json-badge');
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'ab-json-badge';
                        wrap.appendChild(badge);
                    }
                    if (!val) { badge.style.display = 'none'; return; }
                    try {
                        JSON.parse(val);
                        badge.textContent = '✓ Valid JSON';
                        badge.className = 'ab-json-badge ok';
                    } catch(e) {
                        badge.textContent = '✗ Invalid JSON';
                        badge.className = 'ab-json-badge bad';
                    }
                }
            });
            cm.setSize(null, Math.max(130, cm.lineCount() * 18 + 20));
        }
        /* HTML mode for custom code textareas */
        var htmlTas = ['f-custom-code-head', 'f-custom-code-body'];
        htmlTas.forEach(function(id) {
            attachCm(document.getElementById(id), 'htmlmixed');
        });
        /* JSON mode for all .ab-cm-json textareas */
        document.querySelectorAll('.ab-cm-json textarea').forEach(function(ta) {
            attachCm(ta, 'application/json');
        });
    }

    /* ── GSC verification code inline format check ── */
    function initGscValidation() {
        var list = document.getElementById('ab-gsc-codes-list');
        if (!list) { return; }
        function addGscBadge(input) {
            if (input._gscBadge) { return; }
            var badge = document.createElement('span');
            badge.className = 'ab-id-indicator';
            badge.style.marginLeft = '.4rem';
            input.parentNode.appendChild(badge);
            input._gscBadge = badge;
            function validate() {
                var v = input.value.trim();
                if (!v) { badge.style.display = 'none'; return; }
                /* GSC token: alphanumeric + dashes/underscores, no spaces, no < > */
                var ok = /^[A-Za-z0-9_\-]{10,}$/.test(v);
                badge.textContent = ok ? 'Valid token' : 'No <meta> tags — paste content= value only';
                badge.className = 'ab-id-indicator ' + (ok ? 'ok' : 'bad');
            }
            input.addEventListener('input', validate);
            input.addEventListener('blur', validate);
            validate();
        }
        list.querySelectorAll('input[name="gsc_codes_rows[]"]').forEach(addGscBadge);
        /* Watch for dynamically added rows */
        new MutationObserver(function() {
            list.querySelectorAll('input[name="gsc_codes_rows[]"]').forEach(addGscBadge);
        }).observe(list, { childList: true, subtree: true });
    }

    /* ── 8. Frequency pills (changefreq) ── */
    function initFreqPills() {
        document.querySelectorAll('.ab-freq-pills').forEach(function(wrap) {
            var targetName = wrap.getAttribute('data-target');
            var hidden = document.getElementById('f-' + targetName.replace(/_/g, '-'));
            if (!hidden) { return; }
            wrap.querySelectorAll('.ab-freq-pill').forEach(function(pill) {
                pill.addEventListener('click', function() {
                    wrap.querySelectorAll('.ab-freq-pill').forEach(function(p) { p.classList.remove('active'); });
                    pill.classList.add('active');
                    hidden.value = pill.getAttribute('data-val');
                });
            });
        });
    }

    /* ── 9. Chip / tag inputs ── */
    function initChipInputs() {
        /* Pixel IDs chip */
        var pixelWrap   = document.getElementById('ab-pixel-chip-wrap');
        var pixelInput  = document.getElementById('ab-pixel-chip-input');
        var pixelHidden = document.getElementById('f-pixel-ids-hidden');
        if (pixelWrap && pixelInput && pixelHidden) {
            var pixelChipsEl = document.getElementById('ab-pixel-chips');
            function pixelRender() {
                var vals = [];
                try { vals = JSON.parse(pixelHidden.value || '[]'); } catch(e) { vals = []; }
                pixelChipsEl.innerHTML = '';
                vals.forEach(function(v) {
                    if (!v) { return; }
                    var chip = document.createElement('span');
                    chip.className = 'ab-chip';
                    chip.textContent = v;
                    var x = document.createElement('button');
                    x.type = 'button'; x.className = 'ab-chip-x'; x.textContent = '×';
                    x.addEventListener('click', function() {
                        vals = vals.filter(function(i) { return i !== v; });
                        pixelHidden.value = JSON.stringify(vals);
                        pixelRender();
                    });
                    chip.appendChild(x);
                    pixelChipsEl.appendChild(chip);
                });
            }
            pixelRender();
            pixelWrap.addEventListener('click', function() { pixelInput.focus(); });
            pixelInput.addEventListener('keydown', function(e) {
                if (e.key !== 'Enter' && e.key !== ',') { return; }
                e.preventDefault();
                var val = pixelInput.value.trim().replace(/,/g, '');
                if (!val) { return; }
                var vals = [];
                try { vals = JSON.parse(pixelHidden.value || '[]'); } catch(e2) { vals = []; }
                if (vals.indexOf(val) === -1 && vals.length < 5) { vals.push(val); }
                pixelHidden.value = JSON.stringify(vals);
                pixelInput.value = '';
                pixelRender();
            });
        }

        /* Sitemap exclude IDs chip */
        var exclWrap   = document.getElementById('ab-excl-chip-wrap');
        var exclInput  = document.getElementById('ab-excl-chip-input');
        var exclHidden = document.getElementById('f-excl-ids-hidden');
        if (exclWrap && exclInput && exclHidden) {
            var exclChipsEl = document.getElementById('ab-excl-chips');
            function exclGetVals() {
                return (exclHidden.value || '').split(',').map(function(v) { return v.trim(); }).filter(Boolean);
            }
            function exclRender() {
                var vals = exclGetVals();
                exclChipsEl.innerHTML = '';
                vals.forEach(function(v) {
                    var chip = document.createElement('span');
                    chip.className = 'ab-chip';
                    chip.textContent = v;
                    var x = document.createElement('button');
                    x.type = 'button'; x.className = 'ab-chip-x'; x.textContent = '×';
                    x.addEventListener('click', function() {
                        var remaining = exclGetVals().filter(function(i) { return i !== v; });
                        exclHidden.value = remaining.join(', ');
                        exclRender();
                    });
                    chip.appendChild(x);
                    exclChipsEl.appendChild(chip);
                });
            }
            exclRender();
            exclWrap.addEventListener('click', function() { exclInput.focus(); });
            exclInput.addEventListener('keydown', function(e) {
                if (e.key !== 'Enter') { return; }
                e.preventDefault();
                var val = String(parseInt(exclInput.value.trim(), 10) || '');
                if (!val || val === 'NaN') { return; }
                var vals = exclGetVals();
                if (vals.indexOf(val) === -1) { vals.push(val); }
                exclHidden.value = vals.join(', ');
                exclInput.value = '';
                exclRender();
            });
        }
    }

    /* ── 10. Robots.txt rule builder ── */
    function initRobotsBuilder() {
        var agentSel     = document.getElementById('ab-robot-agent');
        var customInput  = document.getElementById('ab-robot-custom-agent');
        var pathInput    = document.getElementById('ab-robot-path');
        var addBtn       = document.getElementById('ab-robot-add-rule');
        var rulesList    = document.getElementById('ab-robot-rules-list');
        var rawToggle    = document.getElementById('ab-robot-raw-toggle');
        var rawWrap      = document.getElementById('ab-robots-raw-wrap');
        var rawTextarea  = document.getElementById('f-crawler-rules-raw');
        var structToggle = document.getElementById('ab-robot-structured-toggle');
        var hiddenField  = document.getElementById('f-crawler-rules');
        if (!agentSel || !rulesList || !hiddenField) { return; }

        /* Parse existing raw value into rule objects */
        var rules = [];
        function parseRaw(raw) {
            raw = (raw || '').trim();
            if (!raw) { return []; }
            var result = [];
            var blocks = raw.split(/\n\s*\n/);
            blocks.forEach(function(block) {
                var lines = block.split('\n').map(function(l) { return l.trim(); }).filter(Boolean);
                var agent = '*'; var action = 'Disallow'; var path = '/';
                lines.forEach(function(line) {
                    var m;
                    if ((m = line.match(/^User-agent:\s*(.+)/i))) { agent = m[1]; }
                    else if ((m = line.match(/^(Disallow|Allow):\s*(.*)/i))) { action = m[1]; path = m[2] || '/'; }
                });
                if (agent) { result.push({ agent: agent, action: action, path: path }); }
            });
            return result;
        }
        function rulesToRaw(r) {
            return r.map(function(rule) {
                return 'User-agent: ' + rule.agent + '\n' + rule.action + ': ' + rule.path;
            }).join('\n\n');
        }
        function syncHidden() {
            hiddenField.value = rulesToRaw(rules);
        }
        function renderRules() {
            /* Clear without innerHTML — safe approach */
            while (rulesList.firstChild) { rulesList.removeChild(rulesList.firstChild); }
            if (!rules.length) {
                var empty = document.createElement('p');
                empty.className = 'text-muted small mb-0';
                empty.textContent = 'No custom rules — add one above.';
                rulesList.appendChild(empty);
                return;
            }
            rules.forEach(function(rule, idx) {
                var item = document.createElement('div');
                item.className = 'ab-robot-rule-item';
                /* Use textContent/DOM nodes — never innerHTML with user-provided strings */
                var agentCode = document.createElement('code');
                agentCode.textContent = String(rule.agent || '*');
                var actionSpan = document.createElement('span');
                actionSpan.className = rule.action === 'Allow' ? 'ab-rule-action-allow' : 'ab-rule-action-disallow';
                actionSpan.textContent = String(rule.action || 'Disallow');
                var pathCode = document.createElement('code');
                pathCode.textContent = String(rule.path || '/');
                item.appendChild(agentCode);
                item.appendChild(document.createTextNode(' '));
                item.appendChild(actionSpan);
                item.appendChild(document.createTextNode(' '));
                item.appendChild(pathCode);
                var del = document.createElement('button');
                del.type = 'button'; del.className = 'ab-robot-rule-del'; del.textContent = '×';
                del.title = 'Remove rule';
                del.addEventListener('click', (function(i) {
                    return function() { rules.splice(i, 1); renderRules(); syncHidden(); };
                }(idx)));
                item.appendChild(del);
                rulesList.appendChild(item);
            });
        }

        /* Initialize from saved value */
        rules = parseRaw(hiddenField.value);
        renderRules();
        if (rawTextarea) { rawTextarea.value = hiddenField.value; }

        /* Show/hide custom agent input */
        agentSel.addEventListener('change', function() {
            if (customInput) { customInput.style.display = agentSel.value === 'custom' ? '' : 'none'; }
        });

        /* Add rule */
        if (addBtn) {
            addBtn.addEventListener('click', function() {
                var agent = agentSel.value === 'custom'
                    ? (customInput ? customInput.value.trim() : '') : agentSel.value;
                if (!agent) { return; }
                var actionEl = document.querySelector('input[name="ab-robot-action"]:checked');
                var action = actionEl ? actionEl.value : 'Disallow';
                var path = (pathInput ? pathInput.value.trim() : '') || '/';
                rules.push({ agent: agent, action: action, path: path });
                renderRules();
                syncHidden();
                if (rawTextarea) { rawTextarea.value = hiddenField.value; }
                if (pathInput) { pathInput.value = '/'; }
            });
        }

        /* Toggle raw */
        if (rawToggle) {
            rawToggle.addEventListener('click', function() {
                if (rawTextarea) { rawTextarea.value = hiddenField.value; }
                rawWrap.style.display = '';
                document.getElementById('ab-robots-builder').style.display = 'none';
            });
        }
        if (structToggle) {
            structToggle.addEventListener('click', function() {
                if (rawTextarea) {
                    hiddenField.value = rawTextarea.value;
                    rules = parseRaw(rawTextarea.value);
                    renderRules();
                }
                rawWrap.style.display = 'none';
                document.getElementById('ab-robots-builder').style.display = '';
            });
        }
        /* Keep hidden in sync when editing raw */
        if (rawTextarea) {
            rawTextarea.addEventListener('input', function() { hiddenField.value = rawTextarea.value; });
        }
    }

    /* ── 11. Social chip cards — toggle ✓ badge on input change ── */
    function initSocialChips() {
        document.querySelectorAll('.ab-sc-card').forEach(function(card) {
            var input = card.querySelector('.ab-sc-input');
            if (!input) { return; }
            function syncBadge() {
                var hasBadge = !!card.querySelector('.ab-sc-badge');
                if (input.value.trim()) {
                    card.classList.add('has-url');
                    if (!hasBadge) {
                        var badge = document.createElement('span');
                        badge.className = 'ab-sc-badge';
                        badge.textContent = '✓';
                        card.querySelector('.ab-sc-head').appendChild(badge);
                    }
                } else {
                    card.classList.remove('has-url');
                    var b = card.querySelector('.ab-sc-badge');
                    if (b) { b.parentNode.removeChild(b); }
                }
            }
            input.addEventListener('input', syncBadge);
            input.addEventListener('change', syncBadge);
        });
    }

    /* ── 12. URL exclusions chip input ── */
    function initUrlExclusionChips() {
        var urlsWrap   = document.getElementById('ab-excl-urls-chip-wrap');
        var urlsInput  = document.getElementById('ab-excl-urls-chip-input');
        var urlsHidden = document.getElementById('f-excl-urls-hidden');
        if (!urlsWrap || !urlsInput || !urlsHidden) { return; }
        var urlsChipsEl = document.getElementById('ab-excl-urls-chips');
        function urlsGetVals() {
            return (urlsHidden.value || '').split(',').map(function(v) { return v.trim(); }).filter(Boolean);
        }
        function urlsRender() {
            var vals = urlsGetVals();
            urlsChipsEl.innerHTML = '';
            vals.forEach(function(v) {
                var chip = document.createElement('span');
                chip.className = 'ab-chip';
                chip.textContent = v;
                var x = document.createElement('button');
                x.type = 'button'; x.className = 'ab-chip-x'; x.textContent = '×';
                x.addEventListener('click', function() {
                    var remaining = urlsGetVals().filter(function(i) { return i !== v; });
                    urlsHidden.value = remaining.join(', ');
                    urlsRender();
                });
                chip.appendChild(x);
                urlsChipsEl.appendChild(chip);
            });
        }
        urlsRender();
        urlsWrap.addEventListener('click', function() { urlsInput.focus(); });
        urlsInput.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter') { return; }
            e.preventDefault();
            var val = urlsInput.value.trim();
            if (!val) { return; }
            var vals = urlsGetVals();
            if (vals.indexOf(val) === -1) { vals.push(val); }
            urlsHidden.value = vals.join(', ');
            urlsInput.value = '';
            urlsRender();
        });
    }

    /* ── 13. URL image thumbnail preview ── */
    function initUrlPreview() {
        /* Generic: add thumbnail preview below any URL input that has data-preview="1" */
        function addPreview(input) {
            var wrap = document.createElement('div');
            wrap.className = 'ab-url-preview';
            var img = document.createElement('img');
            img.alt = 'Preview';
            var err = document.createElement('div');
            err.className = 'ab-url-preview-err';
            wrap.appendChild(img);
            wrap.appendChild(err);
            input.parentNode.insertBefore(wrap, input.nextSibling);
            function update() {
                var url = input.value.trim();
                if (!url) { wrap.style.display = 'none'; return; }
                img.onload  = function() { wrap.style.display = ''; err.textContent = ''; };
                img.onerror = function() { wrap.style.display = ''; err.textContent = 'Could not load image preview.'; img.style.display = 'none'; };
                img.style.display = '';
                img.src = url;
            }
            input.addEventListener('blur', update);
            input.addEventListener('change', update);
            update();
        }
        /* OG default image — find Joomla media picker hidden input */
        var ogInputs = document.querySelectorAll('[name="og_default_image"]');
        ogInputs.forEach(function(el) {
            if (el.type === 'hidden' || el.getAttribute('data-preview-added')) { return; }
            el.setAttribute('data-preview-added', '1');
            addPreview(el);
        });
        /* Also watch for Joomla media picker value changes via MutationObserver on hidden inputs */
        document.querySelectorAll('[name="og_default_image"]').forEach(function(el) {
            if (el.type !== 'hidden') { return; }
            /* find sibling text/url input */
            var parent = el.closest('.field-media-wrapper, .joomla-field-media, .ab-field-group');
            if (!parent) { return; }
            var textInput = parent.querySelector('input[type="text"], input[type="url"]');
            if (textInput && !textInput.getAttribute('data-preview-added')) {
                textInput.setAttribute('data-preview-added', '1');
                addPreview(textInput);
            }
        });
        /* Logo URL — attach preview using ab-logo-preview-wrap / ab-logo-preview-img */
        (function() {
            var wrap  = document.getElementById('ab-logo-preview-wrap');
            var img   = document.getElementById('ab-logo-preview-img');
            var err   = document.getElementById('ab-logo-preview-err');
            if (!wrap || !img) { return; }
            /* Find the input for schema_logo_url — could be Joomla media picker text or plain text */
            function findLogoInput() {
                /* Joomla media picker: input[name="schema_logo_url"] that is not hidden */
                var inputs = document.querySelectorAll('[name="schema_logo_url"]');
                for (var i = 0; i < inputs.length; i++) {
                    if (inputs[i].type !== 'hidden') { return inputs[i]; }
                }
                return null;
            }
            function attachLogoPreview(input) {
                if (!input || input._logoPreviewAttached) { return; }
                input._logoPreviewAttached = true;
                function update() {
                    var url = input.value.trim();
                    if (!url) { wrap.style.display = 'none'; return; }
                    img.onload  = function() { wrap.style.display = ''; if(err) err.textContent = ''; img.style.display = ''; };
                    img.onerror = function() { wrap.style.display = ''; if(err) err.textContent = 'Could not load logo preview.'; img.style.display = 'none'; };
                    img.src = url;
                }
                input.addEventListener('blur', update);
                input.addEventListener('change', update);
                /* Joomla media picker hidden field sync */
                var hiddenInputs = document.querySelectorAll('[name="schema_logo_url"][type="hidden"]');
                hiddenInputs.forEach(function(h) {
                    new MutationObserver(function() { input.value = h.value; update(); }).observe(h, { attributes: true, attributeFilter: ['value'] });
                });
            }
            var logoInput = findLogoInput();
            if (logoInput) {
                attachLogoPreview(logoInput);
            } else {
                /* Deferred: Joomla media picker renders async */
                new MutationObserver(function() {
                    var li = findLogoInput();
                    if (li) { attachLogoPreview(li); }
                }).observe(document.body, { childList: true, subtree: true });
            }
        }());
    }

    function runAll() {
        try { initPrioritySliders(); }  catch(e) { console.error('[AB] sliders:', e); }
        try { initSteppers(); }         catch(e) { console.error('[AB] steppers:', e); }
        try { initIdValidation(); }     catch(e) { console.error('[AB] id-validation:', e); }
        try { initStarRating(); }       catch(e) { console.error('[AB] stars:', e); }
        try { initSmoothConditional(); }catch(e) { console.error('[AB] smooth:', e); }
        try { initOsmPicker(); }        catch(e) { console.error('[AB] osm:', e); }
        try { initFreqPills(); }        catch(e) { console.error('[AB] freq-pills:', e); }
        try { initChipInputs(); }       catch(e) { console.error('[AB] chips:', e); }
        try { initRobotsBuilder(); }    catch(e) { console.error('[AB] robots:', e); }
        try { initUrlPreview(); }       catch(e) { console.error('[AB] url-preview:', e); }
        try { initGscValidation(); }        catch(e) { console.error('[AB] gsc-validation:', e); }
        try { initSocialChips(); }          catch(e) { console.error('[AB] social-chips:', e); }
        try { initUrlExclusionChips(); }    catch(e) { console.error('[AB] url-excl-chips:', e); }
        /* CodeMirror init deferred — libs loaded at bottom of page */
        setTimeout(function() {
            try { initCodeMirror(); } catch(e) { console.error('[AB] codemirror:', e); }
        }, 200);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', runAll);
    } else {
        runAll();
    }
}());
JS
        );

        /* ── AJAX Save ─────────────────────────────────────────────────── */
        $doc->addScriptDeclaration(<<<'JS'
(function () {
    function initSave() {
        var saveBtn = document.getElementById('ab-save-btn');
        var saveMsg = document.getElementById('ab-save-msg');
        if (!saveBtn) { return; }

        saveBtn.addEventListener('click', function () {
            saveBtn.disabled    = true;
            saveBtn.textContent = 'Saving\u2026';

            var form     = document.getElementById('ab-settings-form');
            var formData = new FormData(form);

            form.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
                if (!cb.checked && !cb.name.endsWith('[]')) { formData.set(cb.name, '0'); }
            });

            /* Meta Pixel IDs backward-compat: sync meta_pixel_id = first element of JSON array */
            (function () {
                var ph = document.getElementById('f-pixel-ids-hidden');
                if (!ph) { return; }
                try {
                    var ids = JSON.parse(ph.value || '[]');
                    formData.set('meta_pixel_id', Array.isArray(ids) && ids.length ? String(ids[0]).trim() : '');
                } catch (e) {}
            }());

            fetch('index.php?option=com_aiboost&task=settings.save&format=json', {
                method:  'POST',
                body:    formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                saveMsg.className   = 'ab-save-msg show ' + (data.success ? 'success' : 'error');
                saveMsg.textContent = data.message || (data.success ? 'Settings saved.' : 'Error saving.');
            })
            .catch(function () {
                saveMsg.className   = 'ab-save-msg show error';
                saveMsg.textContent = 'Network error. Please try again.';
            })
            .finally(function () {
                saveBtn.disabled    = false;
                saveBtn.textContent = 'Save Settings';
                setTimeout(function () { saveMsg.className = 'ab-save-msg'; }, 4000);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSave);
    } else {
        initSave();
    }
}());
JS
        );

    }

    /**
     * Detect whether the Pro PACKAGE is installed (mirrors App\HtmlView::detectProInstall).
     * Presence of the pkg_aiboost_pro package OR any aiboost_*_pro plugin row in
     * #__extensions counts — NOT enabled-state, since a package has no meaningful
     * enabled flag and individual Pro plugins may be toggled off without uninstalling.
     */
    private function detectProInstall(): bool
    {
        // Phase 5a — single source of truth (mirrors the App view): install
        // marker OR live activation OR legacy split layout. See
        // PluginRegistry::isProInstall().
        return \AiBoost\Lib\PluginRegistry::isProInstall();
    }

    private function loadSettings(): array
    {
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from('#__aiboost_settings')
                ->where($db->quoteName('setting_key') . '=' . $db->quote('main'));
            $db->setQuery($query);
            $json = $db->loadResult();

            if (!empty($json)) {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        } catch (\Throwable $e) {
        }

        return [];
    }

    /**
     * Load all rows from #__aiboost_translations and return as a nested map:
     *   { fieldKey: { langCode: value, ... }, ... }
     * Used to pre-populate window.aiBoostTranslations for the Vue admin.
     *
     * @return array<string, array<string, string>>
     */
    private function loadTranslationsData(): array
    {
        try {
            $db     = Factory::getDbo();
            $prefix = $db->getPrefix();
            $tables = $db->setQuery('SHOW TABLES LIKE ' . $db->quote($prefix . 'aiboost_translations'))->loadColumn();
            if (empty($tables)) {
                return [];
            }
            $q    = $db->getQuery(true)
                ->select([$db->quoteName('field_key'), $db->quoteName('lang_code'), $db->quoteName('field_value')])
                ->from($db->quoteName('#__aiboost_translations'))
                ->order($db->quoteName('field_key') . ' ASC, ' . $db->quoteName('lang_code') . ' ASC');
            $rows = $db->setQuery($q)->loadObjectList() ?: [];

            $map = [];
            foreach ($rows as $row) {
                $fk = (string) $row->field_key;
                $lc = (string) $row->lang_code;
                if (!isset($map[$fk])) {
                    $map[$fk] = [];
                }
                $map[$fk][$lc] = (string) $row->field_value;
            }
            return $map;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
