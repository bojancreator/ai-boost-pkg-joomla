<?php
/**
 * Minimal stub of \Joomla\CMS\Factory for unit tests.
 *
 * Tests can call Factory::setDbo($fake) to swap in a fake database used by
 * PluginRegistry's loadSimulation() / scan() calls.
 */

namespace Joomla\CMS;

class Factory
{
    public static ?object $db = null;

    public static function setDbo(?object $db): void
    {
        self::$db = $db;
    }

    public static function getDbo(): object
    {
        if (self::$db === null) {
            throw new \RuntimeException('Factory::$db not configured in test.');
        }
        return self::$db;
    }
}
