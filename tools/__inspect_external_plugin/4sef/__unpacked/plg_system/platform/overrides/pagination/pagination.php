<?php
/**
 * Project: 4SEF
 *
 * @package                 4SEF
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 2.6.2.644
 *
 * @date        2025-06-02
 */

use Weeblr\Wblib\Forsef\Factory;

defined('JPATH_PLATFORM') or die;

$platformMajorVersion = Factory::get()->getThe('platform')->majorVersion();
$fileName             = __DIR__ . '/j' . $platformMajorVersion . '/Pagination.php';

switch ($platformMajorVersion)
{
	case 3:
		/**
		 * Do NOT prepend a slash to the JPagination class name, JLoader does not handle that.
		 */
		\JLoader::register('JPagination', __DIR__ . '/j3/Pagination.php');
		break;
	default:
		$platformVersion = Factory::get()->getThe('platform')->version();
		$paginationFile  = (4 === $platformMajorVersion && version_compare($platformVersion, '4.4.7', 'ge'))
						   ||
						   (5 === $platformMajorVersion && version_compare($platformVersion, '5.1.3', 'ge'))
			? '/default/Pagination.php'
			: '/j4-512/Pagination.php';

		/**
		 * J4 does not allow to register a class, as such services are provided through the DI container.
		 * Traditional overrides through the onAfterExtensionBoot event, where the override class is injected
		 * into the container would only work for those extensions using a container so the only efficient
		 * method here seems to be to just include the file.
		 */
		include_once __DIR__ . $paginationFile;
		break;
}
