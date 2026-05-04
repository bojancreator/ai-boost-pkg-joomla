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
 * Provides one-click optimal configuration for common site types:
 * Hotel, Restaurant, Blog, E-commerce, Generic.
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
        ];
    }

    protected function getServiceKey(): string
    {
        return 'vertical_preset';
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
            // SEO basics — universal across verticals
            'enable_robots'             => 1,
            'robots_auto_sync'          => 1,
            'enable_hreflang'           => 1,
            'enable_sitemap'            => 1,
            'sitemap_include_articles'  => 1,
            'sitemap_include_categories' => 1,
            'sitemap_include_menu'      => 1,
            'enable_opengraph'          => 1,
            'enable_schema'             => 1,
            'faq_auto_detect'           => 1,
            'llmstxt_enabled'           => 1,
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
                    'schema_type'                  => 'localbusiness',
                    'schema_price_range'           => '$$',
                    'schema_opening_hours'         => 'Mo-Su 11:00-23:00',
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
                    // Conversion tracking on by default for e-commerce
                    'meta_pixel_track_purchase'    => 1,
                    'meta_pixel_track_add_to_cart' => 1,
                    'meta_pixel_track_lead'        => 1,
                ]);

            case self::PRESET_GENERIC:
            default:
                return array_merge($common, [
                    'schema_type'                  => 'auto',
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

        // Merge: preset values override current params, but only for keys in the preset.
        // User-supplied identifiers (GA4 ID, pixel ID, social URLs, etc.) remain untouched.
        $updated = array_merge($currentParams, $presetValues);

        // Clear the preset selector so it is not re-applied on every save.
        $updated['vertical_preset']       = '';
        $updated['vertical_preset_apply'] = 0;
        // Track which preset was applied (for UI hints / future updates)
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
