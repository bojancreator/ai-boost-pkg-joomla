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
use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Sef extends Base\Base
{
	/**
	 * @var Model\Config
	 */
	protected $routingConfig;

	/**
	 * Store a logger and config for convenience.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->routingConfig = $this->factory->getThis('forsef.config', 'routing');
	}

	/**
	 * Applied configured SEF urls processing such as lowercasing.
	 *
	 * @param string $sef
	 *
	 * @return string
	 */
	public function conform($sef)
	{
		if ($this->factory->getThis('forsef.config', 'routing')->isTruthy('lowerCase'))
		{
			$sef = StringHelper::strtolower($sef);
		}

		return $sef;
	}

	/**
	 * Applied configured SEF urls processing such as lowercasing to a URL pair object.
	 * NB: we apply changes directly to the underlying data array for better perf.
	 *
	 * @param array $urlPairData
	 *
	 * @return array
	 */
	public function conformUrlPairData($urlPairData)
	{
		$keys = [
			'sef',
			'base_path',
			'extra_path'
		];

		if ($this->factory->getThis('forsef.config', 'routing')->isTruthy('lowercase'))
		{
			foreach ($keys as $key)
			{
				// lowercase sef, base_path, extra_path
				$urlPairData[$key] = StringHelper::strtolower($urlPairData[$key]);
			}
		}

		return $urlPairData;
	}

	/**
	 * Apply all required transforms to a URL segment based on user config.
	 * If left empty, the segment will be removed from the URL.
	 *
	 * @param string $segment
	 * @return string mixed
	 */
	public function conformSegment($segment)
	{
		if ('/' === $segment)
		{
			return $segment;
		}

		$segment         = StringHelper::trim($segment);
		$replacementList = $this->routingConfig->getReplacementsList();

		$segment = str_replace(
			Wb\arrayGet($replacementList, 'from', []),
			Wb\arrayGet($replacementList, 'to', []),
			$segment
		);

		$stripList = $this->routingConfig->getToStripList();
		if (!empty($stripList))
		{
			$segment = str_replace($stripList, '', $segment);
		}

		$spacer = $this->routingConfig->get('spacer');

		// remove spaces
		$segment = System\Strings::pr(
			'/\s/',
			$spacer,
			$segment
		);

		$segment = str_replace('\'', $spacer, $segment);
		$segment = str_replace('"', $spacer, $segment);

		// strip # as it breaks anchor management
		$segment = str_replace('#', $spacer, $segment);

		// remove question marks and backslashes
		$segment = str_replace('?', $spacer, $segment);
		$segment = str_replace('\\', $spacer, $segment);

		// remove duplicate replacement chars
		if (!empty($spacer))
		{
			$segment = System\Strings::pr(
				'/' . preg_quote($spacer) . '{2,}/u',
				$spacer,
				$segment
			);
		}

		$segment = StringHelper::trim(
			$segment,
			$this->routingConfig->get('toTrim')
		);

		return $this->routingConfig->isTruthy('lowerCase')
			? StringHelper::strtolower($segment)
			: $segment;
	}
}
