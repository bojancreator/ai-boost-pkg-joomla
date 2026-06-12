<?php

namespace AiBoost\Tests\Lib;

use PHPUnit\Framework\TestCase;

/**
 * Regression guard for the uninstall key-wipe list in pkg_script.php.
 *
 * Perpetual activation survives package uninstall BY DESIGN: pro_activated is
 * written once when a licence key verifies active and must never be cleared —
 * an expired licence only pauses updates/support, never the features. An
 * earlier uninstall() wiped pro_activated/license_key while leaving
 * license_state, so ACTIVE customers were silently re-activated on reinstall
 * (via migrateActivateProPerpetual) while EXPIRED-but-perpetually-activated
 * customers were permanently relocked with no recovery path. These tests pin
 * the wipe list to the three developer/QA override keys so that contradiction
 * can never be reintroduced.
 */
final class PkgScriptUninstallKeysTest extends TestCase
{
    private const SCRIPT_PATH = __DIR__ . '/../../package/pkg_script.php';

    /**
     * Activation/licence keys that MUST survive uninstall. Deliberately
     * hard-coded here (not read from the installer script) so an accidental
     * edit to the shared constant cannot silently weaken this guard.
     */
    private const PRESERVED_KEYS = [
        'pro_activated',
        'pro_activated_at',
        'pro_activated_version',
        'license_key',
        'license_state',
        'install_id',
    ];

    /**
     * The ONLY keys uninstall() may remove: DB-only developer/QA overrides
     * that nothing ever re-creates on a customer install.
     */
    private const DEV_OVERRIDE_KEYS = [
        'dev_license_preview',
        'dev_force_free_tier',
        'license_simulation',
    ];

    public static function setUpBeforeClass(): void
    {
        // pkg_script.php is a plain (unnamespaced) Joomla installer script with
        // no load-time side effects — requiring it only defines the class.
        // _JEXEC and the Joomla\CMS\Factory stub come from the test bootstrap.
        require_once self::SCRIPT_PATH;
    }

    public function testWipedSetContainsOnlyDevOverrideKeys(): void
    {
        $this->assertEqualsCanonicalizing(
            self::DEV_OVERRIDE_KEYS,
            \Pkg_AiboostInstallerScript::UNINSTALL_WIPED_KEYS,
            'uninstall() may wipe ONLY the developer override keys — perpetual '
            . 'activation survives uninstall by design, so adding any other key '
            . 'here risks permanently relocking perpetually activated customers.'
        );
    }

    public function testWipedSetNeverIntersectsThePreservedLicenceKeys(): void
    {
        $intersection = array_values(array_intersect(
            \Pkg_AiboostInstallerScript::UNINSTALL_WIPED_KEYS,
            self::PRESERVED_KEYS
        ));

        $this->assertSame(
            [],
            $intersection,
            'uninstall() must never wipe an activation/licence key: a reinstall '
            . 'has to come back licensed (perpetual activation), and wiping only '
            . 'part of the licence state leaves contradictory writers — '
            . 'migrateActivateProPerpetual() would resurrect ACTIVE customers '
            . 'but permanently relock EXPIRED ones.'
        );
    }

    public function testUninstallBodyWipesViaTheSharedConstant(): void
    {
        // Token-level guard: the wipe loop in uninstall() must keep reading the
        // shared constant the two tests above pin. Re-inlining a literal key
        // list in the method body would bypass this guard, so its presence is
        // asserted against the raw source.
        $src = (string) file_get_contents(self::SCRIPT_PATH);

        $this->assertStringContainsString(
            '$devOverrideKeys = self::UNINSTALL_WIPED_KEYS;',
            $src,
            'uninstall() must source its wipe list from UNINSTALL_WIPED_KEYS — '
            . 'a hand-written list in the method body would escape the '
            . 'preservation guards in this test.'
        );
    }
}
