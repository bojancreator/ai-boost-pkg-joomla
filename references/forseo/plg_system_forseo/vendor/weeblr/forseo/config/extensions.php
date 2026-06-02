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

return [
	'doNotStore' => [
		'available'
	],
	'config'     => [
		'available'                         => [
			'hikashop'      => 'HikaShop',
			'j2store'       => 'J2Store',
			'sh404sef'      => 'sh404SEF / 4SEF',
			'sppagebuilder' => 'SP Page Builder',
		],
		// Hikashop
		'hikashopEnableStructuredData'      => true,

		// J2Store
		'j2storeEnableStructuredData'       => true,

		// J2Store
		'sppagebuilderEnableStructuredData' => true,

		// sh404SEF configuration
		'sh404sefAutoRedirectToJoomla'      => true,
	],
];
