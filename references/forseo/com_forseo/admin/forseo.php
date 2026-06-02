<?php
/**
 * Project: 4SEO
 *
 * @author           Yannick Gaultier - Weeblr llc
 * @copyright        Copyright Weeblr llc - 2020 - 2026
 * @package          4SEO
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          6.10.1.2660
 * @date        2026-01-30
 */

use \Weeblr\Wblib\Forseo\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router;

// no direct access
defined('_JEXEC') || die();

$title    = Text::_('COM_FORSEO_FAILURE_TITLE');
$subTitle = Text::_('COM_FORSEO_FAILURE_SUBTITLE');
$msg1     = Text::sprintf('COM_FORSEO_FAILURE_PLUGIN', Router\Route::_('index.php?option=com_plugins&view=plugins'));
$msg2     = Text::sprintf('COM_FORSEO_FAILURE_HELPDESK', 'https://weeblr.com/helpdesk');

$errorMsg = <<<HTML
    <h2 class="mt-5 ml-5 mb-3 font-weight-bold"
        style="font-size: 1.2rem;">{$title}</h2>
    <h4 class="mb-5 ml-5">{$subTitle}</h4>
    <img class="m-5 mw-100" src="https://cdn.weeblr.net/dist/weeblr/img/4seo/undraw_bug_fixing_oc7a.svg"
         width="600" alt="">
    <p class="ml-5"
       style="max-width: 50rem;">{$msg1}</p>
    <p class="mb-5 ml-5"
       style="max-width: 50rem;">{$msg2}</p>
HTML;

if (
	!defined('WBLIB_EXEC')
	||
	!defined('FORSEO_APP_PATH')
) {
	// plugin disabled or something else went wrong
	// exit gracefully
	echo $errorMsg;
	return;
}

if (!Factory::get()->getThe('platform')->authorize('core.manage', 'com_forseo'))
{
	throw new \Exception(Text::_('JGLOBAL_AUTH_ACCESS_DENIED'));
};

try
{
	Factory::get()
		   ->getThis(
			   'app',
			   'forseo'
		   )->renderAdmin(
			[]
		);
}
catch (\Throwable $e)
{
	echo $errorMsg
		 . '<p class="mt-5 mw-100">Error details:</p>'
		 . "<pre class=\"mw-100\"><code>{$e->getMessage()}</code></pre>";
}

