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

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

use Weeblr\Wblib\Forseo\System;

return [
	'doNotStore'    => [
		'softCronTriggerAfter',
		'softCronRemoveAfter',
		'tasks.period.purgeReferrers',
		'tasks.period.purgeErrors',
		'tasks.period.pingSearchEngines',
		'systemAlert'
	],
	'config'        => [
		// Config Wizard
		'configWizardCompleted'          => false,

		// Logging
		'loggingPreset'                  => System\Log::LOGGING_PRODUCTION,

		// Cron management
		'clientCron'                     => true,
		'cronPerPages'                   => 1, // number of pages on average between 2 insertion of cron pixel in site pages
		'softCronTriggerAfter'           => 3000, // ms before injecting cron pixel into site pages
		'softCronRemoveAfter'            => 3000, // ms before removing cron pixel from page after inserting it
		'cronKey'                        => '', // a secret passed in a header to identify legit crons

		// Install/uninstall
		'uninstallRemoveAllData'         => false,

		// Maintenance
		'tasks.period.purgeReferrers'    => 'PT6H', // run purge referrers once per 6 hours
		'tasks.period.purgeErrors'       => 'PT6H', // run purge errors once per 6 hours
		'tasks.period.pingSearchEngines' => 'PT2M', // submit sitemap to search engines at most each 2 minutes
		'purgeErrorsAfter'               => 'P1M',

		// UI
		'canAutoClearNotifications'      => false,
		'defaultItemsPerPage'            => 10,  // default number of items per page on lists

		// Update key
		'dlid'                           => '',

		// Alert banner displayed throughout
		'systemAlert'                    => [
			'text' => ''
		],

		// Dev only
		'selectedContent'                => []
	],
	'enforcedTypes' => [
		'cronPerPages'         => System\Convert::INT,
		'softCronTriggerAfter' => System\Convert::INT,
		'softCronRemoveAfter'  => System\Convert::INT,
		'cronKey'              => System\Convert::STRING,
		'defaultItemsPerPage'  => System\Convert::INT,
		'dlid'                 => System\Convert::STRING,
		'selectedContent'      => System\Convert::ARRAY
	]
];