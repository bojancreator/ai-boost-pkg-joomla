<?php
/**
 * Project: 4SEO
 *
 * @author           Yannick Gaultier - Weeblr llc
 * @copyright        Copyright Weeblr llc - 2020 - 2026
 * @package          4SEO
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          6.10.1.2660
 * @date        2026-01-30
 */

use Joomla\CMS\Plugin;
use Joomla\CMS\Uri\Uri;
use Weeblr\Wblib\Forseo\Wb;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\String\StringHelper as JoomlaStringHelper;

use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

defined('_JEXEC') or die;

/**
 * Handle commercial extension update authorization
 *
 * @since       2.5
 */
class PlgInstallerForseo extends Plugin\CMSPlugin
{
	/**
	 * @var    String  base update url, to decide whether to process the event or not
	 * @since  2.5
	 */
	private $baseUrl = [
		'https://u1.weeblr.com/dist/forseo/full' => 'forseo',
	];

	/**
	 * @var    String  extension identifier, to retrieve its params
	 * @since  2.5
	 */
	private $extension = 'pkg_forseo';

	/**
	 * @var    String    An id for your product, to be used by the web site when deciding to allow access
	 *                    Not mandatory, depends on subscription management system
	 * @since  2.5
	 */
	private $productId = '';

	/**
	 * @var    String    An edition type (full, free, lite,...) for the product
	 *                    Not mandatory, depends on subscription management system
	 * @since  2.5
	 */
	private $productEdition = 'full';

	/**
	 * Handle adding credentials to package download request
	 *
	 * @param string $url     url from which package is going to be downloaded
	 * @param array  $headers headers to be sent along the download request (key => value format)
	 *
	 * @return  boolean    true        always true
	 *
	 * @since   2.5
	 * @throws Exception
	 */
	public function onInstallerBeforePackageDownload(&$url, &$headers)
	{
		// are we trying to update our extension?
		foreach ($this->baseUrl as $baseUrl => $productId)
		{
			// cannot use Wb\startsWith() here as this needs
			// to run when updating through the CLI, where
			// wblib init code (at onAfterRoute) is not executed
			if (
				!empty($baseUrl)
				&&
				0 === JoomlaStringHelper::strpos($url, $baseUrl)
			) {
				$this->productId = $productId;
				break;
			}
		}

		// not one of our URLs.
		if (empty($this->productId))
		{
			return true;
		}

		// read credentials from extension params or any other source
		$credentials = $this->fetchCredentials($url, $headers);

		// bind credentials to request, either in the urls, or using headers
		// or a combination of both
		$this->bindCredentials($credentials, $url, $headers);

		return true;
	}

	/**
	 * Bind credentials to the download request.
	 * In Joomla 4, update key is manager by Joomla and added to the URL.
	 * Prior to J4, we are using a header. Better but not how it was done in J4.
	 *
	 * @param array  $credentials whatever credentials were retrieved for the current user/website
	 * @param string $url         url from which package is going to be downloaded
	 * @param array  $headers     headers to be sent along the download request (key => value format)
	 *
	 * @return void
	 */
	private function bindCredentials($credentials, &$url, &$headers)
	{
		$headers['X-download-auth-ts'] = time();
		$headers['X-download-auth-id'] = $credentials['id'];

		$host                               = Uri::getInstance()->getHost();
		$host                               = preg_replace('/^www\./', '', $host);
		$headers['X-wblr-origin']           = sha1($host);
		$headers['X-wblr-version-src']      = '6.10.1.2660';
		$headers['X-wblr-version-platform'] = \JVERSION;
		$headers['X-wblr-product-id']       = $this->productId;
	}

	/**
	 * Retrieve user credentials
	 *
	 * @param $url
	 * @param $headers
	 *
	 * @return mixed an array with credentials (id, secret), or null if none found
	 * @throws Exception
	 */
	private function fetchCredentials($url, $headers)
	{
		// maybe the key was put in URL already by something like Watchful?
		$parsedUrl = parse_url($url, PHP_URL_QUERY);
		if (!empty($parsedUrl))
		{
			$parsedQueryVars = [];
			parse_str($parsedUrl, $parsedQueryVars);
			// cannot use Wb\arrayGet() here as this needs
			// to run when updating through the CLI, where
			// wblib init code (at onAfterRoute) is not executed
			$urlKey = empty($parsedQueryVars['wblr_k'])
				? ''
				: $parsedQueryVars['wblr_k'];
			$urlKey = trim($urlKey);
			if (!empty($urlKey))
			{
				$credentials = ['id' => $urlKey];
			}
		}

		if (empty($credentials['id']))
		{
			// fetch credentials from extension parameters
			$config            = $this->getConfigFromDb(
				'forseo',
				'system'
			);
			$credentials       = [
				'id' => empty($config['dlid'])
					? ''
					: $config['dlid']
			];
			$credentials['id'] = trim($credentials['id']);
		}

		// no update id found, display error
		$app = Factory::getApplication();
		if (
			empty($credentials['id'])
			&&
			$app->isClient('administrator')
		) {
			$this->loadLanguage();
			$app->enqueueMessage(
				Text::sprintf(
					'PLG_INSTALLER_FORSEO_UPDATE_NO_CREDENTIALS',
					'https://weeblr.com/doc/products.forseo/current/getting-started/installation-update/'
				),
				'error'
			);
			$app->redirect('index.php?option=com_installer&view=update');
		}

		return $credentials;
	}

	/**
	 * Read from the #__forsef_config table the system config
	 * and decode it from json to an array.
	 *
	 * @return array
	 */
	private function getConfigFromDb($extension, $key)
	{
		try
		{
			$db    = $this->getPlatformDb();
			$query = $db->getQuery(true)
						->select('value')
						->from($db->qn('#__' . $extension . '_config'))
						->where($db->qn('key') . '=' . $db->q($key));
			$json  = $db->setQuery($query)
						->loadResult();
			return (array)json_decode($json, true);
		}
		catch (Exception $e)
		{
			return [];
		}
	}

	/**
	 * Wrapper to get the platform DB object regardless of platform version.
	 *
	 * @return mixed
	 */
	private function getPlatformDb()
	{
		return version_compare(\JVERSION, '4.0', '<')
			? Factory::getDbo()
			: Factory::getContainer()->get('db');
	}
}

