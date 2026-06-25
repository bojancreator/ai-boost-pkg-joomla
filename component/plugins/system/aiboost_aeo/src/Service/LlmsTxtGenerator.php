<?php
/**
 * AI Boost — LlmsTxtGenerator (Free baseline)
 *
 * Builds the Free-tier /llms.txt body. Free sections:
 *   - Site header + description
 *   - Pages (top-level Joomla menu items)
 *   - Custom pages (from llmstxt_custom_pages JSON field)
 *   - Recent articles
 *   - About (org name, type, country, phone, email)
 *   - Social media links
 *   - FAQ (from faq_items JSON field)
 *
 * Pro extras (per-language translations, /llms-full.txt, "Full Index"
 * reference, auto-detected FAQ) are appended by aiboost_aeo_pro via
 * EVENT_FILTER_LLMS_TXT.
 *
 * @package     AiBoost\Plugin\System\AiBoostAeo
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostAeo\Service;

defined('_JEXEC') or die;

use AiBoost\Lib\AppContextInterface;
use Joomla\Database\DatabaseInterface;

class LlmsTxtGenerator
{
    /** @var array<string,mixed> */
    private array               $settings;
    private AppContextInterface $ctx;
    private DatabaseInterface   $db;
    private string              $baseUrl;

    /**
     * @param array<string,mixed> $settings
     */
    public function __construct(
        array $settings,
        AppContextInterface $ctx,
        DatabaseInterface $db
    ) {
        $this->settings = $settings;
        $this->ctx      = $ctx;
        $this->db       = $db;
        $this->baseUrl  = $ctx->getBaseUrl();
    }

    /**
     * Generate the Free-tier /llms.txt body.
     */
    public function generate(): string
    {
        $lines = [];

        $siteName = $this->ctx->getSiteName();
        $orgName  = trim((string) ($this->settings['org_name'] ?? ''));
        $desc     = trim((string) ($this->settings['llmstxt_description'] ?? ''))
                 ?: trim((string) ($this->settings['org_description'] ?? ''));

        $lines[] = '# ' . ($orgName !== '' ? $orgName : ($siteName !== '' ? $siteName : 'Site'));
        $lines[] = '';

        if ($desc !== '') {
            $lines[] = '> ' . $desc;
            $lines[] = '';
        }

        $lines[] = '> Base URL: ' . $this->baseUrl;
        $lines[] = '> Generated: ' . date('Y-m-d') . ' by AI Boost for Joomla (aiboostnow.com)';
        $lines[] = '';

        // Pages (top-level menu)
        if ((int) ($this->settings['llmstxt_include_menu'] ?? 1)) {
            $menuPages = $this->fetchMenuPages();
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
        }

        // Custom pages
        $customPages = $this->getCustomPages();
        if (!empty($customPages)) {
            $lines[] = '## Additional Pages';
            $lines[] = '';
            foreach ($customPages as $page) {
                if (empty($page['url']) || empty($page['title'])) {
                    continue;
                }
                $url  = str_starts_with((string) $page['url'], 'http')
                    ? $page['url']
                    : $this->baseUrl . '/' . ltrim((string) $page['url'], '/');
                $line = '- [' . $page['title'] . '](' . $url . ')';
                if (!empty($page['description'])) {
                    $line .= ': ' . $page['description'];
                }
                $lines[] = $line;
            }
            $lines[] = '';
        }

        // Recent articles
        $maxArticles = (int) ($this->settings['llmstxt_recent_articles'] ?? 5);
        if ($maxArticles > 0) {
            $articles = $this->fetchRecentArticles($maxArticles);
            if (!empty($articles)) {
                $lines[] = '## Recent Content';
                $lines[] = '';
                foreach ($articles as $art) {
                    $line = '- [' . htmlspecialchars_decode((string) $art['title']) . '](' . $art['url'] . ')';
                    if (!empty($art['description'])) {
                        $line .= ': ' . $art['description'];
                    }
                    $lines[] = $line;
                }
                $lines[] = '';
            }
        }

        // About
        if ((int) ($this->settings['llmstxt_include_about'] ?? 1)) {
            $aboutLines = $this->buildAboutLines();
            if (!empty($aboutLines)) {
                $lines[] = '## About';
                $lines[] = '';
                foreach ($aboutLines as $l) {
                    $lines[] = $l;
                }
                $lines[] = '';
            }
        }

        // Social media
        if ((int) ($this->settings['llmstxt_include_socials'] ?? 1)) {
            $socialLines = $this->buildSocialLines();
            if (!empty($socialLines)) {
                $lines[] = '## Social Media';
                $lines[] = '';
                foreach ($socialLines as $l) {
                    $lines[] = $l;
                }
                $lines[] = '';
            }
        }

        // FAQ (Free: manual items only)
        if ((int) ($this->settings['llmstxt_include_faq'] ?? 1)) {
            $faqItems = $this->getFaqItems();
            if (!empty($faqItems)) {
                $lines[] = '## Frequently Asked Questions';
                $lines[] = '';
                foreach ($faqItems as $faq) {
                    $lines[] = '### ' . $faq['question'];
                    $lines[] = '';
                    $lines[] = $faq['answer'];
                    $lines[] = '';
                }
            }
        }

        return implode("\n", $lines) . "\n";
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * @return list<string>
     */
    private function buildAboutLines(): array
    {
        $lines = [];

        $phone   = trim((string) ($this->settings['org_phone'] ?? ''));
        $email   = trim((string) ($this->settings['org_email'] ?? ''));
        $country = trim((string) ($this->settings['org_address_country'] ?? ''));
        $orgType = trim((string) ($this->settings['schema_type'] ?? 'organization'));

        $typeLabels = [
            'organization'  => 'Organization / Website',
            'localbusiness' => 'Local Business',
            'hotel'         => 'Hotel / Hospitality',
            'restaurant'    => 'Restaurant',
            'store'         => 'Retail Store',
            'medical'       => 'Medical / Healthcare',
        ];

        $lines[] = '- Type: ' . ($typeLabels[$orgType] ?? 'Organization / Website');

        if ($country) {
            $lines[] = '- Country: ' . $country;
        }
        if ($phone) {
            $lines[] = '- Contact: ' . $phone;
        }
        if ($email) {
            $lines[] = '- Email: ' . $email;
        }

        $ratingValue  = trim((string) ($this->settings['rating_value']  ?? ''));
        $ratingCount  = trim((string) ($this->settings['rating_count']  ?? ''));
        $ratingSource = trim((string) ($this->settings['rating_source'] ?? ''));
        if ($ratingValue !== '') {
            $ratingLine = '- Rating: ' . $ratingValue . '/5';
            if ($ratingCount !== '') {
                $ratingLine .= ' (' . $ratingCount . ' reviews)';
            }
            if ($ratingSource !== '') {
                $ratingLine .= ' on ' . $ratingSource;
            }
            $lines[] = $ratingLine;
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function buildSocialLines(): array
    {
        $fields = [
            'social_facebook'  => 'Facebook',
            'social_instagram' => 'Instagram',
            'social_youtube'   => 'YouTube',
            'social_twitter'   => 'Twitter/X',
            'social_linkedin'  => 'LinkedIn',
            'social_tiktok'    => 'TikTok',
        ];

        $lines = [];
        foreach ($fields as $key => $label) {
            $url = trim((string) ($this->settings[$key] ?? ''));
            if ($url !== '') {
                $lines[] = '- ' . $label . ': ' . $url;
            }
        }
        return $lines;
    }

    /**
     * @return list<array{title: string, url: string, note: string}>
     */
    private function fetchMenuPages(): array
    {
        $systemPatterns = [
            'com_users', 'com_admin', 'com_config', 'com_templates',
            'com_messages', 'com_cpanel', 'administrator/index.php',
            'view=form', 'layout=edit',
        ];

        try {
            $db    = $this->db;
            $query = $db->getQuery(true)
                ->select($db->quoteName(['m.title', 'm.path', 'm.link', 'm.type', 'm.home']))
                ->from($db->quoteName('#__menu', 'm'))
                ->where($db->quoteName('m.published') . ' = 1')
                ->where($db->quoteName('m.client_id') . ' = 0')
                ->where($db->quoteName('m.parent_id') . ' = 1')
                ->order($db->quoteName('m.lft') . ' ASC');

            $db->setQuery($query);
            $items = $db->loadObjectList() ?: [];

            $pages = [];
            foreach ($items as $item) {
                if (empty($item->title)) {
                    continue;
                }
                $link = (string) ($item->link ?? '');
                $type = (string) ($item->type ?? '');

                $isExternal = str_starts_with($link, 'http');
                if ($type !== 'component' && !$isExternal) {
                    continue;
                }

                $skip = false;
                foreach ($systemPatterns as $pattern) {
                    if (str_contains($link, $pattern)) {
                        $skip = true;
                        break;
                    }
                }
                if ($skip) {
                    continue;
                }

                if ($isExternal) {
                    $url = $link;
                } elseif ((int) ($item->home ?? 0) === 1) {
                    $url = $this->baseUrl;
                } elseif (!empty($item->path)) {
                    $url = $this->baseUrl . '/' . ltrim((string) $item->path, '/');
                } else {
                    continue;
                }

                $pages[] = ['title' => htmlspecialchars_decode((string) $item->title), 'url' => $url, 'note' => ''];
            }

            return $pages;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return list<array{title: string, url: string, description: string}>
     */
    private function fetchRecentArticles(int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }
        try {
            $db    = $this->db;
            $now   = gmdate('Y-m-d H:i:s');
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'title', 'introtext', 'metadesc']))
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('state') . ' = 1')
                ->where('(' . $db->quoteName('publish_up') . ' IS NULL OR '
                    . $db->quoteName('publish_up') . ' <= ' . $db->quote($now) . ')')
                ->where('(' . $db->quoteName('publish_down') . ' IS NULL OR '
                    . $db->quoteName('publish_down') . ' >= ' . $db->quote($now) . ')')
                ->order($db->quoteName('publish_up') . ' DESC');
            $db->setQuery($query, 0, $limit);
            $rows = $db->loadObjectList() ?: [];

            $out = [];
            foreach ($rows as $row) {
                $desc = trim((string) ($row->metadesc ?? ''));
                if ($desc === '') {
                    $desc = mb_substr(trim(strip_tags((string) ($row->introtext ?? ''))), 0, 160);
                }
                $out[] = [
                    'title'       => (string) $row->title,
                    'url'         => $this->baseUrl . '/index.php?option=com_content&view=article&id=' . (int) $row->id,
                    'description' => $desc,
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return list<array{title?:string,url?:string,description?:string}>
     */
    private function getCustomPages(): array
    {
        $raw = trim((string) ($this->settings['llmstxt_custom_pages'] ?? ''));
        if ($raw === '' || $raw === '[]' || $raw === '{}') {
            return [];
        }
        try {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? array_values($decoded) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return list<array{question:string, answer:string}>
     */
    private function getFaqItems(): array
    {
        $raw = trim((string) ($this->settings['faq_items'] ?? ''));
        if ($raw === '' || $raw === '[]' || $raw === '{}') {
            return [];
        }
        try {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return [];
            }
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach (array_values($decoded) as $item) {
            $q = trim((string) ($item['question'] ?? ''));
            $a = trim((string) ($item['answer']   ?? ''));
            if ($q !== '' && $a !== '') {
                $out[] = ['question' => $q, 'answer' => $a];
            }
        }
        return $out;
    }
}
