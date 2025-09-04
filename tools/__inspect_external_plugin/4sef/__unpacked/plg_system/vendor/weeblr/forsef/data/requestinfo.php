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

use Weeblr\Forsef\Helper;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Base\Dataobject;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * IMPORTANT: RequestInfo cannot be used before the onAfterRoute event
 * where it's fully initialized.
 */
class Requestinfo extends Base\Dataobject
{
	/**
	 * @var array Holds data read/written to db. Also a specification of the columns.
	 */
	protected $defaults = [

		// request
		'is_frontend'             => null,
		'status'                  => null,

		// Site info
		'site_name'               => null,
		'site_url'                => null,
		'page_url'                => null,
		'page_path'               => null,
		'page_language'           => null,
		'page_language_direction' => null,
		'page_dynamic_vars'       => null,
		'page_suffix'             => null,
		'page_appended_segment'   => null,
		'page_base_path'          => null,
		'page_extra_path'         => null,
	];

	/**
	 * Initialize all possible request-related details.
	 */
	public function __construct()
	{
		parent::__construct();

		// Request
		$this->data['is_frontend'] = $this->platform->isFrontend();

		// Site info
		$this->data['site_name']               = $this->platform->getSitename();
		$this->data['site_url']                = $this->platform->getRootUrl(false);
		$this->data['page_url']                = $this->platform->getCurrentUrl();
		$this->data['page_query']              = $this->platform->getCurrentQuery();
		$this->data['page_path']               = $this->platform->getCurrentPath();
		$this->data['page_language']           = $this->platform->getCurrentLanguageTag();
		$this->data['page_language_direction'] = $this->platform->getCurrentLanguageDirection();

		$this->filter();
	}

	/**
	 * Parse dynamic segments of the request URL and parse them.
	 * - format (feed)
	 * - page suffix (.html)
	 *
	 * @return $this
	 */
	public function parseDynamicSegments()
	{
		$suffixDetails = $this->factory
			->getA(Helper\Dynamicsegments::class)
			->parse($this->data['page_path']);

		$this->data['page_dynamic_vars']     = Wb\arrayGet($suffixDetails, 'vars');
		$this->data['page_suffix']           = Wb\arrayGet($suffixDetails, 'suffix');
		$this->data['page_appended_segment'] = Wb\arrayGet($suffixDetails, 'appended_segment');
		$this->data['page_base_path']        = Wb\arrayGet($suffixDetails, 'base_path');
		$this->data['page_extra_path']       = Wb\arrayGet($suffixDetails, 'extra_path');

		$this->filter();

		return $this;
	}

	/**
	 * Set data for this object. Override filter the new data
	 * set after it's been updated.
	 *
	 * @param null| string $keyOrData
	 * @param array        $data
	 *
	 * @return Requestinfo
	 * @throws \Exception
	 */
	public function set($keyOrData = null, $data = null)
	{
		return parent::set($keyOrData, $data)
					 ->filter();
	}

	/**
	 * Set an individual key of this object.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return Requestinfo
	 * @throws \Exception
	 */
	protected function setKey($key, $value)
	{
		$this->validateKey($key);
		$this->data[$key] = $value;

		return $this;
	}

	/**
	 * Get an individual key of this object.
	 *
	 * @param string $key
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	protected function getKey($key)
	{
		$this->validateKey($key);

		return $this->data[$key];
	}

	/**
	 * Filter the array of variables values.
	 *
	 * @return Requestinfo
	 */
	private function filter()
	{
		/**
		 * Filter the list of dynamic variables used in content replacement.
		 *
		 * @api     forsef
		 * @package 4SEF\filter\request
		 * @var forsef_request_info
		 * @since   1.0.0
		 *
		 * @param array $expandedVariables Array of variable names/variable values to use in expansions.
		 *
		 * @return array
		 *
		 */
		$this->data = $this->factory
			->getThe('hook')
			->filter(
				'forsef_request_info',
				$this->data
			);

		// safety net
		$this->data =
			(
				empty($this->data)
				||
				!is_array($this->data)
			)
				? []
				: $this->data;

		return $this;
	}
}
