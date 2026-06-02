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

$isSecureSite = Wb\startsWith(
	Factory::get()->getThis('forseo.config', 'pages')->get('canonicalRootUrl'),
	'https://'
);

$poauthEndpoint = WBLIB_Forseo_OP_MODE == 'prod'
	? 'https://weeblr.com/poauth/v1'
	: 'https://dev.weeblr.net/apps/weeblrv3/poauth';

return [
	'doNotStore'    => [
		'isSecureSite',
		'oAuthProxyEndpoint'
	],
	'config'        => [
		'oAuthProxyEndpoint'          => $poauthEndpoint,
		'isSecureSite'                => $isSecureSite,
		'authServicePreselect'        => '',
		'gscEnabled'                  => false,
		'gscConfigStep'               => 'notStarted', // notStarted | selectProperty | addAndVerifyCurrent | verifyCurrent | ready
		'gscProperty'                 => [],
		'gscKwExclusions'             => [],
		'gscPagesExclusions'          => [],
		'gscInsightsMinClicks'        => 0,
		'gscInsightsMinImpressions'   => 0,
		'gscInsightsMaxKwLength'      => 40,
		'googleAnalyticsV4Enabled'    => false,
		'googleAnalyticsV4ConfigStep' => 'notStarted', // notStarted | selectProperty | addAndVerifyCurrent | verifyCurrent | ready
		'googleAnalyticsV4View'       => [],
		'googleAnalyticsUEnabled'     => false,
		'googleAnalyticsUConfigStep'  => 'notStarted', // notStarted | selectProperty | addAndVerifyCurrent | verifyCurrent | ready
		'googleAnalyticsUView'        => [],
	],
	'enforcedTypes' => [
		'authServicePreselect' => System\Convert::STRING,
		'gscEnabled'           => System\Convert::BOOLEAN,
		'gscConfigStep'        => System\Convert::STRING,
		'gscProperty'          => System\Convert::ARRAY
	]
];
