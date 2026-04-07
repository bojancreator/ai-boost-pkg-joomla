<?php
define('_JEXEC', 1);
define('JPATH_BASE', __DIR__);
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

$app = JFactory::getApplication('site');
\Joomla\CMS\Plugin\PluginHelper::importPlugin('system', 'joomlaboost');

require_once JPATH_BASE . '/plugins/system/joomlaboost/src/Services/AbstractService.php';
require_once JPATH_BASE . '/plugins/system/joomlaboost/src/Services/LanguageService.php';

use JoomlaBoost\Plugin\System\JoomlaBoost\Services\LanguageService;

$registry = new \Joomla\Registry\Registry();
$langSvc = new LanguageService($app, $registry);

echo "Falang Active: " . ($langSvc->isFalangActive() ? 'YES' : 'NO') . "\n";
$langs = $langSvc->getActiveLanguages();
echo "Active Languages Count: " . count($langs) . "\n";
print_r($langs);
