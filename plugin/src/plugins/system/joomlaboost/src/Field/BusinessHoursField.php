<?php

/**
 * AI Boost for Joomla - Business Hours Field
 *
 * Compact 3-row widget: Mon–Fri (shared), Saturday, Sunday.
 * Each row: Closed toggle | Open time | Close time.
 * Internally expands Mon–Fri to all 5 weekdays in the stored JSON so that
 * SchemaService continues to receive the full 7-day structure.
 *
 * Stored JSON (7 days, backward-compatible):
 *   { "mon": {"open":"09:00","close":"17:00","closed":false}, ... }
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

    /** Weekday keys that share a single UI row */
    private const WEEKDAYS = ['mon', 'tue', 'wed', 'thu', 'fri'];

    private const DEFAULTS = [
        'weekdays' => ['open' => '09:00', 'close' => '17:00', 'closed' => false],
        'sat'      => ['open' => '09:00', 'close' => '13:00', 'closed' => true],
        'sun'      => ['open' => '10:00', 'close' => '14:00', 'closed' => true],
    ];

    protected function getInput(): string
    {
        $schedule = $this->parseValue();   // keys: weekdays, sat, sun
        $id       = $this->id;
        $name     = $this->name;
        $jsonVal  = htmlspecialchars(
            json_encode($this->expandToFull($schedule), JSON_UNESCAPED_UNICODE),
            ENT_QUOTES,
            'UTF-8'
        );

        $rows = [
            'weekdays' => 'Mon – Fri',
            'sat'      => 'Saturday',
            'sun'      => 'Sunday',
        ];

        $inputStyle = 'width:64px;min-width:0;font-variant-numeric:tabular-nums;text-align:center;';
        $timeAttrs  = 'type="text" class="form-control form-control-sm" maxlength="5" pattern="[0-2][0-9]:[0-5][0-9]"'
                    . ' style="' . $inputStyle . '"';

        $html = '';
        foreach ($rows as $key => $label) {
            $day      = $schedule[$key] ?? self::DEFAULTS[$key];
            $closed   = !empty($day['closed']);
            $open     = htmlspecialchars((string) ($day['open']  ?? ''), ENT_QUOTES, 'UTF-8');
            $close    = htmlspecialchars((string) ($day['close'] ?? ''), ENT_QUOTES, 'UTF-8');

            $closedChk     = $closed ? ' checked' : '';
            $disAttr       = $closed ? ' disabled' : '';
            $openFldsDisp  = $closed ? 'display:none;' : '';
            $closedLblDisp = $closed ? '' : 'display:none;';

            $idRow = $id . '_' . $key;

            $html .= '<tr data-day="' . $key . '" class="jb-hours-row">' . "\n";

            // Day label
            $html .= '  <td class="fw-semibold" style="white-space:nowrap;vertical-align:middle;padding:5px 8px;width:86px;">' . $label . '</td>' . "\n";

            // Closed toggle
            $html .= '  <td style="width:44px;text-align:center;vertical-align:middle;padding:5px 2px;">'
                   . '<div class="form-check form-switch d-flex justify-content-center mb-0">'
                   . '<input class="form-check-input jb-closed-chk" type="checkbox" id="' . $idRow . '_closed"'
                   . ' value="1"' . $closedChk . ' title="Mark as closed" style="cursor:pointer;">'
                   . '</div></td>' . "\n";

            // Hours
            $html .= '  <td style="vertical-align:middle;padding:5px 8px;">' . "\n";
            $html .= '    <div class="jb-open-fields" style="display:flex;align-items:center;gap:5px;' . $openFldsDisp . '">' . "\n";
            $html .= '      <input ' . $timeAttrs . ' class="jb-open" id="' . $idRow . '_open"'
                   . ' value="' . $open . '" placeholder="09:00"' . $disAttr . '>' . "\n";
            $html .= '      <span style="opacity:0.45;font-size:0.9em;">–</span>' . "\n";
            $html .= '      <input ' . $timeAttrs . ' class="jb-close" id="' . $idRow . '_close"'
                   . ' value="' . $close . '" placeholder="17:00"' . $disAttr . '>' . "\n";
            $html .= '    </div>' . "\n";
            $html .= '    <div class="jb-closed-label text-muted small fst-italic" style="' . $closedLblDisp . '">Closed</div>' . "\n";
            $html .= '  </td>' . "\n";
            $html .= '</tr>' . "\n";
        }

        $script = $this->buildScript($id);

        return '<div class="jb-business-hours" id="' . $id . '_widget" style="width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;">'
            . '<input type="hidden" id="' . $id . '" name="' . $name . '" value="' . $jsonVal . '">'
            . '<table class="table table-sm table-bordered mb-0" style="font-size:0.9em;min-width:280px;max-width:420px;">'
            . '<thead><tr>'
            . '<th style="width:86px;">Day</th>'
            . '<th style="width:44px;text-align:center;">Closed</th>'
            . '<th>Hours <span class="text-muted fw-normal" style="font-size:0.8em;">(24h)</span></th>'
            . '</tr></thead>'
            . '<tbody>' . $html . '</tbody>'
            . '</table>'
            . '</div>'
            . $script;
    }

    /**
     * Parse stored 7-day JSON into the 3-group structure (weekdays, sat, sun).
     * Uses Monday as the representative for Mon–Fri.
     *
     * @return array<string, array<string, string|bool>>
     */
    private function parseValue(): array
    {
        $raw     = trim((string) $this->value);
        $decoded = ($raw !== '' && $raw !== '{}') ? json_decode($raw, true) : null;

        $get = function (string $abbr) use ($decoded): array {
            if (is_array($decoded) && isset($decoded[$abbr]) && is_array($decoded[$abbr])) {
                $d = $decoded[$abbr];
                return [
                    'open'   => (string) ($d['open']  ?? ''),
                    'close'  => (string) ($d['close'] ?? ''),
                    'closed' => !empty($d['closed']),
                ];
            }
            return self::DEFAULTS['weekdays'];
        };

        return [
            'weekdays' => isset($decoded['mon']) ? $get('mon') : self::DEFAULTS['weekdays'],
            'sat'      => $get('sat') + self::DEFAULTS['sat'],
            'sun'      => $get('sun') + self::DEFAULTS['sun'],
        ];
    }

    /**
     * Expand 3-group schedule to full 7-day JSON for SchemaService.
     *
     * @param array<string, array<string, string|bool>> $schedule
     * @return array<string, array<string, string|bool>>
     */
    private function expandToFull(array $schedule): array
    {
        $wk  = $schedule['weekdays'] ?? self::DEFAULTS['weekdays'];
        $sat = $schedule['sat']      ?? self::DEFAULTS['sat'];
        $sun = $schedule['sun']      ?? self::DEFAULTS['sun'];

        $full = [];
        foreach (self::WEEKDAYS as $abbr) {
            $full[$abbr] = ['open' => $wk['open'], 'close' => $wk['close'], 'open2' => '', 'close2' => '', 'closed' => $wk['closed']];
        }
        $full['sat'] = ['open' => $sat['open'], 'close' => $sat['close'], 'open2' => '', 'close2' => '', 'closed' => $sat['closed']];
        $full['sun'] = ['open' => $sun['open'], 'close' => $sun['close'], 'open2' => '', 'close2' => '', 'closed' => $sun['closed']];

        return $full;
    }

    private function buildScript(string $id): string
    {
        $jsId = json_encode($id, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

        return <<<JS
<script>
(function () {
    var GROUPS   = ['weekdays', 'sat', 'sun'];
    var WEEKDAYS = ['mon', 'tue', 'wed', 'thu', 'fri'];
    var id = {$jsId};

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

    function collect() {
        var out = {};
        GROUPS.forEach(function (group) {
            var closed = document.getElementById(id + '_' + group + '_closed');
            var open   = document.getElementById(id + '_' + group + '_open');
            var close  = document.getElementById(id + '_' + group + '_close');
            if (!closed) { return; }
            var entry = {
                open:   open   ? open.value   : '',
                close:  close  ? close.value  : '',
                open2:  '',
                close2: '',
                closed: closed.checked
            };
            if (group === 'weekdays') {
                WEEKDAYS.forEach(function (day) { out[day] = entry; });
            } else {
                out[group] = entry;
            }
        });
        return out;
    }

    function save() {
        var hidden = document.getElementById(id);
        if (hidden) { hidden.value = JSON.stringify(collect()); }
    }

    function initRow(row) {
        var closedEl  = row.querySelector('.jb-closed-chk');
        var openFlds  = row.querySelector('.jb-open-fields');
        var closedLbl = row.querySelector('.jb-closed-label');

        function applyClosedState() {
            var isClosed = closedEl.checked;
            if (openFlds) {
                openFlds.querySelectorAll('input').forEach(function (el) { el.disabled = isClosed; });
                openFlds.style.display = isClosed ? 'none' : 'flex';
            }
            if (closedLbl) { closedLbl.style.display = isClosed ? '' : 'none'; }
        }

        if (closedEl) {
            closedEl.addEventListener('change', function () { applyClosedState(); save(); });
            applyClosedState();
        }

        row.querySelectorAll('input[type="text"]').forEach(function (inp) {
            inp.addEventListener('blur', function () { this.value = normaliseTime(this.value); save(); });
            inp.addEventListener('change', save);
        });
    }

    function init() {
        var widget = document.getElementById(id + '_widget');
        if (!widget) { return; }
        widget.querySelectorAll('tr[data-day]').forEach(initRow);
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
