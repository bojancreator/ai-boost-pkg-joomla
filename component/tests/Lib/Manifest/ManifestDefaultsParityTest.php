<?php

namespace AiBoost\Tests\Lib\Manifest;

use PHPUnit\Framework\TestCase;

/**
 * #14 — manifest default-ON parity with the Vue settings DEFAULTS.
 *
 * The backend reads an absent toggle key as ON (`?? 1`), but a Vue checkbox
 * reads an absent key as OFF. So every manifest toggle whose default is '1'
 * MUST also appear in App.vue's DEFAULTS with value '1' — otherwise a plain
 * Save serialises the wrongly-OFF checkbox and silently turns the feature OFF
 * (the #14 bug).
 *
 * Source of truth: the static manifest files (the same set the codegen and the
 * ProRegistry parity test use). Dynamic integration option fields (the falang_
 * and yootheme_ keys registered at runtime by the bridge plugins) are out of
 * scope — they are Pro, locked, and not managed by App.vue DEFAULTS.
 *
 * DOCUMENTED EXCEPTION — the two integration MASTER toggles
 * (integration_falang_enabled / integration_yootheme_enabled) are intentionally
 * NOT mirrored. They are deliberately left default-ON-but-absent until the
 * "Integration master toggles shown locked (upsell)" backlog item ships, which
 * will render them as ProGate-locked controls. Until then this test must skip
 * them by name (see self::EXCLUDED), or it would fail on a deliberate product
 * decision. Billing is unaffected either way — the integrations are gated by
 * build-stripping + the `hasPro()` licence, not by this UI default.
 */
final class ManifestDefaultsParityTest extends TestCase
{
    private const MANIFEST_DIR = __DIR__ . '/../../../lib/src/Manifest';
    private const MANIFESTS    = ['core', 'schema', 'aeo', 'og', 'hreflang', 'code'];
    private const APP_VUE      = __DIR__ . '/../../../com_aiboost/vue-admin/src/App.vue';
    private const TOGGLE_TYPES = ['toggle', 'checkbox', 'switch', 'boolean', 'bool'];

    /**
     * Default-ON toggles intentionally NOT mirrored into DEFAULTS. See the class
     * docblock and BACKLOG "Integration master toggles shown locked (upsell)".
     *
     * @var list<string>
     */
    private const EXCLUDED = [
        'integration_falang_enabled',
        'integration_yootheme_enabled',
    ];

    public function testEveryManifestDefaultOnToggleIsMirroredInVueDefaults(): void
    {
        $defaults = $this->vueDefaults();
        $excluded = array_flip(self::EXCLUDED);

        $missing = [];
        foreach ($this->manifestDefaultOnToggleKeys() as $key) {
            if (isset($excluded[$key])) {
                continue;
            }
            if (!array_key_exists($key, $defaults)) {
                $missing[] = $key . " (absent from DEFAULTS)";
            } elseif ((string) $defaults[$key] !== '1') {
                $missing[] = $key . " (DEFAULTS = '" . $defaults[$key] . "', expected '1')";
            }
        }

        sort($missing);
        $this->assertSame(
            [],
            $missing,
            "The manifest declares these toggles default-ON (default='1') but App.vue DEFAULTS does not "
            . "mirror them as '1'. A plain Save would silently turn these features OFF (#14). Add each to "
            . "the DEFAULTS object in component/com_aiboost/vue-admin/src/App.vue:\n  - "
            . implode("\n  - ", $missing)
        );
    }

    /**
     * Guard the documented exception: each EXCLUDED key must really still be a
     * manifest default-ON toggle. If a future edit removes one or flips its
     * default, this fails so the exclusion list cannot silently rot (e.g. after
     * the "locked upsell" item lands and the keys should be mirrored instead).
     */
    public function testEveryExcludedKeyIsStillADefaultOnToggle(): void
    {
        $required = array_flip($this->manifestDefaultOnToggleKeys());

        foreach (self::EXCLUDED as $key) {
            $this->assertArrayHasKey(
                $key,
                $required,
                "EXCLUDED lists '$key' but it is no longer a manifest default-ON toggle. Remove it from "
                . "EXCLUDED (and mirror it into DEFAULTS if it should now read ON in the UI)."
            );
        }
    }

    /** @return list<string> */
    private function manifestDefaultOnToggleKeys(): array
    {
        $keys = [];
        foreach (self::MANIFESTS as $name) {
            $entries = require self::MANIFEST_DIR . '/' . $name . '.php';
            if (!is_array($entries)) {
                continue;
            }
            foreach ($entries as $field) {
                if (!is_array($field) || !isset($field['key'], $field['type'])) {
                    continue;
                }
                if (!in_array($field['type'], self::TOGGLE_TYPES, true)) {
                    continue;
                }
                $default = $field['default'] ?? null;
                if ($default === '1' || $default === 1 || $default === true) {
                    $keys[] = (string) $field['key'];
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Parse the `const DEFAULTS = { ... }` object from App.vue into key => value
     * (string values only — every toggle default is a quoted '0'/'1').
     *
     * @return array<string,string>
     */
    private function vueDefaults(): array
    {
        $src = file_get_contents(self::APP_VUE);
        $this->assertNotFalse($src, 'Cannot read App.vue at ' . self::APP_VUE);

        if (!preg_match('/const DEFAULTS = \{(.*?)\n\}/s', $src, $block)) {
            $this->fail('Could not locate the DEFAULTS object in App.vue');
        }

        $out = [];
        if (preg_match_all("/^\\s*([a-z0-9_]+)\\s*:\\s*'([^']*)'/im", $block[1], $pairs, PREG_SET_ORDER)) {
            foreach ($pairs as $p) {
                $out[$p[1]] = $p[2];
            }
        }

        return $out;
    }
}
