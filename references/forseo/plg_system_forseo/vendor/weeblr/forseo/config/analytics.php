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

use Weeblr\Forseo\Data;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

$config = [
	'doNotStore' => [
		'providers',
		'enableConsentCheck'
	],
	'config'     => [
		// global
		'enabled'                            => true,

		// snippets locations
		'actionAnalyticsUniversalgaLocation' => Data\Rule::RAW_CONTENT_LOCATION_HEAD_TOP,
		'actionAnalyticsGlobalgaLocation'    => Data\Rule::RAW_CONTENT_LOCATION_HEAD_TOP,
		'actionAnalyticsGtmLocation'         => [
			Data\Rule::RAW_CONTENT_LOCATION_HEAD_TOP,
			Data\Rule::RAW_CONTENT_LOCATION_BODY_TOP
		],
		'actionAnalyticsFbpixelLocation'     => Data\Rule::RAW_CONTENT_LOCATION_HEAD_BOTTOM,
		'actionAnalyticsMatomoLocation'      => Data\Rule::RAW_CONTENT_LOCATION_HEAD_BOTTOM,
		'actionAnalyticsClarityLocation'     => Data\Rule::RAW_CONTENT_LOCATION_HEAD_BOTTOM,
		'actionAnalyticsCloudflareLocation'  => Data\Rule::RAW_CONTENT_LOCATION_BODY_BOTTOM,
		'actionAnalyticsFathomLocation'      => Data\Rule::RAW_CONTENT_LOCATION_HEAD_BOTTOM,

		// Analytics providers
		'providers'                          => [
			'universalga',
			'globalga',
			'gtm',
			'matomo',
			'clarity',
			'cloudflare',
			// 'fathom', removed for now
			'fbpixel'
		],
		'providersRequiringConsent'          => [
			'universalga',
			'globalga',
			'gtm',
			'matomo',
			'clarity',
			'fbpixel'
		],
		'enableConsentCheck'                 => false
	]
];

if (time() > 1719748800)
{
	// UGA stops operating July 1st, 2024
	unset($config['config']['providers']['universalga']);
	unset($config['config']['providersRequiringConsent']['universalga']);
}

return $config;
