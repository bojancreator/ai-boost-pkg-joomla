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

namespace Weeblr\Forseo\Helper;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Fs;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class File extends Base\Base
{
	/**
	 * @var string full path to the file
	 */
	public $path = '';

	/**
	 * @var string|null Buffer to store the content of the file
	 */
	public $content = null;

	/**
	 * @var string MD5 hash of the file content.
	 */
	public $hash = '';

	protected $debugLogger;

	/**
	 * Store a sitemap config for convenience.
	 */
	public function __construct($options = [])
	{
		parent::__construct();

		$this->content = Wb\arrayGet($options, 'content', null);
		$this->path    = Wb\arrayGet($options, 'path', '');

		$this->debugLogger = new System\Log(
			'forseo_debugger',
			Features::isEnabled('enableDebugLogger')
				? System\Log::LOGGING_DETAILED
				: System\Log::LOGGING_NONE
		);
	}

	/**
	 * Reads content of any existing robots.txt file into buffer.
	 *
	 * @return $this
	 */
	public function load($force = true)
	{
		if (is_null($this->content) || $force)
		{
			$this->content =
				file_exists($this->path)
				&&
				is_file($this->path)
					? Fs\File::forceRead($this->path)
					: '';

			$this->hash = md5($this->content);
			$this->debugLogger->debug(__METHOD__ . ': loaded content from ' . $this->path . ', got: ' . "\n" . print_r($this->content, true) . "\n" . ', hash: ' . $this->hash);
		}

		return $this;
	}

	/**
	 * Write content of current buffer into robots.txt. This may result into an empty robots.txt.
	 *
	 * @return $this
	 */
	public function write()
	{
		$this->debugLogger->debug(__METHOD__ . ': writing content to disk for ' . $this->path . ', content to write is: ' . print_r($this->content, true) . "\n" . ', hash: ' . $this->hash);

		$updatedContent = StringHelper::trim(
			$this->content
		);
		if (empty($updatedContent))
		{
			Fs\File::forceDelete($this->path);
			$this->debugLogger->debug(__METHOD__ . ': writing content to disk for ' . $this->path . ', updated content is empty, deleting file');
		}
		else
		{
			Fs\File::forceWrite(
				$this->path,
				$this->content
			);
			$this->debugLogger->debug(__METHOD__ . ': writing content to disk for ' . $this->path . ', content was written to disk.');
		}

		return $this;
	}
}
