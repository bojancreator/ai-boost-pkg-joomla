<?php
/**
 * @package     AiBoost\Component\AiBoost
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

require_once JPATH_ADMINISTRATOR . '/components/com_aiboost/lib/autoload.php';

// Task #471 — explicit Joomla CMS adapter wiring. Idempotent; the plugin
// extensions also call this on onAfterInitialise as a belt-and-braces
// fallback for code paths that bypass the component (e.g. frontend hooks).
\AiBoost\Lib\Cms\AdapterBootstrap::registerJoomla();

use AiBoost\Component\AiBoost\Administrator\Extension\AiBoostComponent;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new MVCFactory('\\AiBoost\\Component\\AiBoost'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\AiBoost\\Component\\AiBoost'));

        $container->set(
            ComponentInterface::class,
            static function (Container $container): ComponentInterface {
                $component = new AiBoostComponent(
                    $container->get(ComponentDispatcherFactoryInterface::class)
                );
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));

                return $component;
            }
        );
    }
};
