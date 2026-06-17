<?php

namespace AiBoost\Tests\Lib;

use AiBoost\Lib\AppContextInterface;
use AiBoost\Lib\HealthCheckService;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for the fix-action url contract (dead-link class).
 *
 * HealthApp renders each fix action as <a :href="fixActionHref(action)">.
 * Actions that target a page OUTSIDE the Settings form (Licenses,
 * Integrations, Errors) used to ship only target_tab without an explicit
 * url, so the client fell back to a dead href="#" — a customer clicking
 * "Open Licenses tab" went nowhere. These tests pin the rule that every
 * such fix action (and its check-level fix_url) carries an explicit
 * SPA deep link (view=app hash route), so the dead-link class cannot
 * silently return.
 *
 * The checks are private; they are invoked via reflection with a fake
 * DatabaseInterface (same approach as InstallIntegrityTest's fake) and a
 * mocked AppContextInterface, so no live Joomla is needed.
 */
final class HealthCheckFixActionUrlTest extends TestCase
{
    private const LICENSES_URL     = 'index.php?option=com_aiboost&view=app#/licenses';
    private const INTEGRATIONS_URL = 'index.php?option=com_aiboost&view=app#/integrations';

    /**
     * Invoke a private check method on a service built with fakes.
     *
     * @param array<string,mixed> $settings
     * @return array<string,mixed> The check row the method returns.
     */
    private function runCheck(string $method, array $settings, mixed $loadResult): array
    {
        $ctx     = $this->createMock(AppContextInterface::class);
        $service = new HealthCheckService($settings, new FakeHealthDatabase($loadResult), $ctx, true);

        $ref = new \ReflectionMethod($service, $method);

        $row = $ref->invoke($service);
        $this->assertIsArray($row);

        return $row;
    }

    public function testProInstalledWithoutActivationEmitsExplicitLicensesSpaUrl(): void
    {
        // Pro package row present (COUNT=1) but no activation recorded.
        $row = $this->runCheck('warningProInstallNoLicense', ['pro_activated' => '0'], 1);

        $this->assertFalse($row['pass']);
        $this->assertSame(self::LICENSES_URL, $row['fix_url']);

        $this->assertCount(1, $row['fix_actions']);
        $action = $row['fix_actions'][0];
        $this->assertSame('Open Licenses tab', $action['label']);
        $this->assertSame(self::LICENSES_URL, $action['url']);
        $this->assertSame('licenses', $action['target_tab']);
        $this->assertSame('license_key', $action['target_field']);
    }

    public function testProInstalledAndActivatedPassesWithNoFixActions(): void
    {
        $row = $this->runCheck('warningProInstallNoLicense', ['pro_activated' => '1'], 1);

        $this->assertTrue($row['pass']);
        $this->assertSame([], $row['fix_actions']);
        // The fix_url stays a valid SPA link either way (never '#').
        $this->assertSame(self::LICENSES_URL, $row['fix_url']);
    }

    /**
     * The three Integration-SDK checks target the Integrations SPA page;
     * each of their fix actions must carry the explicit view=app url.
     */
    public function testIntegrationFixActionsCarryExplicitSpaUrl(): void
    {
        $methods = [
            'infoIntegrationBridgesInstalled',
            'warningBridgeSdkMismatch',
            'warningBridgeSlotCollision',
        ];

        foreach ($methods as $method) {
            $row = $this->runCheck($method, [], 0);

            $this->assertSame(self::INTEGRATIONS_URL, $row['fix_url'], $method);
            $this->assertNotEmpty($row['fix_actions'], $method);

            foreach ($row['fix_actions'] as $action) {
                $this->assertSame('integrations', $action['target_tab'], $method);
                $this->assertSame(self::INTEGRATIONS_URL, $action['url'], $method);
            }
        }
    }

    /**
     * Error-logging check: the failing branch deep-links to the Errors SPA
     * page, the passing branch to the Debug settings tab — both must carry
     * an explicit url alongside the structured target_tab contract.
     */
    public function testErrorLoggingFixActionsAlwaysCarryUrl(): void
    {
        // loadResult=1 → 1 error-level event in 24h → failing branch.
        $failing = $this->runCheck('infoErrorLogging', [], 1);
        $this->assertFalse($failing['pass']);
        $this->assertSame(
            'index.php?option=com_aiboost&view=app&field=errors_clear_all#/errors',
            $failing['fix_actions'][0]['url']
        );
        $this->assertSame('errors', $failing['fix_actions'][0]['target_tab']);

        // loadResult=0 → no events → passing branch, Debug settings deep link.
        $passing = $this->runCheck('infoErrorLogging', [], 0);
        $this->assertTrue($passing['pass']);
        $this->assertSame(
            'index.php?option=com_aiboost&view=settings&tab=debug&field=error_log_enabled',
            $passing['fix_actions'][0]['url']
        );
        $this->assertSame('debug', $passing['fix_actions'][0]['target_tab']);
    }

    /**
     * Guard: no fix action emitted by these checks may resolve to a dead
     * link — every entry needs a non-empty url that is not '#'.
     */
    public function testNoAuditedCheckEmitsUrlLessFixActions(): void
    {
        $cases = [
            ['warningProInstallNoLicense', ['pro_activated' => '0'], 1],
            ['infoIntegrationBridgesInstalled', [], 0],
            ['warningBridgeSdkMismatch', [], 0],
            ['warningBridgeSlotCollision', [], 0],
            ['infoErrorLogging', [], 1],
            ['infoErrorLogging', [], 0],
        ];

        foreach ($cases as [$method, $settings, $loadResult]) {
            $row = $this->runCheck($method, $settings, $loadResult);
            foreach ($row['fix_actions'] as $i => $action) {
                $url = (string) ($action['url'] ?? '');
                $this->assertNotSame('', $url, "$method fix_actions[$i] has no url");
                $this->assertNotSame('#', $url, "$method fix_actions[$i] is a dead link");
            }
        }
    }

    // ── Item 3: global NoIndex/NoFollow Health check ──────────────────────────

    /** Build a service whose AppContext returns $robots for the 'robots' key. */
    private function serviceWithRobots(string $robots, array $settings = []): HealthCheckService
    {
        $ctx = $this->createMock(AppContextInterface::class);
        $ctx->method('getConfigValue')->willReturnCallback(
            static fn (string $key, string $default = ''): string => $key === 'robots' ? $robots : $default
        );

        return new HealthCheckService($settings, new FakeHealthDatabase(0), $ctx, true);
    }

    public function testGlobalNoIndexWarnsWhenConfigBlocksIndexing(): void
    {
        $service = $this->serviceWithRobots('noindex, nofollow');
        $row = (new \ReflectionMethod($service, 'checkGlobalNoIndex'))->invoke($service);

        $this->assertFalse($row['pass']);
        $this->assertSame('warning', $row['status']);
        $this->assertSame('index.php?option=com_config', $row['fix_url']);
        $this->assertStringContainsStringIgnoringCase('noindex', (string) $row['message']);
    }

    public function testGlobalNoIndexPassesWhenIndexingAllowed(): void
    {
        $service = $this->serviceWithRobots('index, follow');
        $row = (new \ReflectionMethod($service, 'checkGlobalNoIndex'))->invoke($service);

        $this->assertTrue($row['pass']);
        $this->assertSame('', $row['fix_url']);
    }

    public function testGlobalNoIndexSuppressedInStagingMode(): void
    {
        // A site-wide noindex is expected on a staging/dev install, so the check
        // must not nag there.
        $service = $this->serviceWithRobots('noindex, nofollow', ['staging_mode' => '1']);
        $row = (new \ReflectionMethod($service, 'checkGlobalNoIndex'))->invoke($service);

        $this->assertTrue($row['pass']);
    }

    // ── Item 9: robots.txt writability Health check ───────────────────────────

    public function testRobotsWritablePassesWhenManagementOff(): void
    {
        // enable_robots unset → nothing is written to disk, so the check passes
        // without touching the filesystem.
        $row = $this->runCheck('checkRobotsWritable', [], 0);
        $this->assertTrue($row['pass']);
        $this->assertSame('warning', $row['status']);
    }
}

/**
 * Minimal DatabaseInterface fake for HealthCheckService checks: every
 * query funnels into a single configurable loadResult() value (used as the
 * COUNT / MAX result by the audited checks). setQuery() widens its
 * parameter to mixed because the service passes both query objects and
 * raw SQL strings.
 */
final class FakeHealthDatabase implements \Joomla\Database\DatabaseInterface
{
    public function __construct(private mixed $result = 0) {}

    public function getQuery(bool $new = false): object
    {
        return new FakeHealthQuery();
    }

    public function setQuery(mixed $query, int $offset = 0, int $limit = 0): static
    {
        return $this;
    }

    public function loadResult(): mixed
    {
        return $this->result;
    }

    public function loadAssocList(?string $key = null): array
    {
        return [];
    }

    public function loadObjectList(?string $key = null): array
    {
        return [];
    }

    public function quote(mixed $text, bool $escape = true): string
    {
        return "'" . (string) $text . "'";
    }

    public function quoteName(mixed $name, mixed $as = null): mixed
    {
        return is_array($name) ? array_map(fn ($n) => "`$n`", $name) : "`$name`";
    }

    public function execute(): bool
    {
        return true;
    }

    public function insertObject(string $table, object &$object, ?string $key = null): bool
    {
        return true;
    }

    public function updateObject(string $table, object &$object, mixed $key, bool $nulls = false): bool
    {
        return true;
    }
}

final class FakeHealthQuery
{
    public function __call(string $name, array $args): self
    {
        return $this;
    }
}
