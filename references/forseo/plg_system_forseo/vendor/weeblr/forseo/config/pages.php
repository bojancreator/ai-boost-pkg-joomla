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
use Weeblr\Wblib\Forseo\System;
use Weeblr\Forseo\Model;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

return [
	'doNotStore'    => [
		'collectionDomainsBuiltin',
		'crawlerPluginsToDisable',
		'crawlerPluginsToConfigure',
		'crawlerUseHeadOnExternal',
		'pluginsToConfigure',
		'perfMeasurementGcDays',
		'perfThresholds',
		'loggedMediaExtensions',
		'crawlerPhysicalFilesExtensions',
		'lowPriorityExtensions',
		'collectSkipTargetBlank',
		'collectSkipHreflang',
	],
	'config'        => [
		// global
		'collectionEnabled'              => true,
		'collectIncomingUrls'            => false,
		'collectIncoming404s'            => false,
		'loggedMediaExtensions'          => [
			'.png',
			'.jpg',
			'.jpeg',
			'.gif',
			'.bmp',
			'.webp',
			'.avif',
			'.svg',
			'.mp4'
		],
		'collectApplyRobotsTxt'          => true,
		'collectApplyNoIndex'            => true,
		'collectApplyNoFollow'           => true,
		'collectSkipTargetBlank'         => false,
		'collectSkipHreflang'            => false,
		'collectSkipNoFollow'            => true,
		'collectionExclusions'           => [],
		'collectionInclusions'           => [],
		'collectionDomainsBuiltin'       => [
			'facebook.com',
			'twitter.com',
			'x.com',
			'instagram.com',
			'whatsapp.com',
			'youtube.com',
			'linkedin.com',
			'pinterest.com',
			'telegram.me',
			'example.com'
		],
		'collectionDomains'              => [],
		'canonicalRootUrl'               => '',
		'enforceCanonicalRootUrl'        => false,
		'enforceLowerCaseUrls'           => false,
		'siteIsPublic'                   => false,

		// Crawler
		'crawlerStatus'                  => Model\Crawler::STATE_RUNNING,
		'crawlerEnableCertsCheck'        => true,
		'crawlerBypassExternalCache'     => true,
		'basicAuthId'                    => '',
		'basicAuthPassword'              => '',
		'crawlerImagesExclusions'        => [
			'/media/mod_languages/{*}'
		],
		'crawlerPluginsToDisable'        => [
			'system' => [
				'jotCache'
			]
		],
		'crawlerPluginsToConfigure'      => [
			'plgSystemJCH_Optimize' => [
				'lazyload_enable' => 0
			]
		],
		'crawlerUseHeadOnExternal'       => true,
		/**
		 * Joomla SEF plugin will inject a self-ref canonical on all pages when anything
		 * is entered as "Domain site". This causes all pages, with any query var, to be
		 * advertized as canonical, which is pretty bad.
		 *
		 * It also breaks 4SEO attempts at finding the proper canonical, especially when we use a token
		 * to bypass caching (default behavior).
		 *
		 * So this is an attempt at preventing Joomla to inject that incorrect canonical.
		 *
		 * We use a simplified version of the code that disable plugins from wbLib. Insted of disabling
		 * a plugin entirely, we change the SEF plugin parameters to clear the "domain". Slightly different
		 * approach on J3 and J4+ as the dispatcher API is different.
		 *
		 * NB: initially, this hack was only active on crawler's requests but it's now always on,
		 * there's no situation where this is a valid thing to do.
		 *
		 */
		'pluginsToConfigure'             => [
			'PlgSystemSef'                           => [
				'domain' => ''
			],
			'Joomla\Plugin\System\Sef\Extension\Sef' => [
				'domain' => ''
			]
		],
		'crawlerPhysicalFilesExtensions' => ['.pdf', '.txt', '.xml', '.csv', '.doc', '.docx', '.ppt', '.pptx', '.xls', '.xlsx', '.zip'],
		'lowPriorityExtensions'          => [],

		// canonical
		'insertAutoCanonical'            => true,
		'insertAutoSelfCanonical'        => false,
		'useCanonicalFallbackStrategy'   => false,

		// meta data
		'metaAutoDescIfMissing'          => true,
		'metaAutoDescRecommendedLength'  => 160,

		// Perf metrics
		'perfMeasurementEnabled'         => true,
		'perfDataExclusions'             => [],
		'perfDataInclusions'             => [],
		'perfProbePerPages'              => 10, // number of pages on average between 2 insertion of performance probe
		'perfProbeTimeBetweenDataPoints' => 2, // 2 minutes
		'perfMeasurementGcDays'          => 28, // 28 days worth of raw data, based on Google def, not user-settable
		'perfThresholds'                 => [
			'minValues' => 1,
			'lcp'       => [
				'good'    => 2500000,
				'improve' => 4000000
			],
			'fid'       => [
				'good'    => 100000,
				'improve' => 300000
			],
			'inp'       => [
				'good'    => 200000,
				'improve' => 500000
			],
			'cls'       => [
				'good'    => 100000,
				'improve' => 250000
			]
		]

	],
	'enforcedTypes' => [
		'basicAuthId'                   => System\Convert::STRING,
		'basicAuthPassword'             => System\Convert::STRING,
		'metaAutoDescRecommendedLength' => System\Convert::INT
	]
];
