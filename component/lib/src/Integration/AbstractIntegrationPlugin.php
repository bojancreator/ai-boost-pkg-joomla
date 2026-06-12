<?php
/**
 * AI Boost — AbstractIntegrationPlugin
 *
 * Base class every `plg_system_aiboost_int_<key>` bridge extends. It
 * handles the boilerplate that used to be copy-pasted into every bridge:
 *
 *   - Boot the shared lib autoloader from com_aiboost on onAfterInitialise.
 *   - Run BridgeDetector against the descriptor's host extension.
 *   - Respond to the `onAiBoostRegisterIntegration` discovery event with
 *     the descriptor returned by the subclass.
 *   - Cache the "detected + plugin enabled" verdict per request.
 *
 * Subclass contract:
 *
 *   final class AiBoostIntFoo extends AbstractIntegrationPlugin
 *   {
 *       protected function describe(): IntegrationDescriptor { ... }
 *
 *       public function onAiBoostRegisterFields(): array { ... }
 *       public function onAiBoostFilterHeadOutput(array $in, FilterResult $r): void { ... }
 *   }
 *
 * The subclass remains a regular Joomla CMSPlugin, so it keeps access to
 * $this->params, $this->_subject, etc.
 *
 * @package     AiBoost\Lib\Integration
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Integration;

defined('_JEXEC') or die;

use AiBoost\Lib\BridgeDetector;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

abstract class AbstractIntegrationPlugin extends CMSPlugin
{
    protected $autoloadLanguage = true;

    private bool $booted   = false;
    private bool $detected = false;
    private ?IntegrationDescriptor $descriptor = null;

    /** Cached #__aiboost_settings 'main' blob — null until first read. */
    private ?array $aiBoostSettings = null;

    /** Subclass returns the descriptor that identifies this bridge. */
    abstract protected function describe(): IntegrationDescriptor;

    // ── Lifecycle ───────────────────────────────────────────────────────

    public function onAfterInitialise(): void
    {
        $this->bootIntegration();
    }

    /**
     * Discovery event — return the descriptor (array-shorthand or instance).
     * Core's IntegrationRegistry collects these to render the dashboard.
     *
     * @return IntegrationDescriptor
     */
    public function onAiBoostRegisterIntegration(): IntegrationDescriptor
    {
        $this->bootIntegration();
        return $this->descriptor();
    }

    // ── Public accessors used by subclasses ────────────────────────────

    final protected function descriptor(): IntegrationDescriptor
    {
        if ($this->descriptor === null) {
            $this->descriptor = $this->describe();
        }
        return $this->descriptor;
    }

    /**
     * True when the bridge's host extension is installed + enabled and the
     * bridge plugin itself is loaded. Subclasses MUST early-return when this
     * is false so a stock AI Boost install with the bridge ZIP but no host
     * extension behaves as if the bridge weren't there.
     */
    final protected function isDetected(): bool
    {
        $this->bootIntegration();
        return $this->detected;
    }

    /**
     * Whether the site admin has this integration switched ON from the
     * AI Boost Integrations page. The master toggle key is
     * `integration_<key>_enabled`; it fails OPEN to '1' so a brand-new
     * install (key never saved) and any bridge without a static master key
     * behave as enabled.
     *
     * This gates RUNTIME EMISSION only — never field registration or
     * discovery, both of which must keep working while the toggle is off so
     * that a plain Settings save does not drop the user's integration keys.
     */
    final protected function isAdminEnabled(): bool
    {
        $key = 'integration_' . $this->descriptor()->key . '_enabled';
        return (string) $this->readAiBoostSetting($key, '1') !== '0';
    }

    /**
     * The single runtime gate every bridge output handler must check:
     * the host extension is present AND the admin has not switched the
     * bridge off. Equivalent to isDetected() && isAdminEnabled().
     */
    final protected function isActive(): bool
    {
        return $this->isDetected() && $this->isAdminEnabled();
    }

    /**
     * Read a single key from the #__aiboost_settings 'main' blob. The blob is
     * loaded once per request and cached; missing keys return $default.
     */
    final protected function readAiBoostSetting(string $key, mixed $default = null): mixed
    {
        if ($this->aiBoostSettings === null) {
            $this->aiBoostSettings = $this->loadAiBoostSettings();
        }
        return $this->aiBoostSettings[$key] ?? $default;
    }

    // ── Internals ───────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    private function loadAiBoostSettings(): array
    {
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from($db->quoteName('#__aiboost_settings'))
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
            $json = $db->setQuery($query)->loadResult();
            $data = $json ? json_decode((string) $json, true) : [];

            return is_array($data) ? $data : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function bootIntegration(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        // Guard against CLI / partial bootstraps where JPATH_ADMINISTRATOR
        // is not defined; without the core lib autoloader the rest of the
        // boot would fatal on the BridgeDetector reference below.
        if (defined('JPATH_ADMINISTRATOR')) {
            $loader = JPATH_ADMINISTRATOR . '/components/com_aiboost/lib/autoload.php';
            if (file_exists($loader)) {
                require_once $loader;
            }
        }

        // Partial-lib guard: a missing lib class file (interrupted or partial
        // base-package uninstall) must never fatal plugin boot. class_exists()
        // normally returns false for an unloadable class, but under JDEBUG
        // Joomla's debug class loader THROWS instead — and the descriptor may
        // reference further lib classes (e.g. ConflictManager constants).
        try {
            if (!class_exists(BridgeDetector::class)) {
                return;
            }

            $d = $this->descriptor();
            $this->detected = match ($d->hostType) {
                'component' => BridgeDetector::isExtensionEnabled($d->hostElement, 'component', ''),
                'template'  => BridgeDetector::isExtensionEnabled($d->hostElement, 'template',  ''),
                default     => BridgeDetector::isExtensionEnabled(
                    $d->hostElement,
                    'plugin',
                    $d->hostFolder !== '' ? $d->hostFolder : 'system'
                ),
            };
        } catch (\Throwable $e) {
            $this->detected = false;
        }
    }
}
