<?php
/**
 * AI Boost — SchemaBuilder (Free baseline)
 *
 * Builds the Free-tier JSON-LD blocks for the current page request and
 * returns them as PHP arrays. The Extension class fires
 * `EVENT_FILTER_SCHEMA_BLOCKS` after collecting these blocks so the
 * closed-source aiboost_schema_pro plugin can decorate the Organization
 * block (upgraded @type, openingHours, aggregateRating, type-specific
 * properties, translations) and append Pro-only blocks (FAQPage,
 * QAPage, Article, HowTo, Event).
 *
 * Free-tier output (always):
 *   - Organization (@type=Organization)         — identity, address, logo, social
 *   - WebSite + SearchAction (homepage only)
 *   - BreadcrumbList                            — from Joomla pathway
 *
 * @package     AiBoost\Plugin\System\AiBoostSchema
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostSchema\Service;

defined('_JEXEC') or die;

use AiBoost\Lib\AppContextInterface;
use Joomla\Database\DatabaseInterface;

class SchemaBuilder
{
    /** @var array<string,mixed> */
    private array               $settings;
    private AppContextInterface $ctx;
    private DatabaseInterface   $db;

    /**
     * @param array<string,mixed> $settings  Shared AI Boost settings array.
     */
    public function __construct(
        array $settings,
        AppContextInterface $ctx,
        DatabaseInterface $db
    ) {
        $this->settings = $settings;
        $this->ctx      = $ctx;
        $this->db       = $db;
    }

    /**
     * Build the Free-tier baseline schema blocks.
     *
     * @return array<int, array<string, mixed>>  One PHP array per JSON-LD block.
     */
    public function buildAll(): array
    {
        $schemas = [];

        if ((int)($this->settings['website_schema_enabled'] ?? 1)) {
            $ws = $this->buildWebSite();
            if ($ws) {
                $schemas[] = $ws;
            }
        }

        $org = $this->buildOrganization();
        if ($org) {
            $schemas[] = $org;
        }

        $bc = $this->buildBreadcrumb();
        if ($bc) {
            $schemas[] = $bc;
        }

        return $schemas;
    }

    /**
     * Organization JSON-LD — emitted on every page.
     *
     * Free tier always emits @type=Organization. The Pro plugin listens on
     * EVENT_FILTER_SCHEMA_BLOCKS and upgrades @type via SiteTypePresetService
     * + decorates with openingHours, aggregateRating, translations, etc.
     *
     * @return array<string, mixed>|null
     */
    private function buildOrganization(): ?array
    {
        $orgName = trim((string)($this->settings['org_name'] ?? ''));
        if ($orgName === '') {
            return null;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => $orgName,
        ];

        $orgUrl = trim((string)($this->settings['org_url'] ?? ''));
        $schema['url'] = $orgUrl !== '' ? $orgUrl : $this->ctx->getBaseUrl() . '/';

        $orgDesc = trim((string)($this->settings['org_description'] ?? ''));
        if ($orgDesc !== '') {
            $schema['description'] = $orgDesc;
        }

        $logo = trim((string)($this->settings['org_logo'] ?? ''));
        if ($logo !== '') {
            $schema['logo'] = ['@type' => 'ImageObject', 'url' => $this->absoluteUrl($logo)];
        }

        $phone = trim((string)($this->settings['org_phone'] ?? ''));
        if ($phone !== '') {
            $schema['telephone'] = $phone;
        }

        $email = trim((string)($this->settings['org_email'] ?? ''));
        if ($email !== '') {
            $schema['email'] = $email;
        }

        $addrStreet  = trim((string)($this->settings['org_address_street']  ?? ''));
        $addrCity    = trim((string)($this->settings['org_address_city']    ?? ''));
        $addrState   = trim((string)($this->settings['org_address_state']   ?? ''));
        $addrZip     = trim((string)($this->settings['org_address_zip']     ?? ''));
        $addrCountry = trim((string)($this->settings['org_address_country'] ?? ''));
        if ($addrStreet || $addrCity || $addrZip || $addrCountry) {
            $addr = ['@type' => 'PostalAddress'];
            if ($addrStreet)  $addr['streetAddress']  = $addrStreet;
            if ($addrCity)    $addr['addressLocality'] = $addrCity;
            if ($addrState)   $addr['addressRegion']   = $addrState;
            if ($addrZip)     $addr['postalCode']      = $addrZip;
            if ($addrCountry) $addr['addressCountry']  = $addrCountry;
            $schema['address'] = $addr;
        }

        $lat = trim((string)($this->settings['org_latitude']  ?? ''));
        $lng = trim((string)($this->settings['org_longitude'] ?? ''));
        if ($lat !== '' && $lng !== '') {
            $schema['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $lat, 'longitude' => $lng];
        }

        $sameAs = [];
        foreach (['facebook', 'instagram', 'youtube', 'twitter', 'linkedin'] as $net) {
            $url = trim((string)($this->settings["social_{$net}"] ?? ''));
            if ($url !== '') {
                $sameAs[] = $url;
            }
        }
        if ($sameAs) {
            $schema['sameAs'] = $sameAs;
        }

        return $schema;
    }

    /**
     * WebSite + SearchAction JSON-LD — homepage only.
     *
     * @return array<string, mixed>|null
     */
    private function buildWebSite(): ?array
    {
        if (!$this->ctx->isHomepage()) {
            return null;
        }

        $baseUrl = trim((string)($this->settings['org_url'] ?? ''));
        if ($baseUrl === '') {
            $baseUrl = $this->ctx->getBaseUrl();
        }
        $baseUrl = rtrim($baseUrl, '/');

        $orgName = trim((string)($this->settings['org_name'] ?? ''));
        $schema  = [
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => $orgName !== '' ? $orgName : $this->ctx->getSiteName(),
            'url'      => $baseUrl . '/',
        ];

        if ((int)($this->settings['enable_search_action'] ?? 1)) {
            $schema['potentialAction'] = [
                '@type'       => 'SearchAction',
                'target'      => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => $baseUrl . '/index.php?option=com_search&searchword={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ];
        }

        return $schema;
    }

    /**
     * BreadcrumbList JSON-LD — auto-generated from the CMS pathway.
     *
     * @return array<string, mixed>|null
     */
    private function buildBreadcrumb(): ?array
    {
        $pathway = $this->ctx->getPathway();
        if (empty($pathway)) {
            return null;
        }

        $root     = $this->ctx->getBaseUrl();
        $items    = [];
        $position = 1;

        $homeName = $this->ctx->translate('HOME');
        $items[]  = [
            '@type'    => 'ListItem',
            'position' => $position++,
            'name'     => $homeName !== '' ? $homeName : 'Home',
            'item'     => $root . '/',
        ];

        foreach ($pathway as $step) {
            $name = trim(strip_tags((string) $step['name']));
            if ($name === '') {
                continue;
            }
            $item = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'name'     => $name,
            ];
            $link = trim((string) $step['link']);
            if ($link !== '' && $link !== 'index.php') {
                if (!str_starts_with($link, 'http')) {
                    $link = $root . '/' . ltrim($link, '/');
                }
                $item['item'] = $link;
            }
            $items[] = $item;
        }

        if (count($items) <= 1) {
            return null;
        }

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /** Ensure a path or URL is absolute (prepend base URL for relative paths). */
    private function absoluteUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return $this->ctx->getBaseUrl() . '/' . ltrim($path, '/');
    }
}
