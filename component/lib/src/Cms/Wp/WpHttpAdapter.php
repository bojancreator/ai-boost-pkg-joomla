<?php
/**
 * AI Boost — WpHttpAdapter (WordPress placeholder)
 *
 * Stub implementation of HttpAdapter for the future WordPress port.
 * Real impl will build a Joomla\Http\Http-compatible shim on top of
 * wp_remote_request(). Until then, getClient() throws so accidental
 * use under WP fails loudly.
 *
 * @package     AiBoost\Lib\Cms\Wp
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Cms\Wp;

defined('_JEXEC') or defined('ABSPATH') or die;

use AiBoost\Lib\Cms\HttpAdapter;
use Joomla\Http\Http;

final class WpHttpAdapter implements HttpAdapter
{
    public function getClient(array $options = []): Http
    {
        // TODO: WP port — wp_remote_request() based Joomla\Http\Http shim.
        throw new \RuntimeException('WpHttpAdapter: not implemented (v2.0 WordPress port).');
    }
}
