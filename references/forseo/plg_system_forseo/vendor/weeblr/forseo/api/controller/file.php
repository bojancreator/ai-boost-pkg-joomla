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

use Weeblr\Forseo\Helper;
use Weeblr\Wblib\Forseo\Api;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Fs;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class File extends Api\Controller
{
	/**
	 * @var string[] List of path that can be read and modified.
	 */
	protected $authorizedPaths = [
		'robots.txt',
		'sitemap-4seo-custom.txt',
		'.htaccess'
	];

	/**
	 * Builds up an array of data for use in API response.
	 *
	 * @param Weeblr\Wblib\APi\Request $request
	 * @param array                    $options
	 *
	 * @return array
	 */
	public function get($request, $options)
	{
		try
		{
			$path = $this->sanitizePath(
				Wb\arrayGet($options, 'path', '')
			);

			if ($path instanceof \Exception)
			{
				return $path;
			}

			$content = $this->factory
				->getA(
					Helper\File::class,
					[
						'content' => null,
						'path'    => $path
					]
				)->load()
				->content;

			return [
				'data'  => [
					'content' => $content
				],
				'count' => 1,
				'total' => 1,
			];
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception('Unable to read the file content. See error log file on server.', System\Http::RETURN_INTERNAL_ERROR);
		}
	}

	/**
	 * Update file content.
	 *
	 * @param Weeblr\Wblib\APi\Request $request
	 * @param array                    $options
	 *
	 * @return array
	 */
	public function put($request, $options)
	{
		try
		{
			$path = $this->sanitizePath(
				Wb\arrayGet($options, 'path', '')
			);

			if ($path instanceof \Exception)
			{
				return $path;
			}

			$content = $request->getBody();
			$this->factory
				->getA(
					Helper\File::class,
					[
						'content' => $content,
						'path'    => $path
					]
				)->write();

			return [
				'status' => System\Http::RETURN_NO_CONTENT
			];
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception('Unable to save the file content. See error log file on server.', System\Http::RETURN_INTERNAL_ERROR);
		}
	}

	/**
	 * Sanitize provided full path to a file. Does not check existence,
	 * only well-formed file path and inside site root.
	 *
	 * @param string $path
	 * @return \Exception|string
	 */
	private function sanitizePath($path)
	{
		if (!in_array($path, $this->authorizedPaths))
		{
			return new \Exception('Trying to read invalid file path.', System\Http::RETURN_BAD_REQUEST);
		}

		$root = $this->platform->getRootPath();
		$path = Wb\slashTrimJoin(
			$root,
			$path
		);

		return $path;
	}
}
