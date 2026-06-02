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

// no direct access
defined('_JEXEC') || die();

defined('WBLIB_EXEC') or define('WBLIB_EXEC', true);

if (file_exists(__DIR__ . '/dev_defines.php'))
{
	include_once(__DIR__ . '/dev_defines.php');
}
else if (file_exists(__DIR__ . '/defines.php'))
{
	include_once(__DIR__ . '/defines.php');
}

if (!defined('FORSEO_APP_PATH'))
{
	return;
}

// init library
$wbLibRootFile = WBLIB_Forseo_ROOT_PATH . '/wblib.php';
if (!file_exists($wbLibRootFile))
{
	return;
}

include_once($wbLibRootFile);
$wbLib = new \Weeblr\Wblib\Forseo\Wblib;
$wbLib->boot();
