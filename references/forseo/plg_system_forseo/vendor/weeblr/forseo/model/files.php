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

class Files extends Base\Base
{
	private $rootPath = '';

	private $allowedImageExtensions = [
		'png',
		'jpg',
		'jpeg',
		'gif'
	];

	private $allowedFileTypes = [
		'folder',
		'image'
	];

	/**
	 * Set the root folder where to look up files and folders.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->rootPath = str_replace(
			'\\',
			'/',
			$this->platform->getUserImagesPath()
		);
	}

	public function files($options)
	{
		$files   = [];
		$folders = [];

		$path = $this->sanitizePath(
			Wb\arrayGet(
				$options,
				'path',
				''
			)
		);

		$onlyTypes = Wb\arrayGet(
			$options,
			'only',
			''
		);

		$onlyTypes         = System\Strings::stringToCleanedArray($onlyTypes);
		$filteredOnlyTypes = array_intersect(
			$onlyTypes,
			$this->allowedFileTypes
		);

		if (!empty($onlyTypes) && empty($filteredOnlyTypes))
		{
			// some types were specified, but included invalid ones
			// return empty
			return [
				'data'  => [],
				'count' => 0,
				'total' => 0,
			];
		}

		foreach (new \DirectoryIterator($path) as $fileInfo)
		{
			if ($fileInfo->isDot())
			{
				// never include dot files
				continue;
			}

			// folders
			if (
				$fileInfo->isDir()
				&&
				!$this->shouldIncludeType('folder', $filteredOnlyTypes)
			) {
				continue;
			}

			// images
			if (
				$fileInfo->isFile()
				&&
				in_array('image', $filteredOnlyTypes)
			) {
				if (!in_array(
					$fileInfo->getExtension(),
					$this->allowedImageExtensions)
				) {
					continue;
				}
			}

			if ($fileInfo->isDir())
			{
				$folders[] = $fileInfo->getFilename();
			}
			else
			{
				$files[] = $fileInfo->getFileName();
			}
		}

		$total = count($files) + count($folders);

		return [
			'data'  => [
				'files'   => $files,
				'folders' => $folders
			],
			'count' => $total,
			'total' => $total,
		];
	}

	/**
	 * Whether a given file type should be included in the returned list
	 * per the request specification.
	 *
	 * @param string $type
	 * @param array  $allowedTypes
	 * @return bool
	 */
	private function shouldIncludeType($type, $allowedTypes)
	{
		return empty($allowedTypes)
			   ||
			   in_array($type, $allowedTypes);
	}

	/**
	 * List all the PNG, JPG and GIF images found under the folder
	 * specified in the path option.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function images($options)
	{
		$path = $this->sanitizePath(
			Wb\arrayGet(
				$options,
				'path',
				''
			)
		);

		$images = [];

		foreach (new \DirectoryIterator($path) as $fileInfo)
		{
			if (
				$fileInfo->isDot()
				||
				$fileInfo->isDir()
			) {
				continue;
			}

			if (!in_array(
				$fileInfo->getExtension(),
				$this->allowedImageExtensions)
			) {
				continue;
			}

			$images[] = $fileInfo->getFilename();
		}

		return [
			'data'  => $images,
			'count' => count($images),
			'total' => count($images),
		];
	}

	/**
	 * List all the folders found under the folder
	 * specified in the path option.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function folders($options)
	{
		$path = $this->sanitizePath(
			Wb\arrayGet(
				$options,
				'path',
				''
			)
		);

		$folders = [];

		foreach (new \DirectoryIterator($path) as $fileInfo)
		{
			if (
				$fileInfo->isDot()
				||
				$fileInfo->isFile()
			) {
				continue;
			}

			$folders[] = $fileInfo->getFilename();
		}

		return [
			'data'  => $folders,
			'count' => count($folders),
			'total' => count($folders),
		];
	}

	/**
	 * Makes sure the provided path is not outside our root path.
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	private function sanitizePath($path)
	{
		if (Wb\contains($path, '..'))
		{
			$path = '';
		}

		$fullPath = realpath(
			Wb\slashTrimJoin(
				[
					$this->rootPath,
					$path
				]
			)
		);

		$fullPath = str_replace(
			'\\',
			'/',
			$fullPath
		);
		if (!Wb\startsWith($fullPath, $this->rootPath))
		{
			$fullPath = $this->rootPath;
		}

		return $fullPath;
	}
}

