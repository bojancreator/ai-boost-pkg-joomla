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

namespace Weeblr\Forsef\Helper;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\Db;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Content extends Base\Base
{
	/**
	 * @var array Memoize "uncategorized" category per extension.
	 */
	public static $uncategorizedCat = array();

	/**
	 * @var Db\Helper
	 */
	protected $db;

	/**
	 * Store a logger and config for convenience.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->db = $this->factory->getThe('db');
	}

	/**
	 * Get details of the "Uncategorized" category for a given extension,
	 * storing the result in a cache variable.
	 *
	 * @param string $extension full name of extension, ie "com_content"
	 */
	public function getUncategorizedCat($extension = 'com_content')
	{
		if (!isset(self::$uncategorizedCat[$extension]))
		{
			try
			{
				self::$uncategorizedCat[$extension] = $this->db->selectObject(
					'#__categories',
					'*',
					'parent_id > 0 and extension = ? and path = ? and level = ?',
					[
						$extension,
						'uncategorised',
						1
					]
				);
			}
			catch (\Exception $e)
			{
				self::$uncategorizedCat[$extension] = null;
			}
		}

		return self::$uncategorizedCat[$extension];
	}
}
