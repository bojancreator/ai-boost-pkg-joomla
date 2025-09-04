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
use Weeblr\Wblib\Forsef\Factory;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Interface to online QRCode generator.
 *
 */
class Qrcode
{
	const ROOT_URL = 'https://qrcode.tec-it.com/API/QRCode';

	/**
	 * Build a link to an online generated QRCode
	 *
	 * @param   string  $data
	 * @param   array   $options
	 *
	 * @return string
	 */
	public static function getLink($data, $options = [])
	{
		if (empty($data))
		{
			return '';
		}

		$defaults = [
			'errorcorrection' => 'L',
			'size'            => 'small'
		];

		$options = array_merge(
			$defaults,
			$options
		);

		return self::ROOT_URL . '?data=' . urlencode($data) . '&' . \http_build_query($options);
	}

	/**
	 * Build an img tag to display an online generated QRCode
	 *
	 * @param   string  $data
	 * @param   array   $options
	 *
	 * @return string
	 */
	public static function getImageTag($data, $attributes = [], $options = [])
	{
		$link = self::getLink($data, $options);
		if (empty($link))
		{
			return '';
		}

		$size = self::getPixelSize(
			Wb\arrayGet(
				$options,
				'size',
				'small'
			)
		);

		$attributes = array_merge(
			[
				'src'    => $link,
				'width'  => $size,
				'height' => $size
			],
			$attributes
		);

		return Factory::get()->getA(Helper::class)->makeTag(
			'img',
			$attributes,
			'',
			['close' => true]
		);
	}

	/**
	 * Figure out pixel size of a QRCode based on tect-it.com convention.
	 *
	 * @param   string  $size
	 *
	 * @return int
	 */
	private static function getPixelSize($size)
	{
		switch ($size)
		{
			case 'medium':
				$pixelSize = 547;
				break;
			case 'large':
				$pixelSize = 938;
				break;
			default:
				$pixelSize = 200;
		}

		return $pixelSize;
	}
}
