<?php
/**
 * AI Boost — BusinessHoursBuilder
 *
 * Converts flat settings array keys (`hours_{day}_opens`, `hours_{day}_closes`,
 * `hours_{day}_closed`) into a schema.org `openingHoursSpecification` array
 * suitable for direct embedding in JSON-LD.
 *
 * Each day can have a single opens/closes pair.  The `closed` toggle removes
 * that day from the output entirely (schema.org interprets a missing day as
 * implicitly closed).
 *
 * Usage:
 *   $builder = new BusinessHoursBuilder();
 *   $spec    = $builder->build($settings);
 *   // → [['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => '...', ...], ...]
 *
 * @package     AiBoost\Plugin\System\AiBoostSchema
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostSchema\Service;

defined('_JEXEC') or die;

class BusinessHoursBuilder
{
    /** Day key → schema.org DayOfWeek fragment */
    private const DAYS = [
        'mon' => 'Monday',
        'tue' => 'Tuesday',
        'wed' => 'Wednesday',
        'thu' => 'Thursday',
        'fri' => 'Friday',
        'sat' => 'Saturday',
        'sun' => 'Sunday',
    ];

    /**
     * Build openingHoursSpecification entries from settings array.
     *
     * Each day uses three keys:
     *   hours_{day}_closed — '1' = closed that day (skip)
     *   hours_{day}_opens  — HH:MM, default '09:00'
     *   hours_{day}_closes — HH:MM, default '17:00'
     *
     * @param  array<string,mixed> $settings  Shared AI Boost settings array.
     * @return array<int, array<string, string>>  openingHoursSpecification items.
     */
    public function build(array $settings): array
    {
        $specs = [];

        foreach (self::DAYS as $key => $dayName) {
            if ((int)($settings["hours_{$key}_closed"] ?? 0)) {
                continue;
            }

            $opens  = trim((string)($settings["hours_{$key}_opens"]  ?? '09:00'));
            $closes = trim((string)($settings["hours_{$key}_closes"] ?? '17:00'));

            if (!$this->isValidTime($opens) || !$this->isValidTime($closes)) {
                continue;
            }

            $specs[] = [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => 'https://schema.org/' . $dayName,
                'opens'     => $opens,
                'closes'    => $closes,
            ];
        }

        return $specs;
    }

    /**
     * Validate that a string looks like HH:MM (24-hour time).
     */
    private function isValidTime(string $time): bool
    {
        if (!preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            return false;
        }
        [$h, $m] = explode(':', $time);
        return (int) $h <= 23 && (int) $m <= 59;
    }
}
