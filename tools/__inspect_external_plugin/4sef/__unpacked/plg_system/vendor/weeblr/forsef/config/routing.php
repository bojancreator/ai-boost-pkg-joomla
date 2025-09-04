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

use Weeblr\Wblib\Forsef\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

return [
	'doNotStore'    => [
		'strict',
		'guessItemidOnHomepage',
		'insertGlobalItemidIfNone',
		'insertTitleIfNoItemid',
		'alwaysInsertMenuTitle',
		'alwaysInsertItemid'
	],
	'config'        => [

		'enabled'                  => false,

		// If true, trigger a 404 is requested URL is not found in 4SEF table.
		// If false, we'll let go and have Joomla router handle the situation.
		// This is a new setting, comparable to the Error page setting selector in sh404SEF.
		'strict'                   => true,

		// URL segments
		'spacer'                   => '-',
		'toStrip'                  => ',~!@%^()<>:;{}[]&`„‹’‘“”•›«´»°',
		'replacements'             => 'Š|S, Œ|O, Ž|Z, š|s, œ|oe, ž|z, Ÿ|Y, ¥|Y, µ|u, À|A, Á|A, Â|A, Ã|A, Ä|A, Å|A, Æ|A, Ç|C, È|E, É|E, Ê|E, Ë|E, Ì|I, Í|I, Î|I, Ï|I, Ð|D, Ñ|N, Ò|O, Ó|O, Ô|O, Õ|O, Ö|O, Ø|O, Ù|U, Ú|U, Û|U, Ü|U, Ý|Y, ß|s, à|a, á|a, â|a, ã|a, ä|a, å|a, æ|a, ç|c, è|e, é|e, ê|e, ë|e, ì|i, í|i, î|i, ï|i, ð|o, ñ|n, ò|o, ó|o, ô|o, õ|o, ö|o, ø|o, ù|u, ú|u, û|u, ü|u, ý|y, ÿ|y, ß|ss, ă|a, ş|s, ţ|t, ț|t, Ț|T, Ș|S, ș|s, Ş|S',
		'toTrim'                   => '-.',
		'lowerCase'                => true,
		'suffix'                   => '',
		'useMenuAlias'             => true,
		'guessItemidOnHomepage'    => false,
		'insertGlobalItemidIfNone' => false,
		'insertTitleIfNoItemid'    => false,
		'alwaysInsertMenuTitle'    => false,
		'alwaysInsertItemid'       => false,
		'feedsSafeMode'            => false,
	],
	'enforcedTypes' => [
		'spacer'       => System\Convert::STRING,
		'toStrip'      => System\Convert::STRING,
		'replacements' => System\Convert::STRING,
		'toTrim'       => System\Convert::STRING,
		'suffix'       => System\Convert::STRING
	]
];

