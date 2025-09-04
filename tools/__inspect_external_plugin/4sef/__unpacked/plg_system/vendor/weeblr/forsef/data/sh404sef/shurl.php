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

namespace Weeblr\Forsef\Data\Sh404sef;

use Weeblr\Wblib\Forsef\Db;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Shurl extends Db\Dataobject
{
	/**
	 * @var string Database table associated with this instance.
	 */
	protected $table = '#__forsef_legacy_pageids';

	/**
	 * @var array Holds data read/written to db. Also a specification of the columns.
	 */
	protected $defaults = [
		'id'     => 0,
		'newurl' => '',
		'pageid' => '',
		'type'   => 0,
		'hits'   => 0,
	];

	/**
	 * Associate this instance to a database table.
	 *
	 * @param string $table
	 *
	 * @throws \Exception
	 */
	public function __construct($table = '')
	{
		parent::__construct();
		$this->table = $this->factory->getThis('forsef.config', 'sh404sef')
									 ->get('shurlsDbTable', $this->table);
	}

	/**
	 * Load instance from db by searching for a given shURL.
	 *
	 * @param string $shurl
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function loadPerShurl($shurl)
	{
		return $this->loadPerColumn(
			'pageid',
			$shurl
		);
	}
}
