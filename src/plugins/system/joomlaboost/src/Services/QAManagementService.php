<?php

/**
 * Q&A Management Service for JoomlaBoost
 *
 * @package     JoomlaBoost
 * @subpackage  Services
 * @since       0.3.0
 * @author      JoomlaBoost Team
 * @copyright   (C) 2026 JoomlaBoost. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Services;

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/**
 * Q&A Management Service
 *
 * Manages manual FAQ entries for Schema.org FAQPage generation.
 * Supports JSON format with validation and deduplication.
 *
 * @since 0.3.0
 */
class QAManagementService extends AbstractService
{
    /**
     * Get manually configured FAQ items from plugin parameters
     * Supports multi-language FAQ fields with fallback
     *
     * @return array Array of FAQ items in Schema.org format
     */
    public function getManualFAQs(): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        // Get current language code
        $lang = \Joomla\CMS\Factory::getLanguage();
        $langTag = $lang->getTag(); // e.g., 'sr-RS', 'en-GB', 'ru-RU'
        $langCode = strtolower(substr($langTag, 0, 2)); // 'sr', 'en', 'ru'

        // Try language-specific field first
        $langField = "manual_faqs_{$langCode}";
        $manualFAQsJson = $this->params->get($langField, '');

        // Fallback to English (if not already EN)
        if (empty($manualFAQsJson) && $langCode !== 'en') {
            $manualFAQsJson = $this->params->get('manual_faqs_en', '');
            $this->logDebug("FAQ fallback to EN field");
        }

        // Fallback to default field (backward compatibility)
        if (empty($manualFAQsJson)) {
            $manualFAQsJson = $this->params->get('manual_faqs', '');
        }

        if (empty($manualFAQsJson)) {
            return [];
        }

        try {
            $faqData = json_decode($manualFAQsJson, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($faqData)) {
                $this->logDebug('Manual FAQs: Invalid JSON format (not an array)');
                return [];
            }

            $this->logDebug("Loaded {count} manual FAQ items for language: {lang}", [
                'count' => count($faqData),
                'lang' => $langCode
            ]);

            return $this->processFAQItems($faqData);
        } catch (\JsonException $e) {
            $this->logDebug('Manual FAQs: JSON parse error - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Process and validate FAQ items
     *
     * @param array $items Raw FAQ items from JSON
     * @return array Validated and formatted FAQ items
     */
    private function processFAQItems(array $items): array
    {
        $processed = [];

        foreach ($items as $index => $item) {
            $validated = $this->validateFAQItem($item, $index);

            if ($validated !== null) {
                $processed[] = $validated;
            }
        }

        $this->logDebug('Processed {count} manual FAQ items', ['count' => count($processed)]);

        return $processed;
    }

    /**
     * Validate single FAQ item
     *
     * @param mixed $item FAQ item to validate
     * @param int $index Item index for error reporting
     * @return array|null Validated FAQ item or null if invalid
     */
    private function validateFAQItem($item, int $index): ?array
    {
        if (!is_array($item)) {
            $this->logDebug('Manual FAQ #{index}: Not an array', ['index' => $index]);
            return null;
        }

        $question = trim($item['question'] ?? $item['q'] ?? '');
        $answer = trim($item['answer'] ?? $item['a'] ?? '');

        if (empty($question) || empty($answer)) {
            $this->logDebug('Manual FAQ #{index}: Missing question or answer', ['index' => $index]);
            return null;
        }

        // Schema.org FAQPage format
        return [
            '@type' => 'Question',
            'name' => $question,
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $answer
            ]
        ];
    }

    /**
     * Merge manual FAQs with auto-detected FAQs
     *
     * @param array $autoFAQs Auto-detected FAQ items
     * @param array $manualFAQs Manual FAQ items
     * @param string $mode Merge mode: 'manual_only', 'auto_only', 'manual_first', 'auto_first'
     * @return array Merged FAQ items
     */
    public function mergeFAQs(array $autoFAQs, array $manualFAQs, string $mode = 'manual_first'): array
    {
        switch ($mode) {
            case 'manual_only':
                return $manualFAQs;

            case 'auto_only':
                return $autoFAQs;

            case 'auto_first':
                $merged = array_merge($autoFAQs, $manualFAQs);
                break;

            case 'manual_first':
            default:
                $merged = array_merge($manualFAQs, $autoFAQs);
                break;
        }

        // Deduplicate by question text
        return $this->deduplicateFAQs($merged);
    }

    /**
     * Remove duplicate FAQ items based on question text
     *
     * @param array $faqs FAQ items to deduplicate
     * @return array Deduplicated FAQ items
     */
    private function deduplicateFAQs(array $faqs): array
    {
        $seen = [];
        $unique = [];

        foreach ($faqs as $faq) {
            $question = strtolower(trim($faq['name'] ?? ''));

            if (empty($question)) {
                continue;
            }

            // Simple normalization: remove punctuation and extra spaces
            $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', '', $question);
            $normalized = preg_replace('/\s+/', ' ', $normalized);

            if (!isset($seen[$normalized])) {
                $seen[$normalized] = true;
                $unique[] = $faq;
            }
        }

        $duplicatesRemoved = count($faqs) - count($unique);
        if ($duplicatesRemoved > 0) {
            $this->logDebug('Removed {count} duplicate FAQ items', ['count' => $duplicatesRemoved]);
        }

        return $unique;
    }

    /**
     * Get the service enable key from params
     *
     * @return string Parameter key for enabling this service
     */
    protected function getServiceKey(): string
    {
        return 'enable_manual_faqs';
    }
}
