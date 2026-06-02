<?php
/**
 * AI Boost — JoomlaHttpAdapter
 *
 * Joomla implementation of HttpAdapter. Delegates to
 * Joomla\CMS\Http\HttpFactory::getHttp().
 *
 * @package     AiBoost\Lib\Cms\Joomla
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Cms\Joomla;

defined('_JEXEC') or die;

use AiBoost\Lib\Cms\HttpAdapter;
use Joomla\CMS\Http\HttpFactory;
use Joomla\Http\Http;
use Joomla\Registry\Registry;

final class JoomlaHttpAdapter implements HttpAdapter
{
    public function getClient(array $options = []): Http
    {
        return $options
            ? HttpFactory::getHttp(new Registry($options))
            : HttpFactory::getHttp();
    }
}
