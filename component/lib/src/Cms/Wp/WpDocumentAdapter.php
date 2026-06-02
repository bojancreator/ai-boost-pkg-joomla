<?php
/**
 * AI Boost — WpDocumentAdapter (placeholder)
 *
 * v2.0 WordPress implementation. Stub: getTitle/getMetaData use wp_title()
 * and the WP head action introspection helpers when available; mutators
 * register filters/actions on the WP head pipeline.
 *
 * Until the WP port lands, mutators are no-ops (so accidental early use
 * does not crash the loader) and accessors return ''.
 *
 * @package     AiBoost\Lib\Cms\Wp
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Cms\Wp;

defined('_JEXEC') or defined('ABSPATH') or die;

use AiBoost\Lib\Cms\DocumentAdapter;

final class WpDocumentAdapter implements DocumentAdapter
{
    public function getTitle(): string
    {
        if (function_exists('wp_get_document_title')) {
            return (string) \wp_get_document_title();
        }
        return '';
    }

    public function setTitle(string $title): void
    {
        // TODO: WP port — add_filter('pre_get_document_title', fn() => $title)
    }

    public function getMetaData(string $name): string
    {
        // WordPress has no native registry of head meta tags — head is
        // assembled by hooks at render time. Return '' until the port
        // wires a buffer that observers can read from.
        return '';
    }

    public function setMetaData(string $name, string $content, bool $httpEquiv = false): void
    {
        // TODO: WP port — add_action('wp_head', fn() => echo '<meta…'>)
    }

    public function addCustomTag(string $html): void
    {
        // TODO: WP port — add_action('wp_head', fn() => echo $html, 99)
    }
}
