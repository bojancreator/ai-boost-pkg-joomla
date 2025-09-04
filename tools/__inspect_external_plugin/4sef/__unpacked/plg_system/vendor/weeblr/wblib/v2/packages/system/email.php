<?php
/**
 * Project:                 4SEF
 *
 * @author           Yannick Gaultier - Weeblr llc
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @package          4SEF
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          @build_version_full_build@
 * @date                2025-06-02
 */

namespace Weeblr\Wblib\Forsef\System;

// no direct access
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/*
 * Based on: http://wordpress.org/plugins/email-address-encoder/
 *
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

/**
 * Copyright 2014 Till Krüss  (http://till.kruss.me/)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package   Email Address Encoder
 * @copyright 2014 Till Krüss
 */
class Email
{
	public static function eae_encode_str($string)
	{
		$chars = str_split($string);
		$seed  = mt_rand(0, (int) abs(crc32($string) / strlen($string)));

		foreach ($chars as $key => $char)
		{

			$ord = ord($char);

			if ($ord < 128)
			{ // ignore non-ascii chars

				$r = ($seed * (1 + $key)) % 100; // pseudo "random function"

				if ($r > 60 && $char != '@')
				{
					;
				} // plain character (not encoded), if not @-sign
				else if ($r < 45)
				{
					$chars[$key] = '&#x' . dechex($ord) . ';';
				} // hexadecimal
				else
				{
					$chars[$key] = '&#' . $ord . ';';
				} // decimal (ascii)

			}
		}

		return implode('', $chars);
	}
}

