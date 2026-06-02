<?php
/**
 * AI Boost — IntegrationDescriptor
 *
 * Immutable value object that an integration bridge returns from its
 * onAiBoostRegisterIntegration event handler. The descriptor lets core
 * render the Integrations dashboard, validate SDK compatibility, and
 * route fix_actions back into the integration's settings tab — all
 * without core needing a hardcoded list of known bridges.
 *
 * @package     AiBoost\Lib\Integration
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Integration;

defined('_JEXEC') or defined('ABSPATH') or die;

final class IntegrationDescriptor
{
    public function __construct(
        /** Short integration key — must match the `int_<key>` capability slot. */
        public readonly string $key,
        /** Plugin element string (`aiboost_int_<key>`). */
        public readonly string $pluginElement,
        /** User-facing label, e.g. "Falang Pro". */
        public readonly string $label,
        /** Vendor / author of the third-party extension being bridged. */
        public readonly string $vendor,
        /** Dashboard category, e.g. "Multilingual", "E-Commerce". */
        public readonly string $category,
        /** Plain-English description shown in the Integrations tile. */
        public readonly string $description,
        /** Host extension type detected: 'plugin'|'component'|'template'. */
        public readonly string $hostType,
        /** Host extension element string, e.g. 'com_falang' or 'yootheme'. */
        public readonly string $hostElement,
        /** Host plugin folder when hostType='plugin' (e.g. 'system'). */
        public readonly string $hostFolder = '',
        /** SDK version this bridge was built against. See Sdk::SDK_VERSION. */
        public readonly int $sdkVersion = Sdk::SDK_VERSION,
        /** Minimum AI Boost core version the bridge requires. */
        public readonly string $minCoreVersion = '0.58.0',
        /** Optional maximum core version (empty = no upper bound). */
        public readonly string $maxCoreVersion = '',
        /** Bridge plugin version (informational). */
        public readonly string $version = '0.0.0',
        /** Marketing / docs URL. */
        public readonly string $learnUrl = '',
        /** Aiboostnow.com integration page URL (shown when bridge isn't installed). */
        public readonly string $addonUrl = '',
        /** Icon class for the dashboard tile (Joomla icomoon set). */
        public readonly string $icon = 'icon-puzzle',
        /** ConflictManager slots this bridge intends to claim. Used for collision detection. */
        public readonly array $claimsSlots = []
    ) {
    }

    /**
     * Hydrate from an event-handler return value (array shorthand).
     *
     * @param  array<string,mixed> $a
     */
    public static function fromArray(array $a): self
    {
        return new self(
            key:            (string) ($a['key'] ?? ''),
            pluginElement:  (string) ($a['pluginElement'] ?? ('aiboost_int_' . ($a['key'] ?? ''))),
            label:          (string) ($a['label'] ?? ''),
            vendor:         (string) ($a['vendor'] ?? ''),
            category:       (string) ($a['category'] ?? 'Other'),
            description:    (string) ($a['description'] ?? ''),
            hostType:       (string) ($a['hostType'] ?? 'plugin'),
            hostElement:    (string) ($a['hostElement'] ?? ''),
            hostFolder:     (string) ($a['hostFolder'] ?? ''),
            sdkVersion:     (int)    ($a['sdkVersion'] ?? Sdk::SDK_VERSION),
            minCoreVersion: (string) ($a['minCoreVersion'] ?? '0.58.0'),
            maxCoreVersion: (string) ($a['maxCoreVersion'] ?? ''),
            version:        (string) ($a['version'] ?? '0.0.0'),
            learnUrl:       (string) ($a['learnUrl'] ?? ''),
            addonUrl:       (string) ($a['addonUrl'] ?? ''),
            icon:           (string) ($a['icon'] ?? 'icon-puzzle'),
            claimsSlots:    array_values(array_filter((array) ($a['claimsSlots'] ?? []), 'is_string'))
        );
    }

    /**
     * Convert back to the legacy associative array shape consumed by
     * IntegrationDetectorService::detect() so the Vue admin SPA does
     * not need to change.
     *
     * @return array<string,mixed>
     */
    public function toLegacyArray(): array
    {
        return [
            'name'        => $this->label,
            'vendor'      => $this->vendor,
            'category'    => $this->category,
            'description' => $this->description,
            'type'        => $this->hostType,
            'element'     => $this->hostElement,
            'folder'      => $this->hostFolder,
            'status_type' => 'addon',
            'addon_url'   => $this->addonUrl,
            'learn_url'   => $this->learnUrl,
            'icon'        => $this->icon,
        ];
    }
}
