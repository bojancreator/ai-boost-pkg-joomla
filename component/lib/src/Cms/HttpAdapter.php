<?php
/**
 * AI Boost — HttpAdapter
 *
 * Thin boundary around the host CMS's HTTP client. Joomla returns
 * HttpFactory::getHttp(); the WordPress port will return a shim built on
 * wp_remote_request() that exposes the same Joomla\Http\Http surface.
 *
 * @package     AiBoost\Lib\Cms
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Cms;

defined('_JEXEC') or defined('ABSPATH') or die;

use Joomla\Http\Http;

interface HttpAdapter
{
    /**
     * Return a Joomla\Http\Http-compatible client.
     *
     * @param array<string,mixed> $options  Optional client options
     *                                      (e.g. 'userAgent' => '…').
     */
    public function getClient(array $options = []): Http;
}
