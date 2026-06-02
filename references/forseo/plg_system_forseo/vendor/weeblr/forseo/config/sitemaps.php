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

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

return [
	'doNotStore' => [
		'trackSearchEnginesVisits',
		'validateSearchEnginesIp',

		// files naming
		'mainFile',
		'fileNamePattern',
		'fileNamePatternRegExp',
		'usePreCompressedPartials',

		// Sitemap building options
		'maxUrlsPerFile',
		'maxPartials',

		// Sitemap submission
		'searchEnginesPing',
		'searchEnginesStatsEnabled',
	],

	'config' => [
		'enabled'                   => true,
		'addToRobotsTxt'            => false,
		'trackSearchEnginesVisits'  => true,
		'validateSearchEnginesIp'   => true,
		'includeImages'             => true,
		'collectImagesOnCategories' => false,
		'imageInclusionMinWidth'    => 200,
		'imageInclusionMinHeight'   => 0,

		// Main sitemap access
		'mainFile'                  => 'sitemap-4seo.xml',
		'fileNamePattern'           => 'sitemap.{{lang}}.{{crawl_id}}.{{serial}}{{type}}.xml',
		'fileNamePatternRegExp'     => '~^sitemap\.[a-z]{2,3}\-[a-z]{2,3}\.[a-z0-9\-]+\.[0-9]+({{types}})?\.xml(\.gz)?$~i',
		'usePreCompressedPartials'  => false,

		// Sitemap building options
		'maxUrlsPerFile'            => 1000,
		'maxPartials'               => 500,
		'refreshSitemapDelay'       => 'PT30M', // delay before rebuilding sitemap after a change has been detected

		// Sitemap submission
		'searchEnginesPingEnabled'  => [],
		'searchEnginesPing'         => [
			'google' => [
				'name'     => 'Google',
				'endpoint' => 'http://www.google.com/ping?sitemap=%s'
			],
			// Bing decommissionned sitemaps submission around mid-december 2021
			// Restored with updated URL feb 2022
			// And finally stopped it May 13
			//			'bing'   => [
			//				'name'     => 'Bing',
			//				'endpoint' => 'https://www.bing.com/webmaster/ping.aspx?sitemap=%s'
			//			],
		],

		'searchEnginesStatsEnabled' => [
			'google',
			'bing'
		],

		// Sitemap inclusions
		'excludeArchived'           => true,
		'exclusions'                => [],
		'inclusions'                => [],
		'imagesExclusions'          => [],
		'imagesInclusions'          => [],
		'imagesDomainsExclusions'   => []
	],

	'enforcedTypes' => [
		'maxUrlsPerFile'          => System\Convert::INT,
		'maxPartials'             => System\Convert::INT,
		'exclusions'              => System\Convert::ARRAY,
		'inclusions'              => System\Convert::ARRAY,
		'imagesExclusions'        => System\Convert::ARRAY,
		'imagesInclusions'        => System\Convert::ARRAY,
		'imagesDomainsExclusions' => System\Convert::ARRAY
	]
];
