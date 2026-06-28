<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

/**
 * #2 — the Pro gate is ONE signal. Every server/runtime Pro decision must route
 * through PluginRegistry::isProActive() / hasPro($sku); NO code may re-derive
 * "is this Pro?" from the raw `license_tier` or by walking `license_state`.
 * Doing so re-introduces the historical leak (SettingsController::isProSetting()
 * and aiboost_sitemap::isPro() once gated on `license_tier` and so emitted Pro
 * output after a licence expired).
 *
 * SCOPE — the EMISSION branch, where such a gate would actually leak Pro OUTPUT:
 *   - component/plugins/system/**            (all front-end emission plugins,
 *                                             incl. *_pro and the integration bridges)
 *   - …/Controller/SettingsController.php    (the settings.save Pro gate)
 *
 * FORBIDDEN here = READING the raw value: `['license_tier']` / `['license_state']`
 * array access, or `->get('license_tier'|'license_state')`. In emission/save code
 * the only purpose of reading the raw tier is to gate on it, and the canonical
 * gate is isProActive()/hasPro(). NOT flagged (per the rule): the key name as a
 * string in a list, a WRITE (`[...] = …`), and comments/docblocks — comments are
 * stripped via the tokenizer before scanning, so prose like "gated on
 * `license_tier`" never trips the guard.
 *
 * SCOPE-EXCLUSION (the licence AUTHORITY — deliberately NOT scanned; see
 * self::LICENSE_AUTHORITY_OUT_OF_SCOPE): PluginRegistry owns license_state and
 * materialises license_tier; LicenseValidator/Heartbeat/Reconcile are the
 * verification authority; pkg_script does install-time edition detection. These
 * are the sanctioned owners of the raw values, not emission/save gates.
 *
 * KNOWN, OUT-OF-SCOPE VIOLATIONS (BACKLOG, post-1.0 — admin/health DISPLAY only,
 * never visitor-facing emission, so NOT a leak): mod_aiboost_health.php:78,
 * HealthCheckService.php:2690, Dashboard/HtmlView.php:269 (checkIsProEnabled),
 * plus the dead ProGate::isProEnabled() / AbstractService::isProTier(). They
 * derive isPro from license_tier, so a perpetual-Pro customer reads "Free" in the
 * admin panel after expiry. Tracked in BACKLOG; this test guards the emission
 * branch so the leak cannot return where it matters.
 *
 * Red-green: add `$settings['license_tier'] === 'pro'` to a plugin emission
 * method and testNoRawLicenseTierOrStateReadInEmissionBranch() goes red naming it.
 */
final class EmissionProGateSourceContractTest extends TestCase
{
    private const COMPONENT = __DIR__ . '/../../';

    /** Emission-branch roots (dir or file), relative to component/. */
    private const SCOPE = [
        'plugins/system',
        'com_aiboost/admin/src/Administrator/Controller/SettingsController.php',
    ];

    /**
     * The licence authority — sanctioned owners of license_tier/license_state,
     * deliberately OUT of the emission scope. Dated + reasoned so the next
     * maintainer knows the exclusion is intentional, not forgotten.
     *
     * @var array<string,string>
     */
    private const LICENSE_AUTHORITY_OUT_OF_SCOPE = [
        'lib/src/PluginRegistry.php'   => '2026-06-24 — owns license_state + materialises the back-compat license_tier; the single canonical gate (isProActive/hasPro).',
        'lib/src/LicenseValidator.php' => '2026-06-24 — Lemon Squeezy key verification authority.',
        'lib/src/LicenseHeartbeat.php' => '2026-06-24 — heartbeat re-validate; persists license_heartbeat.',
        'lib/src/LicenseReconcile.php' => '2026-06-24 — install_id-anchored Pro recovery; reads license_state by design.',
        'package/pkg_script.php'       => '2026-06-24 — install-time edition detection + legacy license_tier migration.',
    ];

    public function testNoRawLicenseTierOrStateReadInEmissionBranch(): void
    {
        $offenders = [];

        foreach ($this->scopeFiles() as $rel => $abs) {
            $code  = $this->codeWithoutComments((string) file_get_contents($abs));
            $lines = explode("\n", $code);
            foreach ($lines as $i => $line) {
                if ($this->isRawTierRead($line)) {
                    $offenders[] = $rel . ':' . ($i + 1) . '  ' . trim($line);
                }
            }
        }

        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            "Emission/save code must derive Pro from PluginRegistry::isProActive()/hasPro(), never "
            . "by reading the raw license_tier/license_state. A raw read here re-opens the historical "
            . "after-expiry Pro leak (isProSetting / sitemap::isPro). Offenders:\n  - "
            . implode("\n  - ", $offenders)
        );
    }

    public function testEmissionScopeResolvesToRealFiles(): void
    {
        $files = $this->scopeFiles();
        $this->assertArrayHasKey(
            'com_aiboost/admin/src/Administrator/Controller/SettingsController.php',
            $files,
            'SettingsController.php must be in scope — the settings.save Pro gate.'
        );
        // The emission plugins must be discovered (core + the per-feature plugins).
        $this->assertGreaterThanOrEqual(
            7,
            count(array_filter(array_keys($files), static fn(string $r): bool => str_starts_with($r, 'plugins/system/'))),
            'Expected the plugins/system emission tree to resolve to many PHP files.'
        );
    }

    /** A raw READ of license_tier/license_state (array access or ->get), excluding writes. */
    private function isRawTierRead(string $line): bool
    {
        // `$x['license_tier']` / `["license_state"]` — but NOT an assignment target
        // (`[...] =` is a write; `[...] ===`/`==` is a read used in a comparison).
        if (preg_match('/\[\s*[\'"]license_(?:tier|state)[\'"]\s*\](?!\s*=(?!=))/', $line)) {
            return true;
        }
        // ->get('license_tier') / ->params->get("license_state") — get() always reads.
        if (preg_match('/->get\(\s*[\'"]license_(?:tier|state)[\'"]/', $line)) {
            return true;
        }
        return false;
    }

    /**
     * Return the source with COMMENT and DOC_COMMENT tokens blanked out (their
     * newlines preserved so line numbers stay aligned). String literals are kept
     * because an array key like `'license_tier'` is itself a string token.
     */
    private function codeWithoutComments(string $src): string
    {
        $out = '';
        foreach (token_get_all($src) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    $out .= str_repeat("\n", substr_count($token[1], "\n"));
                    continue;
                }
                $out .= $token[1];
            } else {
                $out .= $token;
            }
        }
        return $out;
    }

    /** @return array<string,string> relative path => absolute path */
    private function scopeFiles(): array
    {
        $base  = realpath(self::COMPONENT);
        $files = [];

        foreach (self::SCOPE as $entry) {
            $abs = realpath(self::COMPONENT . $entry);
            if ($abs === false) {
                continue;
            }
            if (is_file($abs)) {
                $files[$this->rel($base, $abs)] = $abs;
                continue;
            }
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if (strtolower($file->getExtension()) !== 'php') {
                    continue;
                }
                $path = str_replace('\\', '/', $file->getPathname());
                if (preg_match('#/(tests|vendor|node_modules)/#', $path)) {
                    continue;
                }
                $files[$this->rel($base, $file->getPathname())] = $file->getPathname();
            }
        }

        return $files;
    }

    private function rel(string $base, string $abs): string
    {
        return ltrim(str_replace('\\', '/', substr($abs, strlen($base))), '/');
    }
}
