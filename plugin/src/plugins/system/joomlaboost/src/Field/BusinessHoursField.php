<?php

/**
 * AI Boost for Joomla - Business Hours Field
 *
 * Compact JS widget: 7-row table (Mon–Sun) with Closed toggle, open/close
 * inputs, and optional break slot — all inline per row. Data stored as JSON.
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

    private const DEFAULTS = [
        'mon' => ['open' => '09:00', 'close' => '17:00', 'open2' => '', 'close2' => '', 'closed' => false],
        'tue' => ['open' => '09:00', 'close' => '17:00', 'open2' => '', 'close2' => '', 'closed' => false],
        'wed' => ['open' => '09:00', 'close' => '17:00', 'open2' => '', 'close2' => '', 'closed' => false],
        'thu' => ['open' => '09:00', 'close' => '17:00', 'open2' => '', 'close2' => '', 'closed' => false],
        'fri' => ['open' => '09:00', 'close' => '17:00', 'open2' => '', 'close2' => '', 'closed' => false],
        'sat' => ['open' => '09:00', 'close' => '13:00', 'open2' => '', 'close2' => '', 'closed' => true],
        'sun' => ['open' => '10:00', 'close' => '14:00', 'open2' => '', 'close2' => '', 'closed' => true],
    ];

    private const DAY_LABELS = [
        'mon' => 'Monday',
        'tue' => 'Tuesday',
        'wed' => 'Wednesday',
        'thu' => 'Thursday',
        'fri' => 'Friday',
        'sat' => 'Saturday',
        'sun' => 'Sunday',
    ];

    protected function getInput(): string
    {
        $schedule = $this->parseValue();
        $id       = $this->id;
        $name     = $this->name;
        $jsonVal  = htmlspecialchars(
            json_encode($schedule, JSON_UNESCAPED_UNICODE),
            ENT_QUOTES,
            'UTF-8'
        );

        $rows = '';
        $groupHeaderStyle = 'font-size:0.75em;text-transform:uppercase;letter-spacing:0.06em;opacity:0.55;padding:6px 8px 3px;border-bottom:none;';

        // Weekdays group header
        $rows .= '<tr><td colspan="3" style="' . $groupHeaderStyle . '">Weekdays</td></tr>' . "\n";

        foreach (self::DAY_LABELS as $abbr => $label) {

            // Weekend group header before Saturday
            if ($abbr === 'sat') {
                $rows .= '<tr><td colspan="3" style="' . $groupHeaderStyle . 'border-top:2px solid rgba(128,128,128,0.25);">Weekend</td></tr>' . "\n";
            }

            $day     = $schedule[$abbr] ?? self::DEFAULTS[$abbr];
            $closed  = !empty($day['closed']);
            $open    = htmlspecialchars((string) ($day['open']   ?? ''), ENT_QUOTES, 'UTF-8');
            $close   = htmlspecialchars((string) ($day['close']  ?? ''), ENT_QUOTES, 'UTF-8');
            $open2   = htmlspecialchars((string) ($day['open2']  ?? ''), ENT_QUOTES, 'UTF-8');
            $close2  = htmlspecialchars((string) ($day['close2'] ?? ''), ENT_QUOTES, 'UTF-8');
            $hasSplit = $open2 !== '' && $close2 !== '';

            $disAttr       = $closed ? ' disabled' : '';
            $closedChk     = $closed ? ' checked' : '';
            $splitVis      = $hasSplit ? '' : 'display:none;';
            $closedLblDisp = $closed   ? '' : 'display:none;';
            $openFldsDisp  = $closed   ? 'display:none;' : '';

            $idDay = $id . '_' . $abbr;

            $inputStyle = 'width:72px;font-variant-numeric:tabular-nums;text-align:center;';
            $timeInput  = 'type="text" class="form-control form-control-sm" maxlength="5" pattern="[0-2][0-9]:[0-5][0-9]" style="' . $inputStyle . '"';

            $rows .= '<tr data-day="' . $abbr . '" class="jb-hours-row">' . "\n";

            // Day name
            $rows .= '  <td class="fw-semibold" style="width:88px;white-space:nowrap;vertical-align:middle;padding:5px 8px;">' . $label . '</td>' . "\n";

            // Closed toggle
            $rows .= '  <td style="width:52px;text-align:center;vertical-align:middle;padding:5px 4px;">' . "\n";
            $rows .= '    <div class="form-check form-switch d-flex justify-content-center mb-0">' . "\n";
            $rows .= '      <input class="form-check-input jb-closed-chk" type="checkbox" id="' . $idDay . '_closed" value="1"' . $closedChk . ' title="Closed all day" style="cursor:pointer;">' . "\n";
            $rows .= '    </div>' . "\n";
            $rows .= '  </td>' . "\n";

            // Hours — all inline
            $rows .= '  <td style="vertical-align:middle;padding:5px 8px;">' . "\n";

            // Open fields wrapper
            $rows .= '    <div class="jb-open-fields d-flex align-items-center gap-2 flex-wrap" style="' . $openFldsDisp . '">' . "\n";

            // Primary slot
            $rows .= '      <input ' . $timeInput . ' class="jb-open" id="' . $idDay . '_open" value="' . $open . '" placeholder="09:00"' . $disAttr . '>' . "\n";
            $rows .= '      <span class="text-muted" style="font-size:0.9em;">–</span>' . "\n";
            $rows .= '      <input ' . $timeInput . ' class="jb-close" id="' . $idDay . '_close" value="' . $close . '" placeholder="17:00"' . $disAttr . '>' . "\n";

            // Add-break button (hidden when split is active)
            $rows .= '      <button type="button" class="btn btn-sm jb-split-btn" style="font-size:0.75em;padding:2px 9px;border-radius:20px;background:rgba(13,110,253,0.1);color:#0d6efd;border:1px solid rgba(13,110,253,0.3);" title="Add a lunch break or second working slot"' . $disAttr . ($hasSplit ? ' style="display:none;"' : '') . '>+ Add break</button>' . "\n";

            // Break slot (inline, shown when active)
            $rows .= '      <span class="jb-split-row d-flex align-items-center gap-2" style="' . $splitVis . '">' . "\n";
            $rows .= '        <span class="text-muted" style="font-size:0.75em;white-space:nowrap;">Break:</span>' . "\n";
            $rows .= '        <input ' . $timeInput . ' class="jb-open2" id="' . $idDay . '_open2" value="' . $open2 . '" placeholder="12:00"' . $disAttr . '>' . "\n";
            $rows .= '        <span class="text-muted" style="font-size:0.9em;">–</span>' . "\n";
            $rows .= '        <input ' . $timeInput . ' class="jb-close2" id="' . $idDay . '_close2" value="' . $close2 . '" placeholder="13:00"' . $disAttr . '>' . "\n";
            $rows .= '        <button type="button" class="jb-remove-split" title="Remove break" style="background:none;border:none;color:#dc3545;cursor:pointer;font-size:1em;padding:0 2px;line-height:1;" tabindex="-1">✕</button>' . "\n";
            $rows .= '      </span>' . "\n";

            $rows .= '    </div>' . "\n";
            $rows .= '    <div class="jb-closed-label text-muted small fst-italic" style="' . $closedLblDisp . '">Closed</div>' . "\n";
            $rows .= '  </td>' . "\n";
            $rows .= '</tr>' . "\n";
        }

        $script = $this->buildScript($id);

        return '<div class="jb-business-hours" id="' . $id . '_widget" style="max-width:640px;">'
            . '<input type="hidden" id="' . $id . '" name="' . $name . '" value="' . $jsonVal . '">'
            . '<table class="table table-sm table-bordered mb-0" style="font-size:0.9em;">'
            . '<thead><tr>'
            . '<th style="width:88px;">Day</th>'
            . '<th style="width:52px;text-align:center;" title="Toggle to mark day as closed">Closed</th>'
            . '<th>Hours <span class="text-muted fw-normal" style="font-size:0.8em;">(24h — e.g. 09:00)</span></th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table>'
            . '</div>'
            . $script;
    }

    /**
     * @return array<string, array<string, string|bool>>
     */
    private function parseValue(): array
    {
        $raw = trim((string) $this->value);
        if ($raw === '' || $raw === '{}') {
            return self::DEFAULTS;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return self::DEFAULTS;
        }

        $result = [];
        foreach (self::DAY_LABELS as $abbr => $label) {
            if (isset($decoded[$abbr]) && is_array($decoded[$abbr])) {
                $d             = $decoded[$abbr];
                $result[$abbr] = [
                    'open'   => (string) ($d['open']   ?? ''),
                    'close'  => (string) ($d['close']  ?? ''),
                    'open2'  => (string) ($d['open2']  ?? ''),
                    'close2' => (string) ($d['close2'] ?? ''),
                    'closed' => !empty($d['closed']),
                ];
            } else {
                $result[$abbr] = self::DEFAULTS[$abbr];
            }
        }

        return $result;
    }

    private function buildScript(string $id): string
    {
        $jsId = json_encode($id, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

        return <<<JS
<script>
(function () {
    var DAYS = ['mon','tue','wed','thu','fri','sat','sun'];
    var id   = {$jsId};

    function normaliseTime(val) {
        val = val.replace(/[^\d:]/g, '').trim();
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
        DAYS.forEach(function (day) {
            var closed = document.getElementById(id + '_' + day + '_closed');
            var open   = document.getElementById(id + '_' + day + '_open');
            var close  = document.getElementById(id + '_' + day + '_close');
            var open2  = document.getElementById(id + '_' + day + '_open2');
            var close2 = document.getElementById(id + '_' + day + '_close2');
            if (!closed) { return; }
            out[day] = {
                open:   open   ? open.value   : '',
                close:  close  ? close.value  : '',
                open2:  open2  ? open2.value  : '',
                close2: close2 ? close2.value : '',
                closed: closed.checked
            };
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
        var splitBtn  = row.querySelector('.jb-split-btn');
        var splitRow  = row.querySelector('.jb-split-row');
        var removeBtn = row.querySelector('.jb-remove-split');

        function applyClosedState() {
            var isClosed = closedEl.checked;
            if (openFlds) {
                openFlds.querySelectorAll('input, button').forEach(function (el) {
                    el.disabled = isClosed;
                });
                openFlds.style.display = isClosed ? 'none' : '';
            }
            if (closedLbl) { closedLbl.style.display = isClosed ? '' : 'none'; }
        }

        if (closedEl) {
            closedEl.addEventListener('change', function () {
                applyClosedState();
                save();
            });
            applyClosedState();
        }

        if (splitBtn && splitRow) {
            splitBtn.addEventListener('click', function () {
                splitRow.style.display = '';
                splitBtn.style.display = 'none';
                save();
            });
        }

        if (removeBtn && splitRow) {
            removeBtn.addEventListener('click', function () {
                splitRow.style.display = 'none';
                if (splitBtn) { splitBtn.style.display = ''; }
                var open2  = splitRow.querySelector('.jb-open2');
                var close2 = splitRow.querySelector('.jb-close2');
                if (open2)  { open2.value  = ''; }
                if (close2) { close2.value = ''; }
                save();
            });
        }

        row.querySelectorAll('input[type="text"]').forEach(function (inp) {
            inp.addEventListener('blur', function () {
                this.value = normaliseTime(this.value);
                save();
            });
            inp.addEventListener('change', save);
        });
    }

    function init() {
        var widget = document.getElementById(id + '_widget');
        if (!widget) { return; }
        widget.querySelectorAll('tr[data-day]').forEach(initRow);

        var form = widget.closest('form');
        if (form) {
            form.addEventListener('submit', save, true);
        }
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
