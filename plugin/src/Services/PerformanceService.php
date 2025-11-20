<?php

/**
 * Performance Service for JoomlaBoost
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System.Services
 * @since       Joomla 4.0, PHP 8.1+
 * @author      JoomlaBoost Team
 * @copyright   (C) 2025 JoomlaBoost. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Services;

/**
 * Performance Service for Meta Tag optimization
 *
 * Optimizations:
 * - Memory-efficient caching of meta tag generation
 * - Batch processing of DOM modifications
 * - Lazy loading of heavy operations
 * - Request-level cache for repeated calls
 */
class PerformanceService extends AbstractService
{
    /** @var array<string, mixed> Request-level cache */
    private array $cache = [];

    /** @var array<string, string> Batch meta tags for single DOM operation */
    private array $metaBatch = [];

    /** @var bool Whether heavy operations have been initialized */
    private bool $heavyOpsInitialized = false;

    protected function getServiceKey(): string
    {
        return 'enable_performance';
    }

    /**
     * Cache data for the duration of current request
     */
    public function cacheGet(string $key): mixed
    {
        return $this->cache[$key] ?? null;
    }

    /**
     * Store data in request cache
     */
    public function cacheSet(string $key, mixed $value): void
    {
        $this->cache[$key] = $value;
    }

    /**
     * Check if cache key exists
     */
    public function cacheHas(string $key): bool
    {
        return isset($this->cache[$key]);
    }

    /**
     * Add meta tag to batch (avoids multiple DOM modifications)
     */
    public function addMetaToBatch(string $name, string $content, string $type = 'name'): void
    {
        if (empty($content)) {
            return;
        }

        $key = $type . ':' . $name;
        $this->metaBatch[$key] = $content;
    }

    /**
     * Process all batched meta tags in single DOM operation
     */
    public function processBatchedMeta(\Joomla\CMS\Document\HtmlDocument $document): int
    {
        if (empty($this->metaBatch)) {
            return 0;
        }

        $wrapMarkers = $this->params->get('debug_wrap_markers', 0);
        $ogTagsCount = 0;
        $standardTagsCount = 0;

        // Separate OpenGraph tags from standard tags for organized debug output
        $ogTags = [];
        $standardTags = [];

        foreach ($this->metaBatch as $key => $content) {
            [$type, $name] = explode(':', $key, 2);
            
            if ($type === 'property') {
                $ogTags[] = ['name' => $name, 'content' => $content];
            } else {
                $standardTags[] = ['name' => $name, 'content' => $content];
            }
        }

        // Process OpenGraph tags with debug markers
        if (!empty($ogTags)) {
            if ($wrapMarkers) {
                $document->addCustomTag("\n<!-- [JoomlaBoost DEBUG: OpenGraph Meta Tags - " . count($ogTags) . " tag(s)] -->");
            }

            foreach ($ogTags as $tag) {
                $document->addCustomTag('<meta property="' . htmlspecialchars($tag['name'], ENT_QUOTES, 'UTF-8') . '" content="' . htmlspecialchars($tag['content'], ENT_QUOTES, 'UTF-8') . '">');
                $ogTagsCount++;
            }

            if ($wrapMarkers) {
                $document->addCustomTag("<!-- [JoomlaBoost DEBUG END: OpenGraph Meta Tags] -->\n");
            }
        }

        // Process standard meta tags with debug markers
        if (!empty($standardTags)) {
            if ($wrapMarkers) {
                $document->addCustomTag("\n<!-- [JoomlaBoost DEBUG: Standard Meta Tags - " . count($standardTags) . " tag(s)] -->");
            }

            foreach ($standardTags as $tag) {
                $document->setMetaData($tag['name'], $tag['content']);
                $standardTagsCount++;
            }

            if ($wrapMarkers) {
                $document->addCustomTag("<!-- [JoomlaBoost DEBUG END: Standard Meta Tags] -->\n");
            }
        }

        // Clear the batch after processing
        $this->metaBatch = [];

        $processed = $ogTagsCount + $standardTagsCount;
        $this->logDebug("Processed {$processed} meta tags in batch operation", [
            'opengraph' => $ogTagsCount,
            'standard' => $standardTagsCount
        ]);
        return $processed;
    }

    /**
     * Get current page cache key (URL + query params hash)
     */
    public function getPageCacheKey(): string
    {
        if ($this->cacheHas('page_cache_key')) {
            return $this->cacheGet('page_cache_key');
        }

        try {
            $uri = \Joomla\CMS\Uri\Uri::getInstance();
            $url = $uri->toString(['scheme', 'host', 'port', 'path']);

            // Include relevant query params but exclude session/tracking
            $query = $uri->getQuery(true);
            unset(
                $query['Itemid'],
                $query['tmpl'],
                $query['_'],
                $query['utm_source'],
                $query['utm_medium'],
                $query['utm_campaign'],
                $query['fbclid'],
                $query['gclid']
            );

            ksort($query);
            $queryString = http_build_query($query);

            $cacheKey = 'jb_page_' . md5($url . '|' . $queryString);
            $this->cacheSet('page_cache_key', $cacheKey);

            return $cacheKey;
        } catch (\Throwable $e) {
            $fallbackKey = 'jb_page_fallback';
            $this->cacheSet('page_cache_key', $fallbackKey);
            return $fallbackKey;
        }
    }

    /**
     * Initialize heavy operations only when needed
     */
    public function initializeHeavyOperations(): void
    {
        if ($this->heavyOpsInitialized) {
            return;
        }

        $this->logDebug('Initializing heavy operations (DB connections, API calls)');

        // This is where we'd initialize:
        // - Database connections for schema data
        // - Article image extraction
        // - Complex meta calculations

        $this->heavyOpsInitialized = true;
    }

    /**
     * Check if heavy operations are needed for current page
     */
    public function needsHeavyOperations(): bool
    {
        $input = $this->app->getInput();
        $option = $input->getCmd('option');
        $view = $input->getCmd('view');

        // Only load heavy operations for content pages
        return ($option === 'com_content' && in_array($view, ['article', 'category', 'featured']));
    }

    /**
     * Memory-efficient meta tag existence check
     * Uses Document's internal metadata rather than regex parsing
     */
    public function isMetaTagPresent(string $name, string $type = 'name'): bool
    {
        try {
            $document = $this->app->getDocument();
            if (!($document instanceof \Joomla\CMS\Document\HtmlDocument)) {
                return false;
            }

            $metaData = $document->getMetaData();

            if ($type === 'property') {
                // Check for OpenGraph/property meta tags
                return isset($metaData[$name]) || $this->isPropertyMetaPresent($name);
            }

            return isset($metaData[$name]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check for property-type meta tags (og:, twitter:, etc.)
     */
    private function isPropertyMetaPresent(string $property): bool
    {
        // Check if already in our batch
        $batchKey = 'property:' . $property;
        if (isset($this->metaBatch[$batchKey])) {
            return true;
        }

        // For property-type meta, we need to check custom head data
        try {
            $document = $this->app->getDocument();
            $headData = $document->getHeadData();

            if (isset($headData['custom']) && is_array($headData['custom'])) {
                foreach ($headData['custom'] as $customTag) {
                    if (is_string($customTag) && str_contains($customTag, 'property="' . $property . '"')) {
                        return true;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore errors
        }

        return false;
    }

    /**
     * Get performance metrics for debugging
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'cache_entries' => count($this->cache),
            'batched_meta_tags' => count($this->metaBatch),
            'heavy_ops_initialized' => $this->heavyOpsInitialized,
            'page_cache_key' => $this->getPageCacheKey(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
    }
}
