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

use Weeblr\Wblib\Forsef\Db;
use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Redirect extends Db\Dataobject
{
	// Future use maybe
	const DISABLED = 0;
	const ENABLED  = 1;

	/**
	 * @var string Database table associated with this instance.
	 */
	protected $table = '#__forsef_redirects';

	/**
	 * @var array Holds data read/written to db. Also a specification of the columns.
	 */
	protected $defaults = [
		'id'       => 0,
		'source'   => '',
		'target'   => '',
		'hits'     => 0,
		'last_hit' => null,
		'state'    => self::ENABLED
	];

}
