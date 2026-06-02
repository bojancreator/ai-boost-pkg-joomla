<?php
/**
 * AI Boost — JsonLdAnalyzerService
 * Validates a JSON-LD structured data string against Schema.org conventions.
 * Returns a list of issues and recommendations.
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or die;

class JsonLdAnalyzerService
{
    /**
     * Required fields per @type. Checked when the type is recognized.
     */
    private const REQUIRED_FIELDS = [
        'Organization' => ['name'],
        'LocalBusiness' => ['name', 'address'],
        'Person'       => ['name'],
        'Article'      => ['headline', 'author', 'datePublished'],
        'BlogPosting'  => ['headline', 'author', 'datePublished'],
        'NewsArticle'  => ['headline', 'author', 'datePublished'],
        'FAQPage'      => ['mainEntity'],
        'Product'      => ['name'],
        'Event'        => ['name', 'startDate', 'location'],
        'Recipe'       => ['name', 'recipeIngredient', 'recipeInstructions'],
        'BreadcrumbList' => ['itemListElement'],
        'WebSite'      => ['name', 'url'],
        'WebPage'      => ['name'],
        'ItemList'     => ['itemListElement'],
        'HowTo'        => ['name', 'step'],
        'VideoObject'  => ['name', 'description', 'thumbnailUrl'],
        'ImageObject'  => ['url'],
        'JobPosting'   => ['title', 'hiringOrganization', 'jobLocation'],
        'Course'       => ['name', 'description', 'provider'],
    ];

    /**
     * Recommended (not required) fields per @type — shown as info suggestions.
     */
    private const RECOMMENDED_FIELDS = [
        'Organization'  => ['url', 'logo', 'sameAs'],
        'LocalBusiness' => ['telephone', 'openingHours', 'geo'],
        'Article'       => ['image', 'description', 'publisher'],
        'BlogPosting'   => ['image', 'description', 'publisher'],
        'FAQPage'       => [],
        'Product'       => ['description', 'image', 'offers'],
        'Event'         => ['description', 'endDate', 'url'],
        'WebSite'       => ['potentialAction'],
    ];

    /**
     * Analyze a JSON-LD string and return structured results.
     *
     * @return array{valid: bool, type: string|null, issues: list<array>, score: int}
     */
    public function analyze(string $jsonString): array
    {
        $jsonString = trim($jsonString);
        $issues     = [];

        // ── JSON validity ──────────────────────────────────────────────────
        if ($jsonString === '') {
            return ['valid' => false, 'type' => null, 'issues' => [
                $this->issue('error', 'Empty Input', 'No JSON-LD code was provided. Paste your JSON-LD script content or fetch from a URL.'),
            ], 'score' => 0];
        }

        // Strip surrounding <script> tags if present
        $jsonString = preg_replace('/^\s*<script[^>]*>/i', '', $jsonString) ?? $jsonString;
        $jsonString = preg_replace('/<\/script>\s*$/i', '', $jsonString) ?? $jsonString;
        $jsonString = trim($jsonString);

        $data = json_decode($jsonString, true);
        if ($data === null) {
            $jsonError = json_last_error_msg();
            return ['valid' => false, 'type' => null, 'issues' => [
                $this->issue('error', 'Invalid JSON', "The provided text is not valid JSON. Parse error: {$jsonError}. Check for missing commas, unquoted keys, or mismatched brackets."),
            ], 'score' => 0];
        }

        $issues[] = $this->issue('pass', 'Valid JSON', 'The JSON syntax is valid.');

        // Handle @graph — analyze first item for simplicity
        if (isset($data['@graph']) && is_array($data['@graph'])) {
            $issues[] = $this->issue('info', '@graph Detected', 'This JSON-LD uses the @graph container. Analyzing the first item in the graph.');
            $data = $data['@graph'][0] ?? $data;
        }

        // ── @context ──────────────────────────────────────────────────────
        $context = (string) ($data['@context'] ?? '');
        $contextOk = str_contains($context, 'schema.org');
        if (!$contextOk) {
            $issues[] = $this->issue('error', '@context Missing or Wrong',
                '@context must be "https://schema.org". Found: ' . ($context ?: '(missing)'));
        } else {
            $issues[] = $this->issue('pass', '@context', '@context is set to schema.org correctly.');
        }

        // ── @type ─────────────────────────────────────────────────────────
        $type = (string) ($data['@type'] ?? '');
        if ($type === '') {
            $issues[] = $this->issue('error', '@type Missing',
                '@type is required in every JSON-LD block. It tells search engines what kind of entity this is (e.g., "Organization", "Article").');
            return ['valid' => false, 'type' => null, 'issues' => $issues, 'score' => $this->scoreFromIssues($issues)];
        }

        $issues[] = $this->issue('pass', '@type', "@type is set to \"{$type}\".");

        // ── Required fields ────────────────────────────────────────────────
        $requiredFields    = self::REQUIRED_FIELDS[$type] ?? [];
        $recommendedFields = self::RECOMMENDED_FIELDS[$type] ?? [];

        if (empty($requiredFields) && !isset(self::REQUIRED_FIELDS[$type])) {
            $issues[] = $this->issue('info', 'Unknown Type',
                "@type \"{$type}\" is not in our validation library. Basic structure looks correct. Verify required fields against schema.org/{$type}.");
        }

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $issues[] = $this->issue('error', "Missing Required Field: {$field}",
                    "\"{$field}\" is required for {$type}. Check https://schema.org/{$type} for field documentation.");
            } else {
                $issues[] = $this->issue('pass', "Required Field: {$field}", "\"{$field}\" is present.");
            }
        }

        // ── Recommended fields ─────────────────────────────────────────────
        foreach ($recommendedFields as $field) {
            if (empty($data[$field])) {
                $issues[] = $this->issue('warning', "Recommended Field: {$field}",
                    "Consider adding \"{$field}\" to enhance your {$type} schema. See https://schema.org/{$type}.");
            }
        }

        // ── Type-specific deep checks ──────────────────────────────────────
        $this->deepCheck($type, $data, $issues);

        $score = $this->scoreFromIssues($issues);

        return [
            'valid'  => true,
            'type'   => $type,
            'issues' => $issues,
            'score'  => $score,
        ];
    }

    private function deepCheck(string $type, array $data, array &$issues): void
    {
        switch ($type) {
            case 'FAQPage':
                $entities = $data['mainEntity'] ?? [];
                if (!is_array($entities)) {
                    break;
                }
                foreach ($entities as $i => $entity) {
                    $n = $i + 1;
                    if (empty($entity['name'])) {
                        $issues[] = $this->issue('error', "FAQ #{$n}: Missing question",
                            "mainEntity[{$i}] is missing \"name\" (the question text).");
                    }
                    if (empty($entity['acceptedAnswer']['text'])) {
                        $issues[] = $this->issue('error', "FAQ #{$n}: Missing answer",
                            "mainEntity[{$i}] is missing \"acceptedAnswer.text\" (the answer text).");
                    }
                }
                $count = count($entities);
                if ($count > 0) {
                    $issues[] = $this->issue('pass', 'FAQ Items', "Found {$count} FAQ item(s).");
                }
                break;

            case 'BreadcrumbList':
                $items = $data['itemListElement'] ?? [];
                if (!is_array($items)) {
                    break;
                }
                foreach ($items as $i => $item) {
                    $n = $i + 1;
                    if (!isset($item['position'])) {
                        $issues[] = $this->issue('warning', "Breadcrumb #{$n}: Missing position",
                            "itemListElement[{$i}] should have a \"position\" (integer, e.g. 1, 2, 3).");
                    }
                    if (empty($item['name'])) {
                        $issues[] = $this->issue('error', "Breadcrumb #{$n}: Missing name",
                            "itemListElement[{$i}] is missing \"name\" (the visible label).");
                    }
                }
                break;

            case 'Article':
            case 'BlogPosting':
            case 'NewsArticle':
                if (!empty($data['datePublished'])) {
                    if (!$this->isValidIso8601((string) $data['datePublished'])) {
                        $issues[] = $this->issue('warning', 'datePublished Format',
                            'datePublished should be in ISO 8601 format: "2026-05-01T10:00:00+00:00".');
                    }
                }
                if (!empty($data['author'])) {
                    $author = $data['author'];
                    if (is_array($author) && empty($author['name'])) {
                        $issues[] = $this->issue('warning', 'Author: Missing name',
                            'The "author" object should include "name".');
                    }
                }
                break;
        }
    }

    private function isValidIso8601(string $value): bool
    {
        try {
            $dt = new \DateTime($value);
            return $dt !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function scoreFromIssues(array $issues): int
    {
        $total  = count($issues);
        if ($total === 0) {
            return 100;
        }
        $errors   = count(array_filter($issues, static fn($i) => $i['level'] === 'error'));
        $warnings = count(array_filter($issues, static fn($i) => $i['level'] === 'warning'));
        $passes   = count(array_filter($issues, static fn($i) => $i['level'] === 'pass'));

        $score = 100 - ($errors * 20) - ($warnings * 5);
        if ($passes > 0) {
            $score += (int) min(10, $passes * 2);
        }
        return max(0, min(100, $score));
    }

    private function issue(string $level, string $label, string $message): array
    {
        return [
            'level'   => $level,
            'label'   => $label,
            'message' => $message,
        ];
    }
}
