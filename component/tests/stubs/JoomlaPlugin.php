<?php
/**
 * Minimal CMSPlugin stub so the SDK's AbstractIntegrationPlugin and
 * concrete bridge classes (Falang) can be loaded and reflected over in
 * PHPUnit without a live Joomla CMS installation.
 */

declare(strict_types=1);

namespace Joomla\CMS\Plugin {
    if (!class_exists(CMSPlugin::class, false)) {
        class CMSPlugin
        {
            protected $params;

            public function __construct($subject = null, array $config = [])
            {
                $this->params = $config['params'] ?? null;
            }
        }
    }
}

namespace Joomla\Event {
    if (!interface_exists(DispatcherInterface::class, false)) {
        interface DispatcherInterface
        {
        }
    }
    if (!interface_exists(SubscriberInterface::class, false)) {
        interface SubscriberInterface
        {
            public static function getSubscribedEvents(): array;
        }
    }
}
