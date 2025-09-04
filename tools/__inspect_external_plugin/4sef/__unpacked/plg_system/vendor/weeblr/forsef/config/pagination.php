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

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

use Weeblr\Wblib\Forsef\System;

// The extensions configuration object is entirely dynamic, contrary to others.
// Therefore the 4SEF factory builds a Model\Extensionsconfig object instead of
// a Model\Config object when the extensions configs is requested.
// That model creates the required dynamic properties.


return [
	'doNotStore'    => [],
	'config'        => [
		'slugsPatterns'            => [],
		'spacer'                   => '-',
		'alwaysAppendItemsPerPage' => false
	],
	'enforcedTypes' => [
		'spacer'                   => System\Convert::STRING,
		'alwaysAppendItemsPerPage' => System\Convert::BOOLEAN
	]
];
