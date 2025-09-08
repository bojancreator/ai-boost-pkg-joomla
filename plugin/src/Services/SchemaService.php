<?php

/**
 * Schema.org Service for JoomlaBoost
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System.Services
 * @since       4.0
 * @author      JoomlaBoost Team
 * @copyright   (C) 2025 JoomlaBoost
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Services;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Content\Site\Model\ArticleModel;
use Joomla\Component\Content\Site\Model\CategoryModel;
use Joomla\Registry\Registry;

// Make sure Joomla constants are available
if (!defined('JPATH_ROOT')) {
    define('JPATH_ROOT', realpath(__DIR__ . '/../../../../../../..'));
}
if (!defined('JPATH_SITE')) {
    define('JPATH_SITE', JPATH_ROOT);
}

/**
 * Schema.org Structured Data Service
 *
 * Generates JSON-LD structured data for better SEO
 */
class SchemaService extends AbstractService
{
    /**
     * Get the correct domain URL for Schema.org markup
     * Uses automatic domain detection from the parent AbstractService
     *
     * @return string The correct domain URL with trailing slash
     */
    private function getSchemaUrl(): string
    {
        // Use the automatic domain detection from AbstractService
        $baseUrl = $this->getBaseUrl();

        // Ensure trailing slash for consistency
        return rtrim($baseUrl, '/') . '/';
    }

    /**
     * Main schema generation method with performance optimizations
     *
     * @return array<int, array<string, mixed>>
     */
    public function generateSchema(): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        // Get performance service for caching
        $perfService = new PerformanceService($this->app, $this->params);
        $cacheKey = 'schema_' . $perfService->getPageCacheKey();

        // Check request-level cache first
        if ($perfService->cacheHas($cacheKey)) {
            $this->logDebug('Schema loaded from request cache');
            return $perfService->cacheGet($cacheKey);
        }

        $schema = [];
        $input = $this->app->getInput();
        $option = $input->getCmd('option');
        $view = $input->getCmd('view');

        // Always add lightweight schemas (no DB queries)
        $schema[] = $this->generateWebsiteSchema();
        $schema[] = $this->generateOrganizationSchema();

        // Add context-specific schemas only if needed (heavy operations)
        if ($option === 'com_content') {
            switch ($view) {
                case 'article':
                    if ($perfService->needsHeavyOperations()) {
                        $perfService->initializeHeavyOperations();
                        $articleSchema = $this->generateArticleSchema($perfService);
                        if ($articleSchema) {
                            $schema[] = $articleSchema;
                        }
                    }
                    break;

                case 'category':
                    if ($perfService->needsHeavyOperations()) {
                        $perfService->initializeHeavyOperations();
                        $categorySchema = $this->generateCategorySchema($perfService);
                        if ($categorySchema) {
                            $schema[] = $categorySchema;
                        }
                    }
                    break;

                case 'featured':
                    $schema[] = $this->generateBlogSchema();
                    break;
            }
        }

        // Add BreadcrumbList (lightweight - from existing pathway)
        $breadcrumbSchema = $this->generateBreadcrumbSchema();
        if ($breadcrumbSchema) {
            $schema[] = $breadcrumbSchema;
        }

        $filteredSchema = array_filter($schema);

        // Cache the result for this request
        $perfService->cacheSet($cacheKey, $filteredSchema);

        $this->logDebug('Schema generation completed', [
            'schemas_count' => count($filteredSchema),
            'page_type' => $option . '/' . $view,
            'heavy_ops_used' => $perfService->needsHeavyOperations() && $option === 'com_content'
        ]);

        return $filteredSchema;
    }

    /**
     * Generate Website schema
     *
     * @return array<string, mixed>
     */
    private function generateWebsiteSchema(): array
    {
        $config = Factory::getApplication()->getConfig();

        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $config->get('sitename'),
            'description' => $config->get('MetaDesc'),
            'url' => $this->getSchemaUrl(),
            'inLanguage' => $this->getLanguageCode(),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $this->getSchemaUrl() . 'index.php?option=com_search&searchword={search_term_string}'
                ],
                'query-input' => 'required name=search_term_string'
            ]
        ];
    }

    /**
     * Generate Organization schema
     *
     * @return array<string, mixed>
     */
    private function generateOrganizationSchema(): array
    {
        $config = Factory::getApplication()->getConfig();
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $config->get('sitename'),
            'url' => $this->getSchemaUrl(),
            'description' => $config->get('MetaDesc'),
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'contactType' => 'customer service',
                'availableLanguage' => $this->getLanguageCode()
            ]
        ];

        return $schema;
    }

    /**
     * Generate Article schema for content articles (optimized with caching)
     *
     * @return array<string, mixed>|null
     */
    private function generateArticleSchema(?PerformanceService $perfService = null): ?array
    {
        $id = $this->app->getInput()->getInt('id');
        if (!$id) {
            return null;
        }

        // Use caching if performance service is available
        if ($perfService) {
            $articleCacheKey = 'article_schema_' . $id;
            if ($perfService->cacheHas($articleCacheKey)) {
                return $perfService->cacheGet($articleCacheKey);
            }
        }

        try {
            // More efficient DB query - only get needed fields
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('id, title, introtext, fulltext, metadesc, metakey, created, modified, created_by_alias, images')
                ->from('#__content')
                ->where('id = ' . (int) $id)
                ->where('published = 1');

            $db->setQuery($query);
            $article = $db->loadObject();

            if (!$article) {
                return null;
            }

            $config = $this->app->getConfig();
            $dateCreated = Factory::getDate($article->created)->toISO8601();
            $dateModified = Factory::getDate($article->modified ?: $article->created)->toISO8601();

            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $article->title,
                'description' => $article->metadesc ?: $this->extractDescription($article->introtext),
                'articleBody' => strip_tags($article->fulltext ?: $article->introtext),
                'url' => Uri::getInstance()->toString(),
                'datePublished' => $dateCreated,
                'dateModified' => $dateModified,
                'inLanguage' => $this->getLanguageCode(),
                'author' => [
                    '@type' => 'Person',
                    'name' => $article->created_by_alias ?: 'Author'
                ],
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => $config->get('sitename'),
                    'url' => $this->getSchemaUrl()
                ]
            ];

            // Add images if available (optimized extraction)
            $images = $this->extractImagesOptimized($article);
            if (!empty($images)) {
                $schema['image'] = $images;
            }

            // Add keywords from meta_keywords
            if (!empty($article->metakey)) {
                $keywords = array_map('trim', explode(',', $article->metakey));
                $schema['keywords'] = array_filter($keywords);
            }

            // Cache the result if performance service is available
            if ($perfService) {
                $perfService->cacheSet($articleCacheKey, $schema);
            }

            return $schema;
        } catch (\Throwable $e) {
            $this->logDebug('Article schema generation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate Category schema for content categories (optimized with caching)
     *
     * @return array<string, mixed>|null
     */
    private function generateCategorySchema(?PerformanceService $perfService = null): ?array
    {
        $id = $this->app->getInput()->getInt('id');
        if (!$id) {
            return null;
        }

        // Use caching if performance service is available
        if ($perfService) {
            $categoryCacheKey = 'category_schema_' . $id;
            if ($perfService->cacheHas($categoryCacheKey)) {
                return $perfService->cacheGet($categoryCacheKey);
            }
        }

        try {
            // Direct DB query instead of model for better performance
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('id, title, description, metadesc')
                ->from('#__categories')
                ->where('id = ' . (int) $id)
                ->where('published = 1');

            $db->setQuery($query);
            $category = $db->loadObject();

            if (!$category) {
                return null;
            }

            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $category->title,
                'description' => $category->metadesc ?: strip_tags($category->description),
                'url' => Uri::getInstance()->toString(),
                'inLanguage' => $this->getLanguageCode(),
                'isPartOf' => [
                    '@type' => 'WebSite',
                    'name' => $this->app->getConfig()->get('sitename'),
                    'url' => $this->getSchemaUrl()
                ]
            ];

            // Cache the result if performance service is available
            if ($perfService) {
                $perfService->cacheSet($categoryCacheKey, $schema);
            }

            return $schema;
        } catch (\Throwable $e) {
            $this->logDebug('Category schema generation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate Blog schema for featured articles
     *
     * @return array<string, mixed>
     */
    private function generateBlogSchema(): array
    {
        $config = Factory::getApplication()->getConfig();

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Blog',
            'name' => $config->get('sitename') . ' - Blog',
            'description' => $config->get('MetaDesc') ?: 'Najnoviji članci',
            'url' => Uri::getInstance()->toString(),
            'inLanguage' => $this->getLanguageCode(),
            'publisher' => [
                '@type' => 'Organization',
                'name' => $config->get('sitename'),
                'url' => $this->getSchemaUrl()
            ]
        ];
    }

    /**
     * Generate BreadcrumbList schema
     *
     * @return array<string, mixed>|null
     */
    private function generateBreadcrumbSchema(): ?array
    {
        $pathway = $this->app->getPathway();
        $items = $pathway->getPathWay();

        if (empty($items)) {
            return null;
        }

        $listItems = [];
        $position = 1;

        // Add home
        $listItems[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => 'Početna',
            'item' => $this->getSchemaUrl()
        ];

        // Add pathway items
        foreach ($items as $item) {
            $listItems[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $item->name,
                'item' => $item->link ? $this->getSchemaUrl() . ltrim($item->link, '/') : Uri::getInstance()->toString()
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $listItems
        ];
    }

    /**
     * Get language code for schema
     */
    private function getLanguageCode(): string
    {
        $lang = Factory::getLanguage();
        return $lang->getTag();
    }

    /**
     * Extract description from content
     */
    private function extractDescription(string $content): string
    {
        $text = strip_tags($content);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if (strlen($text) > 160) {
            $text = substr($text, 0, 160);
            $lastSpace = strrpos($text, ' ');
            if ($lastSpace !== false) {
                $text = substr($text, 0, $lastSpace);
            }
            $text .= '...';
        }

        return $text;
    }

    /**
     * Optimized image extraction from article (performance improvements)
     *
     * @param object $article Article object with introtext/fulltext/images properties
     * @return array<int, string>
     */
    private function extractImagesOptimized(object $article): array
    {
        $images = [];

        // First try JSON images (fastest)
        if (!empty($article->images)) {
            $articleImages = json_decode($article->images, true);
            if (is_array($articleImages)) {
                if (!empty($articleImages['image_intro'])) {
                    $images[] = $this->normalizeImageUrl($articleImages['image_intro']);
                }
                if (!empty($articleImages['image_fulltext'])) {
                    $fullImage = $this->normalizeImageUrl($articleImages['image_fulltext']);
                    if (!in_array($fullImage, $images, true)) {
                        $images[] = $fullImage;
                    }
                }
            }
        }

        // If we have images from JSON, limit content parsing for performance
        if (!empty($images)) {
            // Only extract first 2 images from content to avoid over-processing
            $content = substr((string)($article->introtext ?? ''), 0, 2000); // Limit content parsing
            if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
                $src = $this->normalizeImageUrl($matches[1]);
                if (!in_array($src, $images, true)) {
                    $images[] = $src;
                }
            }
        } else {
            // No JSON images, do full content extraction
            $content = (string)($article->introtext ?? '') . (string)($article->fulltext ?? '');
            preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);

            if (!empty($matches[1])) {
                foreach (array_slice($matches[1], 0, 5) as $src) { // Limit to 5 images max
                    $normalizedSrc = $this->normalizeImageUrl($src);
                    if (!in_array($normalizedSrc, $images, true)) {
                        $images[] = $normalizedSrc;
                    }
                }
            }
        }

        return array_slice($images, 0, 3); // Maximum 3 images for performance
    }

    /**
     * Normalize image URL to absolute URL (optimized)
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
        $baseUrl = rtrim($this->getSchemaUrl(), '/');

        if (str_starts_with($imageUrl, '/')) {
            return $baseUrl . $imageUrl;
        } else {
            return $baseUrl . '/' . $imageUrl;
        }
    }

    /**
     * Inject schema into document head
     */
    public function injectSchema(): void
    {
        if (!$this->isEnabled() || !$this->allowSearchEngines()) {
            return;
        }

        $schema = $this->generateSchema();

        if (empty($schema)) {
            return;
        }

        $document = Factory::getDocument();

        foreach ($schema as $schemaItem) {
            $json = json_encode($schemaItem, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $document->addCustomTag('<script type="application/ld+json">' . $json . '</script>');
        }
    }

    /**
     * Check if service is enabled
     */
    public function isEnabled(): bool
    {
        return (bool) $this->params->get('schema_enabled', true);
    }

    protected function getServiceKey(): string
    {
        return 'enable_schema';
    }
}
