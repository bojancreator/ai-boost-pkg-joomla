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

namespace Weeblr\Forseo\Model\Injector;

use Weeblr\Forseo\Data\Requestinfo;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Html;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Ogp extends Base\Base
{
	/**
	 * @var Requestinfo Convenience instance of the current request details.
	 */
	private $requestInfo = null;

	/**
	 * @var array Holds all the structured data to be output as json-ld
	 */
	private $data = [];

	/**
	 * Loads pre-computed info about the request.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->requestInfo = $this->factory->getThe('forseo.requestInfo');
	}

	/**
	 * Builds the final values for the data to inject
	 */
	public function build()
	{
		$config = $this->factory->getThis('forseo.config', 'socialnetworks');
		if ($config->isFalsy('ogpEnabled'))
		{
			return $this;
		}

		$this->data = [
			'og:locale'    => str_replace('-', '_', $this->requestInfo->get('page_language', '')),
			'og:url'       => $this->requestInfo->get('page_url', ''),
			'og:site_name' => str_replace('"', '\'', $this->requestInfo->get('site_name', '')),
			'og:type'      => 'article'
		];

		// Can have per page title and description just for OGP
		$title                  = $this->requestInfo->get('page_custom_title_ogp', '');
		$this->data['og:title'] = empty($title)
			? str_replace('"', '\'', $this->requestInfo->getPageTitle())
			: $title;

		$description                  = $this->requestInfo->get('page_custom_description_ogp', '');
		$this->data['og:description'] = empty($description)
			? str_replace('"', '\'', $this->requestInfo->getMetaDescription())
			: $description;

		if ($config->isTruthy('dummyFbAppId'))
		{
			// Hardcoded sample app_id. Prevents false warning in FB debugger
			// which still requires this option while it's not been in use
			// for a long time. See internal ticket: "Remove fb:appid tag from OGP settings"
			$this->data['fb:app_id'] = $config->get('dummyFbAppId', '966242223397117');
		}

		$image = $this->requestInfo->get('page_custom_sharing_image', '');
		if (empty($image))
		{
			$image = $this->requestInfo->get('page_sharing_image', '');
		}
		if (empty($image))
		{
			$image = $config->get('defaultImage', '');
		}
		if (!empty($image))
		{
			$url       = Wb\arrayGet($image, 'url', '');
			$imageData =
				[
					'og:image'        => $url,
					'og:image:width'  => Wb\arrayGet($image, 'width', 0),
					'og:image:height' => Wb\arrayGet($image, 'height', 0),
				];

			$alt = Wb\arrayGet($image, 'alt');
			if (!empty($alt))
			{
				$imageData['og:image:alt'] = str_replace('"', '\'', $alt);
			}

			if (Wb\startsWith($url, 'https://'))
			{
				$imageData['og:image:secure_url'] = $url;
			}

			$this->data = array_merge(
				$this->data,
				[
					'og:image' => [
						$imageData
					]
				]
			);
		}

		// filter out empty values
		$this->data = array_filter(
			$this->data
		);

		/**
		 * Filter the data array used to build the OGP meta tags.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\frontend\features
		 * @var forseo_ogp_data
		 * @since   4.7.1
		 *
		 * @param array       $ogpData     Computed OpenGraph Data as a key/value array
		 * @param Requestinfo $requestInfo Instance of the current request details.
		 *
		 * @return array
		 *
		 */
		$this->data = $this->factory
			->getThe('hook')
			->filter(
				'forseo_ogp_data',
				$this->data,
				$this->requestInfo
			);

		return $this;
	}

	/**
	 * Inject structured data as OGP meta.
	 */
	public function inject()
	{
		if (empty($this->data))
		{
			return $this;
		}

		$htmlHelper = $this->factory->getA(Html\Helper::class);
		foreach ($this->data as $property => $content)
		{
			if (is_array($content))
			{
				foreach ($content as $propertyGroup)
				{
					foreach ($propertyGroup as $propertyName => $propertyContent)
					{
						$this->injectTag($htmlHelper, $propertyName, $propertyContent);
					}
				}
			}
			else
			{
				$this->injectTag($htmlHelper, $property, $content);
			}
		}

		return $this;
	}

	/**
	 * Actually build and inject the html tag.
	 *
	 * @param $htmlHelper
	 * @param $property
	 * @param $content
	 * @return void
	 */
	private function injectTag($htmlHelper, $property, $content)
	{
		$meta = $htmlHelper->makeTag(
			'meta',
			[
				'property' => $property,
				'content'  => $content,
				'class'    => '4SEO_ogp_tag',
			],
			'',
			[
				'escapeAttrFlags' => ENT_COMPAT
			]
		);
		$this->platform->addCustomTag(
			$meta
		);
	}
}