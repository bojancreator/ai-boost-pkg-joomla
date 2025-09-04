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

use Joomla\CMS\Router\Router as JoomlaRouter;
use Joomla\CMS\Uri;

use Weeblr\Forsef\Data;
use Weeblr\Forsef\Helper;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Wb;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

function shInitializePlugin($lang, $shLangName, $shLangIso, $option)
{
	return true;
}

function shFinalizePlugin($string, $title, &$shAppendString, $shItemidString, $limit, $limitstart, $shLangName, $showall = null,
						  $suppressPagination = false)
{
	return true;
}

function shAddToGETVarsList($name, $value)
{
	\Getvarslist::add($name, $value);
}

function shRemoveFromGETVarsList($name)
{
	\Getvarslist::remove($name);
}

function shRebuildNonSefString($string)
{
}

function getMenuTitle()
{

}

function shMustCreatePageId()
{

}

function shGetComponentPrefix()
{

}
