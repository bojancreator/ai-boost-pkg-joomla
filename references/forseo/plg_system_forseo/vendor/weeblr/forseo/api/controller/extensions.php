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

namespace Weeblr\Forseo\Api\Controller;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Api;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Extensions extends Api\Controller
{
	/**
	 * @var array List of components that should not be shown to user to base SEO rules on.
	 */
	private $componentsToRemove = [
		// admin J3
		'com_actionlogs',
		'com_admin',
		'com_ajax',
		'com_associations',
		'com_cache',
		'com_checkin',
		'com_config',
		'com_contenthistory',
		'com_cpanel',
		'com_fields',
		'com_installer',
		'com_joomlaupdate',
		'com_languages',
		'com_login',
		'com_media',
		'com_menus',
		'com_messages',
		'com_modules',
		'com_plugins',
		'com_postinstall',
		'com_redirect',
		'com_templates',

		// admin J4
		'com_csp',
		'com_mails',
		'com_workflow',

		// Extensions
		'com_forseo',
		'com_sh404sef',
		'com_akeeba',
		'com_admintools'
	];

	/**
	 * @var string[] List of extensions types that can be obtained.
	 */
	private $supportedExtensionTypes = [
		'components'
	];

	/**
	 * @var null|array Holds list of installed components.
	 */
	private $components = null;

	/**
	 * @var string[] List of extensions types that can be patched.
	 */
	private $patchableExtensions = [
		'sh404sef'
	];

	/**
	 * Builds up an array of data for use in API response.
	 *
	 * @param array $options
	 *
	 * @return array | \Exception
	 */
	public function get($request, $options)
	{
		$type = Wb\arrayGet($options, 'type');
		if (!in_array($type, $this->supportedExtensionTypes))
		{
			return new \Exception(
				'Invalid extension type.',
				System\Http::RETURN_NOT_FOUND
			);
		}

		$methodName = 'load' . ucfirst($type);
		$this->{$methodName}();

		return [
			'data'  => $this->components,
			'count' => 1,
			'total' => 1,
		];
	}

	/**
	 * Reads from db installed component, cache them.
	 *
	 */
	private function loadComponents()
	{
		if (is_null($this->components))
		{
			$this->components = $this->platform->getExtensions('components');
		}

		// Clean up and format
		foreach ($this->components as $key => $component)
		{
			$this->components[$key]->option = $component->element;
			$this->components[$key]->name   = ucfirst(Wb\lTrim(strtolower($component->name), 'com_'));
		}

		/**
		 * Filter the list of extensions that should be filtered out of components lists displayed to users.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\admin
		 * @var forseo_filter_extensions_list
		 * @since   1.0.0
		 *
		 * @param array $components Names of components using com_xxx format.
		 *
		 * @return array
		 *
		 */
		$this->componentsToRemove = $this->factory
			->getThe('hook')
			->filter(
				'forseo_filter_extensions_list',
				$this->componentsToRemove
			);

		$this->components = array_diff_key(
			$this->components,
			array_flip($this->componentsToRemove)
		);

		/**
		 * Filter the list of extensions installed on the site, for user display in the admin.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\admin
		 * @var forseo_extensions_list
		 * @since   1.0.0
		 *
		 * @param array $components List of objects each describing an extension.
		 *
		 * @return array
		 *
		 */
		$this->components = $this->factory
			->getThe('hook')
			->filter(
				'forseo_extensions_list',
				$this->components
			);

	}

	/**
	 * Change an option on a 3rd-party extension.
	 *
	 * @param $request
	 * @param $options
	 *
	 * @return array|\Exception
	 */
	public function patch($request, $options)
	{
		try
		{
			$extension = Wb\arrayGet($options, 'type');
			if (
				empty($extension)
				||
				!in_array(
					$extension,
					$this->patchableExtensions
				)
			) {
				return new \Exception('No extension name provided for patch operation, or extension ' . print_r($extension, true) . ' not listed as patchable.', System\Http::RETURN_BAD_REQUEST);
			}

			$className = 'Weeblr\Forseo\Model\Extensions\\' . ucfirst($extension);

			return $this->factory
				->getA($className)
				->setConfigState(
					$request->getBody(),
					$options
				);
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception('Internal error. See error log file.', System\Http::RETURN_INTERNAL_ERROR);
		}
	}
}
