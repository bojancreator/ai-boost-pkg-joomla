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

namespace Weeblr\Forsef\Data;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Config
{
	const DISABLED = 0;
	const ENABLED  = 1;

	// Catgories inclusion
	const CAT_ALL_NESTED = 0;
	const CAT_FIRST      = 1;
	const CAT_LAST       = 2;
	const CAT_FIRST_TWO  = 3;
	const CAT_LAST_TWO   = 4;
	const CAT_NONE       = 5;

	// Slug for uncategorize content
	const UNCAT_SLUG_ITEM_TITLE = 0;
	const UNCAT_SLUG_MENU_TITLE = 1;

	const PROCESS_NORMAL     = 0;
	const PROCESS_USE_JOOMLA = 1;
	const PROCESS_BYPASS     = 2;
	const PROCESS_NON_SEF    = 3;

	const CONTENT_INSERT_ARTICLE_ID_NONE   = 0;
	const CONTENT_INSERT_ARTICLE_ID_BEFORE = 1;
	const CONTENT_INSERT_ARTICLE_ID_AFTER  = 2;
}
