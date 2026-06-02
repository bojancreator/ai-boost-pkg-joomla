<?php
/**
 * AI Boost — Manifest Registry
 *
 * Single source of truth for every settings field in the package.
 * Reads per-tab manifests (schema.php, aeo.php, og.php, hreflang.php,
 * code.php, core.php) and merges runtime contributions from installed
 * Pro / integration plugins via the onAiBoostRegisterFields event.
 *
 * Field shape:
 *   [
 *     'key'           => string,           // settings key, e.g. 'enable_schema'
 *     'tab'           => string,           // e.g. 'schema'
 *     'section'       => string,           // section id inside the tab
 *     'label'         => string,
 *     'type'          => string,           // toggle|text|textarea|select|number|media|json
 *     'default'       => mixed,
 *     'tier'          => 'free'|'pro',
 *     'sku'           => 'core'|'schema'|'aeo'|'og'|'hreflang'|'code',
 *     'integration'   => null|'falang'|'yootheme'|...,
 *     'description'   => string,
 *     'dependsOn'     => string|null,      // key that must be truthy
 *     'options'       => array|null,       // for select
 *     'locked'        => bool,             // set at runtime by combine()
 *     'lock_reason'   => string|null,      // 'pro'|'integration:<name>'
 *
 *     // ── Codegen metadata (Task #462) — all optional ──
 *     'feature_class' => string|null,      // PHP class name in *_pro/src/Features/{Name}.php
 *                                          // scripts/codegen-from-manifest.py generates a stub here.
 *     'health'        => array|null,       // [
 *                                          //   'id'                => 'info_<key>'|'warning_<key>'|'critical_<key>',
 *                                          //   'category'          => HealthCheckService category name,
 *                                          //   'message'           => 'Plain-English check message',
 *                                          //   'expected_artifact' => 'Description of HTML/JSON tag the option produces',
 *                                          //   'fix_actions'       => [['label'=>..., 'target_tab'=>..., 'target_field'=>...]],
 *                                          // ]
 *     'i18n'          => array|null,       // [
 *                                          //   'label_key'       => 'PLG_SYSTEM_AIBOOST_<TAB>_<KEY>_LABEL',
 *                                          //   'description_key' => 'PLG_SYSTEM_AIBOOST_<TAB>_<KEY>_DESC',
 *                                          // ]
 *   ]
 *
 * @package     AiBoost\Lib\Manifest
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Manifest;

defined('_JEXEC') or die;

use AiBoost\Lib\PluginRegistry;

final class Registry
{
    /** @var array<int, array<string,mixed>>|null */
    private static ?array $fieldsCache = null;

    /**
     * Return the complete merged manifest:
     *   - all static fields from per-tab manifest files
     *   - all runtime fields contributed via onAiBoostRegisterFields
     *   - each field annotated with `locked` based on PluginRegistry state
     *
     * @return array<int, array<string,mixed>>
     */
    public static function all(): array
    {
        if (self::$fieldsCache !== null) {
            return self::$fieldsCache;
        }

        $fields = [];
        foreach (self::loadStaticManifests() as $tabFields) {
            foreach ($tabFields as $f) {
                $fields[] = self::normalize($f);
            }
        }

        foreach (self::collectRuntimeFields() as $f) {
            $fields[] = self::normalize($f);
        }

        $caps = PluginRegistry::capabilities();
        foreach ($fields as &$f) {
            self::applyLockState($f, $caps);
        }
        unset($f);

        return self::$fieldsCache = $fields;
    }

    /**
     * Reset the cached manifest (used after a Pro/integration plugin is
     * installed or removed within the same request — rare).
     */
    public static function reset(): void
    {
        self::$fieldsCache = null;
    }

    /**
     * Public capability payload sent to the Vue SPA.
     * Combines plugin registry + locked-field annotation in one JSON-able
     * structure so the SPA can render the locked overlay without further
     * round-trips.
     *
     * @return array{capabilities: array<string,mixed>, fields: array<int, array<string,mixed>>}
     */
    public static function payload(): array
    {
        return [
            'capabilities' => PluginRegistry::capabilities(),
            'fields'       => self::all(),
        ];
    }

    // ── Internals ────────────────────────────────────────────────────────

    /**
     * @return array<string, array<int, array<string,mixed>>>
     */
    private static function loadStaticManifests(): array
    {
        $dir   = __DIR__;
        $files = ['core', 'schema', 'aeo', 'og', 'hreflang', 'code'];
        $out   = [];
        foreach ($files as $name) {
            $path = $dir . '/' . $name . '.php';
            if (!file_exists($path)) {
                continue;
            }
            $entries = require $path;
            if (is_array($entries)) {
                $out[$name] = $entries;
            }
        }
        return $out;
    }

    /**
     * Dispatch onAiBoostRegisterFields and flatten all plugin contributions.
     *
     * @return array<int, array<string,mixed>>
     */
    private static function collectRuntimeFields(): array
    {
        $out = [];
        try {
            $result = \AiBoost\Lib\Cms\AdapterRegistry::events()
                ->trigger('onAiBoostRegisterFields', []);
            foreach ($result as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                foreach ($entry as $field) {
                    if (is_array($field) && !empty($field['key']) && !empty($field['tab'])) {
                        $out[] = $field;
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[AI Boost Manifest] onAiBoostRegisterFields failed: ' . $e->getMessage());
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $f
     * @return array<string,mixed>
     */
    private static function normalize(array $f): array
    {
        return array_merge([
            'key'           => '',
            'tab'           => 'core',
            'section'       => 'general',
            'label'         => '',
            'type'          => 'toggle',
            'default'       => '',
            'tier'          => 'free',
            'sku'           => 'core',
            'integration'   => null,
            'description'   => '',
            'dependsOn'     => null,
            'options'       => null,
            'locked'        => false,
            'lock_reason'   => null,
            // Task #462 — optional codegen metadata
            'feature_class' => null,
            'health'        => null,
            'i18n'          => null,
        ], $f);
    }

    /**
     * Return the static (per-tab manifest file) fields only, without
     * touching Joomla runtime. Used by `scripts/dump-manifest.php` for
     * the codegen pipeline so the build can run outside a CMS request.
     *
     * @return array<int, array<string,mixed>>
     */
    public static function staticOnly(): array
    {
        $out = [];
        foreach (self::loadStaticManifests() as $tabFields) {
            foreach ($tabFields as $f) {
                $out[] = self::normalize($f);
            }
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $field
     * @param array<string,mixed> $caps
     */
    private static function applyLockState(array &$field, array $caps): void
    {
        // Integration-bound field
        if (!empty($field['integration'])) {
            $intKey = 'int_' . $field['integration'];
            $int    = $caps[$intKey] ?? null;
            if (!$int || empty($int['installed']) || empty($int['enabled'])) {
                $field['locked']      = true;
                $field['lock_reason'] = 'integration:' . $field['integration'];
                return;
            }
        }

        // Pro-tier field
        if (($field['tier'] ?? 'free') === 'pro') {
            $sku = (string) ($field['sku'] ?? 'core');
            $cap = $caps['pro_' . $sku] ?? null;
            if (!$cap || empty($cap['installed']) || empty($cap['enabled'])) {
                $field['locked']      = true;
                $field['lock_reason'] = 'pro';
            }
        }
    }
}
