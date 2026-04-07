<?php

/**
 * JoomlaBoost - LLMs.txt Service
 *
 * Generates /llms.txt at the site root — a structured, LLM-friendly
 * overview of site content for AI systems (ChatGPT, Claude, Perplexity).
 *
 * Standard: https://llmstxt.org/
 *
 * @copyright   (C) 2024 emarket1ng.NET
 * @license     GNU General Public License version 2 or later
 */

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Services;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

// phpcs:disable
if (!defined('JPATH_SITE')) {
    define('JPATH_SITE', dirname(__DIR__, 6));
}
// phpcs:enable

/**
 * LLMs.txt Service
 *
 * Generates a structured /llms.txt file that helps AI systems
 * (ChatGPT, Claude, Perplexity, Gemini) understand the site's
 * content, purpose, and page structure.
 *
 * File format: https://llmstxt.org/
 */
class LlmsTxtService extends AbstractService
{
    /**
     * Required by AbstractService
     */
    protected function getServiceKey(): string
    {
        return 'llmstxt';
    }

    /**
     * Check if LLMs.txt generation is enabled.
     */
    public function isEnabled(): bool
    {
        return (bool) $this->params->get('llmstxt_enabled', 0);
    }

    /**
     * Generate and write /llms.txt to the site root.
     * Called when plugin settings are saved.
     */
    public function generateAndWrite(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $content  = $this->generate();
        $filePath = JPATH_SITE . DIRECTORY_SEPARATOR . 'llms.txt';
        $result   = file_put_contents($filePath, $content);

        $this->logDebug('LlmsTxt: file ' . ($result !== false ? 'written' : 'FAILED') . ': ' . $filePath);

        return $result !== false;
    }

    /**
     * Generate the llms.txt markdown content.
     *
     * @return string
     */
    public function generate(): string
    {
        $baseUrl    = rtrim((string) Uri::base(), '/');
        $orgName    = trim((string) $this->params->get('org_name', $this->params->get('org_name_en', '')));
        $orgDesc    = trim((string) $this->params->get('org_description_en', ''));
        $generated  = date('Y-m-d');

        // --- Header ---
        $lines = [];
        $lines[] = '# ' . ($orgName ?: 'Website');
        $lines[] = '';

        if (!empty($orgDesc)) {
            $lines[] = '> ' . $orgDesc;
            $lines[] = '';
        }

        $lines[] = '> Base URL: ' . $baseUrl;
        $lines[] = '> Generated: ' . $generated . ' by JoomlaBoost';
        $lines[] = '';

        // --- Main Navigation (from Joomla top-level menu) ---
        $menuPages = $this->getMenuPages($baseUrl);
        if (!empty($menuPages)) {
            $lines[] = '## Pages';
            $lines[] = '';
            foreach ($menuPages as $page) {
                $line = '- [' . $page['title'] . '](' . $page['url'] . ')';
                if (!empty($page['note'])) {
                    $line .= ': ' . $page['note'];
                }
                $lines[] = $line;
            }
            $lines[] = '';
        }

        // --- Custom Pages (from JSON settings field) ---
        $customPages = $this->getCustomPages();
        if (!empty($customPages)) {
            $lines[] = '## Additional Pages';
            $lines[] = '';
            foreach ($customPages as $page) {
                if (empty($page['url']) || empty($page['title'])) {
                    continue;
                }
                $url  = str_starts_with($page['url'], 'http') ? $page['url'] : $baseUrl . '/' . ltrim($page['url'], '/');
                $line = '- [' . $page['title'] . '](' . $url . ')';
                if (!empty($page['description'])) {
                    $line .= ': ' . $page['description'];
                }
                $lines[] = $line;
            }
            $lines[] = '';
        }

        // --- Organization details ---
        $lines[] = '## About';
        $lines[] = '';

        $schemaType = (string) $this->params->get('schema_type', 'organization');
        if ($schemaType === 'hotel') {
            $lines[] = '- Type: Hotel / Hospitality';
        } elseif ($schemaType === 'localbusiness') {
            $lines[] = '- Type: Local Business';
        } else {
            $lines[] = '- Type: Organization / Website';
        }

        $country = (string) $this->params->get('schema_address_country', '');
        if (!empty($country)) {
            $lines[] = '- Country: ' . $country;
        }

        $phone = (string) $this->params->get('schema_phone', '');
        if (!empty($phone)) {
            $lines[] = '- Contact: ' . $phone;
        }

        $email = (string) $this->params->get('schema_email', '');
        if (!empty($email)) {
            $lines[] = '- Email: ' . $email;
        }

        // Star rating for hotels
        $stars = (int) $this->params->get('schema_hotel_star_rating', 0);
        if ($stars > 0) {
            $lines[] = '- Star Rating: ' . $stars . ' stars';
        }

        // Aggregate rating
        $ratingValue = trim((string) $this->params->get('schema_rating_value', ''));
        $ratingCount = trim((string) $this->params->get('schema_rating_count', ''));
        $ratingSource = trim((string) $this->params->get('schema_rating_source', ''));
        if (!empty($ratingValue)) {
            $ratingLine = '- Guest Rating: ' . $ratingValue . '/5';
            if (!empty($ratingCount)) {
                $ratingLine .= ' (' . $ratingCount . ' reviews)';
            }
            if (!empty($ratingSource)) {
                $ratingLine .= ' on ' . $ratingSource;
            }
            $lines[] = $ratingLine;
        }

        $lines[] = '';

        // --- Social links ---
        $socialFields = [
            'schema_social_facebook'  => 'Facebook',
            'schema_social_instagram' => 'Instagram',
            'schema_social_youtube'   => 'YouTube',
            'schema_social_twitter'   => 'Twitter/X',
            'schema_social_linkedin'  => 'LinkedIn',
        ];
        $socials = [];
        foreach ($socialFields as $field => $label) {
            $url = trim((string) $this->params->get($field, ''));
            if (!empty($url)) {
                $socials[] = $label . ': ' . $url;
            }
        }

        if (!empty($socials)) {
            $lines[] = '## Social Media';
            $lines[] = '';
            foreach ($socials as $s) {
                $lines[] = '- ' . $s;
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Get top-level menu items from Joomla.
     *
     * @param string $baseUrl
     * @return array<int, array<string, string>>
     */
    private function getMenuPages(string $baseUrl): array
    {
        $pages = [];

        try {
            $app  = Factory::getApplication();
            $menu = $app->getMenu();

            if (!$menu) {
                return $pages;
            }

            $items = $menu->getItems('level', 1);

            if (empty($items)) {
                return $pages;
            }

            foreach ($items as $item) {
                if (!is_object($item) || empty($item->title)) {
                    continue;
                }

                // Skip unpublished items
                if (isset($item->published) && (int) $item->published !== 1) {
                    continue;
                }

                // Build URL
                if (isset($item->link) && str_starts_with((string) $item->link, 'http')) {
                    $url = $item->link;
                } else {
                    $route = \Joomla\CMS\Router\Route::_(
                        'index.php?Itemid=' . $item->id
                    );
                    $url = $baseUrl . '/' . ltrim((string) $route, '/');
                }

                $pages[] = [
                    'title' => $item->title,
                    'url'   => $url,
                    'note'  => '',
                ];
            }
        } catch (\Throwable $e) {
            $this->logDebug('LlmsTxt: error getting menu pages: ' . $e->getMessage());
        }

        return $pages;
    }

    /**
     * Get custom pages from plugin JSON settings.
     *
     * @return array<int, array<string, string>>
     */
    private function getCustomPages(): array
    {
        $json = trim((string) $this->params->get('llmstxt_custom_pages', ''));

        if (empty($json)) {
            return [];
        }

        try {
            $data = json_decode($json, true);
            if (!is_array($data)) {
                return [];
            }
            return $data;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
