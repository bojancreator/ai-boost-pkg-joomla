<?php
/**
 * @copyright  Copyright (C) 2005 - 2016 Open Source Matters. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

/**
* @package utf8
*/
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;
//---------------------------------------------------------------
/**
* UTF-8 aware alternative to strcasecmp
* A case insensivite string comparison
* Note: requires utf8_strtolower
* @param string
* @param string
* @return int
* @see http://www.php.net/strcasecmp
* @see utf8_strtolower
* @package utf8
*/
function utf8_strcasecmp($strX, $strY) {
    $strX = utf8_strtolower($strX);
    $strY = utf8_strtolower($strY);
    return strcmp($strX, $strY);
}

