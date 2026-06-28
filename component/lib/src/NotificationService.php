<?php
/**
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Lib;

defined('_JEXEC') or die;

/**
 * NotificationService — assembles the Dashboard "headline" notification list.
 *
 * It does NOT re-implement detection: it reads the same cheap, already-computed
 * signals the rest of the admin uses (settings flags, licence state, plugin
 * enabled state, ConflictDetector output, backup timestamp, setup flags), plus
 * two cheap single-file checks (sitemap.xml present, robots.txt blanket-block).
 * Expensive probes (HTTP HEAD, sitemap URL parsing, remote heartbeat) stay on
 * the Health page — every panel links there with "See the full Health report".
 *
 * Each notification:
 *   [ 'id' => 'notif_…'|'conflict_…',   // [a-z0-9_] so health.dismiss accepts it
 *     'severity' => 'critical'|'warning'|'info',
 *     'title' => string, 'message' => string,
 *     'actions' => [ ['label'=>string, 'url'=>'#/route…' | 'index.php?…'] ],
 *     'dismissible' => bool ]
 *
 * Dismissals are stored in settings['dismissed_checks'] and toggled by the
 * existing HealthController::dismiss() endpoint — shared with Health checks and
 * conflicts so the same issue dismisses everywhere.
 */
final class NotificationService
{
    private const SEVERITY_ORDER = ['critical' => 0, 'warning' => 1, 'info' => 2];
    private const BACKUP_STALE_DAYS = 30;
    private const LICENCE_EXPIRY_SOON_DAYS = 30;

    /** @var array<string,mixed> */
    private array $settings;
    /** @var array<int,string> */
    private array $dismissed;

    /**
     * @param array<string,mixed> $settings   Decoded #__aiboost_settings blob
     * @param array<int,string>   $dismissed  IDs from settings['dismissed_checks']
     */
    public function __construct(array $settings, array $dismissed)
    {
        $this->settings  = $settings;
        $this->dismissed = $dismissed;
    }

    /**
     * Build the notification list.
     *
     * @param array{
     *   conflicts?: array<int,array>,
     *   plugins?: array<string,array>,
     *   multilingualLangCount?: int,
     *   hasSettings?: bool
     * } $ctx  Already-computed context from the Dashboard view.
     *
     * @return array<int,array>  Severity-sorted, dismissed entries removed.
     */
    public function build(array $ctx): array
    {
        $items = [];

        $this->collectPluginHealth($items, $ctx['plugins'] ?? []);
        $this->collectLicence($items);
        $this->collectContentSignals($items);
        $this->collectModeFlags($items);
        $this->collectBackup($items, (bool) ($ctx['hasSettings'] ?? false));
        $this->collectSetupAndDiscovery($items, (int) ($ctx['multilingualLangCount'] ?? 0));
        $this->foldConflicts($items, $ctx['conflicts'] ?? []);

        // Drop dismissed, then sort critical → warning → info (stable within tier).
        $items = array_values(array_filter(
            $items,
            fn(array $n): bool => !in_array($n['id'], $this->dismissed, true)
        ));
        usort(
            $items,
            fn(array $a, array $b): int =>
                (self::SEVERITY_ORDER[$a['severity']] ?? 9) <=> (self::SEVERITY_ORDER[$b['severity']] ?? 9)
        );

        return $items;
    }

    // ── collectors ──────────────────────────────────────────────────────────

    /** Core engine disabled = the whole component is silent. */
    private function collectPluginHealth(array &$items, array $plugins): void
    {
        $core = $plugins['aiboost_core'] ?? null;
        if (is_array($core) && ($core['found'] ?? false) && !($core['enabled'] ?? true)) {
            $items[] = $this->make(
                'notif_core_disabled',
                'critical',
                'AI Boost core is switched off',
                'The core engine plugin is disabled, so AI Boost is not adding anything to your pages. Enable it to turn the component back on.',
                [['label' => 'Manage plugins', 'url' => 'index.php?option=com_plugins&filter[folder]=system&filter[search]=ai+boost']]
            );
        }
    }

    /** Licence expiry / problems — perpetual activation means Pro keeps working;
     *  expiry only pauses updates + support, so these are warnings, not critical. */
    private function collectLicence(array &$items): void
    {
        if ((string) ($this->settings['pro_activated'] ?? '0') !== '1') {
            return; // Free site — no licence notifications (no upsell nagging).
        }

        $heartbeat   = $this->decodeArray($this->settings['license_heartbeat'] ?? null);
        $hbStatus    = strtolower((string) ($heartbeat['status'] ?? ''));
        $licenceFix  = [['label' => 'Open License & Updates', 'url' => '#/licenses']];

        if (in_array($hbStatus, ['expired', 'invalid'], true)) {
            $items[] = $this->make(
                'notif_license_expired',
                'warning',
                'Your Pro licence has lapsed',
                'Pro features stay active, but updates and support are paused until you renew. Renew to keep receiving new versions.',
                $licenceFix
            );
            return;
        }

        // Soonest expiry across per-SKU licence state.
        $soonest = $this->soonestLicenceExpiryDays();
        if ($soonest !== null && $soonest <= self::LICENCE_EXPIRY_SOON_DAYS) {
            $items[] = $this->make(
                'notif_license_expiring',
                'warning',
                $soonest <= 0 ? 'Your Pro licence expires today' : 'Your Pro licence expires soon',
                $soonest <= 0
                    ? 'Renew now to keep receiving updates and support.'
                    : 'It expires in ' . $soonest . ' day' . ($soonest === 1 ? '' : 's') . '. Renew to keep receiving updates and support.',
                $licenceFix
            );
        }
    }

    /** Core SEO output signals (cheap settings + single-file checks). */
    private function collectContentSignals(array &$items): void
    {
        // Organization name — required for almost every schema type.
        $orgName = trim((string) ($this->settings['org_name'] ?? ''));
        $orgEn   = trim((string) ($this->settings['org_name_en'] ?? ''));
        if ($orgName === '' && $orgEn === '') {
            $items[] = $this->make(
                'notif_org_name_missing',
                'critical',
                'Organization name is missing',
                'Schema.org output needs your organization or site name. Add it so search engines and AI can identify your business.',
                [['label' => 'Open Site Identity', 'url' => '#/settings?tab=org&field=org_name']]
            );
        }

        // Schema output switched off entirely.
        if ((string) ($this->settings['enable_schema'] ?? '1') !== '1') {
            $items[] = $this->make(
                'notif_schema_disabled',
                'critical',
                'Schema.org output is turned off',
                'Structured data is the core of AI Boost. With it off, no JSON-LD is added to your pages.',
                [['label' => 'Open Schema', 'url' => '#/settings?tab=schema&field=enable_schema']]
            );
        }

        // Default social share image.
        $ogImage = trim((string) ($this->settings['default_og_image'] ?? ($this->settings['og_default_image'] ?? '')));
        if ($ogImage === '') {
            $items[] = $this->make(
                'notif_og_image_missing',
                'warning',
                'No default social image',
                'Without a fallback image, pages without their own image get no preview thumbnail when shared on social media.',
                [['label' => 'Open Social Meta', 'url' => '#/settings?tab=social&field=default_og_image']]
            );
        }

        // NOTE: the sitemap is served dynamically (no static sitemap.xml file),
        // so "is it written / reachable?" needs a live HTTP probe — that stays on
        // the Health page, not here (a file_exists check would false-alarm).

        // robots.txt blanket block (conservative single-file read).
        if ($this->robotsBlocksEveryone()) {
            $items[] = $this->make(
                'notif_robots_blocks_all',
                'critical',
                'robots.txt is blocking all crawlers',
                'Your robots.txt disallows everything, so search engines and AI crawlers cannot read the site. Review the crawler rules.',
                [['label' => 'Open Crawlers & Robots', 'url' => '#/settings?tab=crawlers&field=enable_robots']]
            );
        }

        // Analytics identifiers (cheap settings reads).
        if (trim((string) ($this->settings['ga4_measurement_id'] ?? '')) === '') {
            $items[] = $this->make(
                'notif_ga4_missing',
                'info',
                'Google Analytics 4 is not configured',
                'Add your GA4 Measurement ID to track visitors, or dismiss this if you use another analytics tool.',
                [['label' => 'Open Analytics', 'url' => '#/settings?tab=analytics&field=ga4_measurement_id']]
            );
        }
    }

    /** Production-risk mode flags. */
    private function collectModeFlags(array &$items): void
    {
        $staging = (string) ($this->settings['staging_mode'] ?? '0') === '1';
        if ($staging) {
            $items[] = $this->make(
                'notif_staging_mode',
                'warning',
                'Staging mode is ON',
                'AI Boost is treating this as a staging site (output may be held back). Turn it off on your live site.',
                [['label' => 'Open Debug settings', 'url' => '#/settings?tab=debug&field=staging_mode']]
            );
        }
        // Debug noise only matters on a live site, not on a declared staging one.
        if (!$staging && (string) ($this->settings['debug_mode'] ?? '0') === '1') {
            $items[] = $this->make(
                'notif_debug_mode',
                'warning',
                'Debug mode is ON',
                'Debug output is enabled. Turn it off on a production site so it is not exposed to visitors.',
                [['label' => 'Open Debug settings', 'url' => '#/settings?tab=debug&field=debug_mode']]
            );
        }
    }

    /** Settings-backup hygiene (server timestamp; per-site, not per-browser). */
    private function collectBackup(array &$items, bool $hasSettings): void
    {
        if (!$hasSettings) {
            return; // Nothing to back up on a first-run install.
        }
        $lastBackup = trim((string) ($this->settings['last_backup_at'] ?? ''));
        if ($lastBackup === '') {
            $items[] = $this->make(
                'notif_backup_never',
                'warning',
                'No settings backup yet',
                'Download a backup so you can restore your configuration after a migration, a major update, or anything unexpected.',
                [['label' => 'Open Import / Export', 'url' => '#/import']]
            );
            return;
        }
        $ageDays = $this->ageInDays($lastBackup);
        if ($ageDays !== null && $ageDays >= self::BACKUP_STALE_DAYS) {
            $items[] = $this->make(
                'notif_backup_stale',
                'warning',
                'Your settings backup is getting old',
                'The last backup is ' . $ageDays . ' days old. Take a fresh one so a restore would not lose recent changes.',
                [['label' => 'Open Import / Export', 'url' => '#/import']]
            );
        }
    }

    /** Onboarding + discovery (info tier). */
    private function collectSetupAndDiscovery(array &$items, int $multilingualLangCount): void
    {
        if ((string) ($this->settings['quick_setup_done'] ?? '0') !== '1') {
            $items[] = $this->make(
                'notif_quick_setup_pending',
                'info',
                'Finish Quick Setup',
                'Run the guided wizard to configure identity, schema, sitemap and social in a few minutes.',
                [['label' => 'Open Quick Setup', 'url' => '#/autopilot']]
            );
        }
        if ((string) ($this->settings['conflict_setup_done'] ?? '0') !== '1') {
            $items[] = $this->make(
                'notif_conflict_setup_pending',
                'info',
                'Run the conflict check',
                'Let AI Boost scan for other SEO plugins so you can decide which one owns each type of output.',
                [['label' => 'Open Conflict Manager', 'url' => '#/conflicts']]
            );
        }
        if ($multilingualLangCount >= 2) {
            $items[] = $this->make(
                'notif_multilingual_detected',
                'info',
                'Multilingual site detected',
                'AI Boost can emit hreflang alternates and store per-language translations. ' . $multilingualLangCount . ' languages found.',
                [['label' => 'Open Multilingual options', 'url' => '#/integrations?open=falang']]
            );
        }
        // llms.txt off — niche, so info tier only.
        if ((string) ($this->settings['llmstxt_enabled'] ?? '0') !== '1') {
            $items[] = $this->make(
                'notif_llmstxt_disabled',
                'info',
                'llms.txt is off',
                'Generate an llms.txt file so AI search engines can discover and cite your most important content.',
                [['label' => 'Open AI Visibility', 'url' => '#/settings?tab=aeo&field=llmstxt_enabled']]
            );
        }
    }

    /** Fold non-dismissed ConflictDetector results into the notification list. */
    private function foldConflicts(array &$items, array $conflicts): void
    {
        foreach ($conflicts as $c) {
            if (!is_array($c) || ($c['dismissed'] ?? false)) {
                continue;
            }
            $severity = ($c['status'] ?? 'warning') === 'critical' ? 'critical' : 'warning';
            $actions  = [];
            foreach ((array) ($c['fix_actions'] ?? []) as $fa) {
                if (is_array($fa) && !empty($fa['url'])) {
                    $actions[] = ['label' => (string) ($fa['label'] ?? 'Fix'), 'url' => (string) $fa['url']];
                }
            }
            if (!$actions && !empty($c['fix_url'])) {
                $actions[] = ['label' => 'Fix', 'url' => (string) $c['fix_url']];
            }
            $items[] = $this->make(
                preg_replace('/[^a-z0-9_]/', '', (string) ($c['id'] ?? 'conflict')),
                $severity,
                (string) ($c['label'] ?? 'Plugin conflict'),
                (string) ($c['message'] ?? ''),
                $actions
            );
        }
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * @param array<int,array{label:string,url:string}> $actions
     * @return array<string,mixed>
     */
    private function make(string $id, string $severity, string $title, string $message, array $actions): array
    {
        return [
            'id'          => $id,
            'severity'    => $severity,
            'title'       => $title,
            'message'     => $message,
            'actions'     => $actions,
            'dismissible' => true,
        ];
    }

    /** @return array<string,mixed> */
    private function decodeArray(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /** Smallest days-until-expiry across active per-SKU licence entries, or null. */
    private function soonestLicenceExpiryDays(): ?int
    {
        $state = $this->decodeArray($this->settings['license_state'] ?? null);
        $soonest = null;
        foreach ($state as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $expires = trim((string) ($entry['expires_at'] ?? ''));
            if ($expires === '') {
                continue;
            }
            $days = $this->ageInDays($expires);
            if ($days === null) {
                continue;
            }
            // ageInDays is negative for future dates → days remaining = -age.
            $remaining = -$days;
            if ($soonest === null || $remaining < $soonest) {
                $soonest = $remaining;
            }
        }
        return $soonest;
    }

    /** Whole days since $iso (negative if $iso is in the future). Null if unparseable. */
    private function ageInDays(string $iso): ?int
    {
        try {
            $then = new \DateTimeImmutable($iso);
            $now  = new \DateTimeImmutable('now');
            return (int) floor(($now->getTimestamp() - $then->getTimestamp()) / 86400);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Conservative: true only when robots.txt clearly disallows the whole site. */
    private function robotsBlocksEveryone(): bool
    {
        try {
            if (!defined('JPATH_ROOT')) {
                return false;
            }
            $path = JPATH_ROOT . '/robots.txt';
            if (!is_file($path)) {
                return false;
            }
            $content = (string) @file_get_contents($path);
            if ($content === '') {
                return false;
            }
            // Look for a "User-agent: *" group containing "Disallow: /" (blanket),
            // and no "Allow:" rule in that group that would re-open the site.
            $lines      = preg_split('/\R/', $content) ?: [];
            $inStar     = false;
            $blocksAll  = false;
            $hasAllow   = false;
            foreach ($lines as $raw) {
                $line = strtolower(trim($raw));
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                if (preg_match('/^user-agent:\s*(.+)$/', $line, $m)) {
                    $inStar = trim($m[1]) === '*';
                    continue;
                }
                if (!$inStar) {
                    continue;
                }
                if (preg_match('/^disallow:\s*\/\s*$/', $line)) {
                    $blocksAll = true;
                }
                if (preg_match('/^allow:\s*\//', $line)) {
                    $hasAllow = true;
                }
            }
            return $blocksAll && !$hasAllow;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
