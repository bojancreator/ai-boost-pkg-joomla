<?php
/**
 * 4SEF
 *
 * @author       Yannick Gaultier - Weeblr llc
 * @copyright    Copyright Weeblr llc - 2022 -2025
 * @package      4SEF
 * @license      GNU General Public License version 3; see LICENSE.md
 * @version      2.6.2.644
 * @date        2025-06-02
 */

namespace Weeblr\Wblib\Forsef\Html\Remoteimage;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;


/**
 * Derived from:
 *
 * FastImage - Because sometimes you just want the size!
 * Based on the Ruby Implementation by Steven Sykes (https://github.com/sdsykes/fastimage)
 *
 * Copyright (c) 2012 Tom Moor
 * Tom Moor, http://tommoor.com
 *
 * MIT Licensed
 * @version 0.1
 *
 * and
 *
 * FasterImage - Because sometimes you just want the size, and you want them in
 * parallel!
 *
 * Based on the PHP stream implementation by Tom Moor (http://tommoor.com)
 * which was based on the original Ruby Implementation by Steven Sykes
 * (https://github.com/sdsykes/fastimage)
 *
 * MIT Licensed
 *
 * @version 0.01
 */

/**
 * Class Exifparser
 *
 * @package FasterImage
 */
class Exifparser
{
	/**
	 * @var int
	 */
	protected $width;
	/**
	 * @var  int
	 */
	protected $height;

	/**
	 * @var
	 */
	protected $short;

	/**
	 * @var
	 */
	protected $long;

	/**
	 * @var  StreamableInterface
	 */
	protected $stream;

	/**
	 * @var int
	 */
	protected $orientation;

	/**
	 * Exifparser constructor.
	 *
	 * @param   StreamableInterface  $stream
	 */
	public function __construct($stream)
	{
		$this->stream = $stream;
		$this->parseExifIfd();
	}

	/**
	 * @return int
	 */
	public function getHeight()
	{
		return $this->height;
	}

	/**
	 * @return int
	 */
	public function getWidth()
	{
		return $this->width;
	}

	/**
	 * @return bool
	 */
	public function isRotated()
	{
        return (! empty($this->orientation) && $this->orientation >= 5 && $this->orientation <= 8);
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function parseExifIfd()
	{
		$byte_order = $this->stream->read(2);

		switch ($byte_order)
		{
			case 'II':
				$this->short = 'v';
				$this->long  = 'V';
				break;
			case 'MM':
				$this->short = 'n';
				$this->long  = 'N';
				break;
			default:
				throw new \Exception('wblib: invalid image in parseExifId');
		}

		$this->stream->read(2);

		$offset = current(unpack($this->long, $this->stream->read(4)));

		$this->stream->read($offset - 8);

		$tag_count = current(unpack($this->short, $this->stream->read(2)));

		for ($i = $tag_count; $i > 0; $i--)
		{

			$type = current(unpack($this->short, $this->stream->read(2)));
			$this->stream->read(6);
			$data = current(unpack($this->short, $this->stream->read(2)));

			switch ($type)
			{
				case 0x0100:
					$this->width = $data;
					break;
				case 0x0101:
					$this->height = $data;
					break;
				case 0x0112:
					$this->orientation = $data;
					break;
			}

			if (isset($this->width) && isset($this->height) && isset($this->orientation))
			{
				return true;
			}

			$this->stream->read(2);
		}

		return false;
	}
}