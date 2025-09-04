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

$appConfig = [
	'persist' => false,
	'config'  => [

		// Common tracking query vars to be filtered out when collecting urls
		'commonTrackingVars'    => [
			// Analytics
			'utm_source',
			'utm_medium',
			'utm_term',
			'utm_content',
			'utm_id',
			'utm_campaign',
			// Facebook
			'gclid',
			'fbclid',
			'fb_xd_bust',
			'fb_xd_fragment',
			'fb_comment_id',
			'fb_action_ids',
			'fb_action_types',
			'fb_source',
			// Joomla
			'hitcount',
			//4SEO,
			'x-wblr-crawler-cdn-bust',
			'x-wblr-cdn-bpass-id',
			// Others
			'XDEBUG_SESSION_START'
		],

		// Common garbage query vars to be ignored when looking up custom meta data
		// NB: 'commonTrackingVars are also removed when looking up custom meta
		'queryVarsToStrip'      => [
			'cachebuster'
		],

		'updateManifestUrl' => 'https://u1.weeblr.com/public/direct/forsef/update/pkg_forsef_full.json',

		// Optional features
		'features'          => [
		],

	]
];

return $appConfig;