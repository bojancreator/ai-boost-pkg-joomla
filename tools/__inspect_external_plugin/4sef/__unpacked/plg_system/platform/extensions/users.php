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

namespace Weeblr\Forsef\Platform\Extensions;

use Joomla\CMS\Uri;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Users extends Base
{
	/**
	 * Builds the SEF URL for a non-sef.
	 *
	 * @param Uri\Uri $uriToBuild
	 * @param Uri\Uri $platformUri
	 * @param Uri\Uri $originalUri
	 *
	 * @return \array
	 * @throws \Exception
	 */
	public function build($uriToBuild, $platformUri, $originalUri)
	{
		$sefSegments = parent::build($uriToBuild, $platformUri, $originalUri);

		$view   = $uriToBuild->getVar('view');
		$task   = $uriToBuild->getVar('task');
		$layout = $uriToBuild->getVar('layout');
		$lang   = $uriToBuild->getVar('lang');

		$prefix = $this->t($lang, 'REGISTRATION');
		if (!empty($prefix))
		{
			$sefSegments[] = $prefix;
		}

		switch ($task)
		{
			case 'register':
				$sefSegments[] = $this->t($lang, 'REGISTER');
				$platformUri->delVar('task');
				break;
			case 'activate':
				$sefSegments[] = $this->t($lang, 'ACTIVATE');
				$platformUri->delVar('task');
				break;
		}

		switch ($view)
		{
			case 'profile' :
				if ('edit' === $layout)
				{
					$sefSegments[] = $this->t($lang, 'EDIT_DETAILS');
				}
				else
				{
					$sefSegments[] = $this->t($lang, 'VIEW_DETAILS');
				}
				break;
			case 'registration':
				$sefSegments[] = $this->t($lang, 'REGISTER');
				break;
			case 'reset':
				$sefSegments[] = $this->t($lang, 'LOST_PASSWORD');
				break;
			case 'remind':
				$sefSegments[] = $this->t($lang, 'REMIND_USER_NAME');
				break;
			case 'login' :
				if (
					'logout' === $layout
					&&
					'logout ' !== $task
				)
				{
					$sefSegments[] = $this->t($lang, 'LOGOUT');
				}
				else
				{
					$sefSegments[] = $this->t($lang, 'LOGIN');
				}
				break;
		}

		if (
			!empty($sefSegments)
			&&
			$this->routingConfig->isFalsy('suffix')
		)
		{
			$sefSegments[] = '/';
		}

		// We know these 3 will always be used
		$platformUri->delVar('view');
		$platformUri->delVar('id');
		$platformUri->delVar('task');

		return $sefSegments;
	}

	/**
	 * Check if passed URI is for an extension configured to be left non-sef.
	 *
	 * @param Uri\Uri $uriToBuild
	 * @return bool
	 */
	public function shouldLeaveNonSef($uriToBuild)
	{
		$view = $uriToBuild->getVar('view');
		$task = $uriToBuild->getVar('task');

		$noTask = !in_array(
			$task,
			[
				'register',
				'activate'
			]
		);

		if (
			$noTask
			&&
			!in_array(
				$view,
				[
					'profile',
					'registration',
					'reset',
					'remind',
					'login'
				]
			)
		)
		{
			return true;
		}

		return false;
	}

	/**
	 * Participate in building a normalized non-sef URL based on an incoming URI. Query vars values are URL-encoded.
	 * Stripping slugs, sorting vars and possibly other things are taken care globally. Only plugin-specific
	 * vars processing should happen here. For instance, stripping pagination variables if the plugin
	 * handles pagination dynamically.
	 *
	 * @param array $vars
	 * @return array
	 */
	public function buildNormalizedNonSef($vars)
	{
		return parent::buildNormalizedNonSef(
			$vars
		);
	}
}
