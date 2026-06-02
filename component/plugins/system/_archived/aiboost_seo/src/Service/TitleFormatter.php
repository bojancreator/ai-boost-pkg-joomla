<?php
/**
 * AI Boost — SEO Plugin — Title Formatter
 *
 * Resolves the final page title by applying a token-based template.
 *
 * Free tokens: {page_title}, {site_name}
 * Pro tokens:  {category}, {author}, {year}, {page_num}
 *
 * Per-view templates (Pro):
 *   If the admin configured a view-specific template (homepage, article,
 *   category, tag), that template takes priority over the global one.
 *
 * Per-article override (Pro):
 *   If an article has a Joomla custom field named `aiboost_seo_title`,
 *   that value is returned as-is (no token substitution needed).
 *
 * @package     AiBoost\Plugin\System\AiBoostSeo
 * @version     0.7.0
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostSeo\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\Registry\Registry;

// SeoCustomFieldReader is in the same namespace/directory; require explicitly
// because the plugin entry-point IIFE only autoloads lib/* classes.
if (!class_exists(SeoCustomFieldReader::class, false)) {
    require_once __DIR__ . '/SeoCustomFieldReader.php';
}

class TitleFormatter
{
    private Registry $params;
    private bool $isPro;
    private CMSApplication $app;

    public function __construct(Registry $params, bool $isPro, CMSApplication $app)
    {
        $this->params = $params;
        $this->isPro  = $isPro;
        $this->app    = $app;
    }

    /**
     * Format the page title.
     *
     * @param  string $rawTitle   The raw Joomla page title (e.g. from $doc->getTitle()).
     * @param  int    $articleId  The current article ID (0 if not an article page).
     * @return string             The final formatted title, or raw title if nothing to change.
     */
    public function format(string $rawTitle, int $articleId = 0): string
    {
        // Pro: per-article custom field override — Falang-aware
        if ($this->isPro && $articleId > 0) {
            $override = SeoCustomFieldReader::read($articleId, 'aiboost_seo_title');
            if ($override !== '') {
                return $override;
            }
        }

        $template = $this->resolveTemplate($rawTitle, $articleId);
        if ($template === '') {
            return $rawTitle;
        }

        return $this->applyTokens($template, $rawTitle, $articleId);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Template resolution
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Pick the right template string for this request.
     *
     * Resolution order:
     *   1. Pro per-view template (homepage, article, category, tag).
     *   2. Global title_template param — with title_separator and
     *      title_position adjustments applied if needed.
     *
     * title_separator replaces the literal `|` in the template with
     * whatever separator the admin chose (-, –, ·, •, >).
     *
     * title_position swaps {page_title} and {site_name} in the template
     * when set to 'prefix' (site name appears first).
     */
    private function resolveTemplate(string $rawTitle, int $articleId): string
    {
        if ($this->isPro) {
            $viewTemplate = $this->getViewSpecificTemplate();
            if ($viewTemplate !== '') {
                return $viewTemplate;
            }
        }

        $template = (string) $this->params->get('title_template', '{page_title} | {site_name}');

        // Apply title_separator: replace every ` | ` occurrence with chosen separator
        $separator = trim((string) $this->params->get('title_separator', '|'));
        if ($separator !== '' && $separator !== '|') {
            $template = str_replace(' | ', ' ' . $separator . ' ', $template);
        }

        // Apply title_position: when 'prefix', swap {page_title} and {site_name}
        // so the site name appears before the page title.
        $position = (string) $this->params->get('title_position', 'suffix');
        if ($position === 'prefix') {
            $ptPos = strpos($template, '{page_title}');
            $snPos = strpos($template, '{site_name}');
            // Only swap when page_title token appears before site_name (default order)
            if ($ptPos !== false && $snPos !== false && $ptPos < $snPos) {
                $template = str_replace('{page_title}', "\x00PAGE_TITLE\x00", $template);
                $template = str_replace('{site_name}', '{page_title}', $template);
                $template = str_replace("\x00PAGE_TITLE\x00", '{site_name}', $template);
            }
        }

        return $template;
    }

    /**
     * Detect the current Joomla view and return a per-view template if configured.
     */
    private function getViewSpecificTemplate(): string
    {
        $input  = $this->app->getInput();
        $option = $input->get('option', '');
        $view   = $input->get('view', '');

        // Homepage
        if ($this->isHomePage()) {
            return trim((string) $this->params->get('title_homepage_template', ''));
        }

        // Article view
        if ($option === 'com_content' && $view === 'article') {
            return trim((string) $this->params->get('title_article_template', ''));
        }

        // Category/blog view
        if ($option === 'com_content' && in_array($view, ['category', 'featured'], true)) {
            return trim((string) $this->params->get('title_category_template', ''));
        }

        // Tag view
        if ($option === 'com_tags' && $view === 'tag') {
            return trim((string) $this->params->get('title_tag_template', ''));
        }

        return '';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Token substitution
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Replace all supported tokens in the template string.
     */
    private function applyTokens(string $template, string $rawTitle, int $articleId): string
    {
        $siteName = (string) $this->app->get('sitename', '');

        $tokens = [
            '{page_title}' => $rawTitle,
            '{site_name}'  => $siteName,
        ];

        // Pro tokens
        if ($this->isPro) {
            $tokens['{category}'] = $this->resolveCategory($articleId);
            $tokens['{author}']   = $this->resolveAuthor($articleId);
            $tokens['{year}']     = date('Y');
            $tokens['{page_num}'] = $this->resolvePageNum();
        }

        $result = str_replace(array_keys($tokens), array_values($tokens), $template);

        // Clean up unreplaced tokens so they don't appear literally
        $result = preg_replace('/\{[a-z_]+\}/', '', $result) ?? $result;

        // Collapse multiple separators / whitespace that may result
        $result = preg_replace('/\s{2,}/', ' ', $result) ?? $result;

        return trim($result);
    }

    /**
     * Resolve {category} — the parent category title of the current article.
     */
    private function resolveCategory(int $articleId): string
    {
        if ($articleId <= 0) {
            return '';
        }
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName('c.title'))
                ->from($db->quoteName('#__content', 'a'))
                ->join(
                    'INNER',
                    $db->quoteName('#__categories', 'c')
                    . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid')
                )
                ->where($db->quoteName('a.id') . ' = ' . $articleId);
            $db->setQuery($query, 0, 1);
            return (string) ($db->loadResult() ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Resolve {author} — the display name of the article's author.
     */
    private function resolveAuthor(int $articleId): string
    {
        if ($articleId <= 0) {
            return '';
        }
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName('u.name'))
                ->from($db->quoteName('#__content', 'a'))
                ->join(
                    'INNER',
                    $db->quoteName('#__users', 'u')
                    . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('a.created_by')
                )
                ->where($db->quoteName('a.id') . ' = ' . $articleId);
            $db->setQuery($query, 0, 1);
            return (string) ($db->loadResult() ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Resolve {page_num} — returns "Page N" when ?start= is present, else empty.
     */
    private function resolvePageNum(): string
    {
        $start    = (int) $this->app->getInput()->get('start', 0);
        $limitstart = (int) $this->app->getInput()->get('limitstart', 0);
        $offset   = max($start, $limitstart);

        if ($offset <= 0) {
            return '';
        }

        // Joomla default list limit (20 items per page)
        $limit = max(1, (int) $this->app->get('list_limit', 20));
        $page  = (int) floor($offset / $limit) + 1;

        return 'Page ' . $page;
    }

    /**
     * Check whether the current request is for the site homepage.
     */
    private function isHomePage(): bool
    {
        $menu = $this->app->getMenu();
        return $menu && $menu->getActive() === $menu->getDefault();
    }

}

