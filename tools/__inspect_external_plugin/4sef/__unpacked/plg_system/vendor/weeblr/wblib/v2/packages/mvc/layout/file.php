<?php
/**
 * Project:                 4SEF
 *
 * @author                  Yannick Gaultier - Weeblr llc
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @package                 4SEF
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 @build_version_full_build@
 *
 * 2025-06-02
 */

namespace Weeblr\Wblib\Forsef\Mvc;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Fs;
use Weeblr\Wblib\Forsef\System;

/** ensure this file is being included by a parent file */
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Base class for rendering a display layout
 * loaded from from a layout file
 *
 * @since       0.2.1
 */
class LayoutFile extends LayoutBase
{
	/**
	 * @var    string  Dot separated path to the layout file, relative to base path
	 * @since  0.2.1
	 */
	protected $layoutId = '';

	/**
	 * @var    string  Base path to use when loading layout files
	 * @since  0.2.1
	 */
	protected $basePath = null;

	/**
	 * @var    string  Full path to actual layout files, after possible template override check
	 * @since  0.2.2
	 */
	private $fullPath = null;

	/**
	 * Method to instantiate the file-based layout.
	 *
	 * @param   string  $layoutId  Dot separated path to the layout file, relative to base path
	 * @param   mixed   $basePath  Base path, or list of base paths to use when loading layout files
	 *
	 * @since   3.0
	 */
	public function __construct($layoutId, $basePath = null)
	{
		parent::__construct();

		$this->layoutId = $layoutId;
		$this->basePath = empty($basePath) ? WBLIB_Forsef_LAYOUTS_PATH . 'layouts' : (is_string($basePath) ? rtrim($basePath, DIRECTORY_SEPARATOR) : $basePath);

		// user supplied base path (on front end)
		if ($this->platform->isFrontend())
		{
			$supplementalBasePaths = $this->validateBasePaths(
				$this->platform->getLayoutOverridesPath()
			);

			// merge everything together
			$this->basePath = array_merge(
				$supplementalBasePaths,
				(array) $this->basePath
			);
		}
	}

	/**
	 * Method to render the layout.
	 *
	 * @param   object  $__data  Object which properties are used inside the layout file to build displayed output
	 *
	 * @return  string  The necessary HTML to display the layout
	 *
	 * @since   3.0
	 */
	public function render($__data)
	{
		$layoutOutput = parent::render($__data);

		// Check possible overrides, and build the full path to layout file
		$path = $this->getPath();

		// If there exists such a layout file, include it and collect its output
		if (!empty($path))
		{
			// Joomla 3 compat.
			$displayData = $__data;
			ob_start();
			include $path;
			$layoutOutput = ob_get_contents();
			if (ob_get_length())
			{
				ob_end_clean();
			}
		}

		// apply a filter for 3rd-party content customization
		if ($this->platform->isFrontend())
		{
			$filterName   = 'wblib_layout_' . str_replace('.', '_', $this->layoutId);
			$layoutOutput = $this->factory->getThe('hook')->filter(
				$filterName,
				$layoutOutput,
				$__data
			);
		}

		return $layoutOutput;
	}

	/**
	 * Finds whether there is at least one layout file
	 * for the current layout id.
	 *
	 * @return bool
	 */
	public function exists()
	{
		$path = $this->getPath();

		return !empty($path);
	}

	/**
	 * Method to finds the full real file path, checking possible overrides
	 *
	 * @return  string  The full path to the layout file
	 *
	 * @since   3.0
	 */
	protected function getPath()
	{
		if (is_null($this->fullPath) && !empty($this->layoutId))
		{
			$rawPath  = str_replace('.', '/', $this->layoutId) . '.php';
			$fileName = basename($rawPath);
			$filePath = dirname($rawPath);

			$possiblePaths = array();

			// add built-in path(s), which are fallbacks if user supplied a folder
			if (is_string($this->basePath))
			{
				$possiblePaths[] = $this->basePath . '/' . $filePath;
			}
			else if (is_array($this->basePath))
			{
				foreach ($this->basePath as $path)
				{
					if (is_string($path))
					{
						$possiblePaths[] = rtrim($path, '/\\') . '/' . $filePath;
					}
				}
			}

			$this->fullPath = Fs\File::find($possiblePaths, $fileName);
		}

		return $this->fullPath;
	}

	/**
	 * Basic validation of user-supplied base path
	 *
	 * @param $paths
	 *
	 * @return string
	 */
	protected function validateBasePaths($paths)
	{
		$validatedPaths = array();
		foreach ($paths as $path)
		{
			if (
				empty($path)
				|| Wb\contains($path, array('..', './'))
				|| !file_exists($path)
			)
			{
				System\Log::libraryError('%s::%d %s', __METHOD__, __LINE__, 'Invalid template include path supplied: ' . $path);
				continue;
			}

			// good t go, keep it
			$validatedPaths[] = $path;
		}

		return $validatedPaths;
	}
}
