<?php
/**
 * AI Boost — Component Installer Script
 *
 * @package     AiBoost\Component\AiBoost
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Language\Text;

/**
 * Task #461 audit note — this component installer script owns
 * preflight/postflight, plus a robots.txt cleanup on uninstall.
 * DB tables are declared in `sql/install.sql` and dropped automatically
 * via `sql/uninstall.sql` on component removal; the package-level
 * uninstall (`pkg_script.php`) remains the canonical place for the
 * cross-package cleanup (all 5 aiboost tables, llms.txt/sitemap files,
 * OG custom fields).
 *
 * Task #566 — robots.txt managed-block cleanup is mirrored HERE because
 * the component is removed in every uninstall path (direct removal AND
 * the package cascade), whereas the package script does not always run.
 * The strip is idempotent, so running in both places is safe.
 */
class Com_AiboostInstallerScript
{
    public const MIN_PHP     = '8.1.0';
    public const MIN_JOOMLA  = '5.0.0';
    public const VERSION     = '0.6.0';

    /**
     * AI Boost owns ONLY a fenced block inside the user's robots.txt. These
     * markers MUST stay byte-identical with
     * AiBoost\Lib\RobotsTxtBuilder::{BEGIN,END}_MARKER and the legacy marker.
     * Inlined because the lib cannot be autoloaded while the component is being
     * uninstalled.
     */
    private const ROBOTS_BEGIN_MARKER  = '# BEGIN AI Boost for Joomla managed block (aiboostnow.com) - do not edit between these markers';
    private const ROBOTS_END_MARKER    = '# END AI Boost for Joomla managed block';
    private const ROBOTS_LEGACY_MARKER = '# Managed by AI Boost';

    public function preflight(string $type, InstallerAdapter $parent): bool
    {
        if (version_compare(PHP_VERSION, self::MIN_PHP, '<')) {
            Factory::getApplication()->enqueueMessage(
                sprintf('AI Boost requires PHP %s or higher. You are running PHP %s.', self::MIN_PHP, PHP_VERSION),
                'error'
            );
            return false;
        }

        if (version_compare(JVERSION, self::MIN_JOOMLA, '<')) {
            Factory::getApplication()->enqueueMessage(
                sprintf('AI Boost requires Joomla %s or higher. You are running Joomla %s.', self::MIN_JOOMLA, JVERSION),
                'error'
            );
            return false;
        }

        return true;
    }

    public function postflight(string $type, InstallerAdapter $parent): void
    {
        // Migration: create #__aiboost_url_scans on upgrade if missing (added v0.24.0)
        if (in_array($type, ['install', 'discover_install', 'update'], true)) {
            try {
                $db = Factory::getDbo();
                $db->setQuery(
                    "CREATE TABLE IF NOT EXISTS `#__aiboost_url_scans` (
                      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                      `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
                      `total_urls` INT UNSIGNED NOT NULL DEFAULT 0,
                      `done_urls` INT UNSIGNED NOT NULL DEFAULT 0,
                      `current_url` VARCHAR(2000) NOT NULL DEFAULT '',
                      `queue_json` MEDIUMTEXT NULL,
                      `results_json` MEDIUMTEXT NULL,
                      `error_message` VARCHAR(500) NOT NULL DEFAULT '',
                      `started_at` DATETIME NOT NULL,
                      `finished_at` DATETIME NULL,
                      `updated_at` DATETIME NOT NULL,
                      PRIMARY KEY (`id`),
                      KEY `idx_status` (`status`),
                      KEY `idx_started_at` (`started_at`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
                )->execute();
            } catch (\Throwable $e) {
                // Non-fatal; install continues.
            }
        }

        if (in_array($type, ['install', 'discover_install'], true)) {
            Factory::getApplication()->enqueueMessage(
                sprintf(
                    'AI Boost for Joomla v%s installed successfully. <a href="index.php?option=com_aiboost">Open AI Boost &rarr;</a>',
                    self::VERSION
                ),
                'message'
            );
        }
    }

    /**
     * Task #566 — strip the AI Boost managed block from robots.txt on uninstall,
     * preserving any user-authored rules. Delete the file outright only when it
     * was entirely ours (fenced-only or legacy pre-fence format).
     */
    public function uninstall(InstallerAdapter $parent): void
    {
        $robotsPath = JPATH_ROOT . '/robots.txt';
        if (!is_file($robotsPath)) {
            return;
        }

        $existing = (string) @file_get_contents($robotsPath);
        $stripped = $this->stripRobotsBlock($existing);
        if ($stripped === $existing) {
            return;
        }

        if (trim($stripped) === '') {
            @unlink($robotsPath);
        } else {
            @file_put_contents($robotsPath, rtrim($stripped) . "\n");
        }
    }

    /**
     * Remove our fenced managed block from existing robots.txt content. Mirrors
     * AiBoost\Lib\RobotsTxtBuilder::stripManagedBlock(); returns '' when the
     * whole file was ours.
     */
    private function stripRobotsBlock(string $existing): string
    {
        if ($existing === '') {
            return '';
        }

        if (str_starts_with(ltrim($existing, "\xEF\xBB\xBF \t\r\n"), self::ROBOTS_LEGACY_MARKER)) {
            return '';
        }

        $pattern = '/\n*' . preg_quote(self::ROBOTS_BEGIN_MARKER, '/')
            . '.*?' . preg_quote(self::ROBOTS_END_MARKER, '/') . '[^\n]*\n?/su';

        return (string) preg_replace($pattern, '', $existing);
    }
}
