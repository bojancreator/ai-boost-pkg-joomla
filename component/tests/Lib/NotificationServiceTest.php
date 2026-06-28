<?php

namespace AiBoost\Tests\Lib;

use AiBoost\Lib\NotificationService;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the Dashboard notification catalogue: each cheap, settings-driven
 * signal maps to the expected id + severity, dismissed entries are removed, the
 * list is severity-sorted (critical → warning → info), and ConflictDetector
 * output is folded in. File-system signals (sitemap.xml / robots.txt) are not
 * exercised here — they degrade safely without JPATH_ROOT — so every baseline
 * keeps `enable_sitemap` off to avoid the "sitemap not written" probe.
 */
final class NotificationServiceTest extends TestCase
{
    /** A clean site that should raise NO notifications. */
    private function cleanSettings(): array
    {
        return [
            'org_name'            => 'Acme Ltd',
            'enable_schema'       => '1',
            'default_og_image'    => 'images/og.jpg',
            'enable_sitemap'      => '0',
            'ga4_measurement_id'  => 'G-ABC123',
            'staging_mode'        => '0',
            'debug_mode'          => '0',
            'last_backup_at'      => gmdate('c'),
            'quick_setup_done'    => '1',
            'conflict_setup_done' => '1',
            'llmstxt_enabled'     => '1',
            'pro_activated'       => '0',
        ];
    }

    private function ctx(array $over = []): array
    {
        return array_merge([
            'conflicts'             => [],
            'plugins'               => ['aiboost_core' => ['found' => true, 'enabled' => true]],
            'multilingualLangCount' => 0,
            'hasSettings'           => true,
        ], $over);
    }

    /** @return array<int,string> ids in returned order */
    private function ids(array $notifs): array
    {
        return array_map(static fn(array $n): string => $n['id'], $notifs);
    }

    private function build(array $settings, array $ctx, array $dismissed = []): array
    {
        return (new NotificationService($settings, $dismissed))->build($ctx);
    }

    public function testCleanSiteRaisesNothing(): void
    {
        $notifs = $this->build($this->cleanSettings(), $this->ctx());
        $this->assertSame([], $notifs, 'A fully-configured site should have no notifications.');
    }

    public function testCoreDisabledIsCritical(): void
    {
        $notifs = $this->build(
            $this->cleanSettings(),
            $this->ctx(['plugins' => ['aiboost_core' => ['found' => true, 'enabled' => false]]])
        );
        $this->assertContains('notif_core_disabled', $this->ids($notifs));
        $byId = $this->byId($notifs);
        $this->assertSame('critical', $byId['notif_core_disabled']['severity']);
    }

    public function testMissingOrgNameAndSchemaDisabledAreCritical(): void
    {
        $s = $this->cleanSettings();
        $s['org_name']      = '';
        $s['enable_schema'] = '0';
        $ids = $this->ids($this->build($s, $this->ctx()));
        $this->assertContains('notif_org_name_missing', $ids);
        $this->assertContains('notif_schema_disabled', $ids);
    }

    public function testStagingAndOgImageAreWarnings(): void
    {
        $s = $this->cleanSettings();
        $s['staging_mode']     = '1';
        $s['default_og_image'] = '';
        $byId = $this->byId($this->build($s, $this->ctx()));
        $this->assertArrayHasKey('notif_staging_mode', $byId);
        $this->assertSame('warning', $byId['notif_staging_mode']['severity']);
        $this->assertArrayHasKey('notif_og_image_missing', $byId);
        $this->assertSame('warning', $byId['notif_og_image_missing']['severity']);
    }

    public function testBackupNeverWhenSettingsExistButNoBackup(): void
    {
        $s = $this->cleanSettings();
        unset($s['last_backup_at']);
        $ids = $this->ids($this->build($s, $this->ctx()));
        $this->assertContains('notif_backup_never', $ids);
    }

    public function testNoBackupNotificationOnFirstRun(): void
    {
        $s = $this->cleanSettings();
        unset($s['last_backup_at']);
        $ids = $this->ids($this->build($s, $this->ctx(['hasSettings' => false])));
        $this->assertNotContains('notif_backup_never', $ids, 'First-run install has nothing to back up.');
    }

    public function testExpiredProLicenceIsWarning(): void
    {
        $s = $this->cleanSettings();
        $s['pro_activated']     = '1';
        $s['license_heartbeat'] = json_encode(['status' => 'expired']);
        $byId = $this->byId($this->build($s, $this->ctx()));
        $this->assertArrayHasKey('notif_license_expired', $byId);
        $this->assertSame('warning', $byId['notif_license_expired']['severity']);
    }

    public function testFreeSiteHasNoLicenceNotification(): void
    {
        $s = $this->cleanSettings();
        $s['pro_activated']     = '0';
        $s['license_heartbeat'] = json_encode(['status' => 'expired']);
        $ids = $this->ids($this->build($s, $this->ctx()));
        $this->assertNotContains('notif_license_expired', $ids);
    }

    public function testDismissedNotificationIsRemoved(): void
    {
        $s = $this->cleanSettings();
        $s['staging_mode'] = '1';
        $ids = $this->ids($this->build($s, $this->ctx(), ['notif_staging_mode']));
        $this->assertNotContains('notif_staging_mode', $ids);
    }

    public function testConflictsAreFoldedInWithSeverity(): void
    {
        $ctx = $this->ctx(['conflicts' => [
            ['id' => 'conflict_4seo', 'status' => 'critical', 'label' => '4SEO', 'message' => 'Both manage schema.', 'fix_url' => 'index.php?option=com_plugins', 'dismissed' => false],
            ['id' => 'conflict_old',  'status' => 'warning',  'label' => 'X', 'message' => 'm', 'dismissed' => true],
        ]]);
        $byId = $this->byId($this->build($this->cleanSettings(), $ctx));
        $this->assertArrayHasKey('conflict_4seo', $byId);
        $this->assertSame('critical', $byId['conflict_4seo']['severity']);
        $this->assertArrayNotHasKey('conflict_old', $byId, 'Dismissed conflicts must not appear.');
    }

    public function testSeveritySortOrder(): void
    {
        $s = $this->cleanSettings();
        $s['org_name']        = '';      // critical
        $s['staging_mode']    = '1';     // warning
        $s['quick_setup_done'] = '0';    // info
        $severities = array_map(static fn(array $n): string => $n['severity'], $this->build($s, $this->ctx()));
        $rank = ['critical' => 0, 'warning' => 1, 'info' => 2];
        $prev = -1;
        foreach ($severities as $sev) {
            $this->assertGreaterThanOrEqual($prev, $rank[$sev], 'Notifications must be severity-sorted.');
            $prev = $rank[$sev];
        }
    }

    /** @return array<string,array> id => notification */
    private function byId(array $notifs): array
    {
        $out = [];
        foreach ($notifs as $n) {
            $out[$n['id']] = $n;
        }
        return $out;
    }
}
