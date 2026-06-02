<?php
/**
 * AI Boost — JoomlaDatabaseAdapter
 *
 * Joomla implementation of DatabaseAdapter. Returns Factory::getDbo(),
 * which already implements Joomla\Database\DatabaseInterface.
 *
 * @package     AiBoost\Lib\Cms\Joomla
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Cms\Joomla;

defined('_JEXEC') or die;

use AiBoost\Lib\Cms\DatabaseAdapter;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

final class JoomlaDatabaseAdapter implements DatabaseAdapter
{
    public function getConnection(): DatabaseInterface
    {
        return Factory::getDbo();
    }
}
