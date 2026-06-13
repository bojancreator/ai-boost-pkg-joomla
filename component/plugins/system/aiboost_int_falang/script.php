<?php
/**
 * AI Boost — Multilang / Falang Integration installer script.
 *
 * Joomla installs standalone plugins DISABLED. This bridge ships as a separate
 * ZIP (it bridges multilingual content), so the package's enablePlugins() sweep
 * never sees it. Self-enable on install so it works out-of-the-box — UNLESS the
 * admin has already made a choice (the `integration_falang_enabled` setting
 * exists, including a deliberate '0'). All best-effort: a DB error never blocks
 * the install.
 *
 * @package     AiBoost\Plugin\System\AiBoostIntFalang
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
                private const ELEMENT     = 'aiboost_int_falang';
                private const SETTING_KEY = 'integration_falang_enabled';

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
                    $this->selfEnable();
                    return true;
                }

                private function selfEnable(): void
                {
                    try {
                        $db  = $this->db;
                        $row = $db->setQuery(
                            $db->getQuery(true)
                                ->select($db->quoteName(['id', 'settings_json']))
                                ->from($db->quoteName('#__aiboost_settings'))
                                ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'))
                        )->loadObject();
                        $settings = [];
                        if ($row) {
                            $decoded  = json_decode((string) $row->settings_json, true);
                            $settings = is_array($decoded) ? $decoded : [];
                        }
                        if (array_key_exists(self::SETTING_KEY, $settings)) {
                            return; // respect the existing choice (incl. deliberate disable)
                        }
                        $db->setQuery(
                            $db->getQuery(true)
                                ->update($db->quoteName('#__extensions'))
                                ->set($db->quoteName('enabled') . ' = 1')
                                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                                ->where($db->quoteName('element') . ' = ' . $db->quote(self::ELEMENT))
                        )->execute();
                        if ($row) {
                            $settings[self::SETTING_KEY] = '1';
                            $db->setQuery(
                                $db->getQuery(true)
                                    ->update($db->quoteName('#__aiboost_settings'))
                                    ->set($db->quoteName('settings_json') . ' = ' . $db->quote(
                                        json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                                    ))
                                    ->where($db->quoteName('id') . ' = ' . (int) $row->id)
                            )->execute();
                        }
                    } catch (\Throwable $e) {
                        // Best-effort: never block the install.
                    }
                }
            }
        );
    }
};
