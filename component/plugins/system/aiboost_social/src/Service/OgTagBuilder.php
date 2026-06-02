<?php
/**
 * AI Boost — OgTagBuilder (Free)
 *
 * Builds the Free baseline set of OpenGraph and Twitter Card meta tags
 * for the current page request. Emits sitewide og:* + twitter:* defaults
 * with og:type=website only — no per-article enrichment, no translations,
 * no fb:app_id / og:locale / twitter:site, no custom-field overrides.
 *
 * Pro enrichment (per-article OG fields with Falang, og:type=article +
 * article:* meta, intro-image fallback, og:locale, fb:app_id, twitter:site,
 * sitewide translations) is implemented in the closed-source Pro plugin
 * (`aiboost_social_pro`) and applied via the `EVENT_FILTER_SOCIAL_PROPS`
 * filter event. The Free service has no knowledge of, and no code path
 * to, those Pro features — they cannot be re-enabled by patching settings.
 *
 * @package     AiBoost\Plugin\System\AiBoostSocial
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostSocial\Service;

defined('_JEXEC') or die;

use AiBoost\Lib\AppContextInterface;
use Joomla\Database\DatabaseInterface;

class OgTagBuilder
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
     * Build the structured OG + Twitter props for the current page.
     *
     * Returns a structured array consumed by `EVENT_FILTER_SOCIAL_PROPS`
     * listeners (Pro decorator) and then rendered to HTML by
     * `renderProps()`. The `context` block carries enough information for
     * a Pro listener to fetch the article / category / image data it
     * needs to enrich the props.
     *
     * @return array{
     *     og: array<string,string>,
     *     tw: array<string,string>,
     *     enable_twitter: bool,
     *     context: array{option:string, view:string, id:int}
     * }
     */
    public function buildProps(): array
    {
        $option = $this->ctx->getCurrentOption();
        $view   = $this->ctx->getCurrentView();
        $id     = $this->ctx->getCurrentId();

        // ── Base values from CMS document ────────────────────────────────────
        $siteName = trim((string)($this->settings['site_name'] ?? ''));
        if ($siteName === '') {
            $siteName = $this->ctx->getSiteName();
        }

        $ogTitle = $this->ctx->getPageTitle();
        $ogDesc  = $this->ctx->getPageDescription();

        // Sitewide OG description override — base setting wins over page meta.
        $ogDescOverride = trim((string)($this->settings['og_description_override'] ?? ''));
        if ($ogDescOverride !== '') {
            $ogDesc = $ogDescOverride;
        }

        $ogUrl       = $this->ctx->getCurrentUrl();
        $ogImageRaw  = self::normaliseImagePath(
            (string) ($this->settings['default_og_image'] ?? $this->settings['og_default_image'] ?? '')
        );

        // ── Assemble OG properties (Free baseline only — og:type=website) ────
        $ogProps = [];
        $this->addProp($ogProps, 'og:type',        'website');
        $this->addProp($ogProps, 'og:url',         $ogUrl);
        $this->addProp($ogProps, 'og:site_name',   $siteName);
        $this->addProp($ogProps, 'og:title',       $ogTitle);
        $this->addProp($ogProps, 'og:description', $ogDesc);
        if ($ogImageRaw !== '') {
            $this->addProp($ogProps, 'og:image', $this->absoluteUrl($ogImageRaw));

            [$imgW, $imgH] = $this->resolveImageDimensions($ogImageRaw);
            if ($imgW > 0) {
                $this->addProp($ogProps, 'og:image:width',  (string) $imgW);
            }
            if ($imgH > 0) {
                $this->addProp($ogProps, 'og:image:height', (string) $imgH);
            }
        }

        // ── Twitter Card props ───────────────────────────────────────────────
        $twProps = [];
        $this->addProp($twProps, 'twitter:card',        'summary_large_image');
        $this->addProp($twProps, 'twitter:title',       $ogTitle);
        $this->addProp($twProps, 'twitter:description', $ogDesc);
        if ($ogImageRaw !== '') {
            $this->addProp($twProps, 'twitter:image', $this->absoluteUrl($ogImageRaw));
        }

        return [
            'og'             => $ogProps,
            'tw'             => $twProps,
            'enable_twitter' => (bool) ((int)($this->settings['enable_twitter_cards'] ?? 1)),
            'context'        => [
                'option' => $option,
                'view'   => $view,
                'id'     => $id,
            ],
        ];
    }

    /**
     * Render the structured props array to ready-to-inject <meta> tags.
     *
     * @param array{og?:array<string,string>, tw?:array<string,string>, enable_twitter?:bool} $props
     * @return string[]
     */
    public static function renderProps(array $props): array
    {
        $tags = [];

        foreach (($props['og'] ?? []) as $property => $content) {
            if ((string) $content === '') {
                continue;
            }
            $tags[] = '<meta property="' . htmlspecialchars((string) $property, ENT_QUOTES, 'UTF-8')
                    . '" content="' . htmlspecialchars((string) $content, ENT_QUOTES, 'UTF-8') . '">';
        }

        $enableTwitter = (bool) ($props['enable_twitter'] ?? true);
        if ($enableTwitter) {
            foreach (($props['tw'] ?? []) as $name => $content) {
                if ((string) $content === '') {
                    continue;
                }
                $tags[] = '<meta name="' . htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8')
                        . '" content="' . htmlspecialchars((string) $content, ENT_QUOTES, 'UTF-8') . '">';
            }
        }

        return $tags;
    }

    /**
     * Backward-compatible one-shot helper — buildProps() + renderProps().
     *
     * @return string[]
     */
    public function build(): array
    {
        return self::renderProps($this->buildProps());
    }

    // ── Helpers (also re-used by Pro decorator via the lib) ───────────────────

    /**
     * Convert a potentially root-relative path to an absolute URL.
     */
    private function absoluteUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return $this->ctx->getBaseUrl() . '/' . ltrim($path, '/');
    }

    /**
     * Resolve og:image dimensions for the given raw image path.
     *
     * @return int[] [width, height]
     */
    private function resolveImageDimensions(string $path): array
    {
        $defaultW = (int)($this->settings['og_image_width']  ?? 1200);
        $defaultH = (int)($this->settings['og_image_height'] ?? 630);

        $path = self::normaliseImagePath($path);

        if ($path !== '' && !str_starts_with($path, 'http://') && !str_starts_with($path, 'https://')) {
            $localPath = rtrim(JPATH_ROOT, '/') . '/' . ltrim($path, '/');
            if (is_file($localPath)) {
                try {
                    $info = @getimagesize($localPath);
                    if ($info && isset($info[0], $info[1]) && $info[0] > 0 && $info[1] > 0) {
                        return [(int) $info[0], (int) $info[1]];
                    }
                } catch (\Throwable $e) {
                    // Fall through to defaults
                }
            }
        }

        return [$defaultW, $defaultH];
    }

    /**
     * Normalise a Joomla media-field image path to a root-relative URL.
     */
    public static function normaliseImagePath(string $raw): string
    {
        $path = trim($raw);
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, '{')) {
            try {
                $decoded = json_decode($path, true, 4, JSON_THROW_ON_ERROR);
                $path = trim((string) ($decoded['imagefile'] ?? ''));
            } catch (\JsonException) {
                return '';
            }
        }

        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'joomlaImage://local-images:')) {
            $path = substr($path, strlen('joomlaImage://local-images:'));
        }
        if (str_starts_with($path, 'local-images://')) {
            $path = substr($path, strlen('local-images://'));
        }

        $path = ltrim(preg_replace('#//+#', '/', $path) ?? '', '/');

        return $path;
    }

    /**
     * Add a property to the map only when the value is non-empty.
     */
    private function addProp(array &$map, string $key, string $value): void
    {
        $value = trim($value);
        if ($value !== '') {
            $map[$key] = $value;
        }
    }
}
