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
use Joomla\CMS\Plugin\CMSPlugin;

abstract class AbstractIntegrationPlugin extends CMSPlugin
{
    protected $autoloadLanguage = true;

    private bool $booted   = false;
    private bool $detected = false;
    private ?IntegrationDescriptor $descriptor = null;

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

    // ── Internals ───────────────────────────────────────────────────────

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
