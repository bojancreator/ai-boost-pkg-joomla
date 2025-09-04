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

class Sh404sefconfig extends Config
{
	/**
	 * Load configuration from database.
	 *
	 * @return $this
	 */
	public function load()
	{
		parent::load();

		foreach ($this->detectSh404sefData() as $key => $value)
		{
			$this->set(
				$key,
				$value
			);
		}

		return $this;
	}

	/**
	 * Can trigger an action after a key is set.
	 *
	 * @param array|string $keys
	 * @param mixed        $newValue
	 * @param mixed        $previousValue
	 *
	 * @return Config
	 */
	protected function afterSet($keys, $newValue, $previousValue)
	{
		if ($newValue === $previousValue)
		{
			return $this;
		}

		$this->enforceTypes(
			$keys,
			$newValue,
			$previousValue
		);

		if (
			'processedSh404sefImport' === $keys
			&&
			empty($newValue)
		)
		{
			$this->factory
				->getThe('forsef.keystore')
				->delete('sh404sef_import.processed');
		}

		if (
			'erroredSh404sefImport' === $keys
			&&
			empty($newValue)
		)
		{
			$this->factory
				->getThe('forsef.keystore')
				->delete('sh404sef_import.errored');
		}

		return $this;
	}

	/**
	 * Look for sh404SEF URLs table with custom URLs to import.
	 *
	 * @param bool $customOnly
	 * @return array
	 */
	private function detectSh404sefData($customOnly = true)
	{
		try
		{
			$customUrlsCount = $this->factory
				->getThe('db')
				->count(
					'#__sh404sef_urls',
					'*',
					[
						['dateadd', '<>', '0000-00-00'],
						['newurl', '<>', ''],
					]
				);

			$totalUrlsCount = $this->factory
				->getThe('db')
				->count(
					'#__sh404sef_urls',
					'*',
					[
						['newurl', '<>', ''],
					]
				);

			$keystore = $this->factory->getThe('forsef.keystore');
			if ($totalUrlsCount > 0)
			{
				$processed = $keystore->get('sh404sef_import.processed', 0);
				$errored   = $keystore->get('sh404sef_import.errored', 0);
			}
			else
			{
				$keystore->delete('sh404sef_import.processed');
				$processed = 0;
				$keystore->delete('sh404sef_import.errored');
				$errored = 0;
			}
		}
		catch (\Throwable $e)
		{
			$customUrlsCount = 0;
			$totalUrlsCount  = -1;
			$processed       = 0;
			$errored         = 0;
		}

		return [
			'canImportFromSh404sef'       => $totalUrlsCount,
			'canImportCustomFromSh404sef' => $customUrlsCount,
			'processedSh404sefImport'     => $processed,
			'erroredSh404sefImport'       => $errored,
		];
	}
}
