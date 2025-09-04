<?php
/**
 * Project: 4SEF
 *
 * @author           Yannick Gaultier - Weeblr llc
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @package          4SEF
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          2.6.2.644
 * @date        2025-06-02
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

if (!defined('FORSEF_APP_PATH'))
{
	return;
}

// init library
$wbLibRootFile = WBLIB_Forsef_ROOT_PATH . '/wblib.php';
if (!file_exists($wbLibRootFile))
{
	return;
}

include_once($wbLibRootFile);
$wbLib = new \Weeblr\Wblib\Forsef\Wblib;
$wbLib->boot();
