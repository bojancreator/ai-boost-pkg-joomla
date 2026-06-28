<?php
/**
 * AI Boost — OgTagProDecorator
 *
 * Pro-only decorator that listens on `EVENT_FILTER_SOCIAL_PROPS` and
 * enriches the structured OG/Twitter props built by the Free
 * `OgTagBuilder`. Lives in the FREE `aiboost_social` plugin but is the
 * physical home for every Pro OpenGraph feature; the build strips this file
 * from the Free package (FREE_EXCLUDE) and the host only instantiates it when
 * PluginRegistry::isProActive(), so a Free install cannot deliver any of these
 * features regardless of settings. (Relocated here during the "Pro replaces
 * Free" collapse; the old aiboost_social_pro home is retired.)
 *
 * Decoration scope:
 *   - Per-language site_name / og_description_override / default_og_image
 *     via TranslationService
 *   - Per-article custom fields (aiboost_og_*) with Falang overlay
 *   - Article intro-image fallback when no custom field set
 *   - og:type=article + article:published_time / modified_time / author / section
 *   - og:locale (auto from active language tag)
 *   - og:video (per-article custom field)
 *   - fb:app_id
 *   - twitter:site handle
 *   - twitter:card type override (custom field)
 *
 * @package     AiBoost\Plugin\System\AiBoostSocial
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostSocial\Service;

defined('_JEXEC') or die;

use AiBoost\Lib\AppContextInterface;
use AiBoost\Lib\Page\PageContext;
use AiBoost\Lib\TranslationService;
use AiBoost\Plugin\System\AiBoostSocial\Service\OgTagBuilder;
use Joomla\Database\DatabaseInterface;

class OgTagProDecorator
{
    private const LOCALE_MAP = [
        'en-GB' => 'en_US',
        'en-US' => 'en_US',
        'de-DE' => 'de_DE',
        'fr-FR' => 'fr_FR',
        'es-ES' => 'es_ES',
        'it-IT' => 'it_IT',
        'pt-BR' => 'pt_BR',
        'ru-RU' => 'ru_RU',
        'nl-NL' => 'nl_NL',
        'pl-PL' => 'pl_PL',
    ];

    public function __construct(
        private readonly AppContextInterface $ctx,
        private readonly DatabaseInterface $db,
        // Nullable (D3): when Multilang Pro is not active the decorator still
        // runs on the 'og' bundle Pro, just without per-language overlays.
        private readonly ?TranslationService $translations = null,
        // T1·S3: the resolved per-request page context. When provided (production,
        // via AdapterRegistry::pageResolver()), the article gate reads its RAW
        // primitives; when null (unit tests) it falls back to $props['context'].
        // Behaviour-identical — the resolver's option/view/rawId are the same
        // values OgTagBuilder seeds into props['context']. (NOT the homepage-first
        // isArticle() semantics — that change is S7.)
        private readonly ?PageContext $pageContext = null,
    ) {}

    /**
     * Mutate the structured props in place, applying every Pro enrichment.
     *
     * @param array{
     *     og: array<string,string>,
     *     tw: array<string,string>,
     *     enable_twitter: bool,
     *     context: array{option:string, view:string, id:int}
     * } $props
     * @param array<string,mixed> $settings
     * @return array<string,mixed>
     */
    public function decorate(array $props, array $settings): array
    {
        $og   = $props['og'] ?? [];
        $tw   = $props['tw'] ?? [];
        $ctx  = $props['context'] ?? ['option' => '', 'view' => '', 'id' => 0];
        $lc   = $this->ctx->getActiveLanguage();

        // ── Sitewide per-language translations ────────────────────────────────
        $siteNameBase = trim((string) ($settings['site_name'] ?? ''));
        if ($siteNameBase === '') {
            $siteNameBase = $this->ctx->getSiteName();
        }
        $siteNameTr = $this->translations?->get('site_name', $lc, $siteNameBase) ?? $siteNameBase;
        if ($siteNameTr !== '') {
            $og['og:site_name'] = $siteNameTr;
        }

        $descOverrideTr = $this->translations?->get('og_description_override', $lc, '') ?? '';
        if ($descOverrideTr !== '') {
            $og['og:description'] = $descOverrideTr;
            $tw['twitter:description'] = $descOverrideTr;
        }

        $imgBase = OgTagBuilder::normaliseImagePath(
            (string) ($settings['default_og_image'] ?? $settings['og_default_image'] ?? '')
        );
        $imgTr = OgTagBuilder::normaliseImagePath(
            $this->translations?->get('default_og_image', $lc, $imgBase) ?? $imgBase
        );
        if ($imgTr !== '' && $imgTr !== $imgBase) {
            $abs = $this->absoluteUrl($imgTr);
            $og['og:image']      = $abs;
            $tw['twitter:image'] = $abs;
            // Parity with the monolithic builder: dimensions follow the final selected image.
            [$w, $h] = $this->resolveImageDimensions($imgTr, $settings);
            if ($w > 0) {
                $og['og:image:width']  = (string) $w;
            }
            if ($h > 0) {
                $og['og:image:height'] = (string) $h;
            }
        }

        // ── Article enrichment ────────────────────────────────────────────────
        // T1·S3: prefer the resolved PageContext raw primitives (production);
        // fall back to the props context array (unit tests / no resolver).
        // Identical values — byte-identical OG output, single-article-home incl.
        $option = $this->pageContext !== null ? $this->pageContext->option : (string) ($ctx['option'] ?? '');
        $view   = $this->pageContext !== null ? $this->pageContext->view   : (string) ($ctx['view']   ?? '');
        $id     = $this->pageContext !== null ? $this->pageContext->rawId  : (int)    ($ctx['id']     ?? 0);

        $ogTypeFromField = false;

        if ($option === 'com_content' && $view === 'article' && $id > 0) {
            $article = $this->loadArticle($id);

            if ($article) {
                $articleImg = '';
                $videoUrl   = '';
                $twCardType = '';

                if ((int) ($settings['enable_per_article_fields'] ?? 1)) {
                    $reader = new CustomFieldReader($this->db);
                    $fields = $reader->read($id, $lc);

                    if ($fields['og_title'] !== '') {
                        $og['og:title']      = $fields['og_title'];
                        $tw['twitter:title'] = $fields['og_title'];
                    }
                    if ($fields['og_description'] !== '') {
                        $og['og:description']      = $fields['og_description'];
                        $tw['twitter:description'] = $fields['og_description'];
                    }
                    if ($fields['og_image'] !== '') {
                        $articleImg = OgTagBuilder::normaliseImagePath($fields['og_image']);
                    }

                    $allowedOgTypes = ['article', 'website', 'video.movie', 'music.song', 'product'];
                    if ($fields['og_type'] !== '' && in_array($fields['og_type'], $allowedOgTypes, true)) {
                        $og['og:type']   = $fields['og_type'];
                        $ogTypeFromField = true;
                    }

                    if ($fields['og_video'] !== '') {
                        $videoUrl = $this->absoluteUrl($fields['og_video']);
                    }

                    $allowedCards = ['summary', 'summary_large_image'];
                    if ($fields['twitter_card'] !== '' && in_array($fields['twitter_card'], $allowedCards, true)) {
                        $twCardType = $fields['twitter_card'];
                    }
                }

                // No custom image field → prefer article intro image over global default
                if ($articleImg === '') {
                    $introRaw = $this->extractIntroImageRaw($article);
                    if ($introRaw !== '') {
                        $articleImg = $introRaw;
                    }
                }

                if ($articleImg !== '') {
                    $absImg              = $this->absoluteUrl($articleImg);
                    $og['og:image']      = $absImg;
                    $tw['twitter:image'] = $absImg;
                    [$w, $h] = $this->resolveImageDimensions($articleImg, $settings);
                    if ($w > 0) {
                        $og['og:image:width'] = (string) $w;
                    }
                    if ($h > 0) {
                        $og['og:image:height'] = (string) $h;
                    }
                }

                if ($videoUrl !== '') {
                    $og['og:video'] = $videoUrl;
                }
                if ($twCardType !== '') {
                    $tw['twitter:card'] = $twCardType;
                }

                // og:type=article + article:* meta
                if ((int) ($settings['enable_article_og_type'] ?? 1)) {
                    if (!$ogTypeFromField) {
                        $og['og:type'] = 'article';
                    }
                    foreach ($this->buildArticleMeta($article) as $prop => $value) {
                        if ($value !== '') {
                            $og[$prop] = $value;
                        }
                    }
                }
            }
        }

        // ── Sitewide Pro tags ────────────────────────────────────────────────
        if ((int) ($settings['enable_og_locale'] ?? 1)) {
            $langTag         = $this->ctx->getActiveLanguage();
            $og['og:locale'] = self::LOCALE_MAP[$langTag] ?? str_replace('-', '_', $langTag);
        }

        $fbAppId = trim((string) ($settings['fb_app_id'] ?? ''));
        if ($fbAppId !== '') {
            $og['fb:app_id'] = $fbAppId;
        }

        $handle = trim((string) ($settings['twitter_site_handle'] ?? ''));
        if ($handle !== '') {
            $tw['twitter:site'] = str_starts_with($handle, '@') ? $handle : '@' . $handle;
        }

        $props['og'] = $og;
        $props['tw'] = $tw;

        return $props;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function loadArticle(int $id): ?object
    {
        try {
            $db    = $this->db;
            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('id'),
                    $db->quoteName('title'),
                    $db->quoteName('metadesc'),
                    $db->quoteName('images'),
                    $db->quoteName('publish_up'),
                    $db->quoteName('modified'),
                    $db->quoteName('created_by'),
                    $db->quoteName('catid'),
                ])
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('id') . ' = ' . $id)
                ->where($db->quoteName('state') . ' = 1');
            $db->setQuery($query, 0, 1);
            return $db->loadObject() ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function extractIntroImageRaw(object $article): string
    {
        $imagesJson = trim((string) ($article->images ?? ''));
        if ($imagesJson === '' || $imagesJson === '{}') {
            return '';
        }
        try {
            $images = json_decode($imagesJson, true);
            $raw    = trim((string) ($images['image_intro'] ?? ''));
            if ($raw === '') {
                $raw = trim((string) ($images['image_fulltext'] ?? ''));
            }
            return OgTagBuilder::normaliseImagePath($raw);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * @param array<string,mixed> $settings
     * @return int[] [width, height]
     */
    private function resolveImageDimensions(string $path, array $settings): array
    {
        $defaultW = (int) ($settings['og_image_width']  ?? 1200);
        $defaultH = (int) ($settings['og_image_height'] ?? 630);

        $path = OgTagBuilder::normaliseImagePath($path);

        if ($path !== '' && !str_starts_with($path, 'http://') && !str_starts_with($path, 'https://')) {
            $localPath = rtrim(JPATH_ROOT, '/') . '/' . ltrim($path, '/');
            if (is_file($localPath)) {
                try {
                    $info = @getimagesize($localPath);
                    if ($info && isset($info[0], $info[1]) && $info[0] > 0 && $info[1] > 0) {
                        return [(int) $info[0], (int) $info[1]];
                    }
                } catch (\Throwable $e) {
                    // fall through
                }
            }
        }

        return [$defaultW, $defaultH];
    }

    /**
     * @return array<string,string>
     */
    private function buildArticleMeta(object $article): array
    {
        $meta = [];

        $publishUp = trim((string) ($article->publish_up ?? ''));
        if ($publishUp && $publishUp !== '0000-00-00 00:00:00') {
            try {
                $meta['article:published_time'] = (new \DateTime($publishUp))->format(\DateTime::ATOM);
            } catch (\Throwable $e) {}
        }

        $modified = trim((string) ($article->modified ?? ''));
        if ($modified && $modified !== '0000-00-00 00:00:00') {
            try {
                $meta['article:modified_time'] = (new \DateTime($modified))->format(\DateTime::ATOM);
            } catch (\Throwable $e) {}
        }

        $authorName = $this->loadAuthorName((int) ($article->created_by ?? 0));
        if ($authorName !== '') {
            $meta['article:author'] = $authorName;
        }

        $section = $this->loadCategoryTitle((int) ($article->catid ?? 0));
        if ($section !== '') {
            $meta['article:section'] = $section;
        }

        return $meta;
    }

    private function loadAuthorName(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }
        try {
            $db    = $this->db;
            $query = $db->getQuery(true)
                ->select($db->quoteName('name'))
                ->from($db->quoteName('#__users'))
                ->where($db->quoteName('id') . ' = ' . $userId);
            $db->setQuery($query, 0, 1);
            return trim((string) ($db->loadResult() ?? ''));
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function loadCategoryTitle(int $catId): string
    {
        if ($catId <= 0) {
            return '';
        }
        try {
            $db    = $this->db;
            $query = $db->getQuery(true)
                ->select($db->quoteName('title'))
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('id') . ' = ' . $catId);
            $db->setQuery($query, 0, 1);
            return trim((string) ($db->loadResult() ?? ''));
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function absoluteUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return $this->ctx->getBaseUrl() . '/' . ltrim($path, '/');
    }
}
