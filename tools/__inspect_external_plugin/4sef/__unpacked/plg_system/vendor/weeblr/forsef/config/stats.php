<?php
/**
 * Project: 4SEF
 *
 * @package          4SEF
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          2.6.2.644
 * @date        2025-06-02
 */

use Weeblr\Wblib\Forsef\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

return [
	'doNotStore'    => [
		'retentionDays',
		'displayDays'
	],
	'config'        => [
		'enabledPerUrl' => true,
		'enabledPerDay' => true,

		// hardcoded
		'retentionDays' => 30,
		'displayDays'   => 10
	],
	'enforcedTypes' => []
];

