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
        // IMPORTANT: use Uri::root() not Uri::base() — base() returns the admin URL
        // when llms.txt is generated from admin context (plugin settings save).
        // root() always returns the frontend site URL regardless of context.
        // Uri::root() returns /administrator/ path in admin context — always use scheme+host only
        $baseUrl    = rtrim(Uri::getInstance()->toString(['scheme', 'host']), '/');
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

        // --- Recent articles (AI citability — fresh content discovery) ---
        $recentArticles = $this->getRecentArticles($baseUrl);
        if (!empty($recentArticles)) {
            $lines[] = '## Recent Content';
            $lines[] = '';
            foreach ($recentArticles as $article) {
                $line = '- [' . $article['title'] . '](' . $article['url'] . ')';
                if (!empty($article['description'])) {
                    $line .= ': ' . $article['description'];
                }
                $lines[] = $line;
            }
            $lines[] = '';
        }

        // --- FAQ (structured Q&A for AI direct-answer extraction) ---
        $faqItems = $this->getFaqItems();
        if (!empty($faqItems)) {
            $lines[] = '## Frequently Asked Questions';
            $lines[] = '';
            foreach ($faqItems as $faq) {
                if (!empty($faq['question']) && !empty($faq['answer'])) {
                    $lines[] = '### ' . $faq['question'];
                    $lines[] = '';
                    $lines[] = $faq['answer'];
                    $lines[] = '';
                }
            }
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
            // Query #__menu directly — Route::_() in admin context generates
            // broken /administrator/... URLs. The 'path' column stores the
            // clean SEF slug (e.g. 'en/accommodation') without admin prefix.
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName(['m.title', 'm.path', 'm.link', 'm.type']))
                ->from($db->quoteName('#__menu', 'm'))
                ->where($db->quoteName('m.published') . ' = 1')
                ->where($db->quoteName('m.client_id') . ' = 0')   // 0 = frontend
                ->where($db->quoteName('m.parent_id') . ' = 1')   // direct children of root
                ->order($db->quoteName('m.lft') . ' ASC');

            $db->setQuery($query);
            $items = $db->loadObjectList();

            foreach ($items as $item) {
                if (empty($item->title)) {
                    continue;
                }

                // External links: use as-is
                if (!empty($item->link) && str_starts_with((string) $item->link, 'http')) {
                    $url = $item->link;
                } elseif (!empty($item->path)) {
                    // path contains SEF route slug (e.g. 'en' or 'en/accommodation')
                    $url = $baseUrl . '/' . ltrim((string) $item->path, '/');
                } else {
                    continue; // no usable URL
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

    /**
     * Get recent published articles (top 10 by date) for AI discovery.
     *
     * @param  string  $baseUrl
     * @return array<int, array<string, string>>
     */
    private function getRecentArticles(string $baseUrl): array
    {
        $maxArticles = (int) $this->params->get('llmstxt_recent_articles', 10);
        if ($maxArticles <= 0) {
            return [];
        }

        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('a.id, a.title, a.alias, a.metadesc, a.catid, c.alias AS cat_alias')
                ->from('#__content AS a')
                ->leftJoin('#__categories AS c ON c.id = a.catid')
                ->where('a.state = 1')
                ->order('a.created DESC')
                ->setLimit($maxArticles);

            $db->setQuery($query);
            $articles = $db->loadObjectList();

            // IMPORTANT: Use Uri::root() for article URLs.
            // Route::_() in admin context generates broken /administrator/... URLs.
            // Building from aliases produces correct SEF frontend URLs.
            // Same fix: Uri::root() returns admin path — use scheme+host only
            $siteRoot = rtrim(Uri::getInstance()->toString(['scheme', 'host']), '/');

            $result = [];
            foreach ($articles as $article) {
                // Build SEF URL from category alias + article alias if both available
                if (!empty($article->cat_alias) && !empty($article->alias)) {
                    $url = $siteRoot . '/' . $article->cat_alias . '/' . $article->alias;
                } else {
                    // Fallback: index.php query string (still a valid absolute URL)
                    $url = $siteRoot . '/index.php?option=com_content&view=article'
                        . '&id=' . $article->id . ':' . $article->alias
                        . '&catid=' . $article->catid;
                }

                $desc = trim((string) $article->metadesc);
                // Trim to 160 chars to keep llms.txt concise
                if (strlen($desc) > 160) {
                    $desc = substr($desc, 0, 157) . '...';
                }

                $result[] = [
                    'title'       => $article->title,
                    'url'         => $url,
                    'description' => $desc,
                ];
            }

            return $result;
        } catch (\Throwable $e) {
            $this->logDebug('LlmsTxt: error getting recent articles: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get FAQ items from plugin settings (EN language).
     * Returns array of ['question' => ..., 'answer' => ...] pairs.
     *
     * @return array<int, array<string, string>>
     */
    private function getFaqItems(): array
    {
        // Try EN FAQ first (most useful for AI systems)
        $faqJson = trim((string) $this->params->get('manual_faqs_en', ''));

        if (empty($faqJson)) {
            $faqJson = trim((string) $this->params->get('manual_faqs', ''));
        }

        if (empty($faqJson)) {
            return [];
        }

        try {
            $data = json_decode($faqJson, true);
            if (!is_array($data)) {
                return [];
            }

            $items = [];
            foreach ($data as $item) {
                // Support both {'q':..,'a':..} and {'question':..,'answer':..} formats
                $question = trim((string) ($item['question'] ?? $item['q'] ?? ''));
                $answer   = trim(strip_tags((string) ($item['answer'] ?? $item['a'] ?? '')));

                if (!empty($question) && !empty($answer)) {
                    $items[] = ['question' => $question, 'answer' => $answer];
                }
            }

            return $items;
        } catch (\Throwable $e) {
            $this->logDebug('LlmsTxt: error parsing FAQ: ' . $e->getMessage());
            return [];
        }
    }
}
