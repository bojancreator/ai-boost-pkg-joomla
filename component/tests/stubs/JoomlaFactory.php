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

    /**
     * Minimal Date stub: pkg_script's INSERTs need a SQL timestamp for the
     * NOT NULL created_at/updated_at columns. A frozen value keeps tests
     * deterministic.
     */
    public static function getDate($time = 'now', $tz = null): object
    {
        return new class {
            public function toSql(): string
            {
                return '2026-01-01 00:00:00';
            }

            public function __toString(): string
            {
                return '2026-01-01 00:00:00';
            }
        };
    }
}
