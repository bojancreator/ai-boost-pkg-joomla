<?php
/**
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 *
 * FAQ Auto-Detect — heuristic parser that extracts question/answer pairs
 * from arbitrary HTML using the common pattern:
 *
 *     <h2|h3|h4> question text? </h>
 *     <p>… answer paragraph(s) …</p>
 *     <ul>|<ol>  (optional list)
 *     …continues until next heading…
 *
 * A heading is treated as a question when it ends with '?' OR begins with a
 * common interrogative word (what / how / why / when / where / who / which /
 * can / do / does / is / are / should / will). This catches both English and
 * common translations via a configurable extra list.
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or die;

final class FaqAutoDetectService
{
    /** Question-word prefixes (lowercased). Includes EN + common SR/HR/DE/FR/ES/IT/RU/PT. */
    private const QUESTION_WORDS = [
        // EN
        'what', 'how', 'why', 'when', 'where', 'who', 'which',
        'can', 'do', 'does', 'is', 'are', 'should', 'will',
        // SR/HR/BS
        'šta', 'sta', 'kako', 'zašto', 'zasto', 'kada', 'gde', 'gdje',
        'ko', 'koji', 'koja', 'koje', 'da li', 'mogu', 'može', 'moze',
        // DE
        'was', 'wie', 'warum', 'wann', 'wo', 'wer', 'welche', 'kann', 'ist', 'sind',
        // FR
        'quoi', 'que', 'comment', 'pourquoi', 'quand', 'où', 'ou', 'qui', 'quel', 'puis', 'est',
        // ES
        'qué', 'que', 'cómo', 'como', 'por qué', 'porque', 'cuándo', 'cuando', 'dónde', 'donde',
        'quién', 'quien', 'cuál', 'cual', 'puedo', 'es',
        // IT
        'cosa', 'come', 'perché', 'perche', 'quando', 'dove', 'chi', 'quale', 'posso', 'è',
        // RU
        'что', 'как', 'почему', 'когда', 'где', 'кто', 'какой',
        // PT
        'o que', 'como', 'porque', 'quando', 'onde', 'quem', 'qual', 'posso',
    ];

    /**
     * Parse an HTML fragment for FAQ pairs.
     *
     * @param string $html  Raw article HTML (introtext + fulltext concatenation is fine).
     * @param int    $max   Maximum number of FAQ pairs to return per document (0 = unlimited).
     *
     * @return list<array{question: string, answer: string}>
     */
    public function parse(string $html, int $max = 0): array
    {
        $html = trim($html);
        if ($html === '') {
            return [];
        }

        // Cap input to keep regex backtracking bounded on huge documents.
        if (strlen($html) > 200000) {
            $html = substr($html, 0, 200000);
        }

        // Strip script/style — they can contain spurious headings.
        $html = (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', ' ', $html);

        // Tokenise into headings and non-heading blocks while preserving order.
        // Matches h1..h6 open/close as delimiters.
        $pattern = '#<(h[1-6])\b[^>]*>(.*?)</\1\s*>#is';
        if (!preg_match_all($pattern, $html, $headings, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return [];
        }

        $items = [];
        $count = count($headings);

        for ($i = 0; $i < $count; $i++) {
            $tag        = strtolower($headings[$i][1][0]);
            $rawHeading = $headings[$i][2][0];
            $offset     = $headings[$i][0][1];
            $headingLen = strlen($headings[$i][0][0]);

            // Skip h1 (page title) and h5/h6 (too deep for FAQ pattern).
            if (!in_array($tag, ['h2', 'h3', 'h4'], true)) {
                continue;
            }

            $question = $this->cleanText($rawHeading);
            if (!$this->looksLikeQuestion($question)) {
                continue;
            }

            // Answer = HTML between this heading and the next heading (or EOF).
            $answerStart = $offset + $headingLen;
            $answerEnd   = ($i + 1 < $count) ? $headings[$i + 1][0][1] : strlen($html);
            $answerHtml  = substr($html, $answerStart, $answerEnd - $answerStart);

            $answer = $this->extractAnswer($answerHtml);
            if ($answer === '') {
                continue;
            }

            $items[] = [
                'question' => $question,
                'answer'   => $answer,
            ];

            if ($max > 0 && count($items) >= $max) {
                break;
            }
        }

        return $items;
    }

    /**
     * Heading qualifies as a question when it ends with '?' or begins with
     * a known question word.
     */
    private function looksLikeQuestion(string $text): bool
    {
        $text = trim($text);
        if ($text === '' || mb_strlen($text) < 6) {
            return false;
        }

        // Trailing '?' (also catches Spanish '¿…?', Greek ';', full-width '？')
        if (preg_match('/[\?？]\s*$/u', $text)) {
            return true;
        }

        $lower = mb_strtolower($text, 'UTF-8');
        foreach (self::QUESTION_WORDS as $word) {
            $word = mb_strtolower($word, 'UTF-8');
            $len  = mb_strlen($word);
            if (mb_substr($lower, 0, $len + 1, 'UTF-8') === $word . ' '
                || mb_substr($lower, 0, $len + 1, 'UTF-8') === $word . "\u{00A0}"
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract plain-text answer from the HTML block between two headings.
     * Keeps paragraph and list-item structure as line breaks.
     */
    private function extractAnswer(string $html): string
    {
        // Insert line breaks at block-level closings before stripping tags.
        $html = preg_replace('#</(p|li|div|br|ul|ol|blockquote)\s*>#i', "$0\n", $html) ?? $html;
        $html = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Collapse whitespace within lines, then trim and join non-empty lines.
        $lines = preg_split('/\r?\n/', $text) ?: [];
        $clean = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $line) ?? '');
            if ($line !== '') {
                $clean[] = $line;
            }
        }

        $answer = implode(' ', $clean);
        // Cap absurdly long answers to keep llms.txt readable.
        if (mb_strlen($answer) > 1200) {
            $answer = mb_substr($answer, 0, 1197) . '…';
        }
        return $answer;
    }

    private function cleanText(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }
}
