<?php
/**
 * AI Boost — WpApplicationAdapter (WordPress placeholder)
 *
 * Stub implementation of ApplicationAdapter. Real impl is v2.0 work:
 * body manipulation will hook into wp_head/wp_footer via ob_start
 * filters, and getHost() will read from $_SERVER['HTTP_HOST'] /
 * home_url().
 *
 * @package     AiBoost\Lib\Cms\Wp
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Cms\Wp;

defined('_JEXEC') or defined('ABSPATH') or die;

use AiBoost\Lib\Cms\ApplicationAdapter;

final class WpApplicationAdapter implements ApplicationAdapter
{
    public function isSite(): bool
    {
        // TODO: WP port — return !is_admin() && !wp_doing_ajax() etc.
        // Defaults to false so head/body builders skip output rather than
        // silently corrupt admin/AJAX/REST responses.
        return false;
    }

    public function getBody(): string
    {
        // TODO: WP port — body manipulation must hook into ob_start filters
        // in wp_head/wp_footer. Calling getBody() before that wiring is a
        // bug, so fail loudly instead of returning silent ''.
        throw new \RuntimeException('WpApplicationAdapter::getBody: not implemented (v2.0 WordPress port).');
    }

    public function setBody(string $body): void
    {
        throw new \RuntimeException('WpApplicationAdapter::setBody: not implemented (v2.0 WordPress port).');
    }

    public function getHost(): string
    {
        return (string) ($_SERVER['HTTP_HOST'] ?? '');
    }
}
