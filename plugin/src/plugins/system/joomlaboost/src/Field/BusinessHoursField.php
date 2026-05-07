<?php

/**
 * JoomlaBoost - Business Hours Field
 *
 * Compact JS widget that replaces 35 individual day-by-day XML fields with a
 * single 7-row table (Mon–Sun). Each row has: Closed checkbox | Open time |
 * Close time | optional split-shift second slot. All data is serialised as a
 * single JSON string stored in one hidden <input>.
 *
 * JSON format (saved to param `schema_business_hours`):
 *   {
 *     "mon": {"open":"09:00","close":"17:00","open2":"","close2":"","closed":false},
 *     ...
 *     "sat": {"open":"09:00","close":"13:00","open2":"","close2":"","closed":true},
 *     "sun": {"open":"10:00","close":"14:00","open2":"","close2":"","closed":true}
 *   }
 *
 * @copyright   (C) 2025 JoomlaBoost Team
 * @license     GNU General Public License version 2 or later
 */

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Field;

use Joomla\CMS\Form\FormField;

defined('_JEXEC') or die;

class BusinessHoursField extends FormField
{
    protected $type = 'BusinessHours';

    /** @var array<string, array<string, string|bool>> Default hours for a typical Mon–Fri business */
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
        foreach (self::DAY_LABELS as $abbr => $label) {
            $day     = $schedule[$abbr] ?? self::DEFAULTS[$abbr];
            $closed  = !empty($day['closed']);
            $open    = htmlspecialchars((string) ($day['open']   ?? ''), ENT_QUOTES, 'UTF-8');
            $close   = htmlspecialchars((string) ($day['close']  ?? ''), ENT_QUOTES, 'UTF-8');
            $open2   = htmlspecialchars((string) ($day['open2']  ?? ''), ENT_QUOTES, 'UTF-8');
            $close2  = htmlspecialchars((string) ($day['close2'] ?? ''), ENT_QUOTES, 'UTF-8');
            $hasSplit = $open2 !== '' && $close2 !== '';

            // Pre-compute values that cannot be interpolated as method calls in heredoc
            $disAttr       = $closed ? ' disabled' : '';
            $closedChk     = $closed ? ' checked' : '';
            $splitVis      = $hasSplit ? '' : ' style="display:none;"';
            $splitBtnLbl   = $hasSplit ? '&minus; Split' : '&plus; Split';
            $closedLblDisp = $closed   ? '' : 'display:none;';
            $openFldsDisp  = $closed   ? 'display:none;' : '';

            $idMon    = $id . '_' . $abbr;

            $rows .= '<tr data-day="' . $abbr . '" class="jb-hours-row">' . "\n";
            $rows .= '  <td class="jb-day-label fw-semibold" style="width:90px;white-space:nowrap;">' . $label . '</td>' . "\n";
            $rows .= '  <td style="width:70px;text-align:center;">' . "\n";
            $rows .= '    <div class="form-check form-switch d-flex justify-content-center mb-0">' . "\n";
            $rows .= '      <input class="form-check-input jb-closed-chk" type="checkbox" id="' . $idMon . '_closed" value="1"' . $closedChk . ' title="Closed all day">' . "\n";
            $rows .= '    </div>' . "\n";
            $rows .= '  </td>' . "\n";
            $rows .= '  <td>' . "\n";
            $rows .= '    <div class="jb-open-fields" style="' . $openFldsDisp . '">' . "\n";
            $rows .= '      <div class="d-flex align-items-center gap-2 flex-wrap">' . "\n";
            $rows .= '        <input type="time" class="form-control form-control-sm jb-open" id="' . $idMon . '_open" value="' . $open . '" style="width:110px;"' . $disAttr . '>' . "\n";
            $rows .= '        <span class="text-muted jb-separator">&ndash;</span>' . "\n";
            $rows .= '        <input type="time" class="form-control form-control-sm jb-close" id="' . $idMon . '_close" value="' . $close . '" style="width:110px;"' . $disAttr . '>' . "\n";
            $rows .= '        <button type="button" class="btn btn-sm btn-outline-secondary jb-split-btn" style="font-size:0.75em;padding:2px 7px;"' . $disAttr . '>' . $splitBtnLbl . '</button>' . "\n";
            $rows .= '      </div>' . "\n";
            $rows .= '      <div class="d-flex align-items-center gap-2 flex-wrap mt-1 jb-split-row"' . $splitVis . '>' . "\n";
            $rows .= '        <input type="time" class="form-control form-control-sm jb-open2" id="' . $idMon . '_open2" value="' . $open2 . '" style="width:110px;"' . $disAttr . '>' . "\n";
            $rows .= '        <span class="text-muted">&ndash;</span>' . "\n";
            $rows .= '        <input type="time" class="form-control form-control-sm jb-close2" id="' . $idMon . '_close2" value="' . $close2 . '" style="width:110px;"' . $disAttr . '>' . "\n";
            $rows .= '        <span class="text-muted small" style="font-size:0.75em;">(2nd slot)</span>' . "\n";
            $rows .= '      </div>' . "\n";
            $rows .= '    </div>' . "\n";
            $rows .= '    <div class="jb-closed-label text-muted small fst-italic" style="padding:4px 0;' . $closedLblDisp . '">Closed</div>' . "\n";
            $rows .= '  </td>' . "\n";
            $rows .= '</tr>' . "\n";
        }

        $script = $this->buildScript($id);

        return '<div class="jb-business-hours" id="' . $id . '_widget" style="max-width:600px;">'
            . '<input type="hidden" id="' . $id . '" name="' . $name . '" value="' . $jsonVal . '">'
            . '<table class="table table-sm table-bordered mb-0" style="font-size:0.9em;">'
            . '<thead><tr class="table-light">'
            . '<th style="width:90px;">Day</th>'
            . '<th style="width:70px;text-align:center;">Closed</th>'
            . '<th>Hours</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table>'
            . '<div class="small text-muted mt-1">Use the <strong>+ Split</strong> button to add a second time slot (e.g. lunch break).</div>'
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
                $d            = $decoded[$abbr];
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
        // Pre-compute JS-safe string so no method call is needed inside the heredoc
        $jsId = json_encode($id, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

        return <<<JS
<script>
(function () {
    var DAYS = ['mon','tue','wed','thu','fri','sat','sun'];
    var id   = {$jsId};

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
        var day       = row.getAttribute('data-day');
        var closedEl  = row.querySelector('.jb-closed-chk');
        var openFlds  = row.querySelector('.jb-open-fields');
        var closedLbl = row.querySelector('.jb-closed-label');
        var splitBtn  = row.querySelector('.jb-split-btn');
        var splitRow  = row.querySelector('.jb-split-row');

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
                if (splitRow.style.display === 'none') {
                    splitRow.style.display = '';
                    splitBtn.innerHTML = '&minus; Split';
                } else {
                    splitRow.style.display = 'none';
                    splitBtn.innerHTML = '&plus; Split';
                    var open2  = splitRow.querySelector('.jb-open2');
                    var close2 = splitRow.querySelector('.jb-close2');
                    if (open2)  { open2.value  = ''; }
                    if (close2) { close2.value = ''; }
                }
                save();
            });
        }

        row.querySelectorAll('input[type="time"]').forEach(function (inp) {
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
