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

namespace Weeblr\Forsef\Model;

use Weeblr\Wblib\Forsef\System;

use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Routingconfig extends Config
{
	public function getReplacementsList()
	{
		static $replacementsList = null;

		if (isset($replacementsList))
		{
			return $replacementsList;
		}

		$from = [];
		$to   = [];

		$items = System\Strings::stringToCleanedArray(
			$this->get('replacements')
		);

		foreach ($items as $item)
		{
			if (!empty($item))
			{
				$bits = explode(
					'|',
					$item
				);

				if (count($bits) !== 2)
				{
					continue;
				}

				$from[] = StringHelper::trim($bits[0]);
				$to[]   = StringHelper::trim($bits[1]);
			}
		}

		return [
			'from' => $from,
			'to'   => $to
		];
	}

	public function getToStripList()
	{
		static $toStripList = null;

		if (isset($toStripList))
		{
			return $toStripList;
		}

		return StringHelper::str_split($this->get('toStrip', ''));
	}
}
