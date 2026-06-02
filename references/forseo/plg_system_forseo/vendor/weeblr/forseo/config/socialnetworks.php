<?php
/**
 * Project: 4SEO
 *
 * @package          4SEO
 * @copyright        Copyright Weeblr llc - 2020 - 2026
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          6.10.1.2660
 * @date        2026-01-30
 */

use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Html;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

return [
	'doNotStore'    => [
		'imageSharingSpec',
		'imageDetectionMethod',
		'dummyFbAppId'
	],
	'config'        => [
		'defaultImage'         => [],
		'imageDetectionMethod' => Html\Image::IMAGE_SEARCH_LARGEST,
		'ogpEnabled'           => true,
		'tCardsEnabled'        => true,
		'tCardsType'           => 'summary',
		'tCardsSiteAccount'    => '',
		'tCardsCreatorAccount' => '',
		'dummyFbAppId'         => '966242223397117',
		'imageSharingSpec'     => [
			'width'  => 600,
			'height' => 0,
			'pixels' => 0
		],
	],
	'enforcedTypes' => [
		'defaultImage'         => System\Convert::ARRAY,
		'tCardsSiteAccount'    => System\Convert::STRING,
		'tCardsCreatorAccount' => System\Convert::STRING,
		'dummyFbAppId'         => System\Convert::STRING,
		'imageSharingSpec'     => System\Convert::ARRAY
	]
];
