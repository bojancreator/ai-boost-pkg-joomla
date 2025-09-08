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

        // og:type - lightweight determination
        if ($override || !$perfService->isMetaTagPresent('og:type', 'property')) {
            $ogType = ($option === 'com_content' && $view === 'article') ? 'article' : 'website';
            $perfService->addMetaToBatch('og:type', $ogType, 'property');
        }

        // og:site_name - from config, no DB hit
        $siteName = $this->params->get('og_site_name', $this->params->get('org_name', ''));
        if (!empty($siteName) && ($override || !$perfService->isMetaTagPresent('og:site_name', 'property'))) {
            $perfService->addMetaToBatch('og:site_name', $siteName, 'property');
        }

        // og:title - from document (already loaded)
        if ($override || !$perfService->isMetaTagPresent('og:title', 'property')) {
            $title = method_exists($document, 'getTitle') ? trim($document->getTitle()) : '';
            if (!empty($title)) {
                $perfService->addMetaToBatch('og:title', $title, 'property');
            }
        }

        // og:description - from document (already loaded)
        if ($override || !$perfService->isMetaTagPresent('og:description', 'property')) {
            $description = method_exists($document, 'getDescription') ? trim($document->getDescription()) : '';
            if (!empty($description)) {
                $perfService->addMetaToBatch('og:description', $description, 'property');
            }
        }

        // og:url - from current request (fast)
        if ($override || !$perfService->isMetaTagPresent('og:url', 'property')) {
            $currentUrl = Uri::getInstance()->toString(['scheme', 'host', 'port', 'path', 'query']);
            $perfService->addMetaToBatch('og:url', $currentUrl, 'property');
        }

        // og:image - from config only (no file system checks here)
        $fallbackImage = $this->params->get('og_image', $this->params->get('org_logo', ''));
        if (!empty($fallbackImage) && ($override || !$perfService->isMetaTagPresent('og:image', 'property'))) {
            $perfService->addMetaToBatch('og:image', $fallbackImage, 'property');
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
        if (!empty($twitterCreator) && $twitterCreator !== $twitterSite && 
            ($override || !$perfService->isMetaTagPresent('twitter:creator'))) {
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
    }

    /**
     * Get article image URL (heavy operation - DB query)
     */
    private function getArticleImage(): string
    {
        try {
            $articleId = $this->app->getInput()->getInt('id', 0);
            if ($articleId <= 0) {
                return '';
            }

            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('images, introtext, fulltext')
                ->from('#__content')
                ->where('id = ' . (int) $articleId)
                ->where('published = 1');

            $db->setQuery($query);
            $article = $db->loadObject();

            if (!$article) {
                return '';
            }

            // Try to extract from images JSON
            if (!empty($article->images)) {
                $images = json_decode($article->images, true);
                if (is_array($images)) {
                    // Try intro image first, then fulltext image
                    $imageFields = ['image_intro', 'image_fulltext'];
                    foreach ($imageFields as $field) {
                        if (!empty($images[$field])) {
                            return $this->normalizeImageUrl($images[$field]);
                        }
                    }
                }
            }

            // Extract from article text as fallback
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
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('created, modified')
                ->from('#__content')
                ->where('id = ' . (int) $articleId);

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
}
