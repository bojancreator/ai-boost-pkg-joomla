<?php
/**
 * AI Boost — SEO Plugin
 *
 * Handles: page title formatting, meta description, canonical URLs,
 * meta robots directives, hreflang (Pro), and redirect management (Pro).
 *
 * Free tier:
 *   - Title template with {page_title} and {site_name} tokens
 *   - Global meta description fallback
 *   - Self-referencing canonical URL (strips tracking query params)
 *   - Global robots meta (index,follow by default)
 *   - Per-menu-item noindex via comma-separated menu item ID list
 *   - ConflictManager: claims SLOT_CANONICAL and 'meta_title'
 *
 * Pro tier (valid license key):
 *   - Per-article SEO title and description via Joomla custom fields
 *     (aiboost_seo_title, aiboost_seo_description)
 *   - Advanced title tokens: {category}, {author}, {year}, {page_num}
 *   - Per-view title templates (homepage, article, category, tag)
 *   - Hreflang tags built from #__associations table
 *   - Pagination canonical: <link rel="prev"> and <link rel="next">
 *   - Redirect manager: 301/302 redirects applied on onAfterRoute
 *
 * @package     AiBoost\Plugin\System\AiBoostSeo
 * @version     0.7.0
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostSeo\Extension;

defined('_JEXEC') or die;

use AiBoost\Plugin\System\AiBoostSeo\Service\CanonicalBuilder;
use AiBoost\Plugin\System\AiBoostSeo\Service\HreflangBuilder;
use AiBoost\Plugin\System\AiBoostSeo\Service\SeoCustomFieldReader;
use AiBoost\Plugin\System\AiBoostSeo\Service\TitleFormatter;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

class AiBoostSeo extends CMSPlugin
{
    use \AiBoost\Lib\ProGate;

    protected $autoloadLanguage = true;

    /** Whether this plugin claimed the SLOT_CANONICAL ConflictManager slot. */
    private bool $canonicalClaimed = false;

    /** Whether this plugin claimed the meta_title ConflictManager slot. */
    private bool $metaTitleClaimed = false;

    // ─────────────────────────────────────────────────────────────────────────
    // Lifecycle hooks
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * onAfterInitialise — claim ConflictManager slots early.
     *
     * Claiming here (before onBeforeCompileHead) ensures other plugins
     * see an accurate registry for the entire request lifecycle.
     */
    public function onAfterInitialise(): void
    {
        if ($this->ensureConflictManager()) {
            $this->canonicalClaimed = \AiBoost\Lib\ConflictManager::claim(
                \AiBoost\Lib\ConflictManager::SLOT_CANONICAL,
                'aiboost_seo'
            );
            $this->metaTitleClaimed = \AiBoost\Lib\ConflictManager::claim(
                'meta_title',
                'aiboost_seo'
            );
        } else {
            $this->canonicalClaimed = true;
            $this->metaTitleClaimed = true;
        }
    }

    /**
     * onAfterRoute — Pro: process redirect rules before any output.
     */
    public function onAfterRoute(): void
    {
        if (!$this->isProEnabled()) {
            return;
        }
        if (!(bool) $this->params->get('enable_redirects', 0)) {
            return;
        }

        $app = Factory::getApplication();
        if (!$app->isClient('site')) {
            return;
        }

        $this->processRedirects($app);
    }

    /**
     * onBeforeCompileHead — inject all SEO tags into <head>.
     *
     * Uses the Joomla Document API so tags are merged cleanly
     * before the renderer serialises the <head> block.
     */
    public function onBeforeCompileHead(): void
    {
        $app = Factory::getApplication();
        if (!$app->isClient('site')) {
            return;
        }

        $doc = $app->getDocument();
        if (!$doc || $doc->getType() !== 'html') {
            return;
        }

        $isPro     = $this->isProEnabled();
        $input     = $app->getInput();
        $option    = $input->get('option', '');
        $view      = $input->get('view', '');
        $articleId = ($option === 'com_content' && $view === 'article')
            ? (int) $input->get('id', 0)
            : 0;

        // ── 1. Title ─────────────────────────────────────────────────────────
        if ($this->metaTitleClaimed) {
            $formatter  = new TitleFormatter($this->params, $isPro, $app);
            $rawTitle   = $doc->getTitle();
            $finalTitle = $formatter->format($rawTitle, $articleId);
            if ($finalTitle !== '' && $finalTitle !== $rawTitle) {
                $doc->setTitle($finalTitle);
            }
        }

        // ── 2. Meta description ───────────────────────────────────────────────
        $this->injectMetaDescription($doc, $articleId, $isPro, $app);

        // ── 3. Robots meta ────────────────────────────────────────────────────
        $this->injectRobots($doc, $app, $articleId, $isPro);

        // ── 4. Canonical ──────────────────────────────────────────────────────
        if ($this->canonicalClaimed && (bool) $this->params->get('enable_canonical', 1)) {
            $builder   = new CanonicalBuilder($this->params, $isPro, $app);
            $canonical = $builder->getCanonical();
            if ($canonical !== '') {
                $doc->addCustomTag('<link rel="canonical" href="' . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . '">');
            }
            // Pro: pagination prev/next
            if ($isPro && (bool) $this->params->get('pagination_canonical', 0)) {
                $prev = $builder->getPrev();
                $next = $builder->getNext();
                if ($prev !== '') {
                    $doc->addCustomTag('<link rel="prev" href="' . htmlspecialchars($prev, ENT_QUOTES, 'UTF-8') . '">');
                }
                if ($next !== '') {
                    $doc->addCustomTag('<link rel="next" href="' . htmlspecialchars($next, ENT_QUOTES, 'UTF-8') . '">');
                }
            }
        }

        // ── 5. Hreflang (Pro) ─────────────────────────────────────────────────
        if (
            $isPro
            && (bool) $this->params->get('enable_hreflang', 0)
            && \AiBoost\Lib\ConflictManager::claim(\AiBoost\Lib\ConflictManager::SLOT_HREFLANG, 'aiboost_seo')
        ) {
            $hreflangBuilder = new HreflangBuilder($this->params, $app);
            $tags = $hreflangBuilder->buildTags($articleId);
            foreach ($tags as $tag) {
                $doc->addCustomTag($tag);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Inject a meta description tag.
     *
     * Priority:
     *  1. Pro: per-article custom field `aiboost_seo_description`
     *     (with Falang translation overlay via SeoCustomFieldReader)
     *  2. Article's own Joomla meta description (already set by Joomla)
     *  3. Global default_meta_description param
     */
    private function injectMetaDescription(
        \Joomla\CMS\Document\HtmlDocument $doc,
        int $articleId,
        bool $isPro,
        \Joomla\CMS\Application\CMSApplication $app
    ): void {
        // Check for per-article override (Pro) — Falang-aware
        if ($isPro && $articleId > 0) {
            $override = SeoCustomFieldReader::read($articleId, 'aiboost_seo_description');
            if ($override !== '') {
                $doc->setMetaData('description', $override);
                return;
            }
        }

        // Fall back to global default if Joomla hasn't set one
        $existing = $doc->getMetaData('description');
        if ($existing === '' || $existing === null) {
            $default = trim((string) $this->params->get('default_meta_description', ''));
            if ($default !== '') {
                $doc->setMetaData('description', $default);
            }
        }
    }

    /**
     * Set the robots meta tag.
     *
     * Priority (highest → lowest):
     *  1. Pro: per-article aiboost_robots custom field
     *  2. Noindex menu item list (Free)
     *  3. Global default_robots param (Free)
     */
    private function injectRobots(
        \Joomla\CMS\Document\HtmlDocument $doc,
        \Joomla\CMS\Application\CMSApplication $app,
        int $articleId,
        bool $isPro
    ): void {
        $allowed = ['index, follow', 'noindex, follow', 'index, nofollow', 'noindex, nofollow'];

        // 1. Pro: per-article custom field override (Falang-aware).
        // Normalize both spaced ("noindex, follow") and unspaced ("noindex,follow")
        // forms so values stored in either format are accepted.
        if ($isPro && $articleId > 0) {
            $rawField = SeoCustomFieldReader::read($articleId, 'aiboost_robots');
            if ($rawField !== '') {
                // Normalise: ensure exactly one space after comma
                $fieldVal = (string) preg_replace('/\s*,\s*/', ', ', $rawField);
                if (in_array($fieldVal, $allowed, true)) {
                    $doc->setMetaData('robots', $fieldVal);
                    return;
                }
            }
        }

        $globalRobots = (string) $this->params->get('default_robots', 'index, follow');

        // 2. Noindex menu item ID list
        $noindexList = (string) $this->params->get('noindex_menu_items', '');
        if ($noindexList !== '') {
            $noindexIds   = array_map('intval', array_filter(array_map('trim', explode(',', $noindexList))));
            $activeItemId = (int) $app->getMenu()->getActive()?->id;
            if ($activeItemId > 0 && in_array($activeItemId, $noindexIds, true)) {
                $doc->setMetaData('robots', 'noindex, follow');
                return;
            }
        }

        // 3. Global default
        $doc->setMetaData('robots', $globalRobots);
    }

    /**
     * Pro: match the current request path against configured redirect rules
     * and issue a header redirect if a rule matches.
     */
    private function processRedirects(\Joomla\CMS\Application\CMSApplication $app): void
    {
        $rules = $this->params->get('redirect_rules', null);
        if (empty($rules)) {
            return;
        }

        // Normalise subform output (object or array)
        if (is_object($rules)) {
            $rules = (array) $rules;
        }

        $requestPath = '/' . ltrim(Uri::getInstance()->getPath(), '/');

        foreach ($rules as $rule) {
            $rule = (array) $rule;
            $source      = trim((string) ($rule['source_path'] ?? ''));
            $destination = trim((string) ($rule['destination_url'] ?? ''));
            $type        = (int) ($rule['redirect_type'] ?? 301);

            if ($source === '' || $destination === '') {
                continue;
            }

            // Normalise source path
            $source = '/' . ltrim($source, '/');

            if ($requestPath === $source) {
                $app->redirect($destination, $type);
                // redirect() calls exit — execution stops here
            }
        }
    }

}

