<?php
/**
 * Project:                 4SEF
 *
 * @package          4SEF
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          @build_version_full_build@
 * @date                2025-06-02
 */

namespace Weeblr\Wblib\Forsef\System;

use Weeblr\Wblib\Forsef\Wb;

// no direct access
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

class Date
{
	const NEVER = 0;
	const CUSTOM = 0;
	const TODAY = 10;
	const YESTERDAY = 11;
	const THIS_WEEK = 20;
	const THIS_MONTH = 30;
	const THIS_YEAR = 40;
	const LAST_7_DAYS = 50;
	const LAST_30_DAYS = 60;
	const LAST_MONTH = 70;
	const LAST_YEAR = 80;

	static protected $_siteTimezoneName = '';
	static protected $_weekStartsOn = 'sunday';
	static protected $defaultTimeZone = null;
	static protected $intervalStrings = array(
		'past'   => array(
			'year'    => 'a year ago',
			'years'   => '%d years ago',
			'month'   => 'a month ago',
			'months'  => '%d months ago',
			'week'    => 'a week ago',
			'weeks'   => '%d weeks ago',
			'day'     => 'a day ago',
			'days'    => '%d days ago',
			'hour'    => 'an hour ago',
			'hours'   => '%d hours ago',
			'minute'  => 'a minute ago',
			'minutes' => '%d minutes ago',
			'second'  => 'just now',
			'seconds' => '%d seconds ago',
		),
		'future' => array(
			'year'    => 'in a year',
			'years'   => 'in %d years',
			'month'   => 'in a month',
			'months'  => 'in %d months',
			'week'    => 'in a week',
			'weeks'   => 'in %d weeks',
			'day'     => 'in a day',
			'days'    => 'in %d days',
			'hour'    => 'in an hour',
			'hours'   => 'in %d hours',
			'minute'  => 'in a minute',
			'minutes' => 'in %d minutes',
			'second'  => 'just now',
			'seconds' => 'in %d seconds',
		)
	);

	/**
	 * User can set specific time zone,
	 * otherwise server timezone will be used
	 *
	 * @param   string  $name
	 */
	static public function setTimezoneName($name)
	{
		self::$_siteTimezoneName = $name;
	}

	static public function getTimezoneName()
	{
		if (empty(self::$_siteTimezoneName))
		{
			self::$_siteTimezoneName = date_default_timezone_get();
		}

		return self::$_siteTimezoneName;
	}

	static public function getWeekStartsOn()
	{
		return self::$_weekStartsOn;
	}

	/**
	 * Day the week starts on, usually either 'sunday' or 'monday'
	 *
	 * @param   string  $weekStartsOn
	 */
	static public function setWeekStartsOn($weekStartsOn)
	{
		self::$_weekStartsOn = $weekStartsOn;
	}

	/**
	 * Builds and cache DateTime object for current UTC time.
	 *
	 * @param   false  $refresh  If true, get the current time, else get the cached one.
	 *
	 * @return \DateTime
	 */
	static public function getUTCNowObject($refresh = false)
	{
		static $now = null;

		if (is_null($now) || $refresh)
		{
			// get datetime with current time
			$current = new \DateTime();

			// set UTC timezone
			$utcZone = new \DateTimeZone('UTC');
			$current->setTimeZone($utcZone);
			if (is_null($now))
			{
				$now = $current;
			}
		}
		else
		{
			$current = clone $now;
		}

		// apply requested format
		return $current;
	}

	/**
	 * Get integer timestamp in seconds for current time.
	 *
	 * @param   bool  $refresh  Use a fresh time stamp if true
	 *
	 * @return int
	 */
	static public function getTimeStamp($refresh = false)
	{
		// apply requested format
		return self::getUTCNowObject($refresh)
			->getTimestamp();
	}

	/**
	 * Get formatted string of now, expressed in UTC time.
	 *
	 * @param   string  $format   format string to be used with date time object
	 *
	 * @param   bool    $refresh  Use a fresh time stamp if true
	 *
	 * @return string
	 */
	static public function getUTCNow($format = 'Y-m-d H:i:s', $refresh = false)
	{
		// apply requested format
		return self::getUTCNowObject($refresh)
			->format($format);
	}

	/**
	 * Get formatted string of now + interval, expressed in UTC time.
	 *
	 * @param   string  $duration  Duration specification for Interval
	 * @param   string  $format
	 * @param   false   $refresh
	 *
	 * @return string
	 * @throws \Exception
	 */
	static public function getUTCFromNow($duration, $format = 'Y-m-d H:i:s', $refresh = false)
	{
		// add interval
		$fromNow = self::getUTCNowObject($refresh)->add(
			new \DateInterval($duration)
		);

		// apply requested format
		return $fromNow->format($format);
	}

	/**
	 * Get formatted string of now, expressed in current site time
	 *
	 * @param   string  $format   format string to be used with date time object
	 * @param   bool    $refresh  Use a fresh time stamp if true
	 *
	 * @return string
	 */
	static public function getSiteNow($format = 'Y-m-d H:i:s', $refresh = false)
	{
		return self::utcToSite(self::getUTCNow($format, $refresh), $format);
	}

	static public function utcToSite($dateString, $format = 'Y-m-d H:i:s', $timezoneName = '')
	{
		if (empty($dateString))
		{
			return '';
		}

		// forced time zone?
		$timezoneName = empty($timezoneName) ? self::getTimezoneName() : $timezoneName;

		// get site timezone
		if (!empty($timezoneName))
		{

			// create a datetime object with incoming date
			$date = new \DateTime($dateString . ' UTC');

			// set timezone
			$timeZone = new \DateTimeZone($timezoneName);
			$date->setTimeZone($timeZone);
		}
		else
		{
			// create a datetime object with incoming date
			$date = new \DateTime($dateString);
		}

		// format and return date
		return $date->format($format);
	}

	static public function siteToUtc($dateString, $format = 'Y-m-d H:i:s', $timezoneName = '')
	{
		if (empty($dateString))
		{
			return '';
		}

		// forced time zone?
		$timezoneName = empty($timezoneName) ? self::getTimezoneName() : $timezoneName;

		// create a datetime object with incoming date
		$date = new \DateTime($dateString . ' ' . $timezoneName);

		// set UTC timezone
		$utcZone = new \DateTimeZone('UTC');
		$date->setTimeZone($utcZone);

		// format and return date
		return $date->format($format);
	}

	/**
	 * Finds the start and end day of the week that contains the provided day
	 * or current date/time if none provided.
	 * Works in both php 5.2 and php 5.3+
	 * Only works if week starts on sunday or monday.
	 *
	 * As a bonus, you get the number of days from start of range to specified date
	 *
	 * NOTE: we use the currently set default time zone. This has been set at system
	 * plugin level, and uses the tz value found in subscriptions configruation panel
	 *
	 * @param   string  $date          a date time representation
	 * @param   string  $format        a date format for the output
	 * @param   string  $weekStartsOn  the week starts on, in plain english, usually 'sunday' or 'monday'
	 * @param   string  $tz            Timezone full name
	 *
	 * @return \stdClass
	 * @throws \Exception
	 */
	static public function getWeekBoundaries($date = '', $format = 'Y-m-d H:i:s', $weekStartsOn = '', $tz = 'UTC')
	{
		$boundaries = new \stdClass();

		$startDay  = empty($weekStartSOn) ? self::$_weekStartsOn : $weekStartsOn;
		$day       = self::toDateTimeObject(
			$date,
			$tz
		);
		$thisDay   = strtolower($day->format('l'));  // 'sunday', 'monday', ...
		$dayNumber = $day->format('w');              // 0 = Sunday, 1 = monday, ...
		if ($startDay != $thisDay)
		{  // 'last' works only if day is not start day
			// well, 'last' with a day of week does not work before php 5.3, so we have to do otherwise
			// we'll simply substract the appropriate number of days

			// calculate number of days until $date,
			$boundaries->elapsedDaysCount = $dayNumber - ($startDay == 'sunday' ? 0 : 1) + 1;
			// special case: sunday
			if ($dayNumber == 0 && $startDay == 'monday')
			{
				$boundaries->elapsedDaysCount = 7;
			}

			$mod = '- ' . ($boundaries->elapsedDaysCount - 1) . ' days';
			$day->modify($mod);
		}
		else
		{
			// if date == first day of week, # of days is 1
			$boundaries->elapsedDaysCount = 1;
		}

		$day->setTime(0, 0, 0);
		$boundaries->startDate = $day->format($format);

		$mod = '+ 1 week last day';
		$day->modify($mod);
		$day->setTime(23, 59, 59);
		$boundaries->endDate = $day->format($format);

		return $boundaries;
	}


	/**
	 * Finds the start and end day of the week that contains the provided day
	 * or current date/time if none provided.
	 *
	 * Requires PHP 7.x
	 *
	 * NOTE: we use the currently set default time zone. This has been set at system
	 * plugin level, and uses the tz value found in subscriptions configruation panel
	 *
	 * @param   string  $date          a date time representation
	 * @param   string  $format        a date format for the output
	 * @param   string  $weekStartsOn  the week starts on, in plain english, usually 'sunday' or 'monday'
	 * @param   string  $tz            Timezone full name
	 *
	 * @return \stdClass
	 * @throws \Exception
	 */
	static public function getWeekBoundariesV2($date = '', $format = 'Y-m-d H:i:s', $weekStartsOn = '', $tz = 'UTC')
	{
		$boundaries = new \stdClass();

		$startDay = empty($weekStartSOn)
			? self::getWeekStartsOn()
			: $weekStartsOn;

		$day       = self::toDateTimeObject(
			$date,
			$tz
		);
		$dayOfWeek = strtolower($day->format('l'));  // 'sunday', 'monday', ...

		if ($dayOfWeek !== $startDay)
		{
			$day->modify('last ' . $startDay);
		}

		$day->setTime(0, 0);
		$boundaries->startDate = $day->format($format);

		$day->modify('+6 days');
		$day->setTime(23, 59, 59);
		$boundaries->endDate = $day->format($format);

		return $boundaries;
	}

	/**
	 * Finds the start and end day of the month that contains the provided day
	 * or current date/time if none provided.
	 *
	 * As a bonus, you get the number of days from start of range to specified date
	 *
	 * NOTE: we use the currently set default time zone. This has been set at system
	 * plugin level, and uses the tz value found in subscriptions configruation panel
	 *
	 * @param   string  $date    a date time representation
	 * @param   string  $format  a date format for the output
	 * @param   string  $tz      Timezone full name
	 *
	 * @return \stdClass
	 * @throws \Exception
	 */
	static public function getMonthBoundaries($date = '', $format = 'Y-m-d H:i:s', $tz = 'UTC')
	{
		$boundaries = new \stdClass();

		$day = self::toDateTimeObject(
			$date,
			$tz
		);

		$boundaries->elapsedDaysCount = $day->format('j');

		$day = self::toDateTimeObject(
			'1 ' . $day->format('F') . ' ' . $day->format('Y') . ' 00:00:00',
			$tz
		);

		$boundaries->startDate = $day->format($format);

		$mod = '+ 1 month last day';
		$day->modify($mod);
		$day->setTime(23, 59, 59);
		$boundaries->endDate = $day->format($format);

		return $boundaries;
	}

	/**
	 * Finds the start and end day of the year that contains the provided day
	 * or current date/time if none provided.
	 * Works in both php 5.2 and php 5.3+, as last,next, etc are not used
	 *
	 * As a bonus, you get the number of days from start of range to specified date
	 *
	 * NOTE: we use the currently set default time zone. This has been set at system
	 * plugin level, and uses the tz value found in subscriptions configruation panel
	 *
	 * @param   string  $date    a date time representation
	 * @param   string  $format  a date format for the output
	 * @param   string  $tz      Timezone full name
	 *
	 * @return \stdClass
	 * @throws \Exception
	 */
	static public function getYearBoundaries($date = '', $format = 'Y-m-d H:i:s', $tz = 'UTC')
	{
		$boundaries = new \stdClass();

		// calculate first day of year!
		$theDay = self::toDateTimeObject(
			$date,
			$tz
		);
		$year   = $theDay->format('Y');
		$month  = $theDay->format('n');
		$day    = $theDay->format('j');

		$firstDay              = self::toDateTimeObject(
			$year . '-1-1' . ' 00:00:00',
			$tz
		);
		$boundaries->startDate = $firstDay->format($format);

		// calculate last day of year
		$lastDay             = self::toDateTimeObject(
			$year . '-12-31' . ' 23:59:59',
			$tz
		);
		$boundaries->endDate = $lastDay->format($format);

		// how many days between now and start of year?
		// even with PHP 5.3.3, we cannot use diff(), as it has a bug on windows box
		// http://bugs.php.net/bug.php?id=52798
		// this does the same sort of calculation.
		// in addition, quick testing has shown this to be 10x faster than using diff()...
		// very strange
		$boundaries->elapsedDaysCount = 0;
		$m                            = 1;
		while ($m < $month)
		{
			$firstDay->setDate($year, $m, 10);                       // calculate next month, use 10th of month to avoid timezone issues if using 1st
			$boundaries->elapsedDaysCount += $firstDay->format('t'); // add number of days in that month
			$m++;
		}

		// add days on current month
		$boundaries->elapsedDaysCount += $day;

		return $boundaries;
	}

	public static function getAsMoments($date, $refDate = 'now', $options = array())
	{
		$date    = self::toDateTimeObject($date);
		$refDate = self::toDateTimeObject($refDate);
		if (empty($date) || empty($refDate))
		{
			return '';
		}

		// compute elapsed time
		$interval = $date->diff($refDate);
		switch (true)
		{
			case ($interval->y > 0) :
				$value        = $interval->y;
				$intervalType = 'year';
				break;
			case ($interval->m > 0) :
				$value        = $interval->m;
				$intervalType = 'month';
				break;
			case ($interval->d > 14) :
				$value        = (int) $interval->d / 7;
				$intervalType = 'week';
				break;
			case ($interval->d > 7) :
				$value        = 1;
				$intervalType = 'week';
				break;
			case ($interval->d > 0) :
				$value        = $interval->d;
				$intervalType = 'day';
				break;
			case ($interval->h > 0) :
				$value        = $interval->h;
				$intervalType = 'hour';
				break;
			case ($interval->i > 0) :
				$value        = $interval->i;
				$intervalType = 'minute';
				break;
			case ($interval->s > 0) :
				$value        = $interval->s;
				$intervalType = 'second';
				break;
		}

		$output = '';
		if (!empty($intervalType))
		{
			$intervalType .= $value > 1 ? 's' : '';
			$stringsType  = $interval->invert ? 'future' : 'past';
			$output       = sprintf(self::$intervalStrings[$stringsType][$intervalType], $value);
		}

		return $output;
	}

	public static function toDateTimeObject($date = 'now', $timezone = null)
	{
		$dateObject = null;
		if (
			is_object($date)
			&&
			(
				\is_a($date, \DateTime::class)
				||
				\is_a($date, Datetimeobject::class)
			)
		)
		{
			$dateObject = clone $date;
		}
		else if (is_string($date))
		{
			try
			{
				if (
					!empty($timezone)
					&&
					is_string($timezone)
				)
				{
					$timezone = new \DateTimeZone($timezone);
				}

				$tz         = Wb\initEmpty($timezone, self::getTimeZone());
				$dateObject = new \DateTime($date, $tz);
			}
			catch (\Throwable $e)
			{
				$dateObject = new \DateTime('now', self::getTimeZone());
			}
			catch (\Exception $e)
			{
				$dateObject = new \DateTime('now', self::getTimeZone());
			}
		}

		return $dateObject;
	}


	/**
	 * Convert a string or a DateTime object to wbLib extended Datetimeobject format.
	 *
	 * @param   string|\DateTime  $spec
	 * @param   string            $tz
	 *
	 * @return Datetimeobject
	 * @throws \Exception
	 */
	public static function toExtendedDateTime($spec = 'now', $tz = 'UTC')
	{
		return new Datetimeobject($spec, $tz);
	}

	/**
	 * Compute the month number for the current time stamp of this DateTime object,
	 * with January 2020 being month 0.
	 *
	 * @param   int  $startYear
	 *
	 * @return int
	 * @throws \Exception
	 */
	public static function toMonthNumber($spec = 'now', $tz = 'UTC', $startYear = 2020)
	{
		$dt    = self::toExtendedDateTime($spec, $tz);
		$year  = (int) $dt->format('Y');
		$month = (int) $dt->format('m');

		return ($year - $startYear) * 12 + $month - 1;
	}

	/**
	 * Returns an object with properties startDate and endDate, each formatted according to the $format param,
	 * which represent the 1st and last day of the specified number of months from january of $startYear.
	 *
	 * @param $monthNumber
	 * @param $format
	 * @param $tz
	 * @param $startYear
	 *
	 * @return \stdClass
	 * @throws \Exception
	 */
	public static function fromMonthNumber($monthNumber, $format = 'Y-m-d H:i:s', $tz = 'UTC', $startYear = 2020)
	{
		$dt = self::toExtendedDateTime($startYear . '-01-01 00:00:01', $tz);
		$dt->add('P' . $monthNumber . 'M');
		$converted = self::getMonthBoundaries(
			$dt,
			$format,
			$tz
		);

		return $converted;
	}

	/**
	 * Check if a given date falls within 2 other dates, inclusively
	 *
	 * @param $date
	 * @param $start
	 * @param $end
	 *
	 * @return bool
	 */
	public static function isBetween($date, $start, $end)
	{
		$isBetween = true;

		// before start date ?
		if (!empty($start) && $date < $start)
		{
			$isBetween = false;
		}

		// after end date ?
		if (!empty($end) && $date > $end)
		{
			$isBetween = false;
		}

		return $isBetween;
	}

	/**
	 * Builds a DateTimeZone object for the request, based on
	 * passed timezone name, or server default if empty
	 * Default to UTC in case of error
	 *
	 * @param   string  $configuredZoneName
	 *
	 * @return \DateTimeZone|null
	 */
	public static function getTimeZone($configuredZoneName = '')
	{
		if (is_null(self::$defaultTimeZone))
		{
			try
			{
				$zoneName              = Wb\initEmpty($configuredZoneName, date_default_timezone_get());
				self::$defaultTimeZone = new \DateTimeZone($zoneName);
			}
			catch (\Throwable $e)
			{
				self::$defaultTimeZone = new \DateTimeZone('UTC');
			}
			catch (\Exception $e)
			{
				self::$defaultTimeZone = new \DateTimeZone('UTC');
			}
		}

		return self::$defaultTimeZone;
	}

	/**
	 * Format a DateTimeZone object to ATOM, suitable for Schema.org markup,
	 * replacing +00:00 time zone indication with Z
	 *
	 * @param   string | DateTime   $date
	 *
	 * @param   null |DateTimeZone  $timezone  Optional timezone, used only when $date is NOT already a DateTime
	 *
	 * @return string
	 */
	public static function toAtom($date, $timezone = null)
	{
		if (!$date instanceof \DateTime)
		{
			// get a date time object
			$date = self::toDateTimeObject($date, $timezone);
		}

		// process
		$formatted = $date->format(\DateTime::ATOM);
		if (substr($formatted, -6) == '+00:00')
		{
			$formatted = substr($formatted, 0, -6) . 'Z';
		}

		return $formatted;
	}
}

