<?php
/**
 * AI Boost — SiteTypePresetService
 *
 * Maps the plugin's `schema_type` param value to a schema.org @type string and
 * carries metadata about which types require a Pro license and whether a type is
 * a subclass of LocalBusiness (affects which structured-data properties are valid).
 *
 * Free tier: Organization only (fallback for any Pro type without a valid license)
 * Pro tier:  All 13 site type presets
 *
 * @package     AiBoost\Plugin\System\AiBoostSchema
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostSchemaPro\Service;

defined('_JEXEC') or die;

final class SiteTypePresetService
{
    /**
     * Preset registry.
     *
     * Keys:
     *   type  — schema.org @type value
     *   pro   — whether a Pro license is required
     *   local — whether the type is a LocalBusiness subtype (enables opening hours,
     *           priceRange, address, geo, and aggregateRating on the schema.org spec)
     *
     * @var array<string, array{type: string, pro: bool, local: bool}>
     */
    private const PRESETS = [
        // ── Free ─────────────────────────────────────────────────────────────
        'organization'        => ['type' => 'Organization',            'pro' => false, 'local' => false],

        // ── Pro — 13 Site Type presets ────────────────────────────────────────
        'localbusiness'       => ['type' => 'LocalBusiness',           'pro' => true,  'local' => true],
        'restaurant'          => ['type' => 'Restaurant',              'pro' => true,  'local' => true],
        'hotel'               => ['type' => 'LodgingBusiness',         'pro' => true,  'local' => true],
        'medicalclinic'       => ['type' => 'MedicalClinic',           'pro' => true,  'local' => true],
        'legalservice'        => ['type' => 'LegalService',            'pro' => true,  'local' => true],
        'realestateagent'     => ['type' => 'RealEstateAgent',         'pro' => true,  'local' => true],
        'automotivebusiness'  => ['type' => 'AutomotiveBusiness',      'pro' => true,  'local' => true],
        'store'               => ['type' => 'Store',                   'pro' => true,  'local' => true],
        'educationalorg'      => ['type' => 'EducationalOrganization', 'pro' => true,  'local' => false],
        'sportsactivity'      => ['type' => 'SportsActivityLocation',  'pro' => true,  'local' => true],
        'touristattraction'   => ['type' => 'TouristAttraction',       'pro' => true,  'local' => true],
        'foodestablishment'   => ['type' => 'FoodEstablishment',       'pro' => true,  'local' => true],
        'professionalservice' => ['type' => 'ProfessionalService',     'pro' => true,  'local' => true],
    ];

    /**
     * Return the schema.org @type for the given key and license status.
     *
     * Free-tier callers requesting a Pro type receive 'Organization' as fallback.
     */
    public static function getSchemaType(string $key, bool $isPro = false): string
    {
        $preset = self::PRESETS[$key] ?? null;
        if ($preset === null) {
            return 'Organization';
        }
        if ($preset['pro'] && !$isPro) {
            return 'Organization'; // Downgrade to free type
        }
        return $preset['type'];
    }

    /**
     * Return true when the resolved type is a subclass of LocalBusiness.
     * Used to decide whether to include priceRange, openingHours, etc.
     */
    public static function isLocalBusiness(string $key, bool $isPro = false): bool
    {
        $preset = self::PRESETS[$key] ?? null;
        if ($preset === null || ($preset['pro'] && !$isPro)) {
            return false;
        }
        return $preset['local'];
    }

    /**
     * Return true when the given key requires a Pro license.
     */
    public static function isProRequired(string $key): bool
    {
        return (self::PRESETS[$key] ?? ['pro' => false])['pro'];
    }

    /**
     * Return all preset keys in display order (Free types first, then Pro).
     *
     * @return string[]
     */
    public static function keys(): array
    {
        return array_keys(self::PRESETS);
    }
}
