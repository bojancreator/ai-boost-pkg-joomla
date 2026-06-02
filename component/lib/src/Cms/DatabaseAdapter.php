<?php
/**
 * AI Boost — DatabaseAdapter
 *
 * Thin CMS-agnostic boundary around the host CMS's database connection.
 * For Joomla we return the framework's DatabaseInterface unchanged so all
 * existing call code (getQuery/quoteName/loadResult) keeps working
 * verbatim. The WordPress port will supply an equivalent shim built on
 * top of $wpdb that satisfies the same surface.
 *
 * Lives under AiBoost\Lib\Cms so future adapters (HttpAdapter,
 * FilesystemAdapter, …) share a single namespace.
 *
 * @package     AiBoost\Lib\Cms
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Cms;

defined('_JEXEC') or defined('ABSPATH') or die;

use Joomla\Database\DatabaseInterface;

interface DatabaseAdapter
{
    /**
     * Return the underlying CMS database connection.
     *
     * For Joomla this is Factory::getDbo(); for the WP port it will be a
     * shim that exposes the same query-builder surface backed by $wpdb.
     */
    public function getConnection(): DatabaseInterface;
}
