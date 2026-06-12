<?php
/**
 * AI Boost — YOOtheme Pro Integration installer script.
 *
 * Postflight migration from the legacy `aiboost_yootheme` add-on bridge to
 * the SDK `aiboost_int_yootheme` bridge:
 *   1. Copy the six yootheme_* settings from the legacy plugin's params into
 *      the AI Boost settings blob (only keys not already set there).
 *   2. Disable the legacy `aiboost_yootheme` plugin row so the two bridges
 *      can never both emit YOOtheme schema.
 *
 * All best-effort: a missing legacy plugin or any DB error never blocks the
 * install, because the new bridge works on its defaults regardless.
 *
 * @package     AiBoost\Plugin\System\AiBoostIntYootheme
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            InstallerScriptInterface::class,
            new class ($container->get(DatabaseInterface::class)) implements InstallerScriptInterface {
                private const LEGACY_ELEMENT = 'aiboost_yootheme';
                private const MIGRATED_KEYS  = [
                    'yootheme_faq_enabled',
                    'yootheme_gallery_enabled',
                    'yootheme_schema_mapping',
                    'yootheme_accordion_selector',
                    'yootheme_meta_override',
                    'yootheme_sitemap_exclude_builder',
                ];

                public function __construct(private DatabaseInterface $db)
                {
                }

                public function install(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function update(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function uninstall(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function preflight(string $type, InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    $this->migrateLegacyBridge();
                    return true;
                }

                private function migrateLegacyBridge(): void
                {
                    try {
                        $legacy = $this->loadLegacyRow();
                        if ($legacy === null) {
                            return; // No legacy bridge installed — fresh install.
                        }

                        $legacyParams = json_decode((string) ($legacy->params ?? ''), true);
                        $legacyParams = is_array($legacyParams) ? $legacyParams : [];

                        $this->mergeIntoSettings($legacyParams);
                        $this->disableLegacyPlugin((int) $legacy->extension_id);
                    } catch (\Throwable $e) {
                        // Best-effort: never block the install.
                    }
                }

                private function loadLegacyRow(): ?object
                {
                    $db = $this->db;
                    $q  = $db->getQuery(true)
                        ->select($db->quoteName(['extension_id', 'params']))
                        ->from($db->quoteName('#__extensions'))
                        ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                        ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                        ->where($db->quoteName('element') . ' = ' . $db->quote(self::LEGACY_ELEMENT));
                    return $db->setQuery($q)->loadObject() ?: null;
                }

                /** @param array<string,mixed> $legacyParams */
                private function mergeIntoSettings(array $legacyParams): void
                {
                    $db  = $this->db;
                    $row = $db->setQuery(
                        $db->getQuery(true)
                            ->select($db->quoteName(['id', 'settings_json']))
                            ->from($db->quoteName('#__aiboost_settings'))
                            ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'))
                    )->loadObject();

                    if (!$row) {
                        return;
                    }

                    $settings = json_decode((string) $row->settings_json, true);
                    $settings = is_array($settings) ? $settings : [];

                    $changed = false;
                    foreach (self::MIGRATED_KEYS as $key) {
                        if (array_key_exists($key, $legacyParams) && !array_key_exists($key, $settings)) {
                            $settings[$key] = (string) $legacyParams[$key];
                            $changed = true;
                        }
                    }

                    if (!$changed) {
                        return;
                    }

                    $db->setQuery(
                        $db->getQuery(true)
                            ->update($db->quoteName('#__aiboost_settings'))
                            ->set($db->quoteName('settings_json') . ' = ' . $db->quote(
                                json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                            ))
                            ->where($db->quoteName('id') . ' = ' . (int) $row->id)
                    )->execute();
                }

                private function disableLegacyPlugin(int $extensionId): void
                {
                    if ($extensionId <= 0) {
                        return;
                    }
                    $db = $this->db;
                    $db->setQuery(
                        $db->getQuery(true)
                            ->update($db->quoteName('#__extensions'))
                            ->set($db->quoteName('enabled') . ' = 0')
                            ->where($db->quoteName('extension_id') . ' = ' . $extensionId)
                    )->execute();
                }
            }
        );
    }
};
