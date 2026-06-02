<?php
/**
 * Project: 4SEO
 *
 * @package          4SEO
 * @copyright        Copyright Weeblr llc - 2020 - 2026
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          6.10.1.2660
 * @date        2026-01-30
 */

namespace Weeblr\Forseo\Data;

use Weeblr\Forseo\Helper;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Base\Dataobject;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Requestinfo extends Base\Dataobject
{
	/**
	 * @var array Holds data read/written to db. Also a specification of the columns.
	 */
	protected $defaults = [
		'user_id'                        => null,
		'user_name'                      => null,

		// Site info
		'site_name'                      => null,
		'site_url'                       => null,
		'page_url'                       => null,
		'page_path'                      => null,
		'page_query'                     => null,
		'page_canonical'                 => null,
		'page_auto_canonical'            => null,
		'page_robots'                    => null,
		'page_language'                  => null,
		'page_language_direction'        => null,
		'page_title'                     => null,
		'page_custom_title'              => null,
		'page_custom_title_ogp'          => null,
		'page_custom_title_tcards'       => null,
		'page_description'               => null,
		'page_auto_description'          => null,
		'page_custom_description'        => null,
		'page_custom_description_ogp'    => null,
		'page_custom_description_tcards' => null,
		'page_custom_canonical'          => null,
		'page_custom_robots'             => null,
		'page_image'                     => [],
		'page_sharing_image'             => [],
		'page_auto_image'                => [],
		'page_auto_sharing_image'        => [],
		'page_custom_image'              => [],
		'page_custom_sharing_image'      => [],

		// meta control
		'page_suppress_meta_description' => false,

		// date time
		'year-month-day'                 => '',
		'year-month-day_time'            => '',
		'day/month/year'                 => '',
		'day/month/year_time'            => '',
		'year'                           => '',
		'year-month'                     => '',
		'month/year'                     => '',
		'month_name'                     => '',
		'month_short_name'               => '',
		'month_number'                   => '',
		'month-day'                      => '',
		'day/month'                      => '',
		'week_number'                    => '',
		'day_name'                       => '',
		'day_short_name'                 => '',
		'time'                           => ''
	];

	/**
	 * Initialize all possible request-related details.
	 */
	public function __construct()
	{
		parent::__construct();

		// User
		$user                    = $this->platform->getUser();
		$this->data['user_id']   = Wb\initEmpty($user->id, '');
		$this->data['user_name'] = Wb\initEmpty($user->username, '');

		// Site info
		$this->data['site_name'] = $this->platform->getSitename();
		$this->data['site_url']  = $this->platform->getRootUrl(false);
		$this->data['page_url']  = $this->factory->getThe('forseo.pageHelper')->getCleanedCurrentUrl();
		$this->data['page_path'] = $this->platform->getCurrentPath();;
		$this->data['page_query'] = $this->platform->getCurrentQuery();

		// Request info, default value
		$this->data['page_status'] = System\Http::RETURN_OK;

		// Date/time
		$dt = $this->factory
			->getA(
				System\Datetimeobject::class,
				'now'
			)->setTimeZone(
				new \DateTimeZone(
					$this->platform->getTimezone()
				)
			);

		// @TODO: build this on demand, at replace time
		$this->data['year-month-day']      = $dt->format('Y-m-d');
		$this->data['year-month-day_time'] = $dt->format('Y-m-d H:i');
		$this->data['day/month/year']      = $dt->format('d/m/Y');
		$this->data['day/month/year_time'] = $dt->format('d/m/Y H:i');
		$this->data['year']                = $dt->format('Y');
		$this->data['year_month']          = $dt->format('Y-m');
		$this->data['month/year']          = $dt->format('m/Y');
		$this->data['month_name']          = $dt->format('F');
		$this->data['month_short_name']    = $dt->format('M');
		$this->data['month_number']        = $dt->format('n');
		$this->data['month_day']           = $dt->format('m-d');
		$this->data['day/month']           = $dt->format('d/m');
		$this->data['week_number']         = $dt->format('W');
		$this->data['day_name']            = $dt->format('l');
		$this->data['day_short_name']      = $dt->format('D');
		$this->data['time']                = $dt->format('H:i:s');

		$this->filter();
	}

	/**
	 * Some information are only available after routing. This method is hooked
	 * into at the forseo_onAfterRoute event.
	 *
	 * @return void
	 */
	public function collectInfoAfterRoute()
	{
		$this->data['page_language']           = $this->platform->getCurrentLanguageTag();
		$this->data['page_language_direction'] = $this->platform->getCurrentLanguageDirection();
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
	 * Helper method to get the title amongst possible options.
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function getPageTitle()
	{
		$title = $this->get('page_custom_title');
		if (empty($title))
		{
			$title = $this->get('page_title', '');
		}

		return $title;
	}

	/**
	 * Helper method to get the meta description amongst possible options.
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function getMetaDescription()
	{
		$desc = $this->get('page_custom_description');
		if (empty($desc))
		{
			$desc = $this->get('page_description');
		}
		if (empty($desc))
		{
			$desc = $this->get('page_auto_description', '');
		}
		return $desc;
	}

	/**
	 * Helper method to get the meta robots amongst possible options.
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function getMetaRobots()
	{
		$robots = $this->get('page_custom_robots');
		if (empty($robots))
		{
			$robots = $this->get('page_robots', '');
		}

		return $robots;
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
		 * @api     forseo
		 * @package 4SEO\filter\request
		 * @var forseo_request_info
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
				'forseo_request_info',
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
