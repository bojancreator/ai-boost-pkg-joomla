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

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;
use Weeblr\Wblib\Forsef\Joomla\Uri;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Credentials extends Base\Base
{
	/**
	 * Get content of credential of a given type.
	 *
	 * @param string $credType
	 * @return mixed
	 * @throws \Exception
	 */
	public function get($credType)
	{
		$methodName = 'get' . ucfirst($credType);
		if (!is_callable([$this, $methodName]))
		{
			throw new \Exception('Getting invalid credential type ' . $credType, 500);
		}

		return $this->{$methodName}();
	}

	/**
	 * Returns update credentials.
	 *
	 * Joomla 3: dlid stored in 4SEF system config
	 * Joomla 4: dlid stored by Joomla, so we must read/write from there to avoid having to sync.
	 *
	 */
	private function getUpdate()
	{
		if ('prod' !== WBLIB_Forsef_OP_MODE)
		{
			return [
				'dlid' => ''
			];
		}

		if ($this->platform->majorVersion() < 4)
		{
			// dlid stored in config
			return [
				'dlid' => $this->factory
					->getThis('forsef.config', 'system')
					->get('updateKey', '')
			];
		};

		if ($this->platform->majorVersion() >= 4)
		{
			// dlid stored by platform
			return $this->platform->getUpdateId(
				'package',
				'pkg_forsef'
			);
		};
	}

	/**
	 * Update a specific key for given credential type.
	 *
	 * @param string $credType
	 * @param string $key
	 * @param mixed  $value Json-encoded value
	 * @throws \Exception
	 */
	public function update($credType, $key, $value)
	{
		$methodName = 'set' . ucfirst($credType);
		if (!is_callable([$this, $methodName]))
		{
			throw new \Exception('Updating Invalid credential type ' . $credType, 500);
		}

		$this->{$methodName}(
			$key,
			$value
		);
	}

	/**
	 * Update credentials is the download Id.
	 *
	 * @param string $key
	 * @param string $value
	 * @return void
	 */
	private function setUpdate($key, $value)
	{
		if ('prod' !== WBLIB_Forsef_OP_MODE)
		{
			return;
		}

		$value = empty($value)
			? ''
			: $value;

		if ($this->platform->majorVersion() < 4)
		{
			// dlid stored in config
			$this->factory
				->getThis('forsef.config', 'system')
				->set(
					'dlid',
					$value
				)->store();
		};

		if ($this->platform->majorVersion() >= 4)
		{
			// dlid stored by platform
			$this->platform
				->setUpdateId(
					'package',
					'pkg_forsef',
					$value
				);
		};
	}
}
