<?php

/**
 * @package     JoomlaBoost
 * @subpackage  Services
 * @author      JoomlaBoost Team
 * @copyright   Copyright (C) 2026 JoomlaBoost. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Services;

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
use Exception;

/**
 * Vertical Preset Service
 * Provides one-click optimal configuration for common site types.
 *
 * Each preset only sets fields that are universally beneficial for that vertical.
 * It never overwrites user-supplied identifiers (GA4 ID, Meta Pixel ID, IndexNow key,
 * verification codes, social URLs, address, etc.) — only behavioural defaults.
 */
class VerticalPresetService extends AbstractService
{
    public const PRESET_NONE       = '';
    public const PRESET_HOTEL      = 'hotel';
    public const PRESET_RESTAURANT = 'restaurant';
    public const PRESET_BLOG       = 'blog';
    public const PRESET_ECOMMERCE  = 'ecommerce';
    public const PRESET_GENERIC    = 'generic';
    public const PRESET_MEDICAL    = 'medical';
    public const PRESET_LAWYER     = 'lawyer';
    public const PRESET_SCHOOL     = 'school';
    public const PRESET_GYM        = 'gym';
    public const PRESET_DENTIST    = 'dentist';
    public const PRESET_REALESTATE = 'realestate';
    public const PRESET_PORTFOLIO  = 'portfolio';
    public const PRESET_NEWS       = 'news';

    /**
     * All preset identifiers and human-readable labels.
     *
     * @return array<string, string>
     */
    public static function listPresets(): array
    {
        return [
            self::PRESET_HOTEL      => 'Hotel / Accommodation',
            self::PRESET_RESTAURANT => 'Restaurant / Cafe',
            self::PRESET_BLOG       => 'Blog / Magazine',
            self::PRESET_ECOMMERCE  => 'E-commerce / Online Shop',
            self::PRESET_GENERIC    => 'Generic Business / Corporate',
            self::PRESET_MEDICAL    => 'Medical Clinic / Healthcare',
            self::PRESET_LAWYER     => 'Lawyer / Law Firm',
            self::PRESET_SCHOOL     => 'School / Educational Institution',
            self::PRESET_GYM        => 'Gym / Sports Club',
            self::PRESET_DENTIST    => 'Dentist / Dental Clinic',
            self::PRESET_REALESTATE => 'Real Estate Agency',
            self::PRESET_PORTFOLIO  => 'Portfolio / Freelancer',
            self::PRESET_NEWS       => 'News / Media Portal',
        ];
    }

    protected function getServiceKey(): string
    {
        return 'vertical_preset';
    }

    /**
     * Build the set of advanced opening hours fields for a standard weekday schedule.
     * Mon-Fri at the given times, Sat optional, Sun closed.
     *
     * @param string $wdOpen   Weekday open time (HH:MM)
     * @param string $wdClose  Weekday close time (HH:MM)
     * @param string $satOpen  Saturday open time (empty = closed)
     * @param string $satClose Saturday close time (empty = closed)
     * @return array<string, mixed>
     */
    private function weekdayHours(string $wdOpen, string $wdClose, string $satOpen = '', string $satClose = ''): array
    {
        $satClosed = empty($satOpen) ? 1 : 0;
        return [
            'schema_hours_mode'      => 'advanced',
            'schema_hours_mon_open'  => $wdOpen,  'schema_hours_mon_close'  => $wdClose,  'schema_hours_mon_closed'  => 0,
            'schema_hours_tue_open'  => $wdOpen,  'schema_hours_tue_close'  => $wdClose,  'schema_hours_tue_closed'  => 0,
            'schema_hours_wed_open'  => $wdOpen,  'schema_hours_wed_close'  => $wdClose,  'schema_hours_wed_closed'  => 0,
            'schema_hours_thu_open'  => $wdOpen,  'schema_hours_thu_close'  => $wdClose,  'schema_hours_thu_closed'  => 0,
            'schema_hours_fri_open'  => $wdOpen,  'schema_hours_fri_close'  => $wdClose,  'schema_hours_fri_closed'  => 0,
            'schema_hours_sat_open'  => $satOpen, 'schema_hours_sat_close'  => $satClose, 'schema_hours_sat_closed'  => $satClosed,
            'schema_hours_sun_open'  => '',        'schema_hours_sun_close'  => '',        'schema_hours_sun_closed'  => 1,
            'schema_hours_appointment_only' => 0,
            'schema_hours_temp_closed'      => 0,
        ];
    }

    /**
     * Build advanced opening hours for all-day every-day schedule (24/7 or fixed daily).
     *
     * @param string $open  Open time (HH:MM)
     * @param string $close Close time (HH:MM)
     * @return array<string, mixed>
     */
    private function everydayHours(string $open, string $close): array
    {
        $fields = ['schema_hours_mode' => 'advanced', 'schema_hours_appointment_only' => 0, 'schema_hours_temp_closed' => 0];
        foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $day) {
            $fields['schema_hours_' . $day . '_open']   = $open;
            $fields['schema_hours_' . $day . '_close']  = $close;
            $fields['schema_hours_' . $day . '_closed'] = 0;
        }
        return $fields;
    }

    /**
     * Get the configuration map for a given preset.
     *
     * @param string $preset Preset identifier
     * @return array<string, mixed> Field name => value
     */
    public function getPresetValues(string $preset): array
    {
        $common = [
            'enable_robots'              => 1,
            'robots_auto_sync'           => 1,
            'enable_hreflang'            => 1,
            'enable_sitemap'             => 1,
            'sitemap_include_articles'   => 1,
            'sitemap_include_categories' => 1,
            'sitemap_include_menu'       => 1,
            'enable_opengraph'           => 1,
            'enable_schema'              => 1,
            'faq_auto_detect'            => 1,
            'llmstxt_enabled'            => 1,
        ];

        switch ($preset) {
            case self::PRESET_HOTEL:
                return array_merge($common, [
                    'schema_type'                  => 'hotel',
                    'schema_hotel_star_rating'     => '3',
                    'schema_hotel_checkin_time'    => '14:00',
                    'schema_hotel_checkout_time'   => '12:00',
                    'schema_hotel_pets_allowed'    => 0,
                    'schema_price_range'           => '$$',
                    'schema_hours_mode'            => 'simple',
                    'schema_opening_hours'         => 'Mo-Su 00:00-24:00',
                    'sitemap_priority_articles'    => '0.8',
                    'sitemap_priority_categories'  => '0.7',
                    'sitemap_priority_menu'        => '0.8',
                    'sitemap_changefreq_articles'  => 'weekly',
                    'sitemap_changefreq_categories' => 'weekly',
                    'sitemap_changefreq_menu'      => 'monthly',
                ]);

            case self::PRESET_RESTAURANT:
                return array_merge($common, [
                    'schema_type'        => 'localbusiness',
                    'schema_price_range' => '$$',
                    'schema_hours_mode'  => 'simple',
                    'schema_opening_hours' => 'Mo-Su 11:00-23:00',
                    'sitemap_priority_articles'    => '0.7',
                    'sitemap_priority_categories'  => '0.7',
                    'sitemap_priority_menu'        => '0.8',
                    'sitemap_changefreq_articles'  => 'weekly',
                    'sitemap_changefreq_categories' => 'monthly',
                    'sitemap_changefreq_menu'      => 'monthly',
                ]);

            case self::PRESET_BLOG:
                return array_merge($common, [
                    'schema_type'                  => 'organization',
                    'sitemap_priority_articles'    => '0.9',
                    'sitemap_priority_categories'  => '0.7',
                    'sitemap_priority_menu'        => '0.6',
                    'sitemap_changefreq_articles'  => 'daily',
                    'sitemap_changefreq_categories' => 'weekly',
                    'sitemap_changefreq_menu'      => 'monthly',
                ]);

            case self::PRESET_ECOMMERCE:
                return array_merge($common, [
                    'schema_type'                  => 'organization',
                    'sitemap_priority_articles'    => '0.8',
                    'sitemap_priority_categories'  => '0.9',
                    'sitemap_priority_menu'        => '0.7',
                    'sitemap_changefreq_articles'  => 'daily',
                    'sitemap_changefreq_categories' => 'daily',
                    'sitemap_changefreq_menu'      => 'weekly',
                    'meta_pixel_track_purchase'    => 1,
                    'meta_pixel_track_add_to_cart' => 1,
                    'meta_pixel_track_lead'        => 1,
                ]);

            case self::PRESET_MEDICAL:
                return array_merge($common,
                    $this->weekdayHours('08:00', '16:00'),
                    [
                        'schema_type'                  => 'medical',
                        'sitemap_priority_articles'    => '0.8',
                        'sitemap_priority_categories'  => '0.6',
                        'sitemap_priority_menu'        => '0.7',
                        'sitemap_changefreq_articles'  => 'weekly',
                        'sitemap_changefreq_categories' => 'monthly',
                        'sitemap_changefreq_menu'      => 'monthly',
                    ]
                );

            case self::PRESET_LAWYER:
                return array_merge($common,
                    $this->weekdayHours('09:00', '17:00'),
                    [
                        'schema_type'                  => 'lawyer',
                        'sitemap_priority_articles'    => '0.8',
                        'sitemap_priority_categories'  => '0.6',
                        'sitemap_priority_menu'        => '0.7',
                        'sitemap_changefreq_articles'  => 'weekly',
                        'sitemap_changefreq_categories' => 'monthly',
                        'sitemap_changefreq_menu'      => 'monthly',
                    ]
                );

            case self::PRESET_SCHOOL:
                return array_merge($common,
                    $this->weekdayHours('08:00', '15:00'),
                    [
                        'schema_type'                  => 'school',
                        'sitemap_priority_articles'    => '0.8',
                        'sitemap_priority_categories'  => '0.7',
                        'sitemap_priority_menu'        => '0.6',
                        'sitemap_changefreq_articles'  => 'weekly',
                        'sitemap_changefreq_categories' => 'weekly',
                        'sitemap_changefreq_menu'      => 'monthly',
                    ]
                );

            case self::PRESET_GYM:
                return array_merge($common,
                    $this->everydayHours('06:00', '22:00'),
                    [
                        'schema_type'                  => 'gym',
                        'schema_price_range'           => '$$',
                        'sitemap_priority_articles'    => '0.7',
                        'sitemap_priority_categories'  => '0.6',
                        'sitemap_priority_menu'        => '0.7',
                        'sitemap_changefreq_articles'  => 'weekly',
                        'sitemap_changefreq_categories' => 'monthly',
                        'sitemap_changefreq_menu'      => 'monthly',
                    ]
                );

            case self::PRESET_DENTIST:
                return array_merge($common,
                    $this->weekdayHours('08:00', '16:00'),
                    [
                        'schema_type'                  => 'dentist',
                        'sitemap_priority_articles'    => '0.8',
                        'sitemap_priority_categories'  => '0.6',
                        'sitemap_priority_menu'        => '0.7',
                        'sitemap_changefreq_articles'  => 'weekly',
                        'sitemap_changefreq_categories' => 'monthly',
                        'sitemap_changefreq_menu'      => 'monthly',
                    ]
                );

            case self::PRESET_REALESTATE:
                return array_merge($common,
                    $this->weekdayHours('09:00', '18:00', '10:00', '14:00'),
                    [
                        'schema_type'                  => 'realestate',
                        'sitemap_priority_articles'    => '0.8',
                        'sitemap_priority_categories'  => '0.8',
                        'sitemap_priority_menu'        => '0.7',
                        'sitemap_changefreq_articles'  => 'daily',
                        'sitemap_changefreq_categories' => 'weekly',
                        'sitemap_changefreq_menu'      => 'monthly',
                    ]
                );

            case self::PRESET_PORTFOLIO:
                return array_merge($common, [
                    'schema_type'                  => 'portfolio',
                    'schema_hours_mode'            => 'advanced',
                    'schema_hours_appointment_only' => 1,
                    'schema_hours_temp_closed'     => 0,
                    'sitemap_priority_articles'    => '0.9',
                    'sitemap_priority_categories'  => '0.7',
                    'sitemap_priority_menu'        => '0.6',
                    'sitemap_changefreq_articles'  => 'weekly',
                    'sitemap_changefreq_categories' => 'monthly',
                    'sitemap_changefreq_menu'      => 'monthly',
                ]);

            case self::PRESET_NEWS:
                return array_merge($common,
                    $this->everydayHours('00:00', '23:59'),
                    [
                        'schema_type'                  => 'news',
                        'sitemap_priority_articles'    => '1.0',
                        'sitemap_priority_categories'  => '0.7',
                        'sitemap_priority_menu'        => '0.5',
                        'sitemap_changefreq_articles'  => 'daily',
                        'sitemap_changefreq_categories' => 'weekly',
                        'sitemap_changefreq_menu'      => 'monthly',
                    ]
                );

            case self::PRESET_GENERIC:
            default:
                return array_merge($common, [
                    'schema_type'                  => 'auto',
                    'schema_hours_mode'            => 'simple',
                    'sitemap_priority_articles'    => '0.8',
                    'sitemap_priority_categories'  => '0.7',
                    'sitemap_priority_menu'        => '0.6',
                    'sitemap_changefreq_articles'  => 'weekly',
                    'sitemap_changefreq_categories' => 'weekly',
                    'sitemap_changefreq_menu'      => 'monthly',
                ]);
        }
    }

    /**
     * Apply a preset to plugin parameters in the database.
     * Merges preset values into the existing params (preserves user identifiers
     * and any field not part of the preset).
     *
     * @param string $preset       Preset identifier
     * @param array  $currentParams Current plugin params (decoded)
     * @return array<string, mixed> Updated params (caller persists them)
     */
    public function applyPreset(string $preset, array $currentParams): array
    {
        if (!array_key_exists($preset, self::listPresets())) {
            $this->logDebug('VerticalPreset: unknown preset "' . $preset . '" — ignored');
            return $currentParams;
        }

        $presetValues = $this->getPresetValues($preset);

        $updated = array_merge($currentParams, $presetValues);

        $updated['vertical_preset']       = '';
        $updated['vertical_preset_apply'] = 0;
        $updated['vertical_preset_last']  = $preset;
        $updated['vertical_preset_last_at'] = Factory::getDate()->toSql();

        $this->logDebug('VerticalPreset: applied "' . $preset . '" — '
            . count($presetValues) . ' fields updated');

        return $updated;
    }

    /**
     * Persist updated params back to the #__extensions table.
     *
     * @param array<string, mixed> $params  Updated params
     * @param int                  $extensionId Plugin extension_id
     * @return bool
     */
    public function persistParams(array $params, int $extensionId): bool
    {
        try {
            $db    = Factory::getDbo();
            $json  = json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('params') . ' = ' . $db->quote($json))
                ->where($db->quoteName('extension_id') . ' = ' . (int) $extensionId);
            $db->setQuery($query);
            $db->execute();
            return true;
        } catch (Exception $e) {
            $this->logDebug('VerticalPreset persist failed: ' . $e->getMessage());
            return false;
        }
    }
}
