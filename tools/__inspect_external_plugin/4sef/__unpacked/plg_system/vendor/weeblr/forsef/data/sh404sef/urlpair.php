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

class Urlpair extends Db\Dataobject
{
	/**
	 * @var string Database table associated with this instance.
	 */
	protected $table = '#__forsef_legacy_urls';

	/**
	 * @var array Holds data read/written to db. Also a specification of the columns.
	 */
	protected $defaults = [
		'id'            => 0,
		'cpt'           => 0,
		'rank'          => 0,
		'oldurl'        => '',
		'newurl'        => '',
		'option'        => '',
		'referrer_type' => 0,
		'dateadd'       => null,
		'last_hit'      => null
	];

	/**
	 * Load instance from db by searching for a given SEF URL.
	 * Only loads main URL, not any duplicate.
	 *
	 * @param string $sef
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function loadPerSef($sef)
	{
		return $this->loadPerColumn(
			[
				'oldurl' => $sef,
				// testing newurl won't be needed if we strip all 404s when importing
				// url table from sh404SEF.
				[
					'newurl', '!=', ''
				],
				'rank'   => 0
			]
		);
	}

	/**
	 * Load instance from db by searching for a given non-SEF URL.
	 *
	 * @param string $nonSef
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function loadPerNonSef($nonSef)
	{
		return $this->loadPerColumn(
			[
				'newurl' => $nonSef
			]
		);
	}
}
