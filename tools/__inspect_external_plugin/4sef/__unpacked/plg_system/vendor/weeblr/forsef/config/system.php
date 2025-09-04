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

return [
	'doNotStore'    => [
		'systemAlert'
	],
	'config'        => [
		// Config Wizard
		'configWizardCompleted'     => true,

		// Logging
		'loggingPreset'             => System\Log::LOGGING_PRODUCTION,

		// Install/uninstall
		'uninstallRemoveAllData'    => false,

		// Maintenance
		'tasks.period.purgeErrors'  => 'PT6H', // run purge errors once per 6 hours
		'purgeErrorsAfter'          => 'P1M',

		// UI
		'canAutoClearNotifications' => false,
		'defaultItemsPerPage'       => 10,  // default number of items per page on lists

		// Update key
		'dlid'                      => '',

		// Alert banner displayed throughout
		'systemAlert'               => []

	],
	'enforcedTypes' => [
		'defaultItemsPerPage' => System\Convert::INT,
		'dlid'                => System\Convert::STRING
	]
];