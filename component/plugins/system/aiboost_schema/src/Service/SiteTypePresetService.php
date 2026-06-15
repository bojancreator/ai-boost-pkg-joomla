<?php
/**
 * AI Boost — SiteTypePresetService
 *
 * Maps the plugin's `schema_type` param value to a schema.org @type string and
 * carries metadata about whether a type is
 * a subclass of LocalBusiness (affects which structured-data properties are valid).
 *
 * One-product tier: all site type presets.
 *
 * @package     AiBoost\Plugin\System\AiBoostSchema
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostSchema\Service;

defined('_JEXEC') or die;

final class SiteTypePresetService
{
    /**
     * Preset registry.
     *
     * Keys:
     *   type  — schema.org @type value
     *   local — whether the type is a LocalBusiness subtype (enables opening hours,
     *           priceRange, address, geo, and aggregateRating on the schema.org spec)
     *
     * @var array<string, array{type: string, pro: bool, local: bool}>
     */
    private const PRESETS = [
        // ── Free ─────────────────────────────────────────────────────────────
        'organization'        => ['type' => 'Organization',            'pro' => false, 'local' => false],

        'localbusiness'       => ['type' => 'LocalBusiness',           'pro' => true,  'local' => true],
        'restaurant'          => ['type' => 'Restaurant',              'pro' => true,  'local' => true],
        'hotel'               => ['type' => 'LodgingBusiness',         'pro' => true,  'local' => true],
        'medicalclinic'       => ['type' => 'MedicalClinic',           'pro' => true,  'local' => true],
        'dentist'             => ['type' => 'Dentist',                 'pro' => true,  'local' => true],
        'legalservice'        => ['type' => 'LegalService',            'pro' => true,  'local' => true],
        'realestateagent'     => ['type' => 'RealEstateAgent',         'pro' => true,  'local' => true],
        'automotivebusiness'  => ['type' => 'AutomotiveBusiness',      'pro' => true,  'local' => true],
        'store'               => ['type' => 'Store',                   'pro' => true,  'local' => true],
        'educationalorg'      => ['type' => 'EducationalOrganization', 'pro' => true,  'local' => false],
        'sportsactivity'      => ['type' => 'SportsActivityLocation',  'pro' => true,  'local' => true],
        'touristattraction'   => ['type' => 'TouristAttraction',       'pro' => true,  'local' => true],
        'foodestablishment'   => ['type' => 'FoodEstablishment',       'pro' => true,  'local' => true],
        'professionalservice' => ['type' => 'ProfessionalService',     'pro' => true,  'local' => true],
        'person'              => ['type' => 'Person',                  'pro' => true,  'local' => false],
        'newsmediaorganization' => ['type' => 'NewsMediaOrganization', 'pro' => true,  'local' => false],
    ];

    private const ALIASES = [
        'organization'              => 'organization',
        'localbusiness'             => 'localbusiness',
        'restaurant'                => 'restaurant',
        'foodestablishment'         => 'foodestablishment',
        'hotel'                     => 'hotel',
        'lodgingbusiness'           => 'hotel',
        'medical'                   => 'medicalclinic',
        'medicalclinic'             => 'medicalclinic',
        'lawyer'                    => 'legalservice',
        'legalservice'              => 'legalservice',
        'realestate'                => 'realestateagent',
        'realestateagent'           => 'realestateagent',
        'automotivebusiness'        => 'automotivebusiness',
        'store'                     => 'store',
        'educationalorganization'   => 'educationalorg',
        'educationalorg'            => 'educationalorg',
        'school'                    => 'educationalorg',
        'sportsactivitylocation'    => 'sportsactivity',
        'sportsactivity'            => 'sportsactivity',
        'gym'                       => 'sportsactivity',
        'dentist'                   => 'dentist',
        'touristattraction'         => 'touristattraction',
        'professionalservice'       => 'professionalservice',
        'person'                    => 'person',
        'portfolio'                 => 'person',
        'newsmediaorganization'     => 'newsmediaorganization',
        'news'                      => 'newsmediaorganization',
    ];

    public static function normalizeKey(string $key): string
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', trim($key)) ?: '');
        return self::ALIASES[$normalized] ?? $normalized;
    }

    /**
     * Return the schema.org @type for the given key and license status.
     */
    public static function getSchemaType(string $key, bool $isPro = false): string
    {
        $key = self::normalizeKey($key);
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
        $key = self::normalizeKey($key);
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
        $key = self::normalizeKey($key);
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

    /**
     * Return every distinct schema.org @type produced by the presets
     * (Organization, LocalBusiness, Restaurant, … Person, NewsMediaOrganization).
     *
     * @return string[]
     */
    public static function schemaTypes(): array
    {
        return array_values(array_unique(array_map(
            static fn(array $preset): string => $preset['type'],
            self::PRESETS
        )));
    }

    /**
     * True when the given schema.org @type is one of the business/identity
     * types the Organization block can be upgraded to. Used to recognise the
     * identity block among emitted JSON-LD blocks regardless of which specific
     * type the site selected (so e.g. translations apply to a Restaurant or
     * Dentist block, not only the generic Organization / LocalBusiness ones).
     */
    public static function isBusinessIdentityType(string $type): bool
    {
        return in_array($type, self::schemaTypes(), true);
    }
}
