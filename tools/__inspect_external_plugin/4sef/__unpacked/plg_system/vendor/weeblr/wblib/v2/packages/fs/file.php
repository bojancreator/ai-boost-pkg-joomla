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

namespace Weeblr\Wblib\Forsef\Fs;

use Weeblr\Wblib\Forsef\Wb;

/** ensure this file is being included by a parent file */
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Manipulate Path on the file system
 *
 */
class File
{
	/**
	 * This method taken from the Joomla! platform, see (c) notice below
	 */
	/**
	 * @package     Joomla.Platform
	 * @subpackage  FileSystem
	 *
	 * @copyright   Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
	 * @license     GNU General Public License version 2 or later; see LICENSE
	 */
	/**
	 * Makes path name safe to use.
	 *
	 * @param   string  $path  The full path to sanitise.
	 *
	 * @return  string  The sanitised string.
	 *
	 * @since   11.1
	 */
	public static function makeSafePath($path)
	{
		$regex = array('#[^A-Za-z0-9_\\\/\(\)\[\]\{\}\#\$\^\+\.\'~`!@&=;,-]#');

		return preg_replace($regex, '', $path);
	}

	/**
	 * Makes the file name safe to use
	 *
	 * @param   string  $file        The name of the file [not full path]
	 * @param   array   $stripChars  Array of regex (by default will remove any leading periods)
	 *
	 * @return  string  The sanitised string
	 *
	 * @since   1.0
	 */
	public static function makeSafe($file, array $stripChars = ['#^\.#'])
	{
		// Try transliterating the file name using the native php function
		if (function_exists('transliterator_transliterate') && function_exists('iconv')) {
			// Using iconv to ignore characters that can't be transliterated
			$file = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", transliterator_transliterate('Any-Latin; Latin-ASCII', $file));
		}

		$regex = array_merge(['#(\.){2,}#', '#[^A-Za-z0-9\.\_\- ]#'], $stripChars);
		$file  = preg_replace($regex, '', $file);

		// Remove any trailing dots, as those aren't ever valid file names.
		$file = rtrim($file, '.');

		return trim($file);
	}

	/**
	 * This method taken from the Joomla! platform, see (c) notice below
	 */
	/**
	 * @package     Joomla.Platform
	 * @subpackage  FileSystem
	 *
	 * @copyright   Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
	 * @license     GNU General Public License version 2 or later; see LICENSE
	 */

	/**
	 * @package     Joomla.Platform
	 * @subpackage  FileSystem
	 *
	 * @copyright   Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
	 * @license     GNU General Public License version 2 or later; see LICENSE
	 */
	public static function find($paths, $file)
	{
		// Force to array
		if (!is_array($paths) && !($paths instanceof \Iterator))
		{
			settype($paths, 'array');
		}

		// Start looping through the path set
		foreach ($paths as $path)
		{
			// Get the path to the file
			$fullname = Wb\slashJoin($path, $file);

			// Is the path based on a stream?
			if (!Wb\contains($path, '://'))
			{
				// Not a stream, so do a realpath() to avoid directory
				// traversal attempts on the local file system.

				// Needed for substr() later
				$path     = realpath($path);
				$fullname = realpath($fullname);
			}

			/*
			 * The substr() check added to make sure that the realpath()
			 * results in a directory registered so that
			 * non-registered directories are not accessible via directory
			 * traversal attempts.
			 */
			if (file_exists($fullname) && substr($fullname, 0, strlen($path)) == $path)
			{
				return $fullname;
			}
		}

		// Could not find the file in the set of paths
		return false;
	}

	/**
	 * This method taken from the Joomla! platform, see (c) notice below
	 */
	/**
	 * Searches the directory paths for a given file.
	 *
	 * @param   mixed   $paths  An path string or array of path strings to search in
	 * @param   string  $file   The file name to look for.
	 *
	 * @return  mixed   The full path and file name for the target file, or boolean false if the file is not found in
	 *     any of the paths.
	 *
	 * @since   11.1
	 */

	/**
	 * Include a file, if it exists and return its content
	 *
	 * @param   String  $fileName  Full path to file
	 *
	 * @return String
	 */
	public static function getIncludedFile($fileName)
	{
		$includedFile = '';
		if (file_exists($fileName))
		{
			ob_start();
			include $fileName;
			$includedFile = ob_get_contents();
			if (ob_get_length())
			{
				ob_end_clean();
			}
		}

		return $includedFile;
	}

	/**
	 * Makes file name safe to use
	 *
	 * @param   string  $file  The name of the file [not full path]
	 *
	 * @return  string  The sanitised string
	 *
	 * @since   11.1
	 */
	public function makeSafeFilename($file)
	{
		// Remove any trailing dots, as those aren't ever valid file names.
		$file = rtrim($file, '.');

		$regex = array('#(\.){2,}#', '#[^A-Za-z0-9\.\_\- ]#', '#^\.#');

		return trim(preg_replace($regex, '', $file));
	}

	/**
	 * Force download by user of some content or an existing file.
	 *
	 * @param   string  $displayName  The display name of the file, will be used by browser to save te file.
	 * @param   string  $filename     The file (fullpathed) name of the file to download, if no content has been provided.
	 * @param   array   $options      A set of options for the download:
	 *                                'content': the actual content to download, used instead of reading a file from disk if provided.
	 *                                'content_type': mime type for the file, defaults to Application/octet-stream.
	 *                                'cookies': an array of cookies defintion: array('name' => 'xxx', 'value'=>'xxx', 'expire' => 123456789).
	 *                                'headers' an array of headers for the response, as strings.
	 *                                'die' : if true, we'll exist after triggering the download. If false, control is returned to the caller,
	 *                                returned value is boolean true.
	 *
	 * @return bool
	 */
	public static function triggerDownload($displayName, $filename = null, $options = array())
	{
		$fileContent = Wb\arrayGet($options, 'content', null);
		$fromFile    = is_null($fileContent);

		// caller wants to download a file, but does the file exist?
		if (
			$fromFile
			&&
			(empty($filename) || !file_exists($filename))
		)
		{
			return false;
		}

		// get filesize
		if ($fromFile)
		{
			$filesize = @filesize($filename);
		}
		else
		{
			$filesize = strlen($fileContent);
		}

		if (ob_get_length())
		{
			ob_end_clean();
		} //flush any other stuff from the ouput buffer

		// output
		header('Expires: 0');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
		header('Pragma: public');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Accept-Ranges: bytes');
		header('Content-Transfer-Encoding: binary');
		header('Content-Length: ' . $filesize);
		header('Content-Type: ' . Wb\arrayGet($options, 'content_type', 'Application/octet-stream'));
		header('Content-Disposition: attachment; filename="' . $displayName . '"');
		header('Connection: close');
		$headers = Wb\arrayGet($options, 'headers', array());
		if (!empty($headers))
		{
			foreach ($headers as $header)
			{
				header($header);
			}
		}

		$cookies = Wb\arrayGet($options, 'cookies', array());
		if (!empty($cookies))
		{
			foreach ($cookies as $cookieDef)
			{
				setcookie(Wb\arrayGet($cookieDef, 'name'), Wb\arrayGet($cookieDef, 'value'), Wb\arrayGet($cookieDef, 'expire'));
			}
		}

		if ($fromFile)
		{
			// read file content by chunks and send it
			$offset = 0;
			do
			{
				$chunk = self::read($filename, $incpath = false, $amount = 81920, $chunksize = 8192, $offset);
				if ($chunk)
				{
					print $chunk;
					$offset += strlen($chunk);
				}
			} while ($chunk);
		}
		else
		{
			// just print the provided content
			print $fileContent;
		}

		// die, to have file content sent
		$die = Wb\arrayGet($options, 'die', true);
		if ($die)
		{
			exit();
		}

		return true;
	}

	/**
	 * This method taken from the Joomla! platform, see (c) notice below
	 */
	/**
	 * Read the contents of a file
	 *
	 * @param   string   $filename   The full file path
	 * @param   boolean  $incpath    Use include path
	 * @param   integer  $amount     Amount of file to read
	 * @param   integer  $chunksize  Size of chunks to read
	 * @param   integer  $offset     Offset of the file
	 *
	 * @return  mixed  Returns file contents or boolean False if failed
	 *
	 * @since   11.1
	 */
	public static function read($filename, $incpath = false, $amount = 0, $chunksize = 8192, $offset = 0)
	{
		$data = null;

		if ($amount && $chunksize > $amount)
		{
			$chunksize = $amount;
		}

		if (false === $fh = fopen($filename, 'rb', $incpath))
		{

			return false;
		}

		clearstatcache();

		if ($offset)
		{
			fseek($fh, $offset);
		}

		if ($fsize = @ filesize($filename))
		{
			if ($amount && $fsize > $amount)
			{
				$data = fread($fh, $amount);
			}
			else
			{
				$data = fread($fh, $fsize);
			}
		}
		else
		{
			$data = '';

			/*
			 * While it's:
			 * 1: Not the end of the file AND
			 * 2a: No Max Amount set OR
			 * 2b: The length of the data is less than the max amount we want
			 */
			while (!feof($fh) && (!$amount || strlen($data) < $amount))
			{
				$data .= fread($fh, $chunksize);
			}
		}

		fclose($fh);

		return $data;
	}

	/**
	 * Wrapper around PHP file_get_contents that tries to workaround
	 * permissions issues.
	 *
	 * @param   string  $file  Full path.
	 *
	 * @return string
	 * @throws \Exception
	 */
	public static function forceRead($file)
	{
		$perms = null;
		if (!is_readable($file))
		{
			$perms = intval(substr(sprintf('%o', fileperms($file)), -4), 8);
			@chmod($file, 0755);
		}
		$content = @file_get_contents($file);
		if (false === $content)
		{
			throw new \Exception('Cannot read content of file ' . $file);
		}

		if (!is_null($perms))
		{
			@chmod($file, $perms);
		}

		return $content;
	}

	/**
	 * Wrapper around PHP file_put_contents that tries to workaround
	 * permissions issues.
	 *
	 * @param   string  $file     Full path.
	 * @param   string  $content  Content to write.
	 *
	 * @throws \Exception
	 */
	public static function forceWrite($file, $content)
	{
		$perms = null;
		if (file_exists($file) && !is_writable($file))
		{
			$perms = intval(substr(sprintf('%o', fileperms($file)), -4), 8);
			@chmod($file, 0755);
		}
		$opResult = @file_put_contents($file, $content);
		if (false === $opResult)
		{
			throw new \Exception('Cannot write to file ' . $file);
		}

		if (!is_null($perms))
		{
			if (empty($perms))
			{
				// sometimes readinf existing perms fails.
				@chmod($file, 0644);
			}
			else
			{
				@chmod($file, $perms);
			}
		}
	}

	/**
	 * Wrapper around PHP unlink that tries to workaround
	 * permissions issues.
	 *
	 * @param   string  $file  Full path.
	 *
	 * @throws \Exception
	 */
	public static function forceDelete($file)
	{
		$perms = null;
		if (!is_writable($file))
		{
			$perms = intval(substr(sprintf('%o', fileperms($file)), -4), 8);
			@chmod($file, 0755);
		}
		$opResult = @unlink($file);
		if (false === $opResult)
		{
			throw new \Exception('Cannot delete file ' . $file);
		}

		if (!is_null($perms))
		{

			unlink($file);
		}
	}
}
