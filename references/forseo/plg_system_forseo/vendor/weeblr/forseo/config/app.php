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

use Weeblr\Forseo\Model;

use Weeblr\Wblib\Forseo\Factory;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

$appConfig = [
	'persist' => false,
	'config'  => [
		// crawler configuration
		'crawlerTimeout'                  => 'PT20S', // 20 seconds
		'crawlerMaxAttempts'              => 3,
		'crawlerMaxReferrersStored'       => 14000, // number of UTFMB4 characters in the referrers column of the #__forseo_collected_urls db table.
		'crawlerPagesPerRun'              => 1,
		'crawlerCronPagesPerRun'          => 5,
		'crawlerRequestTimeout'           => 15,
		'crawlerConcurrency'              => 3,
		'crawlerMaxRedirects'             => 5,
		'crawlerUserAgent'                => FORSEO_CRAWLER_USER_AGENT,
		'crawlerCurlConfig'               => [
		],

		// image detection
		// https://developers.google.com/search/docs/data-types/article#non-amp
		'imageDetectionRequireSizeSd'     => [
			'width'  => 200,
			'height' => 0,
			'pixels' => 0
		],
		// 200x200, recommended mini = 600x315, best 1200x315 and up, max 8MB
		'imageDetectionRequireSizeOgp'    => [
			'width'  => 200,
			'height' => 200,
			'pixels' => 0
		],

		// Extensions 4SEO should not run at all
		'ignoredExtensions'               => [
			// admin J3
			'actionlogs',
			'admin',
			'ajax',
			'associations',
			'cache',
			'checkin',
			'config',
			'contenthistory',
			'cpanel',
			'fields',
			'installer',
			'joomlaupdate',
			'languages',
			'login',
			'media',
			'menus',
			'messages',
			'modules',
			'plugins',
			'postinstall',
			'redirect',
			'templates',

			// admin J4
			'csp',
			'mails',
			'workflow',

			// Extensions
			'acym',
			'acymailing',
			'akeeba',
			'admintools',
			'forseo',
			'sef',
			'sh404sef'
		],
		'extensionsRequiringLanguageInId' => [
			'jshopping' => ['lang']
		],

		// Automatic meta description cleanup reg exp
		'descCleanupExpressions'          => [
			'~<style[^>]*>.*</style>~uUis',
			'~<script[^>]*>.*</script>~uUis',
			'~alt(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+))?~uUis',
			'~{\s*4seo_[^}]+}~uUi',
			'~{\s*jumi[^}]+}~uUi',
			'~{\s*wbamp[^}]+}~uUi',
			'~{\s*sh404sef_[^}]*}~us',
			'~{\s*module[^}]*}~iuUs',
			'~{\s*loadmodule[^}]*}~iuUs',
			'~{\s*loadposition[^}]*}~iuUs',
			'~{(field|fieldgroup)\s+(.*?)}~uUi',
			'~{\s*snippet[^}]*}~iuUs',
			'~{\s*tip[^}]*}~iuUs',
			'~{\s*rsform[^}]*}~iuUs',
			'~{\s*phocagallery[^}]*}~iuUs',
			'~{(.*?)}(.*?){/(.*?)}~us',
			'~\[(.*?)\](.*?)\[/(.*?)\]~us',
			'~\[widgetkit[^\]]+\]~us',
			'~\[quix[^\]]+\]~us',
			'~{\s*unitegallery[^}]+}~uUi',
			'~{\s*igallery[^}]+}~uUi'
		],

		// blocks
		'wafBlockedUrlsDefault'           => [
			'/wp-{*}.php{*}',
			'/wp-admin{*}',
			'/wp-content{*}',
			'/wp-includes{*}',
			'/wp-login{*}',
			'/wp-json{*}',
			'/{?}{?}/wp-{*}.php{*}',
			'/{?}{?}/wp-admin{*}',
			'/{?}{?}/wp-content{*}',
			'/{?}{?}/wp-includes{*}',
			'/{?}{?}/wp-login{*}',
			'/{?}{?}/wp-json{*}',
		],

		// do not log those failed attacks
		'errorLogBypass'                  => [
			// Wordpress files
			'/{*}/wp-{*}.php{*}',
			'{*}/wp-admin{*}',
			'{*}/wp-content{*}',
			'{*}/wp-includes{*}',
			'{*}/wp-login{*}',
			'{*}/wp-json{*}',
			'{*}/xmlrpc.php{*}',
			// archives and other data files
			'/{*}.zip{*}',
			'/{*}.tar{*}',
			'/{*}.tar.gz{*}',
			'/{*}.rar{*}',
			'/{*}.json{*}',
			'/{*}.xml{*}',
			'/{*}.asp{*}',
			'/{*}.less{*}',
			'/{*}.scss{*}',
			'/{*}.log{*}',
			'/{*}.rdf{*}',
			// Editors
			'/{*}fckeditor{*}',
			'/{*}option=com_jce{*}',
			// Various
			'{*}?dns={*}'
		],


		// Common tracking query vars to be filtered out when collecting urls
		'commonTrackingVars'              => [
			'_branch_match_id',
			'_bta_c',
			'_bta_tid',
			'_ga',
			'_ke',
			'dm_i',
			'ef_id',
			'epik',
			'fb_action_ids',
			'fb_action_types',
			'fb_comment_id',
			'fb_source',
			'fb_xd_bust',
			'fb_xd_fragment',
			'fbclid',
			'gclid',
			'gclsrc',
			'gdffi',
			'gdfms',
			'gdftrk',
			'hitcount',
			'hsa_acc',
			'hsa_ad',
			'hsa_cam',
			'hsa_grp',
			'hsa_kw',
			'hsa_mt',
			'hsa_net',
			'hsa_src',
			'hsa_tgt',
			'hsa_ver',
			'matomo_campaign',
			'matomo_cid',
			'matomo_content',
			'matomo_group',
			'matomo_keyword',
			'matomo_medium',
			'matomo_placement',
			'matomo_source',
			'mc_cid',
			'mc_eid',
			'mkwid',
			'msclkid',
			'mtm_campaign',
			'mtm_cid',
			'mtm_content',
			'mtm_group',
			'mtm_keyword',
			'mtm_medium',
			'mtm_placement',
			'mtm_source',
			'pcrid',
			'piwik_campaign',
			'piwik_keyword',
			'piwik_kwd',
			'pk_campaign',
			'pk_keyword',
			'pk_kwd',
			'redirect_log_mongo_id',
			'redirect_mongo_id',
			's_kwcid',
			'sb_referer_host',
			'trk_contact',
			'trk_module',
			'trk_msg',
			'trk_sid',
			'utm_campaign',
			'utm_content',
			'utm_id',
			'utm_medium',
			'utm_source',
			'utm_term',
			FORSEO_CRAWLER_CDN_BUST_VAR
		],

		// Common garbage query vars to be ignored when looking up custom meta data
		// NB: 'commonTrackingVars are also removed when looking up custom meta
		'queryVarsToStrip'                => [
			'cachebuster'
		],

		'defaultRobots' => 'max-snippet:-1, max-image-preview:large, max-video-preview:-1',

		'updateManifestUrl'    => 'https://u1.weeblr.com/public/direct/forseo/update/pkg_forseo_full.json',

		// API keys
		'apiKeys.googleMaps.1' => '',

		// Rules sorting method: fixing initial solution
		'rulesSortingMethod'   => 'orderingField', // orderingField | orderList

		// Optional features
		'features'             => [
			'collectIncomingUrls' => false, // deprecated, replaced with same variable in Pages config
			'perfMeasurement'     => false, // deprecated, replaced with same variable in Pages config
			'enableDebugLogger'   => false
		],

	]
];

if (defined('CURLOPT_HTTP_VERSION'))
{
	if ('dev' == WBLIB_Forseo_OP_MODE)
	{
		$appConfig['crawlerCurlConfig'] = [
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false
		];
	}
	else
	{
		$appConfig['crawlerCurlConfig'] = [
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_SSL_VERIFYPEER => 2,
			CURLOPT_SSL_VERIFYHOST => 2
		];
	}
}

return $appConfig;