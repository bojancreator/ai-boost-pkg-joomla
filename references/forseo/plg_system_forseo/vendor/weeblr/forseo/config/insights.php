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

use Weeblr\Wblib\Forseo\Factory;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

return [
	'doNotStore'    => [
		'gscThresholds'
	],
	'config'        => [
		'gscKwExclusions'           => [],
		'gscPagesExclusions'        => [],
		'gscInsightsMinClicks'      => 5,
		'gscInsightsMinImpressions' => 5,
		'gscInsightsMaxKwLength'    => 30,
		'gscOppMultipleKwPerPage'   => 5,
		'gscThresholds'             => [
			'lowPosition'    => 15,
			'highPosition'   => 6,
			'lowPercentile'  => 0.80,
			'highPercentile' => 0.35
		]
	],
	'enforcedTypes' => [
		'gscKwExclusions'           => System\Convert::ARRAY,
		'gscPagesExclusions'        => System\Convert::ARRAY,
		'gscInsightsMinClicks'      => System\Convert::INT,
		'gscInsightsMinImpressions' => System\Convert::INT,
		'gscInsightsMaxKwLength'    => System\Convert::INT,
	]
];
