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

namespace Weeblr\Forsef\Api\Controller;

use Weeblr\Forsef\Model;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Api;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Extensions extends Api\Controller
{
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
	 * @return array|\Exception
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
			$this->components = $this->factory->getThis('forsef.config', 'app')->getFilteredInstalledExtensions();
		}

		// Clean up and format
		foreach ($this->components as $key => $component)
		{
			$this->components[$key]->option = $component->name;
			$this->components[$key]->name   = ucfirst(Wb\lTrim($component->name, 'com_'));
		}

		/**
		 * Filter the list of extensions installed on the site, for user display in the admin.
		 *
		 * @api     forsef
		 * @package 4SEF\filter\admin
		 * @var forsef_extensions_list
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
				'forsef_extensions_list',
				$this->components
			);

	}

	/**
	 * Update a single URL pair.
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
			)
			{
				return new \Exception('No extension name provided for patch operation, or extension ' . print_r($extension, true) . ' not listed as patchable.', System\Http::RETURN_BAD_REQUEST);
			}

			$className = 'Weeblr\Forsef\Model\Extensions\\' . ucfirst($extension);

			return $this->factory
				->getA($className)
				->setConfigState(
					$request->getBody(),
					$options
				);
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forsef.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception('Internal error. See error log file.', System\Http::RETURN_INTERNAL_ERROR);
		}
	}
}
