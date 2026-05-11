<?php

/**
 * AI Boost for Joomla - Business Hours Field
 *
 * Two-mode widget:
 *   Compact  — 1 shared row for Mon–Fri + Sat + Sun  (default)
 *   Individual — separate row per day (Mon–Sun)
 *
 * Toggle button switches between modes. Sat/Sun are always separate.
 * Stored JSON: full 7-day structure for SchemaService compatibility.
 *
 * @copyright   (C) 2025 AI Boost Team (aiboostnow.com)
 * @license     GNU General Public License version 2 or later
 */

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Field;

use Joomla\CMS\Form\FormField;

defined('_JEXEC') or die;

class BusinessHoursField extends FormField
{
    protected $type = 'BusinessHours';

    private const WEEKDAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri'];

    private const DAY_LABELS = [
        'mon' => 'Monday',
        'tue' => 'Tuesday',
        'wed' => 'Wednesday',
        'thu' => 'Thursday',
        'fri' => 'Friday',
        'sat' => 'Saturday',
        'sun' => 'Sunday',
    ];

    private const DEFAULTS_7 = [
        'mon' => ['open' => '09:00', 'close' => '17:00', 'closed' => false],
        'tue' => ['open' => '09:00', 'close' => '17:00', 'closed' => false],
        'wed' => ['open' => '09:00', 'close' => '17:00', 'closed' => false],
        'thu' => ['open' => '09:00', 'close' => '17:00', 'closed' => false],
        'fri' => ['open' => '09:00', 'close' => '17:00', 'closed' => false],
        'sat' => ['open' => '09:00', 'close' => '13:00', 'closed' => true],
        'sun' => ['open' => '10:00', 'close' => '14:00', 'closed' => true],
    ];

    protected function getInput(): string
    {
        $schedule  = $this->parseValue();          // full 7-day array
        $isCompact = $this->allWeekdaysIdentical($schedule);
        $id        = $this->id;
        $name      = $this->name;
        $jsonVal   = htmlspecialchars(
            json_encode($this->toStoredFormat($schedule), JSON_UNESCAPED_UNICODE),
            ENT_QUOTES,
            'UTF-8'
        );

        $inputAttrs = 'type="text" class="form-control form-control-sm"'
            . ' maxlength="5" pattern="[0-2][0-9]:[0-5][0-9]"'
            . ' style="width:64px;min-width:0;font-variant-numeric:tabular-nums;text-align:center;"';

        // ── Build compact tbody (Mon–Fri combined) ────────────────────────────
        $wk = $schedule['mon'];  // representative for all weekdays
        $compactTbody = $this->buildRow($id, 'weekdays', 'Mon – Fri', $wk, $inputAttrs);

        // ── Build individual tbody (Mon–Fri rows) ─────────────────────────────
        $individualTbody = '';
        foreach (self::WEEKDAY_KEYS as $abbr) {
            $individualTbody .= $this->buildRow($id, $abbr, self::DAY_LABELS[$abbr], $schedule[$abbr], $inputAttrs);
        }

        // ── Weekend tbody (Sat/Sun always visible, shared inputs) ─────────────
        $weekendTbody = '';
        foreach (['sat', 'sun'] as $abbr) {
            $weekendTbody .= $this->buildRow($id, $abbr, self::DAY_LABELS[$abbr], $schedule[$abbr], $inputAttrs);
        }

        // ── Toggle buttons ────────────────────────────────────────────────────
        $btnActive   = 'btn btn-secondary btn-sm';
        $btnInactive = 'btn btn-outline-secondary btn-sm';
        $toggleBar = '<div class="d-flex gap-2 mb-2 align-items-center">'
            . '<span class="text-muted small" style="white-space:nowrap;">Weekdays:</span>'
            . '<div class="btn-group btn-group-sm" role="group" id="' . $id . '_modebtns">'
            . '<button type="button" id="' . $id . '_btn_compact"'
            . ' class="' . ($isCompact ? $btnActive : $btnInactive) . '">All same</button>'
            . '<button type="button" id="' . $id . '_btn_individual"'
            . ' class="' . (!$isCompact ? $btnActive : $btnInactive) . '">Individual</button>'
            . '</div>'
            . '</div>';

        $compactDisplay    = $isCompact ? '' : 'display:none;';
        $individualDisplay = $isCompact ? 'display:none;' : '';
        $initMode          = $isCompact ? 'compact' : 'individual';

        $script = $this->buildScript($id, $initMode);

        return '<div class="jb-business-hours" id="' . $id . '_widget"'
            . ' style="width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;">'
            . '<input type="hidden" id="' . $id . '" name="' . $name . '" value="' . $jsonVal . '">'
            . $toggleBar
            . '<table class="table table-sm table-bordered mb-0"'
            . ' style="font-size:0.9em;min-width:280px;max-width:420px;">'
            . '<thead><tr>'
            . '<th style="width:86px;">Day</th>'
            . '<th style="width:44px;text-align:center;">Closed</th>'
            . '<th>Hours <span class="text-muted fw-normal" style="font-size:0.8em;">(24h)</span></th>'
            . '</tr></thead>'
            . '<tbody id="' . $id . '_tbody_compact" style="' . $compactDisplay . '">'
            . $compactTbody
            . '</tbody>'
            . '<tbody id="' . $id . '_tbody_individual" style="' . $individualDisplay . '">'
            . $individualTbody
            . '</tbody>'
            . '<tbody id="' . $id . '_tbody_weekend">'
            . $weekendTbody
            . '</tbody>'
            . '</table>'
            . '</div>'
            . $script;
    }

    /**
     * Build a single <tr> row.
     *
     * @param array<string, string|bool> $day
     */
    private function buildRow(string $id, string $key, string $label, array $day, string $inputAttrs): string
    {
        $closed   = !empty($day['closed']);
        $open     = htmlspecialchars((string) ($day['open']  ?? ''), ENT_QUOTES, 'UTF-8');
        $close    = htmlspecialchars((string) ($day['close'] ?? ''), ENT_QUOTES, 'UTF-8');

        $closedChk     = $closed ? ' checked' : '';
        $disAttr       = $closed ? ' disabled' : '';
        $openFldsDisp  = $closed ? 'display:none;' : '';
        $closedLblDisp = $closed ? '' : 'display:none;';
        $idRow         = $id . '_' . $key;

        $html  = '<tr data-day="' . $key . '" class="jb-hours-row">' . "\n";
        // Day name
        $html .= '  <td class="fw-semibold" style="white-space:nowrap;vertical-align:middle;padding:5px 8px;">'
               . $label . '</td>' . "\n";
        // Closed toggle
        $html .= '  <td style="text-align:center;vertical-align:middle;padding:5px 2px;">'
               . '<div class="form-check form-switch d-flex justify-content-center mb-0">'
               . '<input class="form-check-input jb-closed-chk" type="checkbox"'
               . ' id="' . $idRow . '_closed" value="1"' . $closedChk
               . ' title="Mark as closed" style="cursor:pointer;">'
               . '</div></td>' . "\n";
        // Hours
        $html .= '  <td style="vertical-align:middle;padding:5px 8px;">' . "\n";
        $html .= '    <div class="jb-open-fields"'
               . ' style="display:flex;align-items:center;gap:5px;' . $openFldsDisp . '">' . "\n";
        $html .= '      <input ' . $inputAttrs . ' class="jb-open"'
               . ' id="' . $idRow . '_open" value="' . $open . '" placeholder="09:00"' . $disAttr . '>' . "\n";
        $html .= '      <span style="opacity:0.45;font-size:0.9em;">–</span>' . "\n";
        $html .= '      <input ' . $inputAttrs . ' class="jb-close"'
               . ' id="' . $idRow . '_close" value="' . $close . '" placeholder="17:00"' . $disAttr . '>' . "\n";
        $html .= '    </div>' . "\n";
        $html .= '    <div class="jb-closed-label text-muted small fst-italic"'
               . ' style="' . $closedLblDisp . '">Closed</div>' . "\n";
        $html .= '  </td>' . "\n";
        $html .= '</tr>' . "\n";

        return $html;
    }

    /**
     * Parse stored JSON into full 7-day schedule.
     *
     * @return array<string, array<string, string|bool>>
     */
    private function parseValue(): array
    {
        $raw     = trim((string) $this->value);
        $decoded = ($raw !== '' && $raw !== '{}') ? json_decode($raw, true) : null;

        $result = [];
        foreach (self::DAY_LABELS as $abbr => $_) {
            $default = self::DEFAULTS_7[$abbr];
            if (is_array($decoded) && isset($decoded[$abbr]) && is_array($decoded[$abbr])) {
                $d = $decoded[$abbr];
                $result[$abbr] = [
                    'open'   => (string) ($d['open']  ?? $default['open']),
                    'close'  => (string) ($d['close'] ?? $default['close']),
                    'closed' => !empty($d['closed']),
                ];
            } else {
                $result[$abbr] = $default;
            }
        }

        return $result;
    }

    /**
     * Check whether all 5 weekdays have identical open/close/closed values.
     *
     * @param array<string, array<string, string|bool>> $schedule
     */
    private function allWeekdaysIdentical(array $schedule): bool
    {
        $ref = $schedule['mon'] ?? self::DEFAULTS_7['mon'];
        foreach (['tue', 'wed', 'thu', 'fri'] as $abbr) {
            $d = $schedule[$abbr] ?? self::DEFAULTS_7[$abbr];
            if ($d['open'] !== $ref['open'] || $d['close'] !== $ref['close'] || $d['closed'] !== $ref['closed']) {
                return false;
            }
        }
        return true;
    }

    /**
     * Normalise to stored 7-day JSON format (adds open2/close2 placeholders).
     *
     * @param array<string, array<string, string|bool>> $schedule
     * @return array<string, array<string, string|bool>>
     */
    private function toStoredFormat(array $schedule): array
    {
        $out = [];
        foreach (self::DAY_LABELS as $abbr => $_) {
            $d = $schedule[$abbr] ?? self::DEFAULTS_7[$abbr];
            $out[$abbr] = [
                'open'   => $d['open'],
                'close'  => $d['close'],
                'open2'  => '',
                'close2' => '',
                'closed' => $d['closed'],
            ];
        }
        return $out;
    }

    private function buildScript(string $id, string $initMode): string
    {
        $jsId   = json_encode($id,       JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        $jsMode = json_encode($initMode, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

        return <<<JS
<script>
(function () {
    var id   = {$jsId};
    var mode = {$jsMode};
    var WEEKDAY_KEYS = ['mon','tue','wed','thu','fri'];

    function normaliseTime(val) {
        val = (val || '').replace(/[^\d:]/g, '').trim();
        if (!val) { return ''; }
        var parts = val.split(':');
        var h = parseInt(parts[0] || '0', 10);
        var m = parseInt(parts[1] || '0', 10);
        if (isNaN(h) || isNaN(m)) { return val; }
        if (h > 23) { h = 23; }
        if (m > 59) { m = 59; }
        return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
    }

    function getRowValues(prefix) {
        return {
            open:   (document.getElementById(id + '_' + prefix + '_open')   || {}).value   || '',
            close:  (document.getElementById(id + '_' + prefix + '_close')  || {}).value   || '',
            closed: !!(document.getElementById(id + '_' + prefix + '_closed') || {}).checked,
            open2:  '',
            close2: ''
        };
    }

    function setRowValues(prefix, vals) {
        var o  = document.getElementById(id + '_' + prefix + '_open');
        var c  = document.getElementById(id + '_' + prefix + '_close');
        var cl = document.getElementById(id + '_' + prefix + '_closed');
        if (o)  { o.value    = vals.open   || ''; }
        if (c)  { c.value    = vals.close  || ''; }
        if (cl) { cl.checked = !!vals.closed; }
        if (o || c || cl) {
            var row = (o || c || cl).closest('tr');
            if (row) { applyClosedState(row); }
        }
    }

    function collect() {
        var out = {};
        if (mode === 'compact') {
            var wk = getRowValues('weekdays');
            WEEKDAY_KEYS.forEach(function (d) { out[d] = wk; });
        } else {
            WEEKDAY_KEYS.forEach(function (d) { out[d] = getRowValues(d); });
        }
        out['sat'] = getRowValues('sat');
        out['sun'] = getRowValues('sun');
        return out;
    }

    function save() {
        var hidden = document.getElementById(id);
        if (hidden) { hidden.value = JSON.stringify(collect()); }
    }

    function applyClosedState(row) {
        var closedEl  = row.querySelector('.jb-closed-chk');
        var openFlds  = row.querySelector('.jb-open-fields');
        var closedLbl = row.querySelector('.jb-closed-label');
        if (!closedEl) { return; }
        var isClosed = closedEl.checked;
        if (openFlds) {
            openFlds.querySelectorAll('input').forEach(function (el) { el.disabled = isClosed; });
            openFlds.style.display = isClosed ? 'none' : 'flex';
        }
        if (closedLbl) { closedLbl.style.display = isClosed ? '' : 'none'; }
    }

    function initRow(row) {
        var closedEl = row.querySelector('.jb-closed-chk');
        if (closedEl) {
            closedEl.addEventListener('change', function () { applyClosedState(row); save(); });
            applyClosedState(row);
        }
        row.querySelectorAll('input[type="text"]').forEach(function (inp) {
            inp.addEventListener('blur',   function () { this.value = normaliseTime(this.value); save(); });
            inp.addEventListener('change', save);
        });
    }

    function switchMode(newMode) {
        if (newMode === mode) { return; }
        var compactTbody    = document.getElementById(id + '_tbody_compact');
        var individualTbody = document.getElementById(id + '_tbody_individual');
        var btnCompact      = document.getElementById(id + '_btn_compact');
        var btnIndividual   = document.getElementById(id + '_btn_individual');

        if (newMode === 'individual') {
            // Copy compact weekday values to all 5 individual rows
            var wk = getRowValues('weekdays');
            WEEKDAY_KEYS.forEach(function (d) { setRowValues(d, wk); });
            if (compactTbody)    { compactTbody.style.display    = 'none'; }
            if (individualTbody) { individualTbody.style.display = ''; }
            if (btnCompact)      { btnCompact.className    = btnCompact.className.replace('btn-secondary','btn-outline-secondary'); }
            if (btnIndividual)   { btnIndividual.className = btnIndividual.className.replace('btn-outline-secondary','btn-secondary'); }
        } else {
            // Copy Monday's individual values to compact weekday row
            setRowValues('weekdays', getRowValues('mon'));
            if (individualTbody) { individualTbody.style.display = 'none'; }
            if (compactTbody)    { compactTbody.style.display    = ''; }
            if (btnIndividual)   { btnIndividual.className = btnIndividual.className.replace('btn-secondary','btn-outline-secondary'); }
            if (btnCompact)      { btnCompact.className    = btnCompact.className.replace('btn-outline-secondary','btn-secondary'); }
        }

        mode = newMode;
        save();
    }

    function init() {
        var widget = document.getElementById(id + '_widget');
        if (!widget) { return; }

        widget.querySelectorAll('tr[data-day]').forEach(initRow);

        var btnCompact    = document.getElementById(id + '_btn_compact');
        var btnIndividual = document.getElementById(id + '_btn_individual');
        if (btnCompact)    { btnCompact.addEventListener('click',    function () { switchMode('compact'); }); }
        if (btnIndividual) { btnIndividual.addEventListener('click', function () { switchMode('individual'); }); }

        var form = widget.closest('form');
        if (form) { form.addEventListener('submit', save, true); }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
JS;
    }
}
