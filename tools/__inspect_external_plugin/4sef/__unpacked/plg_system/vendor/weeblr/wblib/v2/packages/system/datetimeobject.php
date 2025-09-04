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

// no direct access
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

class Datetimeobject
{
	/**
	 * @var \DateTime Holds this object value.
	 */
	private $dt = null;

	/**
	 * Dateobject constructor.
	 *
	 * @param   string|\DateTime  $spec
	 * @param   string            $tz
	 *
	 * @throws \Exception
	 */
	public function __construct($spec, $tz = 'UTC')
	{
		$this->dt = $this->toDateTime(
			$spec,
			$tz
		);
	}

	/**
	 * Break reference to previous \DateTime.
	 *
	 * @return void
	 */
	public function __clone()
	{
		$this->dt = clone $this->dt;
	}

	/**
	 * Convert a string spec to a \DateTime, unless it's already a \DateTime.
	 *
	 * @param   string | \DateTime  $spec
	 * @param   string              $tz
	 *
	 * @return \DateTime
	 * @throws \Exception
	 */
	private function toDateTime($spec = 'now', $tz = 'UTC')
	{
		return $spec instanceof \DateTime
			? clone $spec
			: new \DateTime($spec, new \DateTimeZone($tz));

	}

	/**
	 * Convert a string spec to a \DateInterval unless it's already a \DateInterval.
	 *
	 * @param   string | \DateInterval  $spec
	 *
	 * @return \DateInterval
	 * @throws \Exception
	 */
	private function toDateInterval($spec)
	{
		return $spec instanceof \DateInterval
			? clone $spec
			: new \DateInterval($spec);

	}

	/**
	 * Pass-thru to set a timestamp while still returning this object and allow chaining.
	 *
	 * @param   int  $ts
	 *
	 * @return $this
	 */
	public function setTimestamp($ts)
	{
		$this->dt->setTimestamp((int) $ts);

		return $this;
	}

	/**
	 * Pass-thru to set a timezone while still returning this object and allow chaining.
	 *
	 * @param   \DateTimeZone  $timezone
	 *
	 * @return $this
	 */
	public function setTimezone($timezone)
	{
		$this->dt->setTimezone($timezone);

		return $this;
	}

	/**
	 * Substract some duration from current datetime.
	 *
	 * @param   string| \DateInterval  $spec  Either a string or a \DateInterval.
	 *
	 * @return Datetimeobject
	 * @throws \Exception
	 */
	public function add($spec)
	{
		$this->dt->add(
			$this->toDateInterval($spec)
		);

		return $this;
	}

	/**
	 * Substract some duration from current datetime.
	 *
	 * @param   string| \DateInterval  $spec  Either a string or a \DateInterval.
	 *
	 * @return Datetimeobject
	 * @throws \Exception
	 */
	public function sub($spec)
	{
		$this->dt->sub(
			$this->toDateInterval($spec)
		);

		return $this;
	}

	/**
	 * Compare current datetimeobject to the passed one.
	 *
	 * @param   string | \DateTime  $dt
	 *
	 * @return bool
	 * @throws \Exception
	 */
	private function compare($dt)
	{
		$dt = $this->toDateTime($dt);

		if ($this->dt == $dt)
		{
			return 0;
		}
		else if ($this->dt > $dt)
		{
			return -1;
		}

		return 1;
	}

	/**
	 * Wether this datetime is the same as the passed one.
	 *
	 * @param   string | \DateTime  $dt
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function isSame($dt)
	{
		return $this->compare($dt) == 0;
	}

	/**
	 * Whether this datetime is strictly before the passed one.
	 *
	 * @param   string | \DateTime  $dt
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function isBefore($dt)
	{
		return $this->compare($dt) == 1;
	}

	/**
	 * Whether this datetime is the same or before the passed one.
	 *
	 * @param   string | \DateTime  $dt
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function isBeforeOrSame($dt)
	{
		return
			$this->compare($dt) == 1
			||
			$this->compare($dt) == 0;
	}

	/**
	 * Whether this datetime is strictly earlier than the passed one minus the passed interval.
	 *
	 * @param   string | \DateTime     $dt
	 * @param   string| \DateInterval  $intervalSpec
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function isBeforeBy($dt, $intervalSpec, $tz = 'UTC')
	{
		$intervalSpec = $this->toDateInterval($intervalSpec);
		$dt           = $this->toDateTime($dt, $tz);
		$dt->sub($intervalSpec);

		return $this->isBefore($dt);
	}

	/**
	 * Whether this datetime is same or earlier than the passed one minus the passed interval.
	 *
	 * @param   string | \DateTime     $dt
	 * @param   string| \DateInterval  $intervalSpec
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function isBeforeOrSameBy($dt, $intervalSpec, $tz = 'UTC')
	{
		$intervalSpec = $this->toDateInterval($intervalSpec);
		$dt           = $this->toDateTime($dt, $tz);
		$dt->sub($intervalSpec);

		return $this->isBeforeOrSame($dt);
	}

	/**
	 * Whether this datetime is strictly after the passed one.
	 *
	 * @param   string | \DateTime  $dt
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function isAfter($dt)
	{
		return $this->compare($dt) == -1;
	}

	/**
	 * Whether this datetime is the same or after the passed one.
	 *
	 * @param   string | \DateTime  $dt
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function isAfterOrSame($dt)
	{
		return
			$this->compare($dt) == -1
			||
			$this->compare($dt) == 0;
	}

	/**
	 * Whether this datetime is strictly after the passed one plus the passed interval.
	 *
	 * @param   string | \DateTime     $dt
	 * @param   string| \DateInterval  $intervalSpec
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function isAfterBy($dt, $intervalSpec, $tz = 'UTC')
	{
		$intervalSpec = $this->toDateInterval($intervalSpec);
		$dt           = $this->toDateTime($dt, $tz);
		$dt->add($intervalSpec);

		return $this->isAfter($dt);
	}

	/**
	 * Whether this datetime is the same as the passed one plus the passed interval.
	 *
	 * @param   string | \DateTime     $dt
	 * @param   string| \DateInterval  $intervalSpec
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function isAfterOrSameBy($dt, $intervalSpec, $tz = 'UTC')
	{
		$intervalSpec = $this->toDateInterval($intervalSpec);
		$dt           = $this->toDateTime($dt, $tz);
		$dt->add($intervalSpec);

		return $this->isAfterOrSame($dt);
	}

	/**
	 * Check if a given date falls within 2 other dates, exclusively.
	 *
	 * @param $start
	 * @param $end
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function isBetween($start, $end)
	{
		// before start date ?
		if ($this->isBeforeOrSame($start))
		{
			return false;
		}

		// after end date ?
		if ($this->isAfterOrSame($end))
		{
			return false;
		}

		return true;
	}

	/**
	 * Check if a given date falls within 2 other dates, inclusively.
	 *
	 * @param $start
	 * @param $end
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function isBetweenOrSame($start, $end)
	{
		// before start date ?
		if ($this->isBefore($start))
		{
			return false;
		}

		// after end date ?
		if ($this->isAfter($end))
		{
			return false;
		}

		return true;
	}

	/**
	 * Format current datetime to MYSQL format.
	 *
	 * @return string
	 */
	public function toRfc822()
	{
		return $this->dt->format(\DateTime::RFC822);
	}

	/**
	 * Format current datetime to MYSQL format.
	 *
	 * @return string
	 */
	public function toMysql()
	{
		return $this->dt->format('Y-m-d H:i:s');
	}

	/**
	 * Format a MYSQL UTC datetime for use in sitemap, ie W3C datetime.
	 *
	 * @param   string  $datetime
	 *
	 * @return string
	 */
	public function toW3c()
	{
		$parts = explode(' ', $this->toMysql());

		return $parts[0] . 'T' . $parts[1] . 'Z';
	}

	/**
	 * Magic method to access methods of underlying DateTime object.
	 *
	 * @param   string  $method
	 * @param   array   $arguments
	 *
	 * @return mixed
	 */
	public function __call(string $method, array $arguments)
	{
		if (\is_callable(
			[
				$this->dt,
				$method
			]
		))
		{
			return \call_user_func_array(
				[$this->dt, $method],
				$arguments
			);
		}

		throw new \BadMethodCallException(sprintf('Undefined method %s in class %s', $method, get_class($this)));
	}
}

