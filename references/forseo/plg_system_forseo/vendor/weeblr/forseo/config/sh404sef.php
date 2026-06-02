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
		'importWizardRunMode',
		'canImportMetaFromSh404sef',
		'canImportAliasesFromSh404sef'
	],
	'config'        => [
		// Config Wizard
		'canImportMetaFromSh404sef'    => -1,
		'canImportAliasesFromSh404sef' => -1,
		'importWizardCompleted'        => false,
		'importWizardRunMode'          => 'auto',

	],
	'enforcedTypes' => [
		'canImportMetaFromSh404sef'    => System\Convert::INT,
		'canImportAliasesFromSh404sef' => System\Convert::INT
	]
];
