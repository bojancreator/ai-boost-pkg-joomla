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

use Weeblr\Forsef\Data;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

// The extensions configuration object is entirely dynamic, contrary to others.
// Therefore the 4SEF factory builds a Model\Extensionsconfig object instead of
// a Model\Config object when the extensions configs is requested.
// That model creates the required dynamic properties.


return [
	'doNotStore' => [
		'available',
		'showallSlug',
		'ignored',
		'nonRoutable'
	],
	'config'     => [
		'showallSlug'                      => 'All pages',
		'tagsProcessMode'                  => Data\Config::PROCESS_USE_JOOMLA,
		'tagsProcessModeJoomlaSefWithMenu' => true,

		'contactUseContactAlias' => false,

		// Extensions 4SEF should ignore when displaying configuration options
		'ignored'                   => [
			// admin J3
			'actionlogs',
			'admin',
			'ajax',
			'associations',
			'cache',
			'categories',
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
			'privacy',
			'redirect',
			'templates',
			'users',

			// admin J4
			'csp',
			'mails',
			'workflow',

			// Extensions
			'acym',
			'acymailing',
			'admintools',
			'akeeba',
			'easyfrontendseo',
			'forai',
			'forsef',
			'forseo',
			'gsd',
			'jce',
			'jchoptimize',
			'jchoptimize_pro',
			'jedchecker',
			'jotcache',
			'ochlogfiles',
			'patchtester',
			'rereplacer',
			'sef',
			'sh404sef',
			'tcpdf',
			'virtuemart_allinone'
		],

		// Extensions 4SEF should ignore when displaying configuration options
		'nonRoutable'               => [
			'ajax',
			'contenthistory',

			// Extensions
			'acym',
			'acymailing',
			'akeeba',
			'admintools',
			'easyfrontendseo',
			'forai',
			'forsef',
			'forseo',
			'jce',
			'jchoptimize',
			'jchoptimize_pro',
			'jedchecker',
			'jotcache',
			'ochlogfiles',
			'patchtester',
			'sef',
			'sh404sef',
			'tcpdf',
			'virtuemart_allinone'
		]
	],
];

