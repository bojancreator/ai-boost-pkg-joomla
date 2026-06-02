<?php
/**
 * AI Boost — AEO & AI Signals Plugin (standalone, Joomla 4/5/6)
 *
 * Handles: llms.txt, llms-full.txt, robots.txt (per-crawler control), IndexNow
 *          (with auto-generate key support), Markdown Pages.
 * Standalone: reads all settings from Joomla-native plugin params ($this->params).
 *
 * @package     AiBoost\Plugin\System\AiBoostAeo
 * @version     1.1.0
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostAeo\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

class AiBoostAeo extends CMSPlugin
{
    protected $autoloadLanguage = true;

    // ── Virtual file serving ────────────────────────────────────────────────

    public function onAfterInitialise(): void
    {
        $uri  = $_SERVER['REQUEST_URI'] ?? '';
        $path = ltrim(parse_url($uri, PHP_URL_PATH) ?? '', '/');

        if ($path === '') {
            return;
        }

        // Staging mode: suppress output
        if ((int) $this->params->get('staging_mode', 0)) {
            return;
        }

        // llms.txt
        if ($path === 'llms.txt' && (int) $this->params->get('llmstxt_enabled', 1)) {
            $this->serveLlmsTxt();
            return;
        }

        // llms-full.txt
        if ($path === 'llms-full.txt' && (int) $this->params->get('llms_full_txt_enabled', 1)) {
            $this->serveLlmsFullTxt();
            return;
        }

        // IndexNow key file — serve at both /{key} and /{key}.txt
        $key = trim((string) $this->params->get('indexnow_api_key', ''));
        if ($key) {
            $cleanPath = rtrim($path, '/');
            if ($cleanPath === $key || $cleanPath === $key . '.txt') {
                header('Content-Type: text/plain; charset=utf-8');
                header('Cache-Control: public, max-age=86400');
                echo $key;
                exit;
            }
        }

        // robots.txt
        if ($path === 'robots.txt' && (int) $this->params->get('robots_txt_enabled', 1)) {
            $this->serveRobotsTxt();
            return;
        }

        // Markdown Pages — serve .md version of any page
        if ((int) $this->params->get('markdown_pages_enabled', 0)) {
            $this->maybeServeMarkdownPage($path);
        }
    }

    // ── Markdown discovery tag (link rel="alternate") ───────────────────────

    public function onBeforeCompileHead(): void
    {
        if (!(int) $this->params->get('markdown_pages_enabled', 0)) {
            return;
        }
        if ((int) $this->params->get('staging_mode', 0)) {
            return;
        }

        $app = Factory::getApplication();
        if (!$app->isClient('site')) {
            return;
        }
        $document = $app->getDocument();
        if (!$document || $document->getType() !== 'html') {
            return;
        }

        $currentUrl = rtrim(Uri::current(), '/');
        $document->addCustomTag(
            '<link rel="alternate" type="text/markdown" href="' . htmlspecialchars($currentUrl . '.md') . '">'
        );

        $this->injectAiMeta($document, $app);
    }

    private function injectAiMeta($document, $app): void
    {
        $input = $app->getInput();
        if ($input->get('option') !== 'com_content' || $input->get('view') !== 'article') {
            return;
        }
        $articleId = (int) $input->get('id', 0);
        if (!$articleId) {
            return;
        }

        $db  = Factory::getDbo();
        $q   = $db->getQuery(true)
            ->select(['title', 'introtext'])
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('id') . ' = ' . $articleId)
            ->where($db->quoteName('state') . ' = 1');
        $db->setQuery($q);
        $row = $db->loadObject();
        if (!$row) {
            return;
        }

        $baseUrl     = Uri::getInstance()->getScheme() . '://' . Uri::getInstance()->getHost();
        $currentUrl  = Uri::current();
        $description = mb_substr(trim(strip_tags((string) ($row->introtext ?? ''))), 0, 300);

        $speakable = [
            '@context'  => 'https://schema.org',
            '@type'     => 'WebPage',
            'url'       => $currentUrl,
            'speakable' => [
                '@type'   => 'SpeakableSpecification',
                'cssSelector' => ['h1', '.article-fulltext p:first-of-type'],
            ],
        ];
        $document->addCustomTag(
            '<script type="application/ld+json">' . json_encode($speakable, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>'
        );

        if ($description) {
            $document->addCustomTag(
                '<meta name="ai:description" content="' . htmlspecialchars($description, ENT_QUOTES) . '">'
            );
        }
    }

    // ── IndexNow auto-submit on article save ────────────────────────────────

    public function onContentAfterSave(string $context, object $article, bool $isNew): void
    {
        if ($context !== 'com_content.article') {
            return;
        }
        if ((int) ($article->state ?? 0) !== 1) {
            return;
        }
        if (!(int) $this->params->get('indexnow_auto_submit', 0)) {
            return;
        }
        $key = trim((string) $this->params->get('indexnow_api_key', ''));
        if (!$key) {
            return;
        }
        if ((int) $this->params->get('staging_mode', 0)) {
            return;
        }
        $url = $this->resolveArticleUrl((int) ($article->id ?? 0));
        if ($url) {
            $this->submitToIndexNow($url, $key);
        }
    }

    public function onContentChangeState(string $context, array $pks, int $value): void
    {
        if ($context !== 'com_content.article' || $value !== 1) {
            return;
        }
        if (!(int) $this->params->get('indexnow_auto_submit', 0)) {
            return;
        }
        $key = trim((string) $this->params->get('indexnow_api_key', ''));
        if (!$key || (int) $this->params->get('staging_mode', 0)) {
            return;
        }
        foreach ($pks as $pk) {
            $url = $this->resolveArticleUrl((int) $pk);
            if ($url) {
                $this->submitToIndexNow($url, $key);
            }
        }
    }

    // ── llms.txt ────────────────────────────────────────────────────────────

    private function serveLlmsTxt(): void
    {
        try {
            $orgName = trim((string) $this->params->get('llmstxt_org_name', ''));
            $orgDesc = trim((string) $this->params->get('llmstxt_org_desc', ''));
            $orgUrl  = trim((string) $this->params->get('llmstxt_org_url', ''));

            if (!$orgUrl) {
                $uri    = Uri::getInstance();
                $orgUrl = $uri->getScheme() . '://' . $uri->getHost();
            }
            $orgUrl = rtrim($orgUrl, '/');

            if (!$orgName) {
                try {
                    $orgName = Factory::getApplication()->get('sitename', '');
                } catch (\Throwable) {
                    $orgName = '';
                }
            }

            $lines   = [];
            $lines[] = '# ' . ($orgName ?: 'Website');
            if ($orgDesc) {
                $lines[] = '> ' . $orgDesc;
            }
            $lines[] = '';

            $customPages = trim((string) $this->params->get('llmstxt_custom_pages', ''));
            if ($customPages) {
                $lines[] = '## Pages';
                foreach (array_filter(array_map('trim', explode("\n", $customPages))) as $line) {
                    $lines[] = $line;
                }
                $lines[] = '';
            }

            $lines[] = '## More';
            $lines[] = '- [Full content index](' . $orgUrl . '/llms-full.txt)';
            $lines[] = '';

            $content = implode("\n", $lines);

            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: public, max-age=3600');
            header('X-Robots-Tag: noindex');
            echo $content;
            Factory::getApplication()->close();
        } catch (\Throwable $e) {
            error_log('[AI Boost AEO] llms.txt error: ' . $e->getMessage());
        }
    }

    private function serveLlmsFullTxt(): void
    {
        try {
            $orgName = trim((string) $this->params->get('llmstxt_org_name', ''));
            $orgUrl  = trim((string) $this->params->get('llmstxt_org_url', ''));
            if (!$orgUrl) {
                $uri    = Uri::getInstance();
                $orgUrl = $uri->getScheme() . '://' . $uri->getHost();
            }
            $orgUrl = rtrim($orgUrl, '/');

            if (!$orgName) {
                try {
                    $orgName = Factory::getApplication()->get('sitename', '');
                } catch (\Throwable $e) {
                    error_log('[AI Boost AEO] Sitename fetch error: ' . $e->getMessage());
                }
            }

            $lines   = [];
            $lines[] = '# ' . ($orgName ?: 'Website') . ' — Full Content Index';
            $lines[] = '';
            $lines[] = '## Articles';

            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select(['a.id', 'a.title', 'a.alias', 'a.introtext', 'c.alias AS cat_alias'])
                ->from($db->quoteName('#__content', 'a'))
                ->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON c.id = a.catid')
                ->where('a.state = 1')
                ->order('a.created DESC');
            $db->setQuery($query, 0, 200);
            $articles = $db->loadObjectList();

            foreach ($articles as $art) {
                $slug = ($art->cat_alias ? $art->cat_alias . '/' : '') . $art->alias;
                $url  = $orgUrl . '/' . $slug;
                $desc = trim(strip_tags((string) ($art->introtext ?? '')));
                $desc = mb_substr($desc, 0, 120);
                $lines[] = '- [' . ($art->title ?? '') . '](' . $url . ')' . ($desc ? ' — ' . $desc : '');
            }

            $lines[] = '';

            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: public, max-age=3600');
            header('X-Robots-Tag: noindex');
            echo implode("\n", $lines);
            Factory::getApplication()->close();
        } catch (\Throwable $e) {
            error_log('[AI Boost AEO] llms-full.txt error: ' . $e->getMessage());
        }
    }

    // ── robots.txt ──────────────────────────────────────────────────────────

    private function serveRobotsTxt(): void
    {
        try {
            $custom = trim((string) $this->params->get('robots_txt_custom', ''));

            if ($custom) {
                header('Content-Type: text/plain; charset=utf-8');
                header('Cache-Control: public, max-age=3600');
                echo $custom;
                Factory::getApplication()->close();
                return;
            }

            $uri    = Uri::getInstance();
            $orgUrl = $uri->getScheme() . '://' . $uri->getHost();

            // Determine AI crawler access mode.
            // Backwards compat: if new param robots_ai_mode is not set, fall back
            // to the legacy robots_allow_ai_crawlers toggle (v1.0.0).
            $mode = trim((string) $this->params->get('robots_ai_mode', ''));
            if ($mode === '') {
                $mode = (int) $this->params->get('robots_allow_ai_crawlers', 1) ? 'allow_all' : 'block_all';
            }

            // Map: [user-agent string => param name for "block this crawler"]
            // Claude-Web shares a param with ClaudeBot (same Anthropic family).
            $crawlerMap = [
                'GPTBot'          => 'robots_block_gptbot',
                'ChatGPT-User'    => 'robots_block_chatgpt_user',
                'ClaudeBot'       => 'robots_block_claudebot',
                'Claude-Web'      => 'robots_block_claudebot',
                'anthropic-ai'    => 'robots_block_anthropic',
                'PerplexityBot'   => 'robots_block_perplexity',
                'Google-Extended' => 'robots_block_google_extended',
                'CCBot'           => 'robots_block_ccbot',
                'cohere-ai'       => 'robots_block_cohere',
                'Googlebot'       => 'robots_block_googlebot',
                'Bingbot'         => 'robots_block_bingbot',
            ];

            $lines = [
                'User-agent: *',
                'Allow: /',
                '',
                '# Joomla system paths',
                'Disallow: /administrator/',
                'Disallow: /api/',
                'Disallow: /bin/',
                'Disallow: /cache/',
                'Disallow: /cli/',
                'Disallow: /components/',
                'Disallow: /includes/',
                'Disallow: /installation/',
                'Disallow: /language/',
                'Disallow: /layouts/',
                'Disallow: /libraries/',
                'Disallow: /logs/',
                'Disallow: /modules/',
                'Disallow: /plugins/',
                'Disallow: /tmp/',
                '',
                '# Allow public assets',
                'Allow: /templates/',
                'Allow: /media/',
                'Allow: /images/',
                '',
            ];

            foreach ($crawlerMap as $bot => $param) {
                $lines[] = 'User-agent: ' . $bot;

                if ($mode === 'allow_all') {
                    $lines[] = 'Allow: /';
                } elseif ($mode === 'block_all') {
                    $lines[] = 'Disallow: /';
                } else {
                    // custom — per-crawler setting; default to allow (0 = allow, 1 = block)
                    $blocked = (int) $this->params->get($param, 0);
                    $lines[] = $blocked ? 'Disallow: /' : 'Allow: /';
                }

                $lines[] = '';
            }

            $lines[] = 'Sitemap: ' . $orgUrl . '/sitemap.xml';

            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: public, max-age=3600');
            echo implode("\n", $lines);
            Factory::getApplication()->close();
        } catch (\Throwable $e) {
            error_log('[AI Boost AEO] robots.txt error: ' . $e->getMessage());
        }
    }

    // ── Markdown Pages ──────────────────────────────────────────────────────

    private function maybeServeMarkdownPage(string $path): void
    {
        $isMdSuffix   = str_ends_with($path, '.md');
        $isMdParam    = isset($_GET['markdown']) && $_GET['markdown'] === '1';
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
        $isMdAccept   = str_contains($acceptHeader, 'text/markdown');

        if (!$isMdSuffix && !$isMdParam && !$isMdAccept) {
            return;
        }

        // Only for com_content article pages
        $uri  = $_SERVER['REQUEST_URI'] ?? '';
        $qs   = parse_url($uri, PHP_URL_QUERY) ?? '';
        parse_str($qs, $params);

        $articleId = 0;
        if (!empty($params['id']) && !empty($params['view']) && $params['view'] === 'article') {
            $articleId = (int) $params['id'];
        }

        if (!$articleId && ($isMdSuffix || $isMdAccept || $isMdParam)) {
            $cleanPath = $isMdSuffix ? substr($path, 0, -3) : $path;
            $alias = basename(rtrim($cleanPath, '/'));
            if ($alias && $alias !== 'index.php') {
                $db = Factory::getDbo();
                $q  = $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName('#__content'))
                    ->where($db->quoteName('alias') . ' = ' . $db->quote($alias))
                    ->where($db->quoteName('state') . ' = 1');
                $db->setQuery($q, 0, 1);
                $articleId = (int) $db->loadResult();
            }
        }

        if (!$articleId) {
            return;
        }

        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select(['title', 'introtext', 'fulltext', 'created', 'modified'])
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('id') . ' = ' . $articleId)
                ->where($db->quoteName('state') . ' = 1');
            $db->setQuery($query);
            $row = $db->loadObject();
        } catch (\Throwable $e) {
            error_log('[AI Boost AEO] Markdown article load error: ' . $e->getMessage());
            return;
        }

        if (!$row) {
            return;
        }

        $title    = $row->title ?? '';
        $intro    = trim(strip_tags((string) ($row->introtext ?? '')));
        $full     = trim(strip_tags((string) ($row->fulltext ?? '')));
        $body     = $intro . ($full ? "\n\n" . $full : '');
        $created  = $row->created ?? '';

        $md  = '# ' . $title . "\n\n";
        if ($created) {
            $md .= '_Published: ' . date('Y-m-d', strtotime($created)) . "_\n\n";
        }
        $md .= $body;

        header('Content-Type: text/markdown; charset=utf-8');
        header('Cache-Control: public, max-age=600');
        echo $md;
        Factory::getApplication()->close();
    }

    // ── IndexNow submission ─────────────────────────────────────────────────

    private function resolveArticleUrl(int $id): string
    {
        if (!$id) {
            return '';
        }
        try {
            $uri = Uri::getInstance();
            return $uri->getScheme() . '://' . $uri->getHost() . '/index.php?option=com_content&view=article&id=' . $id;
        } catch (\Throwable) {
            return '';
        }
    }

    private function submitToIndexNow(string $url, string $key): void
    {
        try {
            if (!function_exists('curl_init')) {
                return;
            }
            $orgUrl      = trim((string) $this->params->get('llmstxt_org_url', ''));
            if (!$orgUrl) {
                $uri    = Uri::getInstance();
                $orgUrl = $uri->getScheme() . '://' . $uri->getHost();
            }
                $keyLocation = rtrim($orgUrl, '/') . '/' . $key . '.txt';
            $payload = json_encode([
                'host'        => parse_url($url, PHP_URL_HOST),
                'key'         => $key,
                'keyLocation' => $keyLocation,
                'urlList'     => [$url],
            ]);
            $ch = curl_init('https://api.indexnow.org/indexnow');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable $e) {
            error_log('[AI Boost AEO] IndexNow submit error: ' . $e->getMessage());
        }
    }
}
