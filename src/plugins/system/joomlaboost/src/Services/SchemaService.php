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
     * Get localized parameter value with language fallback
     *
     * Priority: lang-specific param → EN param → generic param → database
     *
     * @param string $fieldName Base field name (e.g., 'org_name', 'schema_description')
     * @param mixed $default Default value if all fields are empty
     * @return mixed Localized value or fallback
     */
    private function getLocalizedParam(string $fieldName, $default = '')
    {
        $lang     = Factory::getLanguage();
        $langCode = strtolower(substr($lang->getTag(), 0, 2)); // e.g. sr-RS -> sr

        // 1. Try current language param (e.g. org_name_sr)
        $value = $this->params->get("{$fieldName}_{$langCode}", '');
        if (!empty($value)) {
            $this->logDebug("Schema: Using param {$fieldName}_{$langCode}");
            return $value;
        }

        // 2. Try English param (e.g. org_name_en)
        if ($langCode !== 'en') {
            $value = $this->params->get("{$fieldName}_en", '');
            if (!empty($value)) {
                $this->logDebug("Schema: Fallback to {$fieldName}_en");
                return $value;
            }
        }

        // 3. Try generic/legacy param (e.g. org_name) - backward compat with v0.5.x
        $value = $this->params->get($fieldName, '');
        if (!empty($value)) {
            $this->logDebug("Schema: Using legacy param {$fieldName}");
            return $value;
        }

        // 4. Try database-backed translations (last resort)
        try {
            $translationService = new TranslationService($this->app, $this->params);
            $value = $translationService->get($fieldName);
            if (!empty($value)) {
                $this->logDebug("Schema: DB translation for {$fieldName} ({$langCode})");
                return $value;
            }
        } catch (\Exception $e) {
            $this->logDebug("Schema TranslationService error: {$e->getMessage()}");
        }

        return $default;
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

        // FAQ schema — auto-detected from article content (article pages only)
        if ($option === 'com_content' && $view === 'article' && $perfService->needsHeavyOperations()) {
            $contentFaqSchema = $this->generateContentFAQSchema();
            if ($contentFaqSchema) {
                $schema[] = $contentFaqSchema;
            }
        }

        // FAQ schema — manually configured in plugin settings (any page)
        $manualFaqSchema = $this->generateFAQSchema();
        if ($manualFaqSchema) {
            $schema[] = $manualFaqSchema;
        }

        // Event schema — from plugin configuration (any page)
        $eventsSchemas = $this->generateEventsSchema();
        foreach ($eventsSchemas as $eventSchema) {
            $schema[] = $eventSchema;
        }

        $filteredSchema = array_filter($schema);

        // Cache the result for this request
        $perfService->cacheSet($cacheKey, $filteredSchema);

        $this->logDebug('Schema generation completed', [
            'schemas_count' => count($filteredSchema),
            'page_type'     => $option . '/' . $view,
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

        // WebSite description should describe the whole site/organization,
        // NOT the current page. Use org_description from plugin settings,
        // fall back to Joomla global MetaDesc only if not set.
        $description = $this->getLocalizedParam('org_description', '');
        if (empty($description)) {
            $description = (string) $config->get('MetaDesc', '');
        }

        return [
            '@context'       => 'https://schema.org',
            '@type'          => 'WebSite',
            'name'           => $config->get('sitename'),
            'description'    => $description,
            'url'            => $this->getSchemaUrl(),
            'inLanguage'     => $this->getLanguageCode(),
            'potentialAction' => [
                '@type'  => 'SearchAction',
                'target' => [
                    '@type'       => 'EntryPoint',
                    // com_finder is the modern Joomla Smart Search (com_search is deprecated since Joomla 4)
                    'urlTemplate' => $this->getSchemaUrl() . 'index.php?option=com_finder&q={search_term_string}'
                ],
                'query-input' => 'required name=search_term_string'
            ]
        ];
    }

    /**
     * Generate Organization schema with LocalBusiness enhancement
     *
     * @return array<string, mixed>
     */
    private function generateOrganizationSchema(): array
    {
        $config = Factory::getApplication()->getConfig();
        $baseUrl = $this->getSchemaUrl();

        // Get organization name with language support
        $orgName = $this->getLocalizedParam('org_name', $config->get('sitename'));

        // Get organization description with language support
        $orgDescription = $this->getLocalizedParam('org_description', $config->get('MetaDesc') ?: ($orgName . ' - Professional services'));

        // Get organization logo with language support (per-language logo, fallback to og_image)
        $orgLogoRaw = $this->getLocalizedParam('org_logo', (string) $this->params->get('og_image', ''));
        $orgLogo = '';
        if (!empty($orgLogoRaw)) {
            // Strip Joomla media picker fragment: #joomlaImage://local-images/...
            if (str_contains($orgLogoRaw, '#joomlaImage://')) {
                $orgLogoRaw = explode('#joomlaImage://', $orgLogoRaw)[0];
            }
            // Strip query parameters (width, height etc.) that confuse schema validators
            if (str_contains($orgLogoRaw, '?')) {
                $orgLogoRaw = explode('?', $orgLogoRaw)[0];
            }
            $orgLogoRaw = trim($orgLogoRaw);
            $orgLogo = str_starts_with($orgLogoRaw, 'http')
                ? $orgLogoRaw
                : rtrim($baseUrl, '/') . '/' . ltrim($orgLogoRaw, '/');
        }

        // Determine schema type from config (auto-detect by default)
        $schemaType = $this->params->get('schema_type', 'auto');

        if ($schemaType === 'auto') {
            // Auto-detect based on presence of geo/business fields
            $hasGeo = !empty($this->params->get('schema_latitude')) || !empty($this->params->get('schema_longitude'));
            $hasAddress = !empty($this->params->get('schema_address_country'));
            $schemaType = ($hasGeo || $hasAddress) ? 'localbusiness' : 'organization';
        }

        if ($schemaType === 'hotel') {
            // Hotel / LodgingBusiness schema with accommodation-specific fields
            $schema = [
                '@context' => 'https://schema.org',
                '@type'    => 'LodgingBusiness',
                'name'     => $orgName,
                'url'      => $baseUrl,
                'description' => $orgDescription,
                'address'  => [
                    '@type'           => 'PostalAddress',
                    'addressCountry'  => $this->params->get('schema_address_country', ''),
                    'addressLocality' => $this->getLocalizedParam('schema_address_locality', ''),
                    'streetAddress'   => $this->getLocalizedParam('schema_address_street', ''),
                    'postalCode'      => $this->params->get('schema_address_zip', ''),
                ],
                'telephone' => $this->params->get('schema_phone', ''),
                'email'     => $this->params->get('schema_email', ''),
                'petsAllowed' => (bool) $this->params->get('schema_hotel_pets_allowed', 0),
                'checkInTime'  => $this->params->get('schema_hotel_checkin_time', '14:00'),
                'checkOutTime' => $this->params->get('schema_hotel_checkout_time', '11:00'),
                'priceRange' => $this->params->get('schema_price_range', '$$'),
                'sameAs' => $this->getSocialMediaProfiles($baseUrl),
            ];

            // Star Rating
            $starRating = (int) $this->params->get('schema_hotel_star_rating', 0);
            if ($starRating > 0) {
                $schema['starRating'] = [
                    '@type'       => 'Rating',
                    'ratingValue' => $starRating,
                    'bestRating'  => 5,
                    'worstRating' => 1,
                ];
            }

            // Geo coordinates
            $latitude  = $this->params->get('schema_latitude', '');
            $longitude = $this->params->get('schema_longitude', '');
            if (!empty($latitude) && !empty($longitude)) {
                $schema['geo'] = [
                    '@type'     => 'GeoCoordinates',
                    'latitude'  => (float) $latitude,
                    'longitude' => (float) $longitude,
                ];
            }

            // Logo
            if (!empty($orgLogo)) {
                $schema['logo']  = $orgLogo;
                $schema['image'] = $orgLogo;
            }
            // AggregateRating (guest reviews)
            $aggregateRating = $this->buildAggregateRating();
            if ($aggregateRating !== null) {
                $schema['aggregateRating'] = $aggregateRating;
            }
        } elseif ($schemaType === 'localbusiness') {
            // LocalBusiness schema with geo and address data
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'LocalBusiness',
                'name' => $orgName,
                'url' => $baseUrl,
                'description' => $orgDescription,
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressCountry'  => $this->params->get('schema_address_country', 'RS'),
                    'addressLocality' => $this->getLocalizedParam('schema_address_locality', 'Budva'),
                    'streetAddress'   => $this->getLocalizedParam('schema_address_street', ''),
                    'postalCode'      => $this->params->get('schema_address_zip', '')
                ],
                'contactPoint' => [
                    '@type'             => 'ContactPoint',
                    'contactType'       => 'customer service',
                    'telephone'         => $this->params->get('schema_phone', ''),
                    'email'             => $this->params->get('schema_email', ''),
                    'availableLanguage' => [$this->getLanguageCode(), 'en']
                ],
                'priceRange' => $this->params->get('schema_price_range', '$$'),
                'openingHours' => $this->params->get('schema_opening_hours', 'Mo-Su 09:00-18:00'),
                'sameAs' => $this->getSocialMediaProfiles($baseUrl)
            ];

            // Add geo coordinates if configured
            $latitude = $this->params->get('schema_latitude', '');
            $longitude = $this->params->get('schema_longitude', '');
            if (!empty($latitude) && !empty($longitude)) {
                $schema['geo'] = [
                    '@type' => 'GeoCoordinates',
                    'latitude' => (float)$latitude,
                    'longitude' => (float)$longitude
                ];

                // Add areaServed based on country
                $countryCode = $this->params->get('schema_address_country', 'RS');
                // Comprehensive ISO 3166-1 alpha-2 → country name map (extend as needed)
                $countryNames = [
                    'AD' => 'Andorra',          'AL' => 'Albania',
                    'AT' => 'Austria',          'BA' => 'Bosnia and Herzegovina',
                    'BE' => 'Belgium',          'BG' => 'Bulgaria',
                    'CH' => 'Switzerland',      'CY' => 'Cyprus',
                    'CZ' => 'Czech Republic',   'DE' => 'Germany',
                    'DK' => 'Denmark',          'EE' => 'Estonia',
                    'ES' => 'Spain',            'FI' => 'Finland',
                    'FR' => 'France',           'GB' => 'United Kingdom',
                    'GR' => 'Greece',           'HR' => 'Croatia',
                    'HU' => 'Hungary',          'IE' => 'Ireland',
                    'IT' => 'Italy',            'LT' => 'Lithuania',
                    'LU' => 'Luxembourg',       'LV' => 'Latvia',
                    'ME' => 'Montenegro',       'MK' => 'North Macedonia',
                    'MT' => 'Malta',            'NL' => 'Netherlands',
                    'NO' => 'Norway',           'PL' => 'Poland',
                    'PT' => 'Portugal',         'RO' => 'Romania',
                    'RS' => 'Serbia',           'RU' => 'Russia',
                    'SE' => 'Sweden',           'SI' => 'Slovenia',
                    'SK' => 'Slovakia',         'TR' => 'Turkey',
                    'UA' => 'Ukraine',          'US' => 'United States',
                    'AU' => 'Australia',        'CA' => 'Canada',
                    'CN' => 'China',            'IN' => 'India',
                    'JP' => 'Japan',            'BR' => 'Brazil',
                    'MX' => 'Mexico',           'ZA' => 'South Africa',
                    'AE' => 'United Arab Emirates',
                ];
                $schema['areaServed'] = [
                    '@type' => 'Country',
                    'name' => $countryNames[$countryCode] ?? $countryCode,
                ];
            }

            // Add logo if configured
            if (!empty($orgLogo)) {
                $schema['logo'] = $orgLogo;
                $schema['image'] = $orgLogo;
            }

            // AggregateRating (guest reviews)
            $aggregateRating = $this->buildAggregateRating();
            if ($aggregateRating !== null) {
                $schema['aggregateRating'] = $aggregateRating;
            }
        } else {
            // Standard Organization schema for other sites
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => $orgName,
                'url' => $baseUrl,
                'description' => $orgDescription,
                'contactPoint' => [
                    '@type' => 'ContactPoint',
                    'contactType' => 'customer service',
                    'availableLanguage' => $this->getLanguageCode()
                ],
                'sameAs' => $this->getSocialMediaProfiles($baseUrl)
            ];

            // Add logo if configured
            if (!empty($orgLogo)) {
                $schema['logo'] = $orgLogo;
                $schema['image'] = $orgLogo;
            }
        }

        return $schema;
    }

    /**
     * Build AggregateRating block from plugin settings.
     * Returns null if no rating value is configured.
     *
     * @return array<string, mixed>|null
     */
    private function buildAggregateRating(): ?array
    {
        $ratingValue = trim((string) $this->params->get('schema_rating_value', ''));
        $ratingCount = trim((string) $this->params->get('schema_rating_count', ''));

        if (empty($ratingValue)) {
            return null;
        }

        $rating = [
            '@type'       => 'AggregateRating',
            // Cast to float — schema validators require numeric type (not string)
            'ratingValue' => (float) $ratingValue,
            'bestRating'  => (float) $this->params->get('schema_rating_best', '5'),
            'worstRating' => (float) $this->params->get('schema_rating_worst', '1'),
        ];

        if (!empty($ratingCount)) {
            $rating['reviewCount'] = $ratingCount;
        }

        $source = trim((string) $this->params->get('schema_rating_source', ''));
        if (!empty($source)) {
            $rating['description'] = 'Based on reviews from ' . $source;
        }

        return $rating;
    }
    /**
     * Get social media profiles for the organization
     *
     * @param string $baseUrl Site base URL
     * @return array<int, string>
     */
    private function getSocialMediaProfiles(string $baseUrl): array
    {
        $profiles = [];

        // Read social media URLs from plugin configuration
        $socialFields = [
            'schema_social_facebook',
            'schema_social_instagram',
            'schema_social_youtube',
            'schema_social_twitter',
            'schema_social_linkedin'
        ];

        foreach ($socialFields as $field) {
            $url = trim((string) $this->params->get($field, ''));
            if (!empty($url)) {
                $profiles[] = $url;
            }
        }

        return array_filter($profiles);
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
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'title', 'introtext', 'fulltext', 'metadesc', 'metakey', 'created', 'modified', 'created_by', 'created_by_alias', 'images']))
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('id') . ' = ' . (int) $id)
                ->where($db->quoteName('published') . ' = 1');

            $db->setQuery($query);
            $article = $db->loadObject();

            if (!$article) {
                return null;
            }

            $config = $this->app->getConfig();
            $dateCreated = Factory::getDate($article->created)->toISO8601();
            $dateModified = Factory::getDate($article->modified ?: $article->created)->toISO8601();

            $schema = [
                '@context'    => 'https://schema.org',
                '@type'       => 'Article',
                'headline'    => $article->title,
                'description' => $article->metadesc ?: $this->extractDescription($article->introtext),
                'articleBody' => strip_tags($article->fulltext ?: $article->introtext),
                'url'         => Uri::getInstance()->toString(),
                'datePublished' => $dateCreated,
                'dateModified'  => $dateModified,
                'inLanguage'    => $this->getLanguageCode(),
                // Enhanced author for E-E-A-T: try full Joomla user profile first
                'author'    => $this->getAuthorSchema((int) ($article->created_by ?? 0), $article->created_by_alias),
                'publisher' => [
                    '@type' => 'Organization',
                    'name'  => $config->get('sitename'),
                    'url'   => $this->getSchemaUrl()
                ],
                // Speakable: help Google Assistant / voice search identify the most
                // important spoken content on the page (h1 + intro paragraph)
                'speakable' => [
                    '@type'       => 'SpeakableSpecification',
                    'cssSelector' => ['h1', '.article-intro', '.item-intro', '.com-content-article__intro']
                ]
            ];

            // Add images if available (optimized extraction)
            $images = $this->extractImagesOptimized($article);
            if (!empty($images)) {
                $schema['image'] = $images;
            }

            // Add keywords from meta_keywords
            if (!empty($article->metakey)) {
                $keywords          = array_map('trim', explode(',', $article->metakey));
                $schema['keywords'] = array_filter($keywords);
            }

            // VideoObject: auto-detect YouTube/Vimeo embeds in article content
            $videoObject = $this->detectVideoObject($article->introtext . ' ' . $article->fulltext);
            if ($videoObject !== null) {
                $schema['video'] = $videoObject;
                $this->logDebug('Article schema: VideoObject detected and added');
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
            '@type'    => 'ListItem',
            'position' => $position++,
            'name'     => 'Home',
            'item'     => $this->getSchemaUrl()
        ];

        $baseUrl = rtrim($this->getSchemaUrl(), '/');

        // Add pathway items — convert internal Joomla URLs to SEF URLs via Route::_()
        foreach ($items as $item) {
            $itemObj  = (object) $item;
            $itemName = isset($itemObj->name) ? (string) $itemObj->name : '';
            $itemLink = isset($itemObj->link) ? (string) $itemObj->link : '';

            if ($itemLink) {
                try {
                    // Route::_(url, false) = convert to SEF, no HTML-encoding
                    $sefPath = Route::_($itemLink, false);
                    // Ensure absolute URL
                    $itemUrl = str_starts_with($sefPath, 'http')
                        ? $sefPath
                        : $baseUrl . '/' . ltrim($sefPath, '/');
                } catch (\Throwable $e) {
                    // Fallback to raw link if routing fails
                    $itemUrl = $baseUrl . '/' . ltrim($itemLink, '/');
                }
            } else {
                $itemUrl = Uri::getInstance()->toString(['scheme', 'host', 'path']);
            }

            $listItems[] = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'name'     => $itemName,
                'item'     => $itemUrl
            ];
        }

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $listItems
        ];
    }

    /**
     * Generate FAQ schema using QAManagementService (supports multi-language fallback)
     *
     * @return array<string, mixed>|null
     */
    private function generateFAQSchema(): ?array
    {
        // Check if FAQ schema is enabled
        if (!(bool)$this->params->get('faq_schema_enabled', 1)) {
            return null;
        }

        if (!(bool)$this->params->get('enable_manual_faqs', 0)) {
            return null;
        }

        // Use QAManagementService for proper multi-language support and fallback chain
        try {
            $qaService  = new QAManagementService($this->app, $this->params);
            $faqItems   = $qaService->getManualFAQs();
        } catch (\Throwable $e) {
            $this->logDebug('FAQ schema: QAManagementService error - ' . $e->getMessage());

            // Last-resort fallback: read generic manual_faqs param directly
            $faqItems = [];
            $json     = trim((string)$this->params->get('manual_faqs', ''));
            if (!empty($json)) {
                try {
                    $raw = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($raw)) {
                        foreach ($raw as $item) {
                            if (!empty($item['question']) && !empty($item['answer'])) {
                                $faqItems[] = [
                                    '@type' => 'Question',
                                    'name'  => $item['question'],
                                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']]
                                ];
                            }
                        }
                    }
                } catch (\JsonException $je) {
                    // Skip invalid JSON
                }
            }
        }

        if (empty($faqItems)) {
            $this->logDebug('FAQ schema: No FAQ items found');
            return null;
        }

        $this->logDebug('FAQ schema: Generated with ' . count($faqItems) . ' items');
        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $faqItems
        ];
    }


    /**
     * Generate FAQ schema by auto-detecting Q&A pairs from current article content
     *
     * Supported HTML patterns (in priority order):
     * 1. <dl><dt>Question</dt><dd>Answer</dd></dl>  ← recommended, semantic HTML
     * 2. <h3>Question</h3><p>Answer</p>             ← common heading + paragraph
     * 3. <h4>Question</h4><p>Answer</p>             ← sub-heading variant
     *
     * @return array<string, mixed>|null
     */
    private function generateContentFAQSchema(): ?array
    {
        $input     = $this->app->getInput();
        $articleId = (int)$input->getInt('id');

        if (!$articleId) {
            return null;
        }

        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('introtext, fulltext')
                ->from('#__content')
                ->where('id = ' . (int)$articleId)
                ->where('state = 1');

            $db->setQuery($query);
            $row = $db->loadObject();

            if (!$row) {
                return null;
            }

            $content = ($row->introtext ?? '') . ' ' . ($row->fulltext ?? '');

            if (empty(trim($content))) {
                return null;
            }

            $faqItems = $this->extractFAQFromContent($content);

            if (empty($faqItems)) {
                $this->logDebug('FAQ auto-detect: No Q&A patterns found in article ' . $articleId);
                return null;
            }

            $this->logDebug('FAQ auto-detect: Found ' . count($faqItems) . ' Q&A pairs in article ' . $articleId);

            return [
                '@context'   => 'https://schema.org',
                '@type'      => 'FAQPage',
                'mainEntity' => $faqItems,
            ];
        } catch (\Throwable $e) {
            $this->logDebug('FAQ auto-detect error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract FAQ Q&A pairs from HTML content using DOMDocument
     *
     * Parser hierarchy:
     * 1. <dl> with <dt>/<dd> pairs  (most semantic, preferred for editors)
     * 2. <h3> or <h4> followed by <p> or <div>  (common content pattern)
     *
     * @param string $html  Raw HTML content from article
     * @return array<int, array<string, mixed>>  Array of Schema.org Question objects
     */
    private function extractFAQFromContent(string $html): array
    {
        // Suppress DOMDocument warnings (malformed HTML is common in CMS content)
        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $faqItems = [];
        $xpath    = new \DOMXPath($dom);

        // ── Find the content root node ─────────────────────────────────────────
        // Priority: <main> → <article> → <div id="content"> → entire body
        // This prevents picking up dl/dt/dd from YooTheme cookie consent modal,
        // navigation, sidebars and other non-content areas of the page.
        $contentRoot = null;

        $candidates = [
            '//main',
            '//article',
            '//*[@id="content"]',
            '//*[@id="main-content"]',
            '//*[contains(concat(" ",normalize-space(@class)," ")," uk-section ")]',  // YooTheme section (multi-class safe)
        ];

        foreach ($candidates as $xpathExpr) {
            $nodes = $xpath->query($xpathExpr);
            if ($nodes && $nodes->length > 0) {
                $contentRoot = $nodes->item(0);
                $this->logDebug('FAQ extract: Content root found via ' . $xpathExpr);
                break;
            }
        }

        // Fallback: use DOMDocument as XPath context (searches all elements)
        // Note: do NOT use $dom->documentElement — for HTML fragments (e.g. extracted
        // jb--faq container), documentElement is the first child element, so .//*
        // would only search its descendants, missing sibling el-items and the root itself.
        if ($contentRoot === null) {
            $this->logDebug('FAQ extract: No content root found, scanning full document');
            $contentRoot = $dom;
        }

        // ── Pattern 1: <dl> / <dt> / <dd> ────────────────────────────────────
        $dlNodes = $xpath->query('.//dl', $contentRoot);
        if ($dlNodes && $dlNodes->length > 0) {
            foreach ($dlNodes as $dl) {
                $dtNodes = $xpath->query('./dt', $dl);
                $ddNodes = $xpath->query('./dd', $dl);

                if (!$dtNodes || !$ddNodes) {
                    continue;
                }

                $count = min($dtNodes->length, $ddNodes->length);
                for ($i = 0; $i < $count; $i++) {
                    $question = trim($dtNodes->item($i)->textContent ?? '');
                    $answer   = trim($ddNodes->item($i)->textContent ?? '');

                    if ($question !== '' && $answer !== '') {
                        $faqItems[] = $this->buildFAQItem($question, $answer);
                    }
                }
            }
        }

        // ── Pattern 2: <h3> or <h4> followed by <p> or <div> ────────────────
        // Only run if <dl> pattern found nothing (to avoid mixing sources)
        if (empty($faqItems)) {
            $headingNodes = $xpath->query('.//*[self::h3 or self::h4]', $contentRoot);
            if ($headingNodes) {
                foreach ($headingNodes as $heading) {
                    $question = trim($heading->textContent ?? '');
                    if ($question === '') {
                        continue;
                    }

                    // Look for the next sibling that is a <p>, <div>, or <ul>
                    $sibling = $heading->nextSibling;
                    while ($sibling && $sibling->nodeType === XML_TEXT_NODE) {
                        $sibling = $sibling->nextSibling; // skip whitespace text nodes
                    }

                    if (
                        $sibling &&
                        $sibling->nodeType === XML_ELEMENT_NODE &&
                        in_array(strtolower($sibling->nodeName), ['p', 'div', 'ul', 'ol'], true)
                    ) {
                        $answer = trim($sibling->textContent ?? '');
                        if ($answer !== '' && strlen($answer) > 15) { // min length sanity check
                            $faqItems[] = $this->buildFAQItem($question, $answer);
                        }
                    }
                }
            }
        }

        // ── Pattern 3: YooTheme UIkit — el-item > el-title + el-content ─────
        // Covers Accordion (<a class="el-title">), Grid (<h3 class="el-title">),
        // List (<div class="el-title">) — all YooTheme elements share this structure.
        // Only run if previous patterns found nothing.
        if (empty($faqItems)) {
            $itemNodes = $xpath->query('.//*[contains(concat(" ",normalize-space(@class)," ")," el-item ")]', $contentRoot);
            if ($itemNodes) {
                foreach ($itemNodes as $item) {
                    // el-title can be any tag: <a>, <h3>, <div>, etc.
                    $titleNodes = $xpath->query('.//*[contains(concat(" ",normalize-space(@class)," ")," el-title ")]', $item);
                    $contentNodes = $xpath->query('.//*[contains(concat(" ",normalize-space(@class)," ")," el-content ")]', $item);

                    if (!$titleNodes || $titleNodes->length === 0 || !$contentNodes || $contentNodes->length === 0) {
                        continue;
                    }

                    // Strip UIkit accordion icon text from title (span[uk-accordion-icon])
                    $titleNode = $titleNodes->item(0);
                    $iconNodes = $xpath->query('.//span[@uk-accordion-icon]', $titleNode);
                    if ($iconNodes) {
                        foreach ($iconNodes as $icon) {
                            $icon->parentNode?->removeChild($icon);
                        }
                    }

                    $question = trim($titleNode->textContent ?? '');
                    $answer   = trim($contentNodes->item(0)->textContent ?? '');

                    if ($question !== '' && $answer !== '' && strlen($answer) > 15) {
                        $faqItems[] = $this->buildFAQItem($question, $answer);
                    }
                }
            }
        }

        return $faqItems;
    }



    /**
     * Build a single Schema.org Question object
     *
     * @param string $question
     * @param string $answer
     * @return array<string, mixed>
     */
    private function buildFAQItem(string $question, string $answer): array
    {
        return [
            '@type' => 'Question',
            'name'  => htmlspecialchars_decode(strip_tags($question), ENT_QUOTES),
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => htmlspecialchars_decode(strip_tags($answer), ENT_QUOTES),
            ],
        ];
    }



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
     * Detect FAQ Q&A pairs from the fully-rendered HTML body buffer and inject
     * the FAQPage JSON-LD schema directly into the <head> section of the page.
     *
     * This method is intended to be called from onAfterRender(), at which point
     * the entire page HTML (including YooTheme / Falang translated content) is
     * available via $app->getBody().
     *
     * Why HTML-buffer instead of DB query
     * ------------------------------------
     * - YooTheme pages are NOT stored in #__content → DB approach returns nothing.
     * - Falang/Joomla multilingual translation completes BEFORE onAfterRender, so
     *   the buffer already contains the translated <dt>/<dd> text — no extra
     *   language handling is needed; it works for every active language automatically.
     *
     * Duplicate-prevention
     * ---------------------
     * If onBeforeCompileHead already injected a FAQPage schema (e.g. for a real
     * com_content article), we skip injection to avoid duplicate schemas.
     *
     * @param  string $body  Full HTML string from $app->getBody()
     * @return string        Modified HTML (schema inserted before </head>) or original
     */
    public function injectFAQFromHtmlBuffer(string $body): string
    {
        // Guard: service must be enabled and search-engine-accessible
        if (!$this->isEnabled() || !$this->allowSearchEngines()) {
            return $body;
        }

        // Guard: FAQ schema must be enabled
        if (!(bool)$this->params->get('faq_schema_enabled', 1)) {
            return $body;
        }

        // Guard: must have a </head> to inject into
        if (stripos($body, '</head>') === false) {
            return $body;
        }

        // Guard: skip if a FAQPage schema was already injected by onBeforeCompileHead
        // (should not happen anymore, but kept as safety net)
        if (stripos($body, '"@type":"FAQPage"') !== false || stripos($body, '"@type": "FAQPage"') !== false) {
            $this->logDebug('FAQ buffer-inject: FAQPage already present in head, skipping');
            return $body;
        }

        $faqItems = [];

        // ── Step 1: Look for jb--faq container in rendered HTML ──────────────────
        // Users mark their FAQ sections with class="jb--faq".
        // We scan ONLY that container — no false positives from navigation, headers, etc.
        // Supports all patterns inside the container: <dl>/<dt>/<dd>, <h3><p>, <h4><p>.
        if ((bool)$this->params->get('faq_auto_detect', 1)) {
            $containers = $this->extractAllJbFaqContainers($body);

            if (!empty($containers)) {
                foreach ($containers as $containerHtml) {
                    $items = $this->extractFAQFromContent($containerHtml);
                    $faqItems = array_merge($faqItems, $items);
                }
                if (!empty($faqItems)) {
                    $this->logDebug('FAQ buffer-inject: Found ' . count($containers) . ' jb--faq container(s), extracted ' . count($faqItems) . ' Q&A pairs total');
                } else {
                    $this->logDebug('FAQ buffer-inject: jb--faq container(s) found but no Q&A pairs extracted');
                }
            } else {
                $this->logDebug('FAQ buffer-inject: No jb--faq container found — will use global FAQ');
            }
        }

        // ── Step 2: Apply global/manual FAQ based on configured scope ─────────────
        $enableManual = (bool)$this->params->get('enable_manual_faqs', 0);
        $scope        = (string)$this->params->get('manual_faq_scope', 'fallback_all');
        $isHomePage   = $this->isHomePage();
        $shouldInject = $enableManual ? $this->shouldInjectManualFAQ($scope, $faqItems) : false;

        if ($this->isDebugMode()) {
            $debugInfo = sprintf(
                "\n<!-- JB-DEBUG: scope=%s autoItems=%d isHomePage=%d shouldInject=%d -->\n",
                $scope,
                count($faqItems),
                (int) $isHomePage,
                (int) $shouldInject
            );
            $body = str_ireplace('</head>', $debugInfo . '</head>', $body);
        }

        if ($enableManual && $shouldInject) {
            try {
                $qaService   = new QAManagementService($this->app, $this->params);
                $manualItems = $qaService->getManualFAQs();

                if (!empty($manualItems)) {
                    $faqItems = $manualItems;
                    $this->logDebug('FAQ buffer-inject: Global FAQ applied (scope=' . $scope . ', ' . count($faqItems) . ' items)');
                } else {
                    $this->logDebug('FAQ buffer-inject: getManualFAQs returned empty array');
                }
            } catch (\Throwable $e) {
                $this->logDebug('FAQ buffer-inject: QAManagementService error - ' . $e->getMessage());
            }
        }


        if (empty($faqItems)) {
            $this->logDebug('FAQ buffer-inject: No FAQ items to inject');
            return $body;
        }

        $schema = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $faqItems,
        ];

        $json      = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $scriptTag = "\n<script type=\"application/ld+json\">\n" . $json . "\n</script>\n";

        // Insert before closing </head>
        $body = str_ireplace('</head>', $scriptTag . '</head>', $body);

        return $body;
    }

    /**
     * Extract the inner HTML of the first element with class="jb--faq".
     *
     * Users wrap their FAQ content in any block element with this class:
     *   <div class="jb--faq"> ... <dl><dt>Q</dt><dd>A</dd></dl> ... </div>
     *   <section class="jb--faq"> ... <h3>Q</h3><p>A</p> ... </section>
     *
     * Returns the innerHTML of the container, or null if not found.
     */
    private function extractJbFaqContainer(string $body): ?string
    {
        // Fast pre-check before full regex — avoids regex overhead on most pages
        if (stripos($body, 'jb--faq') === false) {
            return null;
        }

        // Match opening tag with jb--faq class (handles extra classes and attributes)
        // e.g. <div class="jb--faq other-class" id="faq">
        if (!preg_match('/<([a-z][a-z0-9]*)\b[^>]*\bclass=["\'][^"\']*\bjb--faq\b[^"\']*["\'][^>]*>/i', $body, $open, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $tagName    = $open[1][0];           // e.g. 'div'
        $openStart  = $open[0][1];           // byte offset of opening tag
        $openLen    = strlen($open[0][0]);   // length of opening tag
        $innerStart = $openStart + $openLen; // start of inner HTML

        // Find the matching closing tag, respecting nesting
        $depth  = 1;
        $pos    = $innerStart;
        $len    = strlen($body);
        $inner  = '';

        while ($pos < $len && $depth > 0) {
            // Look for next opening or closing tag of the same element
            $nextOpen  = stripos($body, '<' . $tagName, $pos);
            $nextClose = stripos($body, '</' . $tagName, $pos);

            if ($nextClose === false) {
                break; // Malformed HTML
            }

            if ($nextOpen !== false && $nextOpen < $nextClose) {
                $depth++;
                $pos = $nextOpen + 1;
            } else {
                $depth--;
                if ($depth === 0) {
                    $inner = substr($body, $innerStart, $nextClose - $innerStart);
                } else {
                    $pos = $nextClose + 1;
                }
            }
        }

        return $inner !== '' ? $inner : null;
    }

    /**
     * Extract inner HTML of ALL elements with class="jb--faq" in the page body.
     *
     * Supports multiple FAQ sections on one page (e.g. one per hotel).
     * Returns an array of innerHTML strings, one per matched container.
     * Returns empty array if none found.
     *
     * @return string[]
     */
    private function extractAllJbFaqContainers(string $body): array
    {
        // Fast pre-check before full regex — avoids regex overhead on most pages
        if (stripos($body, 'jb--faq') === false) {
            return [];
        }

        $pattern = '/<([a-z][a-z0-9]*)\b[^>]*\bclass=["\'][^"\']*\bjb--faq\b[^"\']*["\'][^>]*>/i';

        // Find all opening tags with jb--faq class
        if (!preg_match_all($pattern, $body, $allMatches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $results = [];
        $len     = strlen($body);

        foreach ($allMatches[0] as $index => $match) {
            $openTag    = $match[0];                    // Full opening tag string
            $openStart  = $match[1];                    // Byte offset of opening tag
            $tagName    = $allMatches[1][$index][0];    // Tag name e.g. 'div'
            $openLen    = strlen($openTag);
            $innerStart = $openStart + $openLen;

            // Find the matching closing tag, respecting nesting
            $depth = 1;
            $pos   = $innerStart;
            $inner = '';

            while ($pos < $len && $depth > 0) {
                $nextOpen  = stripos($body, '<' . $tagName, $pos);
                $nextClose = stripos($body, '</' . $tagName, $pos);

                if ($nextClose === false) {
                    break; // Malformed HTML
                }

                if ($nextOpen !== false && $nextOpen < $nextClose) {
                    $depth++;
                    $pos = $nextOpen + 1;
                } else {
                    $depth--;
                    if ($depth === 0) {
                        $inner = substr($body, $innerStart, $nextClose - $innerStart);
                    } else {
                        $pos = $nextClose + 1;
                    }
                }
            }

            if ($inner !== '') {
                $results[] = $inner;
            }
        }

        return $results;
    }



    /**
     * Determine whether the global/manual FAQ should be injected on the current page,
     * based on the configured `manual_faq_scope` parameter.
     *
     * @param string  $scope      Value of `manual_faq_scope` param
     * @param array   $autoItems  FAQ items already found by auto-detect (may be empty)
     * @return bool   true = proceed with manual FAQ injection
     */
    private function shouldInjectManualFAQ(string $scope, array $autoItems): bool
    {
        if ($scope === 'disabled') {
            return false;
        }

        $isHome = $this->isHomePage();

        return match ($scope) {
            // Inject on any page, but only if auto-detect found nothing
            'fallback_all'  => empty($autoItems),
            // Inject on every page regardless of auto-detect
            'always_all'    => true,
            // Homepage only — inject only if auto-detect found nothing
            'fallback_home' => $isHome && empty($autoItems),
            // Homepage only — always inject (ignore auto-detect result)
            'always_home'   => $isHome,
            // Unknown value: safe default = don't inject
            default         => false,
        };
    }

    /**
     * Detect whether the current request is for the site homepage.
     */
    private function isHomePage(): bool
    {
        try {
            // ── Strategy 1: URL path check (most reliable for Falang sites) ──────
            // Falang sets Joomla input `id` internally even for /me/, /en/ homepage
            // routes, so we MUST check the URL pattern first.
            $rawPath = $_SERVER['REQUEST_URI'] ?? '';
            $rawPath = (string) (explode('?', $rawPath)[0] ?? $rawPath);
            $base    = trim(parse_url((string) \Joomla\CMS\Uri\Uri::root(), PHP_URL_PATH) ?? '', '/');
            $path    = trim($rawPath, '/');

            if ($base !== '' && str_starts_with($path, $base)) {
                $path = trim(substr($path, strlen($base)), '/');
            }

            // Homepage patterns:
            //   ''               → bare domain root
            //   'en', 'me'       → 2-char language prefix (SEF homepage, Falang)
            //   'index.php'      → Joomla without URL rewriting
            //   'en/index.php'   → language prefix + Joomla index
            if (
                $path === '' ||
                $path === 'index.php' ||
                preg_match('/^[a-z]{2}$/i', $path) ||
                preg_match('/^[a-z]{2}\/index\.php$/i', $path)
            ) {
                return true;
            }

            // ── Strategy 2: Joomla menu active vs. default ────────────────────────
            // Only used if URL pattern doesn't match (non-SEF or fallback).
            // NOTE: In Joomla 6, Menu::getDefault() takes NO arguments.
            $menu    = $this->app->getMenu();
            $active  = $menu ? $menu->getActive()  : null;
            $default = $menu ? $menu->getDefault()  : null;

            if ($active && $default && $active->id === $default->id) {
                return true;
            }

            return false;
        } catch (\Throwable $e) {
            return false;
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

    // =========================================================================
    // P2 NEW METHODS — Event, VideoObject, Author (E-E-A-T)
    // =========================================================================

    /**
     * Generate Event schema objects from plugin JSON configuration.
     *
     * Admin enters events as a JSON array in plugin settings (schema_events field).
     * Each event object: name, startDate, endDate, url, description, price, location
     *
     * @return array<int, array<string, mixed>>
     */
    private function generateEventsSchema(): array
    {
        if (!(bool) $this->params->get('schema_events_enabled', 0)) {
            return [];
        }

        $json = trim((string) $this->params->get('schema_events', ''));
        if (empty($json)) {
            return [];
        }

        try {
            $events = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($events)) {
                return [];
            }

            $baseUrl = $this->getSchemaUrl();
            $orgName = trim((string) $this->params->get('org_name', ''));

            $schemas = [];
            foreach ($events as $event) {
                $name      = trim((string) ($event['name'] ?? ''));
                $startDate = trim((string) ($event['startDate'] ?? ''));

                // name and startDate are required by Google Rich Results
                if (empty($name) || empty($startDate)) {
                    continue;
                }

                $eventSchema = [
                    '@context'  => 'https://schema.org',
                    '@type'     => 'Event',
                    'name'      => $name,
                    'startDate' => $startDate,
                    'eventStatus'      => 'https://schema.org/EventScheduled',
                    'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                ];

                if (!empty($event['endDate'])) {
                    $eventSchema['endDate'] = trim($event['endDate']);
                }

                if (!empty($event['description'])) {
                    $eventSchema['description'] = trim($event['description']);
                }

                if (!empty($event['url'])) {
                    $url = trim($event['url']);
                    $eventSchema['url'] = str_starts_with($url, 'http') ? $url : $baseUrl . ltrim($url, '/');
                }

                // Location: use event-specific location or fallback to organization
                $locationName = trim((string) ($event['location'] ?? ''));
                if (!empty($locationName)) {
                    $eventSchema['location'] = [
                        '@type' => 'Place',
                        'name'  => $locationName,
                    ];
                } elseif (!empty($orgName)) {
                    $address = trim((string) $this->params->get('schema_address_street', ''));
                    $eventSchema['location'] = [
                        '@type'   => 'Place',
                        'name'    => $orgName,
                        'address' => !empty($address) ? $address : $orgName,
                    ];
                }

                // Offer/price
                $price = trim((string) ($event['price'] ?? ''));
                if (!empty($price)) {
                    $eventSchema['offers'] = [
                        '@type'         => 'Offer',
                        'price'         => $price,
                        'priceCurrency' => trim((string) ($event['currency'] ?? 'EUR')),
                        'availability'  => 'https://schema.org/InStock',
                    ];
                }

                // Organizer
                if (!empty($orgName)) {
                    $eventSchema['organizer'] = [
                        '@type' => 'Organization',
                        'name'  => $orgName,
                        'url'   => $baseUrl,
                    ];
                }

                $schemas[] = $eventSchema;
            }

            $this->logDebug('Event schema: Generated ' . count($schemas) . ' events');
            return $schemas;
        } catch (\Throwable $e) {
            $this->logDebug('Event schema error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Detect YouTube or Vimeo embed in HTML content and return a VideoObject schema.
     * Only the first video found is returned (primary video for the article).
     *
     * @param  string  $content  Raw HTML content (introtext + fulltext)
     * @return array<string, mixed>|null  VideoObject schema or null if no video found
     */
    private function detectVideoObject(string $content): ?array
    {
        if (empty($content)) {
            return null;
        }

        // ── YouTube ────────────────────────────────────────────────────────────
        // Matches: youtube.com/embed/{id}, youtube-nocookie.com/embed/{id}
        if (preg_match('#youtube(?:-nocookie)?\.com/embed/([a-zA-Z0-9_-]{11})#', $content, $m)) {
            $videoId      = $m[1];
            $watchUrl     = 'https://www.youtube.com/watch?v=' . $videoId;
            $thumbnailUrl = 'https://img.youtube.com/vi/' . $videoId . '/maxresdefault.jpg';

            $this->logDebug('VideoObject: YouTube id=' . $videoId);

            return [
                '@type'        => 'VideoObject',
                'embedUrl'     => 'https://www.youtube.com/embed/' . $videoId,
                'url'          => $watchUrl,
                'thumbnailUrl' => $thumbnailUrl,
                'description'  => 'Video content',
            ];
        }

        // ── Vimeo ─────────────────────────────────────────────────────────────
        // Matches: player.vimeo.com/video/{id}
        if (preg_match('#player\.vimeo\.com/video/(\d+)#', $content, $m)) {
            $videoId  = $m[1];
            $watchUrl = 'https://vimeo.com/' . $videoId;

            $this->logDebug('VideoObject: Vimeo id=' . $videoId);

            return [
                '@type'    => 'VideoObject',
                'embedUrl' => 'https://player.vimeo.com/video/' . $videoId,
                'url'      => $watchUrl,
                // Vimeo thumbnail requires API call; omit to avoid empty properties
            ];
        }

        return null;
    }

    /**
     * Build a Person schema for article author (E-E-A-T signal).
     * Falls back to alias or generic 'Author' if user not found.
     *
     * @param  int     $userId  Joomla user ID (created_by)
     * @param  string  $alias   created_by_alias from article
     * @return array<string, mixed>
     */
    private function getAuthorSchema(int $userId, string $alias = ''): array
    {
        $base = ['@type' => 'Person'];

        if ($userId > 0) {
            try {
                $db    = Factory::getDbo();
                $query = $db->getQuery(true)
                    ->select('name, email')
                    ->from('#__users')
                    ->where('id = ' . $userId);

                $db->setQuery($query);
                $user = $db->loadObject();

                if ($user) {
                    // Prefer alias (custom pen name) over real name
                    $base['name'] = !empty($alias) ? $alias : $user->name;

                    // Build author profile URL if Joomla has registered user articles
                    $authorRoute = \Joomla\CMS\Router\Route::_(
                        'index.php?option=com_content&view=articles&filter[author_id]=' . $userId,
                        false
                    );
                    if (!empty($authorRoute) && $authorRoute !== '/') {
                        $base['url'] = rtrim($this->getSchemaUrl(), '/') . '/' . ltrim($authorRoute, '/');
                    }

                    return $base;
                }
            } catch (\Throwable $e) {
                $this->logDebug('Author schema fetch failed: ' . $e->getMessage());
            }
        }

        // Fallback
        $base['name'] = !empty($alias) ? $alias : 'Author';
        return $base;
    }
}
