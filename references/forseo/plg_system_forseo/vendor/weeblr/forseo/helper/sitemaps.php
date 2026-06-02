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

use Weeblr\Forseo\Data;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Sitemaps extends Base\Base
{
	/**
	 * @var string[] Suffixes to be injected in main sitemap file names.
	 */
	private $sitemapSuffixesPerType = [
		Data\Sitemap::CONTENT => '',
		Data\Sitemap::NEWS    => 'news',
		Data\Sitemap::IMAGES  => 'images',
		Data\Sitemap::VIDEOS  => 'videos',
	];

	/**
	 * @var Model\Config The sitemap config object.
	 */
	private $sitemapConfig;

	/**
	 * Store a sitemap config for convenience.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->sitemapConfig = $this->factory
			->getThis('forseo.config', 'sitemaps');
	}

	/**
	 * Build the main XML sitemap URL.
	 *
	 * @return string
	 */
	public function xmlUrl($type = Data\Sitemap::CONTENT)
	{
		static $url;

		if (is_null($url))
		{
			$url = Wb\slashTrimJoin(
				$this->factory
					->getThis('forseo.config', 'pages')
					->get('canonicalRootUrl'),
				$this->platform->getUrlRewritingPrefix(),
				$this->getMainFileName($type)
			);
		}

		return $url;
	}

	/**
	 * Insert a sitemap type code in the main file name URL, for types
	 * that are different than standard content XML sitemap (ie news, images, videos).
	 *
	 * @param int $type
	 * @return string
	 */
	public function getMainFileName($type = Data\Sitemap::CONTENT)
	{
		$baseFileName = $this->sitemapConfig->get('mainFile');
		$suffix       = $this->getSitemapTypeSuffix($type);

		return str_replace(
			'.xml',
			(
			empty($suffix)
				? ''
				: '.'
			)
			. $suffix
			. '.xml',
			$baseFileName
		);
	}

	/**
	 * Get the string suffix identifying sitemap type in file name and URLs.
	 *
	 * @param int $type
	 * @return string
	 */
	public function getSitemapTypeSuffix($type = Data\Sitemap::CONTENT)
	{
		return Wb\arrayGet(
			$this->sitemapSuffixesPerType,
			$type,
			''
		);
	}

	/**
	 * Check if a provided file name matches a partial file name structure.
	 *
	 * @param string $fileName
	 * @return bool
	 */
	public function isPartialFileName($fileName)
	{
		$types = array_map(
			function ($type)
			{
				return '\.' . $type;
			},
			array_filter(
				array_values(
					$this->sitemapSuffixesPerType
				)
			)
		);

		$sitemapFilesPattern = str_replace(
			'{{types}}',
			implode(
				'|',
				$types
			),
			$this->sitemapConfig->get('fileNamePatternRegExp')
		);

		$match = preg_match(
			$sitemapFilesPattern,
			$fileName
		);

		return !empty($match);
	}

	/**
	 * Include a line in robots.txt to direct search engines to xml sitemap(s) if a flag
	 * has been set during installation. This avoids having to update robots.txt file only
	 * when a new sitemap is available, which implies managing concurrency to read/write that file.
	 * Instead we just insert the sitemap line(s) into the file right after installation, and then
	 * only when configuration is modified (ie: disabling sitemap, disabling insertion in robots.txt).
	 *
	 * Called only in the admin, normally will be executed right after installation, so we're sure to
	 * run at least once.
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function updateRobotsTxtAfterInstall($sitemaps = [Data\Sitemap::CONTENT])
	{
		$keyStore = $this->factory
			->getThe('forseo.keystore');

		$shouldInsert = $keyStore->get(
			'sitemap.injectInRobotsTxt',
			false
		);

		if (empty($shouldInsert))
		{
			return $this;
		}

		$this->factory->getThe('forseo.logger')->debug('Sitemap: injected sitemap line(s) into robots.txt as sitemap.injectInRobotsTxt flag is set.');

		$this->updateRobotsTxt($sitemaps);

		$keyStore->put('sitemap.injectInRobotsTxt', false);

		return $this;
	}

	/**
	 * Include a line in robots.txt to direct search engines to xml sitemap(s).
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function updateRobotsTxt($sitemaps = [Data\Sitemap::CONTENT])
	{
		$sitemapsConfig = $this->factory->getThis('forseo.config', 'sitemaps');
		if (
			$sitemapsConfig->isFalsy('enabled')
			||
			$sitemapsConfig->isFalsy('addToRobotsTxt')
		) {
			$this->factory
				->getThe('forseo.robotsTxtHelper')
				->removeSitemap();

			$this->factory->getThe('forseo.logger')->debug('Sitemap: removed sitemap entries from robots.txt, if there was any.');

			return $this;
		}

		if (empty($sitemaps))
		{
			$this->factory->getThe('forseo.logger')->debug('Sitemap: not adding sitemap to robots.txt, no sitemap type specificed.');

			return $this;
		}

		$sitemapsList = [];
		foreach ($sitemaps as $sitemapType)
		{
			$sitemapsList[$sitemapType] = $this->xmlUrl($sitemapType);
		}

		$this->factory
			->getThe('forseo.robotsTxtHelper')
			->addOrUpdateSitemap(
				$sitemapsList
			);

		return $this;
	}
}