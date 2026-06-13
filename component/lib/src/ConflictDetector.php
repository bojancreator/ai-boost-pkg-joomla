<?php
/**
 * AI Boost — ConflictDetector
 * Queries #__extensions for known conflicting SEO plugins and checks Joomla Core OG tag setting.
 *
 * Conflict matrix covers: 4SEO, Sh404SEF, JoomSEF/OpenSEF, AdminTools, OSMap/Xmap,
 * and Joomla Core MetaOgp (Global Configuration → Metadata Settings).
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or defined('ABSPATH') or die;

use AiBoost\Lib\Cms\AdapterRegistry;
use Joomla\Database\DatabaseInterface;

class ConflictDetector
{
    private DatabaseInterface $db;
    private array $settings;
    private array $dismissed;

    public function __construct(DatabaseInterface $db, array $settings, array $dismissed = [])
    {
        $this->db        = $db;
        $this->settings  = $settings;
        $this->dismissed = $dismissed;
    }

    /**
     * Run all conflict checks.
     *
     * @return list<array{id:string,status:string,category:string,label:string,pass:bool,
     *                     show_pass:bool,message:string,fix_url:string,fix_actions:list<array>,dismissed:bool}>
     */
    public function scan(): array
    {
        $results = [];
        $this->check4Seo($results);
        $this->checkSh404Sef($results);
        $this->checkJoomSef($results);
        $this->checkAdminTools($results);
        $this->checkOsMap($results);
        $this->checkJoomlaOg($results);
        return $results;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INDIVIDUAL CONFLICT CHECKS
    // ─────────────────────────────────────────────────────────────────────────

    private function check4Seo(array &$results): void
    {
        // 4SEO is Weeblr's product; its real #__extensions.element is 'forseo'
        // (system plugin "System - 4SEO") + component 'com_forseo' — NOT '4seo'
        // or the install-directory name 'plg_system_4seo' the previous check
        // used, so the conflict never fired (identical bug fixed for Admin Tools
        // above). Verified live: element 'forseo' on a 4SEO install.
        if (!$this->isEnabled('plugin', 'forseo', 'system')
            && !$this->isEnabled('component', 'com_forseo', '')) {
            return;
        }

        $results[] = $this->makeConflict(
            'conflict_4seo', 'critical', '4SEO Plugin',
            '4SEO detected — both plugins manage title, meta, canonical and OpenGraph tags. '
            . 'Running both simultaneously causes duplicate tags and conflicting canonical URLs.',
            [
                ['label' => 'Disable 4SEO in Plugin Manager', 'url' => 'index.php?option=com_plugins&filter[folder]=system&filter[search]=4seo'],
                ['label' => 'View conflict guide', 'url' => 'https://aiboostnow.com/docs/conflicts#4seo'],
            ]
        );
    }

    private function checkSh404Sef(array &$results): void
    {
        // #__extensions.element is the bare 'sh404sef', not 'plg_system_sh404sef'.
        if (!$this->isEnabled('plugin', 'sh404sef', 'system')
            && !$this->isEnabled('component', 'com_sh404sef', '')) {
            return;
        }

        $results[] = $this->makeConflict(
            'conflict_sh404sef', 'critical', 'Sh404SEF',
            'Sh404SEF detected — it manages canonical URLs, title tags and meta descriptions. '
            . 'Running both plugins simultaneously may cause duplicate or conflicting SEO output.',
            [
                ['label' => 'Configure Sh404SEF overlap', 'url' => 'index.php?option=com_plugins&filter[folder]=system&filter[search]=sh404sef'],
                ['label' => 'View conflict guide', 'url' => 'https://aiboostnow.com/docs/conflicts#sh404sef'],
            ]
        );
    }

    private function checkJoomSef(array &$results): void
    {
        // #__extensions.element stores the bare 'joomsef' / 'opensef'.
        if (!$this->isEnabled('plugin', 'joomsef', 'system')
            && !$this->isEnabled('component', 'com_joomsef', '')
            && !$this->isEnabled('plugin', 'opensef', 'system')) {
            return;
        }

        $results[] = $this->makeConflict(
            'conflict_joomsef', 'warning', 'JoomSEF / OpenSEF',
            'A JoomSEF-family SEO router is installed. It may generate its own canonical URLs '
            . 'and meta tags that conflict with AI Boost output.',
            [
                ['label' => 'Manage SEF plugins', 'url' => 'index.php?option=com_plugins&filter[folder]=system&filter[search]=joomsef'],
                ['label' => 'View conflict guide', 'url' => 'https://aiboostnow.com/docs/conflicts#joomsef'],
            ]
        );
    }

    private function checkAdminTools(array &$results): void
    {
        // Admin Tools and AI Boost only collide over robots.txt, so this is a
        // conflict ONLY when AI Boost is itself managing robots.txt. When AI
        // Boost's robots.txt management is off, Admin Tools owning the file is
        // perfectly fine — stay quiet. (enable_robots defaults to '1'.)
        if ((string) ($this->settings['enable_robots'] ?? '1') !== '1') {
            return;
        }

        // Akeeba Admin Tools ships a system plugin (#__extensions element
        // 'admintools') and a component ('com_admintools'); the robots.txt
        // editor lives in the component, so detect either. The previous check
        // looked for element 'plg_system_admintools', which is the install
        // directory name, NOT the #__extensions.element value — so it never
        // matched and the conflict never fired.
        if (!$this->isEnabled('plugin', 'admintools', 'system')
            && !$this->isEnabled('component', 'com_admintools', '')) {
            return;
        }

        $results[] = $this->makeConflict(
            'conflict_admintools', 'warning', 'Admin Tools (Akeeba)',
            'Admin Tools is installed and can manage robots.txt, and AI Boost is also set to '
            . 'manage robots.txt. Keep robots.txt editing in one tool only — otherwise whichever '
            . 'tool writes last wins and overwrites the other.',
            [
                ['label' => 'Configure Admin Tools', 'url' => 'index.php?option=com_admintools'],
                ['label' => 'AI Boost robots.txt settings', 'url' => 'index.php?option=com_aiboost&view=app#/crawlers-robots'],
                ['label' => 'View conflict guide', 'url' => 'https://aiboostnow.com/docs/conflicts#admintools'],
            ]
        );
    }

    private function checkOsMap(array &$results): void
    {
        if (!$this->isEnabled('component', 'com_osmap', '')
            && !$this->isEnabled('component', 'com_xmap', '')) {
            return;
        }

        $results[] = $this->makeConflict(
            'conflict_osmap', 'warning', 'OSMap / Xmap',
            'OSMap or Xmap is installed and also generates an XML sitemap. Both can coexist '
            . 'but may produce duplicate sitemap entries if routing overlaps.',
            [
                ['label' => 'Manage Extensions', 'url' => 'index.php?option=com_installer&view=manage'],
                ['label' => 'View conflict guide', 'url' => 'https://aiboostnow.com/docs/conflicts#osmap'],
            ]
        );
    }

    private function checkJoomlaOg(array &$results): void
    {
        try {
            $fs      = AdapterRegistry::filesystem();
            if (!$fs->siteFileExists('configuration.php')) {
                return;
            }
            $content = (string) $fs->readSiteFile('configuration.php');
            if (!preg_match('/\$MetaOgp\s*=\s*[\'"]?([01])[\'"]?/', $content, $m)) {
                return;
            }
            if ((int) $m[1] !== 1) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        $results[] = $this->makeConflict(
            'conflict_joomla_og', 'warning', 'Joomla Core OG Tags',
            "Joomla's built-in OpenGraph tags are enabled in Global Configuration → Metadata Settings. "
            . 'This produces duplicate og:title and og:description tags alongside AI Boost Social plugin.',
            [
                ['label' => 'Open Global Configuration', 'url' => 'index.php?option=com_config'],
                ['label' => 'AI Boost Social Settings', 'url' => 'index.php?option=com_aiboost&view=settings#tab-social-btn'],
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Check whether an extension is installed AND enabled in #__extensions.
     * Disabled extensions do not conflict — only active/enabled ones can.
     */
    private function isEnabled(string $type, string $element, string $folder): bool
    {
        try {
            $query = $this->db->getQuery(true)
                ->select('COUNT(*)')
                ->from('#__extensions')
                ->where($this->db->quoteName('type') . '=' . $this->db->quote($type))
                ->where($this->db->quoteName('element') . '=' . $this->db->quote($element))
                ->where($this->db->quoteName('enabled') . '=1');

            if ($folder !== '') {
                $query->where($this->db->quoteName('folder') . '=' . $this->db->quote($folder));
            }

            return (int) $this->db->setQuery($query)->loadResult() > 0;
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning($e, [
                'where'   => 'ConflictDetector::isEnabled',
                'type'    => $type,
                'element' => $element,
            ]);
            return false;
        }
    }

    /**
     * Build a conflict check result item.
     *
     * @param list<array{label:string,url:string}> $fixActions
     */
    private function makeConflict(
        string $id,
        string $severity,
        string $label,
        string $message,
        array  $fixActions = []
    ): array {
        return [
            'id'          => $id,
            'status'      => $severity,
            'category'    => 'Conflicts',
            'label'       => $label,
            'pass'        => false,
            'show_pass'   => false,
            'message'     => $message,
            'fix_url'     => $fixActions[0]['url'] ?? '',
            'fix_actions' => $fixActions,
            'dismissed'   => in_array($id, $this->dismissed, true),
        ];
    }
}
