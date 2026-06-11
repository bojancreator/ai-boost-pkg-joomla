<?php
/**
 * AI Boost — MarkdownConverterService
 *
 * Converts the main content region of a fully-rendered Joomla HTML page into
 * clean Markdown for AI agents (ChatGPT, Claude, Perplexity, custom bots).
 *
 * Pure PHP — uses only the built-in DOMDocument extension, no Composer
 * dependencies. The converter:
 *
 *   1. Loads the HTML into a DOMDocument (libxml errors suppressed).
 *   2. Locates the main content region by trying a list of CSS-style
 *      selectors in priority order: <main>, #sp-component, .item-page,
 *      .blog, <article>, fallback to <body>.
 *   3. Strips chrome elements (nav/header/footer/aside, .mod-*,
 *      #sp-top-bar/#sp-nav/#sp-footer, <script>, <style>, <noscript>,
 *      <iframe>, <form>).
 *   4. Walks the remaining DOM and renders Markdown for the common tags:
 *      h1-h6, p, a, ul/ol/li, strong/b, em/i, code, pre, blockquote, img,
 *      hr, br, table.
 *
 * The output is UTF-8 Markdown, with the document <title> as the top H1
 * when the main region does not already start with one.
 *
 * Lives in the shared lib (Korak 3.2 #3) so the Free aiboost_aeo plugin can
 * serve Markdown pages — Markdown is now a Free feature.
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Lib;

defined('_JEXEC') or die;

class MarkdownConverterService
{
    /** Tags removed before walking the tree. */
    private const STRIP_TAGS = ['nav', 'header', 'footer', 'aside', 'script', 'style', 'noscript', 'iframe', 'form'];

    /** id / class prefixes to strip from the main region. */
    private const STRIP_IDS = ['sp-top-bar', 'sp-nav', 'sp-header', 'sp-footer', 'sp-bottom'];

    /** class prefixes to strip (matched with str_starts_with on each class). */
    private const STRIP_CLASS_PREFIXES = ['mod-', 'module-', 'breadcrumb'];

    /**
     * Convert a full HTML page to Markdown.
     *
     * @param string $html Full rendered HTML document.
     */
    public function convert(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        // Prefix with UTF-8 meta so DOMDocument doesn't mis-decode the bytes.
        $loadHtml = '<?xml encoding="UTF-8"?>' . $html;
        $dom->loadHTML($loadHtml, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        // Page title for H1 fallback.
        $pageTitle = '';
        $titleNodes = $dom->getElementsByTagName('title');
        if ($titleNodes->length > 0) {
            $pageTitle = trim((string) $titleNodes->item(0)->textContent);
        }

        $main = $this->findMainNode($dom);
        if ($main === null) {
            return $pageTitle !== '' ? '# ' . $pageTitle . "\n" : '';
        }

        $this->stripChrome($main);

        $body = trim($this->renderNode($main));
        $body = $this->collapseBlankLines($body);

        $hasH1 = preg_match('/^#\s+\S/m', $body) === 1;
        if (!$hasH1 && $pageTitle !== '') {
            $body = '# ' . $pageTitle . "\n\n" . $body;
        }

        return $body . "\n";
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function findMainNode(\DOMDocument $dom): ?\DOMNode
    {
        $xpath = new \DOMXPath($dom);
        $candidates = [
            '//main',
            '//*[@id="sp-component"]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " item-page ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " blog ")]',
            '//article',
            '//body',
        ];
        foreach ($candidates as $q) {
            $r = $xpath->query($q);
            if ($r !== false && $r->length > 0) {
                return $r->item(0);
            }
        }
        return null;
    }

    private function stripChrome(\DOMNode $root): void
    {
        $dom = $root->ownerDocument;
        if ($dom === null) {
            return;
        }
        $xpath = new \DOMXPath($dom);

        // Tag-based removal scoped to the main region.
        foreach (self::STRIP_TAGS as $tag) {
            $nodes = $xpath->query('.//' . $tag, $root);
            if ($nodes === false) continue;
            $toRemove = [];
            foreach ($nodes as $n) $toRemove[] = $n;
            foreach ($toRemove as $n) {
                if ($n->parentNode) $n->parentNode->removeChild($n);
            }
        }

        // id-based removal.
        foreach (self::STRIP_IDS as $id) {
            $nodes = $xpath->query('.//*[@id="' . $id . '"]', $root);
            if ($nodes === false) continue;
            $toRemove = [];
            foreach ($nodes as $n) $toRemove[] = $n;
            foreach ($toRemove as $n) {
                if ($n->parentNode) $n->parentNode->removeChild($n);
            }
        }

        // class-prefix removal.
        $allWithClass = $xpath->query('.//*[@class]', $root);
        if ($allWithClass !== false) {
            $toRemove = [];
            foreach ($allWithClass as $node) {
                $classes = preg_split('/\s+/', (string) $node->getAttribute('class')) ?: [];
                foreach ($classes as $cls) {
                    if ($cls === '') continue;
                    foreach (self::STRIP_CLASS_PREFIXES as $prefix) {
                        if (str_starts_with($cls, $prefix)) {
                            $toRemove[] = $node;
                            continue 3;
                        }
                    }
                }
            }
            foreach ($toRemove as $n) {
                if ($n->parentNode) $n->parentNode->removeChild($n);
            }
        }
    }

    /**
     * Recursive node-to-Markdown renderer.
     *
     * @param int $listDepth Nesting depth for ordered/unordered lists.
     * @param string $listMarker '-' for ul, '1.' for ol (ignored at depth 0).
     */
    private function renderNode(\DOMNode $node, int $listDepth = 0, string $listMarker = ''): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = (string) $node->nodeValue;
            // Collapse interior whitespace; preserve a single space.
            $text = preg_replace('/[ \t\r\n]+/u', ' ', $text) ?? $text;
            return $this->escapeInline($text);
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return '';
        }

        /** @var \DOMElement $node */
        $tag = strtolower($node->nodeName);

        switch ($tag) {
            case 'h1': case 'h2': case 'h3':
            case 'h4': case 'h5': case 'h6':
                $level = (int) substr($tag, 1);
                $inner = trim($this->renderChildren($node));
                if ($inner === '') return '';
                return "\n\n" . str_repeat('#', $level) . ' ' . $inner . "\n\n";

            case 'p':
                $inner = trim($this->renderChildren($node));
                return $inner === '' ? '' : "\n\n" . $inner . "\n\n";

            case 'br':
                return "  \n";

            case 'hr':
                return "\n\n---\n\n";

            case 'strong': case 'b':
                $inner = trim($this->renderChildren($node));
                return $inner === '' ? '' : '**' . $inner . '**';

            case 'em': case 'i':
                $inner = trim($this->renderChildren($node));
                return $inner === '' ? '' : '*' . $inner . '*';

            case 'code':
                if ($node->parentNode && strtolower($node->parentNode->nodeName) === 'pre') {
                    return (string) $node->textContent;
                }
                return '`' . (string) $node->textContent . '`';

            case 'pre':
                $code = (string) $node->textContent;
                return "\n\n```\n" . rtrim($code) . "\n```\n\n";

            case 'blockquote':
                $inner = trim($this->renderChildren($node));
                if ($inner === '') return '';
                $quoted = preg_replace('/^/m', '> ', $inner) ?? $inner;
                return "\n\n" . $quoted . "\n\n";

            case 'a':
                $href = trim((string) $node->getAttribute('href'));
                $text = trim($this->renderChildren($node));
                if ($text === '') $text = $href;
                if ($href === '' || str_starts_with($href, 'javascript:')) {
                    return $text;
                }
                return '[' . $text . '](' . $href . ')';

            case 'img':
                $src = trim((string) $node->getAttribute('src'));
                if ($src === '') return '';
                $alt = trim((string) $node->getAttribute('alt'));
                return '![' . $alt . '](' . $src . ')';

            case 'ul':
                return $this->renderList($node, $listDepth, '-');

            case 'ol':
                return $this->renderList($node, $listDepth, '1.');

            case 'li':
                $indent  = str_repeat('  ', max(0, $listDepth - 1));
                $marker  = $listMarker !== '' ? $listMarker : '-';
                $inner   = trim($this->renderChildrenForListItem($node, $listDepth));
                if ($inner === '') return '';
                $first   = $indent . $marker . ' ';
                $cont    = $indent . str_repeat(' ', strlen($marker) + 1);
                // Indent continuation lines so nested blocks belong to this <li>.
                $lines = preg_split('/\n/', $inner) ?: [$inner];
                $out   = $first . array_shift($lines);
                foreach ($lines as $ln) {
                    $out .= "\n" . ($ln === '' ? '' : $cont . $ln);
                }
                return $out . "\n";

            case 'table':
                return $this->renderTable($node);

            case 'thead': case 'tbody': case 'tfoot': case 'tr':
            case 'th': case 'td':
                // Tables are rendered via renderTable; loose cells fall through to text.
                return $this->renderChildren($node);

            case 'figure':
                return "\n\n" . trim($this->renderChildren($node)) . "\n\n";

            case 'figcaption': case 'small':
                $inner = trim($this->renderChildren($node));
                return $inner === '' ? '' : "\n*" . $inner . "*\n";

            case 'div': case 'section': case 'article': case 'span': case 'main':
                $inner = $this->renderChildren($node, $listDepth, $listMarker);
                if ($tag === 'span') return $inner;
                $inner = trim($inner);
                return $inner === '' ? '' : "\n\n" . $inner . "\n\n";

            default:
                return $this->renderChildren($node, $listDepth, $listMarker);
        }
    }

    private function renderChildren(\DOMNode $node, int $listDepth = 0, string $listMarker = ''): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= $this->renderNode($child, $listDepth, $listMarker);
        }
        return $out;
    }

    private function renderChildrenForListItem(\DOMNode $li, int $listDepth): string
    {
        // Inline content within an <li> shouldn't get wrapped paragraph breaks.
        $out = '';
        foreach ($li->childNodes as $child) {
            $piece = $this->renderNode($child, $listDepth, '');
            $out .= $piece;
        }
        return $this->collapseBlankLines(trim($out));
    }

    private function renderList(\DOMElement $list, int $parentDepth, string $marker): string
    {
        $depth = $parentDepth + 1;
        $out   = $depth === 1 ? "\n\n" : "\n";
        foreach ($list->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && strtolower($child->nodeName) === 'li') {
                $out .= $this->renderNode($child, $depth, $marker);
            }
        }
        return $out . ($depth === 1 ? "\n" : '');
    }

    private function renderTable(\DOMElement $table): string
    {
        $rows = [];
        $headerSeen = false;

        $cellText = function (\DOMNode $cell): string {
            $t = trim($this->renderChildren($cell));
            return str_replace(['|', "\n"], [' \| ', ' '], $t);
        };

        foreach ($table->getElementsByTagName('tr') as $tr) {
            $cols = [];
            $isHeader = false;
            foreach ($tr->childNodes as $cell) {
                if ($cell->nodeType !== XML_ELEMENT_NODE) continue;
                $cn = strtolower($cell->nodeName);
                if ($cn !== 'th' && $cn !== 'td') continue;
                if ($cn === 'th') $isHeader = true;
                $cols[] = $cellText($cell);
            }
            if (!$cols) continue;
            if ($isHeader && !$headerSeen) {
                $rows[] = ['header', $cols];
                $headerSeen = true;
            } else {
                $rows[] = ['row', $cols];
            }
        }
        if (!$rows) return '';

        // Normalise column count from the widest row.
        $width = 0;
        foreach ($rows as [, $cols]) $width = max($width, count($cols));
        if ($width === 0) return '';

        $lines = [];
        if ($rows[0][0] !== 'header') {
            $lines[] = '| ' . implode(' | ', array_fill(0, $width, ' ')) . ' |';
            $lines[] = '|' . str_repeat(' --- |', $width);
        }
        foreach ($rows as [$kind, $cols]) {
            $cols = array_pad($cols, $width, '');
            $lines[] = '| ' . implode(' | ', $cols) . ' |';
            if ($kind === 'header') {
                $lines[] = '|' . str_repeat(' --- |', $width);
            }
        }
        return "\n\n" . implode("\n", $lines) . "\n\n";
    }

    private function escapeInline(string $text): string
    {
        // Escape only the Markdown chars that would otherwise change inline meaning.
        return str_replace(
            ['\\', '`', '*', '_', '[', ']'],
            ['\\\\', '\`', '\*', '\_', '\[', '\]'],
            $text,
        );
    }

    private function collapseBlankLines(string $s): string
    {
        $s = preg_replace("/[ \t]+\n/", "\n", $s) ?? $s;
        $s = preg_replace("/\n{3,}/", "\n\n", $s) ?? $s;
        return $s;
    }
}
