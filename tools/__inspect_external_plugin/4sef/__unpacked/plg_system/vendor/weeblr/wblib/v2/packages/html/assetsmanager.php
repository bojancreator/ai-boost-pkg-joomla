<?php
/**
 * Project:                 4SEF
 *
 * @author           Yannick Gaultier - Weeblr llc
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @package          4SEF
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          @build_version_full_build@
 * @date         2025-06-02
 */

namespace Weeblr\Wblib\Forsef\Html;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Assets-related helper.
 *
 */
class Assetsmanager extends Base\Base
{
	const VERSION = '@build_version_full_build@';

	const ASSETS_PATH = '/assets';

	const DEV = 0;
	const PRODUCTION = 1;
	public $assetsMode = self::DEV;

	private $absoluteRootUrl = null;
	private $rootUrl = null;
	private $absoluteFilesRoot = null;
	private $filesRoot = null;

	/**
	 * @var string
	 */
	private $filesPath;

	public function __construct($options)
	{
		parent::__construct();

		$debug            = Wb\arrayGet($options, 'enableDebug', $this->platform->isDebugEnabled());
		$this->assetsMode = Wb\arrayGet($options, 'assetsMode', $debug ? self::DEV : self::PRODUCTION);

		$this->absoluteRootUrl = Wb\arrayGet($options, 'absoluteRootUrl', $this->platform->getRootUrl(false));
		$this->absoluteRootUrl = rtrim($this->absoluteRootUrl, '/');

		$this->rootUrl = Wb\arrayGet($options, 'rootUrl', $this->platform->getRootUrl());
		$this->rootUrl = rtrim($this->rootUrl, '/');
		$this->rootUrl = empty($this->rootUrl) ? '/' : $this->rootUrl;

		$this->filesRoot = Wb\arrayGet($options, 'filesRoot', $this->platform->getRootPath());
		$this->filesRoot = rtrim($this->filesRoot, '/');

		$this->absoluteFilesRoot = Wb\arrayGet($options, 'absoluteFilesRoot', $this->platform->getRootPath(false));
		$this->absoluteFilesRoot = rtrim($this->absoluteFilesRoot, '/');

		$this->filesPath = Wb\arrayGet($options, 'filesPath', '');
		$this->filesPath = trim($this->filesPath, '/');
	}

	/**
	 * Simply join a relative URL to the root URL set in
	 * this manager constructor.
	 *
	 * @param   string  $relativePath
	 *
	 * @return mixed
	 */
	public function getImageUrl($relativePath)
	{
		return Wb\slashJoin($this->rootUrl, $relativePath);
	}

	/**
	 * Build ups the full URL to a CSS or JS production file, using the content-hash filename.
	 *
	 * @param   string  $name  JS file name, no extension
	 * @param   array   $options
	 *                         pathFromRoot string Path from the root location set in assets manager instance constructor
	 *                         absolute bool If trueish, absolute URL will be used.
	 *
	 * @return string
	 */
	public function getHashedMediaLink($name, $options = array())
	{
		return $this->getMediaLinkMaybeHashed($name, true, $options);
	}

	/**
	 * Build ups the full URL to a CSS or JS file, using its regular name (ie not content-based hashed)
	 *
	 * @param   string  $name  JS file name, no extension
	 * @param   array   $options
	 *                         pathFromRoot string Path from the root location set in assets manager instance constructor
	 *                         absolute bool If trueish, absolute URL will be used.
	 *
	 * @return string
	 */
	public function getMediaLink($name, $options = array())
	{
		return $this->getMediaLinkMaybeHashed($name, false, $options);
	}

	/**
	 * Build ups the full URL to a CSS or JS file, possibly minified/versioned/gzipped
	 *
	 * @param   string  $name    JS file name, no extension
	 * @param   bool    $hashed  Whether to use the content-hashed file name or the regular file name.
	 * @param   array   $options
	 *                           pathFromRoot string Path from the root location set in assets manager instance constructor
	 *                           absolute bool If trueish, absolute URL will be used.
	 *
	 * @return string
	 */
	private function getMediaLinkMaybeHashed($name, $hashed, $options = array())
	{
		$pathFromRoot = Wb\arrayGet($options, 'pathFromRoot', '');
		$pathFromRoot = trim($pathFromRoot, '/');
		$absolute     = Wb\arrayGet($options, 'absolute', false);

		return $this->getMedia('url', $name, $pathFromRoot, $hashed, $absolute);
	}

	/**
	 * Build ups the full PATH (including filename) to a CSS or JS file, possibly minified/versioned/gzipped
	 *
	 * @param   string  $name  JS file name, no extension
	 * @param   array   $options
	 *                         pathFromRoot string Path from the root location set in assets manager instance constructor
	 *                         hashed bool Locate the hashed version of the file
	 *
	 * @return string
	 */
	public function getMediaFullPath($name, $options = array())
	{
		$pathFromRoot = Wb\arrayGet($options, 'pathFromRoot', '');
		$pathFromRoot = trim($pathFromRoot, '/');

		$hashed = Wb\arrayGet($options, 'hashed', false);

		// getting a path: files_root is considered URL root
		return $this->getMedia('file', $name, $pathFromRoot, $hashed, $absolute = true);
	}

	private function getMedia($resultType, $name, $pathFromRoot, $hashed, $absolute)
	{
		return $this->buildFullPath($resultType, $name, $pathFromRoot, $hashed, $absolute);
	}

	private function buildFullPath($resultType, $name, $pathFromRoot, $hashed, $absolute)
	{
		if ('file' == $resultType)
		{
			$root = $absolute ? $this->absoluteFilesRoot : $this->filesRoot;
		}
		else
		{
			$root = $absolute ? $this->absoluteRootUrl : $this->rootUrl;
		}

		$filesRoot = System\Route::normalizePath(
			Wb\slashJoin(
				$root,
				$this->filesPath
			)
		);

		if ($this->assetsMode == self::PRODUCTION)
		{
			$link = Wb\slashJoin(
				$filesRoot,
				$pathFromRoot,
				'dist',
				// hashed file name is loaded from "$name.php"
				$hashed ? $this->loadHashedName($name, $pathFromRoot, 'dist') : $name
			);
		}
		else
		{
			// for dev mode, we may or may not have hashed file names.
			$maybeHashedFileName = $hashed ? $this->loadHashedName($name, $pathFromRoot, 'dev') : $name;
			$maybeHashedFileName = empty($maybeHashedFileName) ? $name : $maybeHashedFileName;
			$link                = Wb\slashJoin(
				$filesRoot,
				$pathFromRoot,
				'dev',
				$maybeHashedFileName
			);
		}

		// make sure there are no uneeded leading slashes
		$link = Wb\startsWith($link, '/') ? '/' . ltrim($link, '/') : $link;

		if ('file' == $resultType && !file_exists($link))
		{
			$link = '';
		}

		return str_replace(
			'\\',
			'/',
			$link
		);
	}

	/**
	 * Load the filename of the hashed version of an assets from a PHP file of the same name
	 * created when the assets was hashed.
	 *
	 * ie: /xxxx/example.js ==> /xxxx/example.js.php
	 *
	 * @param   string  $name
	 * @param   string  $pathFromRoot
	 * @param   string  $folder
	 *
	 * @return mixed|string
	 */
	private function loadHashedName($name, $pathFromRoot, $folder = 'dist')
	{
		$hashedFilename = '';
		$filesRoot      = System\Route::normalizePath(
			Wb\slashJoin(
				$this->absoluteFilesRoot,
				$this->filesPath
			)
		);
		$file           = Wb\slashJoin(
			$filesRoot,
			$pathFromRoot,
			$folder,
			$name . '.php'
		);
		if (file_exists($file))
		{
			$hashedFilename = include $file;
		}

		return $hashedFilename;
	}
}
