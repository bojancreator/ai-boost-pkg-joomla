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
		'canImportFromSh404sef',
		'canImportCustomFromSh404sef',
		'processedSh404sefImport',
		'erroredSh404sefImport',
		'shurlsDbTable'
	],
	'config'        => [
		// Config Wizard
		'canImportFromSh404sef'       => -1,
		'canImportCustomFromSh404sef' => 0,
		'processedSh404sefImport'     => 0,
		'erroredSh404sefImport'       => 0,
		'importWizardCompleted'       => false,
		'importWizardRunMode'         => 'auto',
		'executeShurls'               => false,
		'shurlsDbTable'               => '#__forsef_legacy_pageids',

	],
	'enforcedTypes' => [
		'canImportFromSh404sef'       => System\Convert::INT,
		'canImportCustomFromSh404sef' => System\Convert::INT,
		'processedSh404sefImport'     => System\Convert::INT,
		'erroredSh404sefImport'       => System\Convert::INT
	]
];