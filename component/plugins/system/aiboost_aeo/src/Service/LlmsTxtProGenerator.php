<?php
/**
 * AI Boost — LlmsTxtProGenerator (Pro)
 *
 * Pro builder for the /llms.txt and /llms-full.txt content. The Free
 * plugin emits the Free baseline /llms.txt and dispatches
 * `EVENT_FILTER_LLMS_TXT`; this class is invoked from the Pro listener
 * to rebuild the body with:
 *
 *   - Per-language translations (org_name, llmstxt_description,
 *     manual FAQ entries) via TranslationService
 *   - Auto-detected FAQ from recent articles
 *   - "Full Index" reference when llms_full_txt_enabled
 *
 * Also generates /llms-full.txt (complete article + category index).
 *
 * @package     AiBoost\Plugin\System\AiBoostAeo
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostAeo\Service;

defined('_JEXEC') or die;

use AiBoost\Lib\AppContextInterface;
use AiBoost\Lib\Page\IndexabilityPolicy;
use AiBoost\Lib\TranslationService;
use Joomla\Database\DatabaseInterface;

class LlmsTxtProGenerator
{
    /** @var array<string,mixed> */
    private array               $settings;
    private AppContextInterface $ctx;
    private DatabaseInterface   $db;
    private string              $baseUrl;
    private string              $siteRoot;
    private ?TranslationService $translations;
    /** When non-empty, overrides ctx->getActiveLanguage() for translation lookups. */
    private string $overrideLangCode;

    /**
     * @param array<string,mixed> $settings
     */
    public function __construct(
        array $settings,
        AppContextInterface $ctx,
        DatabaseInterface $db,
        ?TranslationService $translations = null,
        string $overrideLangCode = ''
    ) {
        $this->settings         = $settings;
        $this->ctx              = $ctx;
        $this->db               = $db;
        $this->translations     = $translations;
        $this->overrideLangCode = $overrideLangCode;
        $this->baseUrl          = $ctx->getBaseUrl();
        $this->siteRoot         = $ctx->getBaseUrl();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate /llms.txt (Pro — translated + Full Index ref).
     */
    public function generate(): string
    {
        $lines = [];

        $siteName   = $this->ctx->getSiteName();
        $lc         = $this->overrideLangCode !== '' ? $this->overrideLangCode : $this->ctx->getActiveLanguage();
        $applyTrans = $this->translations !== null;

        $orgNameBase = trim((string) ($this->settings['org_name'] ?? ''));
        $orgName     = $applyTrans
            ? $this->translations->get('org_name', $lc, $orgNameBase)
            : $orgNameBase;

        $descBase = trim((string) ($this->settings['llmstxt_description'] ?? ''))
                 ?: trim((string) ($this->settings['org_description'] ?? ''));
        $desc     = $applyTrans
            ? $this->translations->get('llmstxt_description', $lc, $descBase)
            : $descBase;

        $lines[] = '# ' . ($orgName !== '' ? $orgName : ($siteName !== '' ? $siteName : 'Site'));
        $lines[] = '';

        if ($desc) {
            $lines[] = '> ' . $desc;
            $lines[] = '';
        }

        $lines[] = '> Base URL: ' . $this->baseUrl;
        $lines[] = '> Generated: ' . date('Y-m-d') . ' by AI Boost for Joomla (aiboostnow.com)';
        $lines[] = '';

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

        if ((int) ($this->settings['llmstxt_include_faq'] ?? 1)) {
            $faqItems = $this->getFaqItems($lc, $applyTrans);
            if ((int) ($this->settings['faq_auto_detect'] ?? 0)) {
                $detected = $this->detectFaqFromArticles();
                if (!empty($detected)) {
                    $seen = [];
                    foreach ($faqItems as $f) {
                        $seen[mb_strtolower(trim($f['question']))] = true;
                    }
                    foreach ($detected as $f) {
                        $key = mb_strtolower(trim($f['question']));
                        if (!isset($seen[$key])) {
                            $faqItems[] = $f;
                            $seen[$key] = true;
                        }
                    }
                }
            }
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

        if ((int) ($this->settings['llms_full_txt_enabled'] ?? 0)) {
            $lines[] = '## Full Index';
            $lines[] = '';
            $lines[] = '- [Full Content Index](' . $this->siteRoot . '/llms-full.txt): Complete article and category listing for AI engines.';
            $lines[] = '';
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Generate /llms-full.txt (Pro tier — complete content index).
     */
    public function generateFull(): string
    {
        $lines = [];

        $siteName    = $this->ctx->getSiteName();
        $lc2         = $this->overrideLangCode !== '' ? $this->overrideLangCode : $this->ctx->getActiveLanguage();
        $applyTrans2 = $this->translations !== null;

        $orgName2Base = trim((string) ($this->settings['org_name'] ?? ''));
        $orgName      = $applyTrans2
            ? $this->translations->get('org_name', $lc2, $orgName2Base)
            : $orgName2Base;

        $descBase = trim((string) ($this->settings['llmstxt_description'] ?? ''))
                 ?: trim((string) ($this->settings['org_description'] ?? ''));
        $desc     = $applyTrans2
            ? $this->translations->get('llmstxt_description', $lc2, $descBase)
            : $descBase;

        $lines[] = '# ' . ($orgName !== '' ? $orgName : ($siteName !== '' ? $siteName : 'Site')) . ' — Full Content Index';
        $lines[] = '';

        if ($desc) {
            $lines[] = '> ' . $desc;
            $lines[] = '';
        }

        $lines[] = '> Base URL: ' . $this->baseUrl;
        $lines[] = '> Generated: ' . date('Y-m-d') . ' by AI Boost for Joomla (aiboostnow.com)';
        $lines[] = '';

        $aboutLines = $this->buildAboutLines();
        if (!empty($aboutLines)) {
            $lines[] = '## About';
            $lines[] = '';
            foreach ($aboutLines as $l) {
                $lines[] = $l;
            }
            $lines[] = '';
        }

        $socialLines = $this->buildSocialLines();
        if (!empty($socialLines)) {
            $lines[] = '## Social Media';
            $lines[] = '';
            foreach ($socialLines as $l) {
                $lines[] = $l;
            }
            $lines[] = '';
        }

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

        $categories = $this->fetchCategories();
        if (!empty($categories)) {
            $lines[] = '## Categories';
            $lines[] = '';
            foreach ($categories as $cat) {
                $catUrl  = $this->baseUrl . '/index.php?option=com_content&view=category&id=' . $cat->id;
                $catDesc = mb_substr(trim(strip_tags((string) ($cat->description ?? ''))), 0, 150);
                $lines[] = '- [' . htmlspecialchars_decode((string) $cat->title) . '](' . $catUrl . ')';
                if ($catDesc) {
                    $lines[] = '  > ' . $catDesc;
                }
            }
            $lines[] = '';
        }

        // Honour the configured cap (UI range 10–5000; default 500).
        $maxFull  = (int) ($this->settings['llms_full_max_articles'] ?? 500);
        $maxFull  = max(10, min(5000, $maxFull));
        $articles = $this->fetchAllArticles($maxFull);
        if (!empty($articles)) {
            $lines[] = '## Articles';
            $lines[] = '';
            foreach ($articles as $art) {
                $artUrl  = $this->baseUrl . '/index.php?option=com_content&view=article&id=' . $art->id;
                $excerpt = mb_substr(trim(strip_tags((string) ($art->introtext ?? ''))), 0, 500);
                $modified = '';
                if (!empty($art->modified) && $art->modified !== '0000-00-00 00:00:00') {
                    try {
                        $modified = (new \DateTime($art->modified))->format('Y-m-d');
                    } catch (\Throwable $e) {}
                }
                $lines[] = '- [' . htmlspecialchars_decode((string) $art->title) . '](' . $artUrl . ')';
                if ($modified) {
                    $lines[] = '  Updated: ' . $modified;
                }
                if ($excerpt) {
                    $lines[] = '  > ' . $excerpt;
                }
                $lines[] = '';
            }
        }

        $faqItems = $this->getFaqItems($lc2, $applyTrans2);
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

        $lines[] = '## Summary';
        $lines[] = '';
        $lines[] = '- [Summary (llms.txt)](' . $this->siteRoot . '/llms.txt)';
        $lines[] = '';

        return implode("\n", $lines) . "\n";
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers (kept in sync with Free LlmsTxtGenerator)
    // ─────────────────────────────────────────────────────────────────────────

    /** @return list<string> */
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

    /** @return list<string> */
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

    /** @return list<array{title:string,url:string,note:string}> */
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

    /** @return list<array{title:string,url:string,description:string}> */
    private function fetchRecentArticles(int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }
        try {
            $db    = $this->db;
            $query = $db->getQuery(true)
                ->select('a.id, a.title, a.alias, a.metadesc, a.catid, c.alias AS cat_alias')
                ->from('#__content AS a')
                ->leftJoin('#__categories AS c ON c.id = a.catid')
                ->order('a.created DESC')
                ->setLimit($limit);

            // T1·S4 — item indexability via the shared IndexabilityPolicy. The Pro
            // recent list filters on state only (no window, no access), as before.
            foreach ((new IndexabilityPolicy())->itemWhereClauses($db, publishedExpr: 'a.state') as $where) {
                $query->where($where);
            }

            $db->setQuery($query);
            $rows = $db->loadObjectList() ?: [];

            $result = [];
            foreach ($rows as $row) {
                if (!empty($row->cat_alias) && !empty($row->alias)) {
                    $url = $this->baseUrl . '/' . $row->cat_alias . '/' . $row->alias;
                } else {
                    $url = $this->baseUrl . '/index.php?option=com_content&view=article'
                         . '&id=' . $row->id . ':' . $row->alias
                         . '&catid=' . $row->catid;
                }

                $desc = trim((string) ($row->metadesc ?? ''));
                if (strlen($desc) > 160) {
                    $desc = substr($desc, 0, 157) . '...';
                }

                $result[] = [
                    'title'       => $row->title,
                    'url'         => $url,
                    'description' => $desc,
                ];
            }
            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @return list<object> */
    private function fetchAllArticles(int $limit): array
    {
        try {
            $db    = $this->db;
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'title', 'introtext', 'modified']))
                ->from($db->quoteName('#__content'))
                ->order($db->quoteName('modified') . ' DESC')
                ->setLimit($limit);

            // T1·S4 — item indexability via the shared IndexabilityPolicy. The full
            // index filters on state + public access (access=1), as before.
            foreach ((new IndexabilityPolicy())->itemWhereClauses(
                $db,
                publishedExpr: 'state',
                accessExpr: 'access',
                publicAccessOnly: true,
            ) as $where) {
                $query->where($where);
            }

            $db->setQuery($query);
            return $db->loadObjectList() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @return list<object> */
    private function fetchCategories(): array
    {
        try {
            $db    = $this->db;
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'title', 'description']))
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))   // category scope (not indexability)
                ->order($db->quoteName('lft') . ' ASC');

            // T1·S4 — item indexability via the shared IndexabilityPolicy. Categories
            // filter on published + public access (access=1), as before.
            foreach ((new IndexabilityPolicy())->itemWhereClauses(
                $db,
                publishedExpr: 'published',
                accessExpr: 'access',
                publicAccessOnly: true,
            ) as $where) {
                $query->where($where);
            }

            $db->setQuery($query);
            return $db->loadObjectList() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @return list<array{title?:string,url?:string,description?:string}> */
    private function getCustomPages(): array
    {
        $json = trim((string) ($this->settings['llmstxt_custom_pages'] ?? ''));
        if ($json === '' || $json === '[]') {
            return [];
        }
        try {
            $data = json_decode($json, true, 10);
            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @return list<array{question:string,answer:string}> */
    private function detectFaqFromArticles(): array
    {
        try {
            $db    = $this->db;
            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('introtext'),
                    $db->quoteName('fulltext'),
                ])
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('state') . ' = 1')
                ->where('(' . $db->quoteName('publish_up') . ' IS NULL OR '
                    . $db->quoteName('publish_up') . ' <= ' . $db->quote(gmdate('Y-m-d H:i:s')) . ')')
                ->order($db->quoteName('modified') . ' DESC');
            $db->setQuery($query, 0, 25);
            $rows = $db->loadObjectList() ?: [];
        } catch (\Throwable $e) {
            return [];
        }

        if (empty($rows)) {
            return [];
        }

        $detector = new \AiBoost\Lib\FaqAutoDetectService();
        $out      = [];
        $seen     = [];

        foreach ($rows as $row) {
            $html = trim((string) ($row->introtext ?? '')) . "\n"
                  . trim((string) ($row->fulltext  ?? ''));
            if (trim($html) === '') {
                continue;
            }
            $pairs = $detector->parse($html, 10);
            foreach ($pairs as $p) {
                $key = mb_strtolower(trim($p['question']));
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[]      = $p;
                if (count($out) >= 30) {
                    return $out;
                }
            }
        }
        return $out;
    }

    /**
     * Parse FAQ items from JSON setting field (with per-language overrides).
     *
     * @return list<array{question:string,answer:string}>
     */
    private function getFaqItems(string $lc = '', bool $applyTrans = false): array
    {
        // FAQ is defined once in Schema.org (faq_items) and reused here — single
        // source of truth (Korak 3.2 #7). The legacy llmstxt_faq_items key is no
        // longer read.
        $json = trim((string) ($this->settings['faq_items'] ?? ''));
        if ($json === '' || $json === '[]') {
            return [];
        }
        try {
            $data = json_decode($json, true, 10);
            if (!is_array($data)) {
                return [];
            }
            $items = [];
            foreach ($data as $idx => $item) {
                $q = trim((string) ($item['question'] ?? $item['q'] ?? ''));
                $a = trim(strip_tags((string) ($item['answer'] ?? $item['a'] ?? '')));
                if ($q === '' || $a === '') {
                    continue;
                }
                if ($applyTrans && $lc !== '' && $this->translations !== null) {
                    $tQ = $this->translations->get('faq_' . $idx . '_q', $lc, '');
                    $tA = $this->translations->get('faq_' . $idx . '_a', $lc, '');
                    if ($tQ !== '') {
                        $q = $tQ;
                    }
                    if ($tA !== '') {
                        $a = strip_tags($tA);
                    }
                }
                $items[] = ['question' => $q, 'answer' => $a];
            }
            return $items;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
