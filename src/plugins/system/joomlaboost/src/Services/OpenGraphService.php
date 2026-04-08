<?php

/**
 * OpenGraph Service for JoomlaBoost
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System.Services
 * @since       Joomla 4.0, PHP 8.1+
 * @author      JoomlaBoost Team
 * @copyright   (C) 2025 JoomlaBoost. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Services;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Document\HtmlDocument;

/**
 * OpenGraph Service with Performance Optimizations
 *
 * Performance Features:
 * - Request-level caching of meta generation
 * - Batch processing via PerformanceService
 * - Lazy loading of expensive operations (DB queries, image processing)
 * - Memory-efficient duplicate checking
 * - Conditional heavy operations only when needed
 */
class OpenGraphService extends AbstractService
{
    private ?PerformanceService $performanceService = null;

    protected function getServiceKey(): string
    {
        return 'enable_opengraph';
    }

    /**
     * Get or create performance service instance
     */
    private function getPerformanceService(): PerformanceService
    {
        if ($this->performanceService === null) {
            $this->performanceService = new PerformanceService($this->app, $this->params);
        }
        return $this->performanceService;
    }

    /**
     * Generate OpenGraph meta tags with performance optimizations
     */
    public function generateOpenGraphTags(): void
    {
        if (!$this->isEnabled()) {
            $this->logDebug('OpenGraph generation skipped - service disabled');
            return;
        }

        $perfService = $this->getPerformanceService();
        $cacheKey = 'og_tags_' . $perfService->getPageCacheKey();

        // Check if we already generated tags for this page in current request
        if ($perfService->cacheHas($cacheKey)) {
            $this->logDebug('OpenGraph tags loaded from request cache');
            return;
        }

        try {
            $document = $this->app->getDocument();
            if (!($document instanceof HtmlDocument)) {
                return;
            }

            $input = $this->app->getInput();
            $option = $input->getCmd('option');
            $view = $input->getCmd('view');

            // Generate basic OG tags (fast operations)
            $this->addBasicOpenGraphTags($perfService, $option, $view);

            // Add article-specific tags only if needed (heavy operations)
            if ($option === 'com_content' && $view === 'article' && $perfService->needsHeavyOperations()) {
                $perfService->initializeHeavyOperations();
                $this->addArticleOpenGraphTags($perfService);
            }

            // Add fallback image if no article image was set
            $this->addFallbackOpenGraphImage($perfService);

            // Add Twitter Card tags (fast operations)
            $this->addTwitterCardTags($perfService);

            // Process all batched meta tags in single DOM operation
            $processed = $perfService->processBatchedMeta($document);

            // Cache the fact that we generated tags for this page
            $perfService->cacheSet($cacheKey, true);

            $this->logDebug("OpenGraph generation completed", [
                'processed_tags' => $processed,
                'page_type' => $option . '/' . $view,
                'heavy_ops_used' => $perfService->needsHeavyOperations()
            ]);
        } catch (\Throwable $e) {
            $this->logDebug("OpenGraph generation failed: " . $e->getMessage());
        }
    }

    /**
     * Add basic OpenGraph tags (fast operations only)
     */
    private function addBasicOpenGraphTags(PerformanceService $perfService, string $option, string $view): void
    {
        $document = $this->app->getDocument();
        $override = (bool) $this->params->get('og_override', 0);

        // og:type — always force correct value.
        // Homepage always = 'website' (even if menu item is Single Article).
        // Other pages: 'article' only for com_content/view=article, else 'website'.
        try {
            $menu       = $this->app->getMenu();
            $isHomepage = ($menu && $menu->getActive() === $menu->getDefault());
        } catch (\Throwable $e) {
            $isHomepage = false;
        }
        $ogType = ($isHomepage)
            ? 'website'
            : (($option === 'com_content' && $view === 'article') ? 'article' : 'website');
        $perfService->addMetaToBatch('og:type', $ogType, 'property');

        // og:site_name - from config, no DB hit
        $siteName = $this->params->get('og_site_name', $this->params->get('org_name', ''));
        if (!empty($siteName) && ($override || !$perfService->isMetaTagPresent('og:site_name', 'property'))) {
            $perfService->addMetaToBatch('og:site_name', $siteName, 'property');
        }

        // og:title - Priority: 1. Custom Field (articles only) → 2. Document title
        if ($override || !$perfService->isMetaTagPresent('og:title', 'property')) {
            $title = '';

            // Check custom field for articles
            if ($option === 'com_content' && $view === 'article') {
                $articleId = $this->app->getInput()->getInt('id', 0);
                if ($articleId > 0) {
                    $customTitle = $this->getArticleCustomField($articleId, 'custom_og_title');
                    if (!empty($customTitle)) {
                        $title = trim($customTitle);
                        $this->logDebug("Using custom_og_title from Custom Field for article $articleId");
                    }
                }
            }

            // Fallback to document title
            if (empty($title)) {
                $title = method_exists($document, 'getTitle') ? trim($document->getTitle()) : '';
            }

            if (!empty($title)) {
                $perfService->addMetaToBatch('og:title', $title, 'property');
            }
        }

        // og:description - Priority: 1. Custom Field (articles only) → 2. Document description
        if ($override || !$perfService->isMetaTagPresent('og:description', 'property')) {
            $description = '';

            // Check custom field for articles
            if ($option === 'com_content' && $view === 'article') {
                $articleId = $this->app->getInput()->getInt('id', 0);
                if ($articleId > 0) {
                    $customDesc = $this->getArticleCustomField($articleId, 'custom_og_description');
                    if (!empty($customDesc)) {
                        $description = trim($customDesc);
                        $this->logDebug("Using custom_og_description from Custom Field for article $articleId");
                    }
                }
            }

            // Fallback to document description
            if (empty($description)) {
                $description = method_exists($document, 'getDescription') ? trim($document->getDescription()) : '';
            }

            if (!empty($description)) {
                $perfService->addMetaToBatch('og:description', $description, 'property');
            }
        }

        // og:url - from current request (fast)
        if ($override || !$perfService->isMetaTagPresent('og:url', 'property')) {
            $currentUrl = Uri::getInstance()->toString(['scheme', 'host', 'port', 'path', 'query']);
            $perfService->addMetaToBatch('og:url', $currentUrl, 'property');
        }

        // og:image will be added in addArticleOpenGraphTags or addFallbackOpenGraphImage
        // to ensure proper priority: article image > fallback image
    }

    /**
     * Add fallback OpenGraph image if no article image was set
     */
    private function addFallbackOpenGraphImage(PerformanceService $perfService): void
    {
        $override = (bool) $this->params->get('og_override', 0);

        // Only add fallback if no og:image is already set
        if ($override || !$perfService->isMetaTagPresent('og:image', 'property')) {
            $fallbackImage = $this->params->get('og_image', $this->params->get('org_logo', ''));
            if (!empty($fallbackImage)) {
                $normalizedImage = $this->normalizeAndCleanImageUrl($fallbackImage);
                if (!empty($normalizedImage)) {
                    $this->logDebug("Using fallback og:image: $normalizedImage");
                    $perfService->addMetaToBatch('og:image', $normalizedImage, 'property');

                    // og:image:width / og:image:height — required by Facebook, LinkedIn, WhatsApp validators
                    [$imgWidth, $imgHeight] = $this->getImageDimensions($normalizedImage);
                    if ($imgWidth > 0) {
                        $perfService->addMetaToBatch('og:image:width', (string) $imgWidth, 'property');
                    }
                    if ($imgHeight > 0) {
                        $perfService->addMetaToBatch('og:image:height', (string) $imgHeight, 'property');
                    }

                    // og:image:alt — accessibility requirement + Facebook validation
                    $siteName = (string) $this->params->get('org_name',
                        (string) $this->params->get('og_site_name', ''));
                    if (!empty($siteName)) {
                        $perfService->addMetaToBatch('og:image:alt', $siteName, 'property');
                    }
                }
            }
        }
    }
    /**
     * Add article-specific OpenGraph tags (heavy operations - DB queries)
     */
    private function addArticleOpenGraphTags(PerformanceService $perfService): void
    {
        $override = (bool) $this->params->get('og_override', 0);

        // Try to get article image (involves DB query)
        $articleImageCacheKey = 'article_image_' . $this->app->getInput()->getInt('id', 0);

        if (!$perfService->cacheHas($articleImageCacheKey)) {
            $articleImage = $this->getArticleImage();
            $perfService->cacheSet($articleImageCacheKey, $articleImage);
        } else {
            $articleImage = $perfService->cacheGet($articleImageCacheKey);
        }

        if (!empty($articleImage) && ($override || !$perfService->isMetaTagPresent('og:image', 'property'))) {
            $perfService->addMetaToBatch('og:image', $articleImage, 'property');

            // og:image:width / og:image:height — required by Facebook, LinkedIn validators
            [$imgWidth, $imgHeight] = $this->getImageDimensions($articleImage);
            if ($imgWidth > 0) {
                $perfService->addMetaToBatch('og:image:width', (string) $imgWidth, 'property');
            }
            if ($imgHeight > 0) {
                $perfService->addMetaToBatch('og:image:height', (string) $imgHeight, 'property');
            }

            // og:image:alt — use article title for accessibility
            try {
                $document = $this->app->getDocument();
                $imgAlt   = method_exists($document, 'getTitle') ? trim($document->getTitle()) : '';
                if (!empty($imgAlt)) {
                    $perfService->addMetaToBatch('og:image:alt', $imgAlt, 'property');
                }
            } catch (\Throwable $e) {
                // skip alt if document not available
            }
        }

        // Article-specific type
        if ($override || !$perfService->isMetaTagPresent('og:type', 'property')) {
            $perfService->addMetaToBatch('og:type', 'article', 'property');
        }

        // Add article:published_time and article:modified_time if available
        $this->addArticleTimestamps($perfService);
    }

    /**
     * Add Twitter Card meta tags (fast operations)
     */
    private function addTwitterCardTags(PerformanceService $perfService): void
    {
        $override = (bool) $this->params->get('og_override', 0);
        $twitterSite = $this->params->get('twitter_site', '');

        // twitter:card
        if ($override || !$perfService->isMetaTagPresent('twitter:card')) {
            $perfService->addMetaToBatch('twitter:card', 'summary_large_image');
        }

        // twitter:site
        if (!empty($twitterSite) && ($override || !$perfService->isMetaTagPresent('twitter:site'))) {
            $perfService->addMetaToBatch('twitter:site', $twitterSite);
        }

        // twitter:creator - only if different from site
        $twitterCreator = $this->params->get('twitter_creator', '');
        if (
            !empty($twitterCreator) && $twitterCreator !== $twitterSite &&
            ($override || !$perfService->isMetaTagPresent('twitter:creator'))
        ) {
            $perfService->addMetaToBatch('twitter:creator', $twitterCreator);
        }
    }

    /**
     * Add article timestamps (requires DB query - heavy operation)
     */
    private function addArticleTimestamps(PerformanceService $perfService): void
    {
        $articleId = $this->app->getInput()->getInt('id', 0);
        if ($articleId <= 0) {
            return;
        }

        $timestampCacheKey = 'article_timestamps_' . $articleId;

        if (!$perfService->cacheHas($timestampCacheKey)) {
            $timestamps = $this->getArticleTimestamps($articleId);
            $perfService->cacheSet($timestampCacheKey, $timestamps);
        } else {
            $timestamps = $perfService->cacheGet($timestampCacheKey);
        }

        if (!empty($timestamps['published'])) {
            $perfService->addMetaToBatch('article:published_time', $timestamps['published'], 'property');
        }

        if (!empty($timestamps['modified'])) {
            $perfService->addMetaToBatch('article:modified_time', $timestamps['modified'], 'property');
        }

        // article:author — helps LinkedIn/Facebook identify content creator
        if (!empty($timestamps['author'])) {
            $perfService->addMetaToBatch('article:author', $timestamps['author'], 'property');
        }

        // article:section — article category (helps social platforms categorize content)
        if (!empty($timestamps['section'])) {
            $perfService->addMetaToBatch('article:section', $timestamps['section'], 'property');
        }
    }

    /**
     * Get article image URL (heavy operation - DB query)
     *
     * Priority: 1. Custom Field (custom_og_image) → 2. Featured Image (intro/fulltext) → 3. Extracted from content
     */
    private function getArticleImage(): string
    {
        try {
            $articleId = $this->app->getInput()->getInt('id', 0);

            if ($articleId <= 0) {
                return '';
            }

            // Priority 1: Custom OG Image (per-article override)
            $customImage = $this->getArticleCustomField($articleId, 'custom_og_image');
            if (!empty($customImage)) {
                $this->logDebug("Using custom_og_image from Custom Field for article $articleId");
                return $this->normalizeAndCleanImageUrl($customImage);
            }

            // Priority 2 & 3: Featured images from article or extracted from content
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName(['images', 'introtext', 'fulltext']))
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('id') . ' = ' . (int) $articleId);

            $db->setQuery($query);
            $article = $db->loadObject();

            if (!$article) {
                return '';
            }

            // Try to extract from images JSON (Priority 2)
            $this->logDebug('Article images raw: ' . ($article->images ?? 'NULL'));

            if (!empty($article->images)) {
                $images = json_decode($article->images, true);
                $this->logDebug('Article images decoded: ' . json_encode($images));

                if (is_array($images)) {
                    // Try all possible Joomla image field variations
                    $imageFields = [
                        'image_intro',      // Joomla 3.x/4.x standard
                        'image_fulltext',   // Joomla 3.x/4.x standard
                        'intro_image',      // Alternative naming
                        'full_image',       // Alternative naming
                        'introimage',       // No underscore variant
                        'fullimage'         // No underscore variant
                    ];

                    foreach ($imageFields as $field) {
                        if (isset($images[$field])) {
                            $imageValue = trim($images[$field]);

                            if (!empty($imageValue)) {
                                return $this->normalizeAndCleanImageUrl($imageValue);
                            }
                        }
                    }

                    $this->logDebug('No valid image found. Available keys: ' . implode(', ', array_keys($images)));
                }
            } else {
                $this->logDebug('Article images field is empty');
            }

            // Extract from article text as fallback (Priority 3)
            return $this->extractImageFromContent($article->introtext . ' ' . $article->fulltext);
        } catch (\Throwable $e) {
            $this->logDebug('Article image extraction failed: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Get article timestamps (heavy operation - DB query)
     */
    private function getArticleTimestamps(int $articleId): array
    {
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('a.created, a.modified, a.created_by_alias, c.title AS cat_title')
                ->from('#__content AS a')
                ->leftJoin('#__categories AS c ON c.id = a.catid')
                ->where('a.id = ' . (int) $articleId);

            $db->setQuery($query);
            $result = $db->loadObject();

            if (!$result) {
                return [];
            }

            $timestamps = [];

            if (!empty($result->created) && $result->created !== $db->getNullDate()) {
                $timestamps['published'] = (new \DateTime($result->created))->format('c');
            }

            if (!empty($result->modified) && $result->modified !== $db->getNullDate()) {
                $timestamps['modified'] = (new \DateTime($result->modified))->format('c');
            }

            // Author and section for article:author / article:section OG tags
            if (!empty($result->created_by_alias)) {
                $timestamps['author'] = $result->created_by_alias;
            }
            if (!empty($result->cat_title)) {
                $timestamps['section'] = $result->cat_title;
            }

            return $timestamps;
        } catch (\Throwable $e) {
            $this->logDebug('Article timestamps extraction failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Extract image from HTML content (lightweight regex)
     */
    private function extractImageFromContent(string $content): string
    {
        if (empty($content)) {
            return '';
        }

        // Look for img tags with src
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
            return $this->normalizeImageUrl($matches[1]);
        }

        return '';
    }

    /**
     * Normalize image URL to absolute URL
     */
    private function normalizeImageUrl(string $imageUrl): string
    {
        if (empty($imageUrl)) {
            return '';
        }

        // Already absolute URL
        if (str_starts_with($imageUrl, 'http://') || str_starts_with($imageUrl, 'https://')) {
            return $imageUrl;
        }

        // Relative URL - make absolute
        $baseUrl = $this->getBaseUrl();
        $baseUrl = rtrim($baseUrl, '/');

        if (str_starts_with($imageUrl, '/')) {
            return $baseUrl . $imageUrl;
        } else {
            return $baseUrl . '/' . $imageUrl;
        }
    }
    /**
     * Normalize and clean image URL for social media validators
     * Removes Joomla fragments (#joomlaImage://...) and query parameters that confuse Facebook/Twitter
     */
    private function normalizeAndCleanImageUrl(string $imageUrl): string
    {
        if (empty($imageUrl)) {
            return '';
        }

        // Remove Joomla image fragments (e.g., #joomlaImage://local-images/...?width=807&height=835)
        if (str_contains($imageUrl, '#joomlaImage://')) {
            // Extract the actual image path before the fragment
            $parts = explode('#joomlaImage://', $imageUrl);
            $imageUrl = $parts[0];
        }

        // Remove query parameters that social validators don't need
        if (str_contains($imageUrl, '?')) {
            $imageUrl = explode('?', $imageUrl)[0];
        }

        // Normalize to absolute URL
        return $this->normalizeImageUrl(trim($imageUrl));
    }

    /**
     * Get image dimensions from a URL.
     * Tries getimagesize() on local file; falls back to configured plugin defaults.
     *
     * @param  string  $imageUrl  Absolute image URL
     * @return array{0: int, 1: int}  [width, height] — both 0 if unknown
     */
    private function getImageDimensions(string $imageUrl): array
    {
        if (empty($imageUrl)) {
            return [0, 0];
        }

        try {
            $baseUrl   = rtrim($this->getBaseUrl(), '/');
            $localPath = '';

            if (str_starts_with($imageUrl, $baseUrl)) {
                $relativePath = ltrim(str_replace($baseUrl, '', $imageUrl), '/');
                $candidate    = realpath(JPATH_SITE . DIRECTORY_SEPARATOR . $relativePath);

                if ($candidate !== false && is_readable($candidate)) {
                    $localPath = $candidate;
                }
            }

            if (!empty($localPath)) {
                $size = @getimagesize($localPath);
                if ($size !== false && $size[0] > 0) {
                    $this->logDebug("og:image dimensions from file: {$size[0]}x{$size[1]}");
                    return [(int) $size[0], (int) $size[1]];
                }
            }
        } catch (\Throwable $e) {
            // Fall through to defaults
        }

        // Configured defaults (admin can set these in plugin params)
        $w = (int) $this->params->get('og_image_width', 0);
        $h = (int) $this->params->get('og_image_height', 0);

        return [$w, $h];
    }

    /**
     * Get article custom field value (Joomla com_fields integration)
     *
     * @param int $articleId Article ID
     * @param string $fieldName Custom field name (custom_og_image, custom_og_title, custom_og_description)
     * @return string|null Custom field value or null if not set
     */
    private function getArticleCustomField(int $articleId, string $fieldName): ?string
    {
        if ($articleId <= 0) {
            return null;
        }

        try {
            $db = Factory::getDbo();

            // Query #__fields for field ID by name
            $fieldQuery = $db->getQuery(true)
                ->select('id')
                ->from('#__fields')
                ->where('name = ' . $db->quote($fieldName))
                ->where('context = ' . $db->quote('com_content.article'))
                ->where('state = 1');  // Back to checking published state

            $db->setQuery($fieldQuery);
            $fieldId = $db->loadResult();

            if (!$fieldId) {
                return null; // Field not found or disabled
            }

            // Query #__fields_values for article-specific value
            $valueQuery = $db->getQuery(true)
                ->select('value')
                ->from('#__fields_values')
                ->where('field_id = ' . (int) $fieldId)
                ->where('item_id = ' . (int) $articleId);

            $db->setQuery($valueQuery);
            $value = $db->loadResult();

            // Return value if not empty
            if (empty($value)) {
                return null;
            }

            // Special handling for custom_og_image: Joomla media field stores JSON
            if ($fieldName === 'custom_og_image') {
                $decoded = json_decode($value, true);
                if (is_array($decoded) && isset($decoded['imagefile'])) {
                    $this->logDebug("Parsed JSON from custom_og_image: " . $decoded['imagefile']);
                    return $decoded['imagefile'];
                }
            }

            return (string) $value;
        } catch (\Throwable $e) {
            $this->logDebug("Custom field '$fieldName' read failed: " . $e->getMessage());
            return null;
        }
    }
}
