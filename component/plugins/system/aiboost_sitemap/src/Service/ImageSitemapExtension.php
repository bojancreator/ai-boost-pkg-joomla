<?php
/**
 * AI Boost — Image Sitemap Extension (Pro)
 *
 * Generates <image:image> child elements inside <url> blocks so Google can
 * index article images through Google Image Search.
 *
 * Requires the `image:` XML namespace declared on the <urlset> element:
 *   xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
 *
 * $baseUrl is injected by the Extension class (rtrim(Uri::root(), '/')) so
 * this service makes no Uri:: calls.
 *
 * @package     AiBoost\Plugin\System\AiBoostSitemap
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Plugin\System\AiBoostSitemap\Service;

defined('_JEXEC') or die;

class ImageSitemapExtension
{
    /**
     * @param string $baseUrl  Absolute site root without trailing slash,
     *                         e.g. 'https://example.com'. Injected by the Extension class.
     */
    public function __construct(
        private readonly string $baseUrl,
    ) {}

    /**
     * Render the <image:image> block for a single article entry.
     *
     * @param  string $introImage  Relative path or absolute URL to the image.
     * @param  string $title       Article title — used as image caption/title.
     * @return string              XML fragment (indented, ready to embed inside <url>).
     */
    public function render(string $introImage, string $title): string
    {
        if ($introImage === '') {
            return '';
        }

        $imageLoc = $this->buildImageUrl($introImage);
        if ($imageLoc === '') {
            return '';
        }

        $xml  = "    <image:image>\n";
        $xml .= '      <image:loc>'   . htmlspecialchars($imageLoc, ENT_XML1) . "</image:loc>\n";

        if ($title !== '') {
            $xml .= '      <image:title>' . htmlspecialchars($title, ENT_XML1) . "</image:title>\n";
        }

        $xml .= "    </image:image>\n";

        return $xml;
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build an absolute URL for the image.
     *
     * If the stored path is already absolute (starts with http), return as-is.
     * Otherwise prepend the injected base URL.
     */
    private function buildImageUrl(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return $this->baseUrl . '/' . ltrim($path, '/');
    }
}
