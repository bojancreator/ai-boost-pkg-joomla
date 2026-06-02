<?php

namespace AiBoost\Tests\Lib\Manifest;

use PHPUnit\Framework\TestCase;

/**
 * Every manifest field that declares `i18n.label_key` / `i18n.description_key`
 * must have a matching row in the corresponding free plugin's en-GB .ini.
 *
 * codegen-from-manifest.py appends these placeholders idempotently, but the
 * appended lines can drift if someone hand-edits the .ini or renames a
 * manifest key. This test makes that drift visible per-PR.
 */
final class ManifestI18nIniKeysTest extends TestCase
{
    private const MANIFEST_DIR = __DIR__ . '/../../../lib/src/Manifest';
    private const PLUGINS_DIR  = __DIR__ . '/../../../plugins/system';
    private const TABS = ['core', 'schema', 'aeo', 'og', 'hreflang', 'code'];

    /** Mirrors TAB_TO_FREE_DIR in scripts/codegen-from-manifest.py */
    private const TAB_TO_FREE_DIR = [
        'schema'   => 'aiboost_schema',
        'aeo'      => 'aiboost_aeo',
        'og'       => 'aiboost_social',
        'social'   => 'aiboost_social',
        'hreflang' => 'aiboost_aeo',
        'code'     => 'aiboost_code',
        'core'     => 'aiboost_core',
    ];

    /** @return array<int, array<string,mixed>> */
    private function loadAll(): array
    {
        $all = [];
        foreach (self::TABS as $tab) {
            $entries = require self::MANIFEST_DIR . '/' . $tab . '.php';
            if (is_array($entries)) {
                foreach ($entries as $e) {
                    if (is_array($e)) {
                        $all[] = $e;
                    }
                }
            }
        }
        return $all;
    }

    private function iniKeys(string $iniPath): array
    {
        if (!is_file($iniPath)) {
            return [];
        }
        $src = (string) file_get_contents($iniPath);
        preg_match_all('/^\s*([A-Z0-9_]+)\s*=/m', $src, $m);
        return $m[1] ?? [];
    }

    public function testEveryI18nKeyExistsInTargetIni(): void
    {
        $missing = [];
        $iniCache = [];

        foreach ($this->loadAll() as $f) {
            $i18n = $f['i18n'] ?? null;
            if (!$i18n) {
                continue;
            }
            $tab = (string) ($f['tab'] ?? 'core');
            $dir = self::TAB_TO_FREE_DIR[$tab] ?? null;
            if (!$dir) {
                $missing[] = "key={$f['key']}: no free-plugin dir for tab '$tab'";
                continue;
            }
            $iniPath = self::PLUGINS_DIR . '/' . $dir . "/language/en-GB/plg_system_$dir.ini";
            if (!isset($iniCache[$iniPath])) {
                $iniCache[$iniPath] = array_flip($this->iniKeys($iniPath));
            }
            $keys = $iniCache[$iniPath];

            foreach (['label_key', 'description_key'] as $sub) {
                $ik = $i18n[$sub] ?? null;
                if (!$ik) {
                    continue;
                }
                if (!isset($keys[$ik])) {
                    $missing[] = sprintf(
                        "%s.%s = '%s' missing from %s",
                        $f['key'],
                        $sub,
                        $ik,
                        str_replace(self::PLUGINS_DIR . '/', '', $iniPath)
                    );
                }
            }
        }

        $this->assertSame(
            [],
            $missing,
            "Manifest i18n keys with no matching .ini row (run scripts/codegen-from-manifest.py):\n  - "
            . implode("\n  - ", $missing)
        );
    }
}
