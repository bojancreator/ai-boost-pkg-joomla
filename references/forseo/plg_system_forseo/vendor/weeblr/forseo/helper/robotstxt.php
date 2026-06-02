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
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Fs;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

use Weeblr\Wblib\Forseo\RobotsTxtParser;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Robotstxt extends File
{
	/**
	 * @var string Pattern to build a line listing a sitemap file inside of robots.txt. Two \n at the end to delimitate user agent statement
	 */
	private $sitemapRulePattern = "\n# 4SEO-sitemap: %s sitemap - added on %s - Please do not modify manually.\nSitemap: %s\nUser-agent: *\nAllow: %s\n\n# /4SEO-sitemap\n";

	/**
	 * @var string RegEx to remove Sitemap inclusions in robots.txt
	 */
	private $sitemapRuleRemovalPattern = '~\n# 4SEO-sitemap:.+# /4SEO-sitemap\n~iuUs';

	/**
	 * @var
	 */
	private $robotstxtValidator;

	/**
	 * Store a sitemap config for convenience.
	 */
	public function __construct($options = [])
	{
		parent::__construct();

		$this->path = Wb\slashTrimJoin(
			$this->platform->getRootPath(),
			'robots.txt'
		);

		$this->debugLogger->debug(__METHOD__ . ': creating a robots.txt object, initial path: ' . $this->path . ', initial content: ' . Wb\arrayGet($options, 'content', null));
	}

	/**
	 * Inject a list of sitemaps URLs into the robots.txt file on disk.
	 *
	 * @param array $sitemaps List of sitemap types to include.
	 *
	 * @return Robotstxt
	 */
	public function addOrUpdateSitemap($sitemaps = [])
	{
		$this->load()
			 ->removePrevious()
			 ->add($sitemaps)
			 ->write();

		$this->factory
			->getThe('forseo.logger')
			->debug('Sitemap : added/updated line into robots.txt file: url(s): ' . print_r($sitemaps, true));

		return $this;
	}

	/**
	 * Inject a list of sitemaps URLs into the robot.txt file content.
	 *
	 * @param array $sitemaps
	 * @return $this
	 */
	private function add($sitemaps)
	{
		$now           = System\Date::getUTCNow();
		$sitemapHelper = $this->factory->getA(Sitemaps::class);
		foreach ($sitemaps as $type => $sitemapUrl)
		{
			$this->content .= sprintf(
				$this->sitemapRulePattern,
				ucfirst(
					$sitemapHelper->getSitemapTypeSuffix($type)
				),
				$now,
				$sitemapUrl,
				$this->platform->getUrlRewritingPrefix() . System\Route::makeRootRelative(
					Wb\rTrim($sitemapUrl, '.xml')
				)
			);
		}
		return $this;
	}

	/**
	 * Remove sitemap entries from robots.txt on disk, if any.
	 *
	 * @return $this
	 */
	public function removeSitemap()
	{
		if (!file_exists($this->path))
		{
			return $this;
		}

		$this->load()
			 ->removePrevious()
			 ->write();

		return $this;
	}

	/**
	 * Check if the provided URL should be excluded by the current robots.txt.
	 *
	 * @param string $url
	 * @return bool
	 * @throws \Exception
	 */
	public function isExcluded($url)
	{
		$this->load(false); // allow caching of robots.txt content
		if (empty($this->content))
		{
			return false;
		}

		if (!Wb\startsWith($url, '/'))
		{
			throw new \Exception(__METHOD__ . ': Invalid URL passed, does not start with /.');
		}

		return !$this->getRobotstxtValidator()->isUrlAllow($url);
	}

	/**
	 * Build and memoize a robots.txt parser.
	 *
	 * @return RobotsTxtParser\RobotsTxtValidator
	 */
	private function getRobotstxtValidator()
	{
		if (empty($this->robotstxtValidator))
		{
			include_once WBLIB_Forseo_ROOT_PATH . '/vendor/bopoda/robots-txt-parser/vendor/bopoda/robots-txt-parser/src/RobotsTxtParser/RobotsTxtParser.php';
			include_once WBLIB_Forseo_ROOT_PATH . '/vendor/bopoda/robots-txt-parser/vendor/bopoda/robots-txt-parser/src/RobotsTxtParser/RobotsTxtValidator.php';

			$parser                   = new RobotsTxtParser\RobotsTxtParser($this->content);
			$this->robotstxtValidator = new RobotsTxtParser\RobotsTxtValidator($parser->getRules());
		}

		return $this->robotstxtValidator;
	}

	/**
	 * Remove sitemap entries from robots.txt file content, if any.
	 *
	 * @return $this
	 */
	private function removePrevious()
	{
		$this->debugLogger->debug(__METHOD__ . ': before removing previous content from ' . $this->path . ', current is: ' . print_r($this->content, true) . "\n" . ', hash: ' . $this->hash);
		$this->content = preg_replace(
			$this->sitemapRuleRemovalPattern,
			'',
			$this->content
		);
		$this->hash    = md5($this->content);

		$this->debugLogger->debug(__METHOD__ . ': after removing previous content from ' . $this->path . ', new content is: ' . print_r($this->content, true) . "\n" . ', hash: ' . $this->hash);

		return $this;
	}
}