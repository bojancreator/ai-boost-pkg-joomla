<?php
/**
 * Project:                 4SEF
 *
 * @author                  Yannick Gaultier - Weeblr llc
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @package                 4SEF
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 @build_version_full_build@
 *
 * 2025-06-02
 *
 * This package is WordPress only.
 */

namespace Weeblr\Wblib\Forsef\System;

use Weeblr\Wblib\Forsef\Wb;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Store and display admin notices
 */
class Notices
{
	const ERROR = 'error';
	const WARNING = 'warning';
	const SUCCESS = 'success';
	const ALERT = 'alert';

	const CAN_DISMISS = 'can_dismiss';
	const CANNOT_DISMISS = 'cannot_dismiss';

	const REQUEST_ONLY = 'request';
	const TIMED = 'timed';

	const MINUTE_1 = 60;
	const MINUTES_2 = 120;
	const MINUTES_5 = 300;
	const MINUTES_15 = 900;
	const MINUTES_30 = 1800;
	const HOUR_1 = 3600;
	const HOUR_6 = 21600;
	const HOUR_12 = 43200;
	const DAY_1 = 86400;
	const DAY_2 = 172800;
	const DAY_5 = 432000;
	const DAY_30 = 2592000;
	const WEEK_1 = 604800;
	const WEEK_2 = 1209600;
	const MONTH_3 = 7776000;
	const MONTH_6 = 15552000;
	const YEAR_ONE = 31104000;
	const FOREVER = 0;
	const NEVER = 0;

	private $pages = null;

	private $notices = array(
		'error'   => array(),
		'warning' => array(),
		'success' => array(),
		'alert'   => array(),
	);

	/**
	 * Enqueue an admin notice for later display.
	 *
	 * @param   string  $notice
	 * @param   string  $type
	 * @param   string  $type
	 *
	 * @return $this
	 *
	 * @deprecated Use addTimedNotice instead.
	 * @throws \Exception
	 */
	public function add($notice, $type = self::SUCCESS, $dismissable = self::CAN_DISMISS)
	{
		if (!isset($this->notices[$type]))
		{
			Wb\throwException(new  \InvalidArgumentException('Invalid admin notice type ' . $type));
		}

		$this->notices[$type][] = array(
			'notice'      => $notice,
			'dismissable' => $dismissable,
			'options'     => array(
				'lifetime' => self::REQUEST_ONLY
			)
		);

		return $this;
	}

	/**
	 * Adds a timed notice, one that will display for some time, and then hidden for another time.
	 *
	 * @param   string  $id
	 * @param   string  $message
	 * @param   array   $options
	 *      string type Notices::ERROR | Notices::SUCCESS | Notices::WARNING | Notices::ALERT
	 *      string dismissal Notices::CAN_DISMISS | Notices::CANNOT_DISMISS
	 *      int hide_after In seconds, how long to show the notice, 0 = forever
	 *      int remove_after In seconds, how long to keep the notice record, 0 = forever, default to a week
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function addTimedNotice($id, $message, $options = array())
	{
		$type = Wb\arrayGet($options, 'type', self::SUCCESS);
		if (!isset($this->notices[$type]))
		{
			Wb\throwException(new  \InvalidArgumentException('Invalid admin notice type ' . $type));
		}
		$options['id']         = $id;
		$options['lifetime']   = self::TIMED;
		$options['hide_after'] = isset($options['hide_after']) ? $options['hide_after'] : self::NEVER;
		// Only store a permanent transient if specifically asked to. Default to 1 week otherwise
		$options['remove_after'] = isset($options['remove_after']) ? $options['remove_after'] : self::WEEK_1;
		$this->notices[$type][]  = array(
			'notice'      => $message,
			'dismissable' => Wb\arrayGet($options, 'dismissable', self::CAN_DISMISS),
			'options'     => $options
		);

		return $this;
	}

	/**
	 *
	 * Remove a time-base notice.
	 *
	 * @param   string  $id
	 *
	 * @return $this
	 */
	public function removeTimedNotice($id)
	{
		delete_transient(
			$this->getNoticeUid($id)
		);

		return $this;
	}

	/**
	 * Handle storing/putting on hold/hiding time-based notices
	 */
	private function processNoticesExpiration()
	{
		foreach ($this->notices as $type => $noticesOfType)
		{
			foreach ($noticesOfType as $key => $notice)
			{
				$requestType = Wb\arrayGet($notice, array('options', 'lifetime'), self::REQUEST_ONLY);
				switch ($requestType)
				{
					case self::TIMED:
						$this->processTimedNotice($notice, $key, $type);
						break;
					default:
						break;
				}
			}
		}
	}

	/**
	 * Add a page to the list of pages where we want
	 * to display our notices
	 *
	 * @param   string  $page
	 */
	public function addDisplayPage($page)
	{
		$this->pages[] = $page;
	}

	/**
	 * Action renderer for admin notices
	 *
	 * @return string
	 */
	public function adminActionRenderNotices($typeFilters = array())
	{
		if ($this->shouldShow())
		{

			// check for expired/ on hold notices
			$this->processNoticesExpiration();

			if (!empty($typeFilters))
			{
				$notices = array_intersect_key(
					$this->notices,
					array_flip($typeFilters)
				);
			}
			else
			{
				$notices = $this->notices;
			}

			// will hold rendered partials
			$__data = array('notices' => $notices);

			// display the fully rendered page
			echo WblMvcLayout_Helper::render('wblib.notices.notices', $__data, WBLIB_Forsef_LAYOUTS_PATH);
		}
	}

	/**
	 * Handle an individual timed notice.
	 *
	 * @param $notice
	 * @param $key
	 * @param $type
	 */
	private function processTimedNotice($notice, $key, $type)
	{
		// build the notice unique id
		$uid = $this->getNoticeUid(
			Wb\arrayGet(
				$notice,
				array('options', 'id')
			)
		);

		// do we have an active record for it?
		$storedOptions = get_transient($uid);
		if (empty($storedOptions))
		{
			// first display, or transient expired, show again
			$options                 = Wb\arrayGet($notice, 'options', array());
			$options['hide_after']   = empty($options['hide_after']) ? self::NEVER : time() + (int) $options['hide_after'];
			$options['remove_after'] = (int) $options['remove_after'];
			set_transient($uid, $options, $options['remove_after']);
		}
		else
		{
			// transient not expired yet, we must hide the notice
			$hideAfter = (int) Wb\arrayGet($storedOptions, 'hide_after');
			if ($hideAfter > self::NEVER && time() > $hideAfter)
			{
				unset($this->notices[$type][$key]);
			}
		}
	}

	/**
	 * Builds a unique ID for a notice, for storage. Based either on user-provided id,
	 * or a default one based on notice content if no id provided.
	 *
	 * @param $id
	 *
	 * @return string
	 */
	private function getNoticeUid($id)
	{
		return 'weeblramp_notice_' . md5($id);
	}

	/**
	 * True if no page restriction was set, or the we are on one
	 * of the specified pages
	 *
	 * @return bool
	 */
	private function shouldShow()
	{
		return is_null($this->pages) || in_array(get_current_screen()->id, $this->pages);
	}
}
