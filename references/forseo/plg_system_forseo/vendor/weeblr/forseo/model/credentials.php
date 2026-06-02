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

namespace Weeblr\Forseo\Model;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;
use Weeblr\Wblib\Forseo\Joomla\Uri;

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
	 * Joomla 3: dlid stored in 4SEO system config
	 * Joomla 4: dlid stored by Joomla, so we must read/write from there to avoid having to sync.
	 *
	 */
	private function getUpdate()
	{
		if ('prod' !== WBLIB_Forseo_OP_MODE)
		{
			return [
				'dlid' => ''
			];
		}

		if ($this->platform->majorVersion() < 4)
		{
			// dlid stored in config
			return [
				'dlid' => trim(
					$this->factory
						->getThis('forseo.config', 'system')
						->get('updateKey', '')
				)
			];
		};

		if ($this->platform->majorVersion() >= 4)
		{
			// dlid stored by platform
			return $this->platform->getUpdateId(
				'package',
				'pkg_forseo'
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
	 * @return mixed
	 */
	private function setUpdate($key, $value)
	{
		if ('prod' !== WBLIB_Forseo_OP_MODE)
		{
			return;
		}

		$value = empty($value)
			? ''
			: trim($value);

		if ($this->platform->majorVersion() < 4)
		{
			// dlid stored in config
			$this->factory
				->getThis('forseo.config', 'system')
				->set(
					'dlid',
					$value
				)->store();
		};

		if ($this->platform->majorVersion() >= 4)
		{
			// dlid stored by platform
			return $this->platform
				->setUpdateId(
					'package',
					'pkg_forseo',
					$value
				);
		};
	}
}
