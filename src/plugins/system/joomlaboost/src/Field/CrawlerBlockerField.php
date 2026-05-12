<?php

/**
 * AI Boost for Joomla - AI Crawler Blocker Field
 *
 * Compact toggle widget identical in style to BusinessHoursField.
 *   Mode "all"    — All crawlers allowed (default, table hidden).
 *   Mode "custom" — Table with per-bot Allow/Block toggle switches.
 *
 * Stored JSON: {"gptbot":1,"claudebot":0, ...}  1 = Allow, 0 = Block.
 * RobotService reads crawler_rules param and expands to ai_allow_* map.
 *
 * @copyright   (C) 2025 AI Boost Team (aiboostnow.com)
 * @license     GNU General Public License version 2 or later
 */

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Field;

use Joomla\CMS\Form\FormField;

defined('_JEXEC') or die;

class CrawlerBlockerField extends FormField
{
    protected $type = 'CrawlerBlocker';

    /** @var array<string,array{label:string,desc:string,group:string}> */
    private const BOTS = [
        'gptbot'       => ['label' => 'GPTBot',          'desc' => 'OpenAI / ChatGPT',              'group' => 'ai'],
        'oaisearchbot' => ['label' => 'OAI-SearchBot',   'desc' => 'OpenAI Search',                  'group' => 'ai'],
        'claudebot'    => ['label' => 'ClaudeBot',       'desc' => 'Anthropic Claude',               'group' => 'ai'],
        'anthropicai'  => ['label' => 'anthropic-ai',    'desc' => 'Anthropic (secondary)',           'group' => 'ai'],
        'perplexity'   => ['label' => 'PerplexityBot',   'desc' => 'Perplexity AI',                  'group' => 'ai'],
        'googleext'    => ['label' => 'Google-Extended', 'desc' => 'Google Gemini / AI Overviews',   'group' => 'ai'],
        'cohereai'     => ['label' => 'cohere-ai',       'desc' => 'Cohere AI',                      'group' => 'ai'],
        'facebookbot'  => ['label' => 'FacebookBot',     'desc' => 'Meta AI (Llama)',                'group' => 'ai'],
        'amazonbot'    => ['label' => 'Amazonbot',       'desc' => 'Amazon Alexa / AI',              'group' => 'ai'],
        'applebot'     => ['label' => 'Applebot',        'desc' => 'Apple Siri & Spotlight',         'group' => 'ai'],
        'ccbot'        => ['label' => 'CCBot',           'desc' => 'Common Crawl (AI training)',     'group' => 'ai'],
        'youbot'       => ['label' => 'YouBot',          'desc' => 'You.com AI Search',              'group' => 'ai'],
        'timpibot'     => ['label' => 'Timpibot',        'desc' => 'Timpi AI',                       'group' => 'ai'],
        'bytespider'   => ['label' => 'Bytespider',      'desc' => 'ByteDance / TikTok AI',          'group' => 'ai'],
        'duckassist'   => ['label' => 'DuckAssistBot',   'desc' => 'DuckDuckGo AI',                  'group' => 'ai'],
        'semrush'      => ['label' => 'SemrushBot',      'desc' => 'Semrush',                        'group' => 'seo'],
        'ahrefs'       => ['label' => 'AhrefsBot',       'desc' => 'Ahrefs',                         'group' => 'seo'],
        'mj12bot'      => ['label' => 'MJ12bot',         'desc' => 'Majestic',                       'group' => 'seo'],
        'dotbot'       => ['label' => 'DotBot',          'desc' => 'Moz',                            'group' => 'seo'],
        'dataforseo'   => ['label' => 'DataForSeoBot',   'desc' => 'DataForSEO',                     'group' => 'seo'],
        'diffbot'      => ['label' => 'Diffbot',         'desc' => 'Diffbot AI',                     'group' => 'seo'],
        'yandexbot'    => ['label' => 'YandexBot',       'desc' => 'Yandex Search',                  'group' => 'seo'],
        'omgili'       => ['label' => 'omgili',          'desc' => 'Webhose.io',                     'group' => 'seo'],
        'ia_archiver'  => ['label' => 'ia_archiver',     'desc' => 'Internet Archive / Wayback',     'group' => 'seo'],
        'scrapy'       => ['label' => 'Scrapy',          'desc' => 'Python scraping framework',      'group' => 'seo'],
        'kangaroo'     => ['label' => 'KangoorooBot',    'desc' => 'Kangaroo',                       'group' => 'seo'],
    ];

    protected function getInput(): string
    {
        $values  = $this->parseValue();
        $isAll   = $this->isAllAllowed($values);
        $id      = $this->id;
        $name    = $this->name;
        $jsonVal = htmlspecialchars(
            json_encode($values, JSON_UNESCAPED_UNICODE),
            ENT_QUOTES,
            'UTF-8'
        );

        // ── Toggle bar ─────────────────────────────────────────────────────────
        $btnActive   = 'btn btn-secondary btn-sm';
        $btnInactive = 'btn btn-outline-secondary btn-sm';
        $toggleBar = '<div class="d-flex gap-2 mb-2 align-items-center">'
            . '<span class="text-muted small" style="white-space:nowrap;">Block crawlers?</span>'
            . '<div class="btn-group btn-group-sm" role="group" id="' . $id . '_modebtns">'
            . '<button type="button" id="' . $id . '_btn_all"'
            . ' class="' . ($isAll ? $btnActive : $btnInactive) . '">All allowed</button>'
            . '<button type="button" id="' . $id . '_btn_custom"'
            . ' class="' . (!$isAll ? $btnActive : $btnInactive) . '">Custom</button>'
            . '</div>'
            . '</div>';

        // ── Table ──────────────────────────────────────────────────────────────
        $tableDisplay = $isAll ? 'display:none;' : '';
        $tbody        = '';
        $lastGroup    = '';

        foreach (self::BOTS as $key => $bot) {
            if ($bot['group'] !== $lastGroup) {
                $groupLabel = $bot['group'] === 'ai' ? '🤖 AI Crawlers' : '🔍 SEO Tools';
                $tbody .= '<tr class="jb-crawler-group-header">'
                    . '<td colspan="2" style="padding:4px 8px 2px;font-size:0.75em;'
                    . 'text-transform:uppercase;letter-spacing:.05em;'
                    . 'color:#6c757d;border-bottom:none;background:transparent;">'
                    . $groupLabel . '</td></tr>' . "\n";
                $lastGroup = $bot['group'];
            }

            $allowed = (int) ($values[$key] ?? 1);
            $checked = $allowed ? ' checked' : '';   // ON = allowed (plav), OFF = blokiran

            $tbody .= '<tr data-bot="' . $key . '" class="jb-crawler-row">' . "\n";
            $tbody .= '  <td class="fw-semibold" style="white-space:nowrap;vertical-align:middle;'
                . 'padding:4px 8px;font-size:0.9em;">'
                . htmlspecialchars($bot['label'], ENT_QUOTES, 'UTF-8')
                . '<span class="text-muted fw-normal" style="font-size:0.8em;margin-left:5px;">'
                . htmlspecialchars($bot['desc'], ENT_QUOTES, 'UTF-8')
                . '</span></td>' . "\n";
            $tbody .= '  <td style="text-align:center;vertical-align:middle;padding:4px 8px;">'
                . '<div class="form-check form-switch d-flex justify-content-center mb-0">'
                . '<input class="form-check-input jb-crawler-chk" type="checkbox"'
                . ' id="' . $id . '_' . $key . '" value="1"' . $checked
                . ' title="Allow this crawler" style="cursor:pointer;">'
                . '</div></td>' . "\n";
            $tbody .= '</tr>' . "\n";
        }

        $table = '<table class="table table-sm table-bordered mb-0"'
            . ' style="font-size:0.9em;min-width:280px;max-width:520px;">'
            . '<thead><tr>'
            . '<th>Crawler / Bot</th>'
            . '<th style="width:60px;text-align:center;">Allow</th>'
            . '</tr></thead>'
            . '<tbody>' . $tbody . '</tbody>'
            . '</table>';

        $script = $this->buildScript($id, $isAll ? 'all' : 'custom');

        return '<div class="jb-crawler-blocker" id="' . $id . '_widget"'
            . ' style="width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;">'
            . '<input type="hidden" id="' . $id . '" name="' . $name . '" value="' . $jsonVal . '">'
            . $toggleBar
            . '<div id="' . $id . '_table_wrap" style="' . $tableDisplay . '">'
            . $table
            . '</div>'
            . '</div>'
            . $script;
    }

    /** @return array<string,int> */
    private function parseValue(): array
    {
        $raw     = trim((string) $this->value);
        $decoded = ($raw !== '' && $raw !== '{}') ? json_decode($raw, true) : null;
        $out     = [];
        foreach (self::BOTS as $key => $_) {
            $out[$key] = (is_array($decoded) && array_key_exists($key, $decoded))
                ? (int) $decoded[$key]
                : 1;
        }
        return $out;
    }

    /** @param array<string,int> $values */
    private function isAllAllowed(array $values): bool
    {
        foreach ($values as $v) {
            if ($v === 0) {
                return false;
            }
        }
        return true;
    }

    private function buildScript(string $id, string $initMode): string
    {
        $jsId   = json_encode($id,       JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        $jsMode = json_encode($initMode, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        $keys   = json_encode(array_keys(self::BOTS));

        return <<<JS
<script>
(function () {
    var id   = {$jsId};
    var mode = {$jsMode};
    var KEYS = {$keys};

    function collect() {
        var out = {};
        KEYS.forEach(function (k) {
            var el = document.getElementById(id + '_' + k);
            out[k] = (el && el.checked) ? 1 : 0;  // ON (checked, plav) = 1 (Allow), OFF = 0 (Block)
        });
        return out;
    }

    function save() {
        var hidden = document.getElementById(id);
        if (hidden) { hidden.value = JSON.stringify(collect()); }
    }

    function switchMode(newMode) {
        if (newMode === mode) { return; }
        var tableWrap   = document.getElementById(id + '_table_wrap');
        var btnAll      = document.getElementById(id + '_btn_all');
        var btnCustom   = document.getElementById(id + '_btn_custom');

        if (newMode === 'all') {
            if (tableWrap) { tableWrap.style.display = 'none'; }
            if (btnAll)    { btnAll.className    = btnAll.className.replace('btn-outline-secondary', 'btn-secondary'); }
            if (btnCustom) { btnCustom.className = btnCustom.className.replace('btn-secondary', 'btn-outline-secondary'); }
        } else {
            if (tableWrap) { tableWrap.style.display = ''; }
            if (btnCustom) { btnCustom.className = btnCustom.className.replace('btn-outline-secondary', 'btn-secondary'); }
            if (btnAll)    { btnAll.className    = btnAll.className.replace('btn-secondary', 'btn-outline-secondary'); }
        }

        mode = newMode;
        save();
    }

    function init() {
        var widget = document.getElementById(id + '_widget');
        if (!widget) { return; }

        widget.querySelectorAll('.jb-crawler-chk').forEach(function (chk) {
            chk.addEventListener('change', save);
        });

        var btnAll    = document.getElementById(id + '_btn_all');
        var btnCustom = document.getElementById(id + '_btn_custom');
        if (btnAll)    { btnAll.addEventListener('click',    function () { switchMode('all'); }); }
        if (btnCustom) { btnCustom.addEventListener('click', function () { switchMode('custom'); }); }

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
