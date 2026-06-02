<?php
/**
 * AI Boost — WpDatabaseAdapter (WordPress placeholder)
 *
 * Stub implementation of DatabaseAdapter for the future WordPress port.
 * Real impl will build a Joomla\Database\DatabaseInterface-compatible
 * shim on top of the global $wpdb instance. Until then, calling
 * getConnection() throws so accidental use under WP fails loudly.
 *
 * @package     AiBoost\Lib\Cms\Wp
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Cms\Wp;

defined('_JEXEC') or defined('ABSPATH') or die;

use AiBoost\Lib\Cms\DatabaseAdapter;
use Joomla\Database\DatabaseInterface;

final class WpDatabaseAdapter implements DatabaseAdapter
{
    public function getConnection(): DatabaseInterface
    {
        // TODO: WP port — return a $wpdb-backed DatabaseInterface shim.
        throw new \RuntimeException('WpDatabaseAdapter: not implemented (v2.0 WordPress port).');
    }
}
