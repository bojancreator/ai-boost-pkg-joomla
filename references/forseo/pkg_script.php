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
 *
 */

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Plugin;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Version;

// No direct access
defined('_JEXEC') or die;

/**
 * Class Pkg_ForseoInstallerScript
 *
 * https://docs.joomla.org/J3.x:Developing_an_MVC_Component/Adding_an_install-uninstall-update_script_file
 *
 */
class Pkg_ForseoInstallerScript
{
	public const EXTENSION              = 'forseo';
	public const EXTENSION_MEDIA_FOLDER = 'com_forseo';
	public const ASSETS                 = [
		'js'  => [
			'admin',
			'forseo',
			'fe-edit',
			'fe-loader',
			'perf-probe'
		],
		'css' => [
			'admin.base',
			'admin',
			'forseo.base',
			'forseo',
			'fe-edit',
			'fe-edit.base',
			'fe-loader'
		]
	];

	public const MIN_PLATFORM_VERSION     = '3.9.0';
	public const MAX_PLATFORM_VERSION     = '7.0';
	public const INCLUDE_PLATFORM_VERSION = '[]';
	public const EXCLUDE_PLATFORM_VERSION = '[]';

	public const MIN_PHP_VERSION     = '7.2.5';
	public const MAX_PHP_VERSION     = '';
	public const INCLUDE_PHP_VERSION = '[]';
	public const EXCLUDE_PHP_VERSION = '[]';

	public const TYPE_DANGER  = '1_danger';
	public const TYPE_WARNING = '2_warning';
	public const TYPE_INFO    = '3_info';

	public const STATE_CREATED   = 0;
	public const STATE_PENDING   = 1;
	public const STATE_DISMISSED = 2;

	public const DISMISS_TYPE_NONE        = 0;
	public const DISMISS_TYPE_POSTPONABLE = 1;
	public const DISMISS_TYPE_DISMISSABLE = 2;

	public const DELAY_5MN  = 'PT5M';
	public const DELAY_10MN = 'PT10M';
	public const DELAY_15MN = 'PT15M';
	public const DELAY_30MN = 'PT30M';
	public const DELAY_1H   = 'PT1H';
	public const DELAY_24H  = 'P1D';
	public const DELAY_1W   = 'P1W';
	public const DELAY_2W   = 'P2W';
	public const DELAY_1M   = 'P1M';
	public const DELAY_3M   = 'P3M';

	/**
	 * @var array Stores messages that should be added to the messaging system. All output at once after an install/update.
	 */
	private $messages = [];

	/**
	 * @var string[] Those caches will be cleared at postflight.
	 */
	private $cachesToClean = [
		'4seo_updates'
	];

	/**
	 * Called before any type of action
	 *
	 * @param string    $type
	 * @param \stdClass $parent
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function preflight($type, $parent)
	{
		// check Joomla! version
		if (version_compare(\JVERSION, self::MIN_PLATFORM_VERSION, '<') || version_compare(\JVERSION, self::MAX_PLATFORM_VERSION, 'ge'))
		{
			Factory::getApplication()
				   ->enqueueMessage(
					   sprintf(
						   '4SEO requires Joomla! version between %s and %s (you are using %s). Aborting installation',
						   self::MIN_PLATFORM_VERSION, self::MAX_PLATFORM_VERSION, \JVERSION
					   ), 'error'
				   );

			return false;
		}

		if (
			version_compare(phpversion(), self::MIN_PHP_VERSION, '<')
			||
			(
				!empty(self::MAX_PHP_VERSION)
				&&
				version_compare(
					phpversion(), self::MAX_PHP_VERSION, 'ge'
				)
			)
		) {
			Factory::getApplication()
				   ->enqueueMessage(
					   sprintf(
						   '4SEO requires PHP version between %s and %s (you are using %s). Aborting installation',
						   self::MIN_PHP_VERSION, self::MAX_PHP_VERSION, phpversion()
					   ), 'error'
				   );

			return false;
		}

		if (function_exists('apc_clear_cache'))
		{
			@apc_clear_cache();
		}

		if (function_exists('opcache_reset'))
		{
			@opcache_reset();
		}

		return true;
	}

	/**
	 * Called after any type of action
	 *
	 * @param string    $type //install|uninstall|discover_install|update
	 * @param \stdClass $parent
	 *
	 * @return void
	 * @throws Exception
	 */
	public function postflight($type, $parent)
	{
		// auto enable plugins upon first installation
		try
		{
			if (in_array($type, ['install', 'discover_install']))
			{
				$plugins   = [
					[
						'folder'   => 'system',
						'element'  => 'forseo',
						'ordering' => 'last'
					],
					[
						'folder'  => 'installer',
						'element' => 'forseo'
					]
				];
				$db        = $this->getPlatformDb();
				$overrides = ['enabled' => 1];

				foreach ($plugins as $plugin)
				{
					$query = $db->getQuery(true);
					$query->select('extension_id')->from('#__extensions')->where($db->qn('type') . '=' . $db->q('plugin'))
						  ->where($db->qn('element') . '=' . $db->q($plugin['element']))
						  ->where($db->qn('folder') . '=' . $db->q($plugin['folder']));
					$db->setQuery($query);
					$pluginId = $db->loadResult();

					if (
						!empty($plugin['ordering'])
						&&
						'last' == $plugin['ordering'])
					{
						$query = $db->getQuery(true);
						$query->select('MAX(ordering)')->from('#__extensions')->where($db->qn('type') . '=' . $db->q('plugin'))
							  ->where($db->qn('folder') . '=' . $db->q($plugin['folder']));
						$db->setQuery($query);
						$currentLastOrdering   = $db->loadResult();
						$overrides['ordering'] = $currentLastOrdering + 1;
					}

					if (!empty($pluginId))
					{
						$extension = Version::MAJOR_VERSION >= 6
							? new \Joomla\CMS\Table\Extension(
								\Joomla\CMS\Factory::getContainer()->get('db')
							)
							: Table::getInstance('Extension');
						$extension->load($pluginId);
						$extension->bind($overrides);
						$extension->store();
					}
					else
					{
						Factory::getApplication()->enqueueMessage('4SEO: Error updating plugin DB record: ' . $plugin['folder'] . ' / ' . $plugin['element']);
					}
				}
			}

			// clear caches, in case cache handling or remote config has been modified
			foreach ($this->cachesToClean as $cacheName)
			{
				Factory::getCache($cacheName)->clean();
			}

			if (in_array($type, ['install', 'discover_install', 'update']))
			{
				$this->insertMessages();
			}

			// Joomla can't clean up older versions of js and css
			$this->cleanUpMedia();

			// Build a simple post-install message
			if (in_array($type, ['install', 'discover_install', 'update']))
			{
				$logoUrl         = Uri::root(true) . '/media/com_forseo/vendor/weeblr/forseo/assets/images/logo/forseo.svg';
				$thankYouMessage = Language\Text::_('COM_FORSEO_THANK_YOU_INSTALL');
				$html            = <<<HTML

<h1 style="margin:4rem 0 2rem;text-align:center;">{$thankYouMessage}</h1>
HTML;

				if (in_array($type, ['install', 'discover_install']))
				{
					$useMsg = Language\Text::sprintf(
						'COM_FORSEO_INSTALL_ADMIN_LINK',
						'index.php?option=com_forseo&src=installComplete#/forseo/dashboard'
					);
				}
				if ('update' === $type)
				{
					$useMsg = Language\Text::sprintf(
						'COM_FORSEO_UPDATE_ADMIN_LINK',
						'index.php?option=com_forseo&src=updateComplete#/forseo/dashboard'
					);
				}

				$html .= <<<HTML
<p style="text-align:center;">{$useMsg}</p>
<p style="margin: 4rem 0;text-align: center"><img width="150" src="{$logoUrl}"></p>
HTML;
				echo $html;
			}
		}
		catch (\Throwable $e)
		{
			$message = $e->getMessage();
			if (empty($message))
			{
				$message = '<pre>' . print_r($e, true) . '</pre>';
			}
			Factory::getApplication()->enqueueMessage('Error: ' . $message, 'error');
		}
	}

	/**
	 * Called on installation
	 *
	 * @param $parent
	 *
	 * @return void True on success
	 */
	public function install($parent)
	{
		// We run the db update on install in case of re-installing
		// over a past install: db tables already exist but they
		// might be outdated.
		$this->versionSpecificUpdates();
	}

	/**
	 * Called on update
	 *
	 * @param $parent
	 *
	 * @return void True on success
	 */
	public function update($parent)
	{
		$this->versionSpecificUpdates();
	}

	/**
	 * Called on uninstallation
	 *
	 * @param $parent
	 * @throws Exception
	 */
	public function uninstall($parent)
	{
		// are we set to remove all data upon uninstall?
		$db = $this->getPlatformDb();
		try
		{
			$query = $db->getQuery(true);
			$query->select('value')
				  ->from('#__forseo_config')
				  ->where($db->quoteName('scope') . '=' . $db->quote('default'))
				  ->where($db->quoteName('key') . '=' . $db->quote('system'));

			$systemConfigJson = $db->setQuery($query)
								   ->loadResult();

			if (!empty($systemConfigJson))
			{
				$systemConfig = json_decode($systemConfigJson, true);
				if (!empty($systemConfig['uninstallRemoveAllData']))
				{
					// drop all database tables
					$tables   = $db->getTableList();
					$prefix   = $db->getPrefix();
					$toDelete = [];
					foreach ($tables as $table)
					{
						$table = preg_replace('|^' . $prefix . '|', '#__', $table);
						if (0 === strpos($table, '#__forseo_'))
						{
							$toDelete[] = $table;
						}
					}

					if (!empty($toDelete))
					{
						Factory::getApplication()->enqueueMessage('4SEO was configured to delete its data and configuration when uninstalling.');
						Factory::getApplication()->enqueueMessage(count($toDelete) . ' 4SEO database tables to delete...');
						foreach ($toDelete as $table)
						{
							Factory::getApplication()->enqueueMessage('Deleting ' . $table . ' table...');
							$db->dropTable($table);
						}
						Factory::getApplication()->enqueueMessage('All 4SEO code and data was removed from this site.');
					}
				}
			}
		}
		catch (\Throwable $e)
		{
			Factory::getApplication()->enqueueMessage(
				'Error removing data from database, there may be some data left behind in the database . Please try again or contact Weeblr support for assistance. Details: <pre>' . $e->getMessage() . '</pre>'
			);
		}
	}

	/**
	 * Reconcile db table with current version and runs any version-specific
	 * update code.
	 *
	 * Runs AFTER the main SQL file, DB tables are not created here,
	 * only updated.
	 *
	 * IMPORTANT: Look at this file Git history for actual code performing most common
	 * operation.
	 *
	 */
	private function versionSpecificUpdates()
	{
		$versions = [
			'1.0.3',
			'1.0.4',
			'1.1.2',
			'1.3.2',
			'1.4.0',
			'1.4.1',
			'1.5.0',
			'1.6.1',
			'2.0.0',
			'4.3.0',
			'5.2.0',
			'6.8.0'
		];

		foreach ($versions as $version)
		{
			try
			{
				$methodName = 'update' . str_replace('.', '_', $version);
				$this->{$methodName}();
			}
			catch (\Throwable $e)
			{
				Factory::getApplication()->enqueueMessage(
					'Error performing update, this extension may not work properly. Please try again or contact Weeblr support for assistance. Details: <pre>' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n" . '</pre>'
				);
			}
		}
	}

	/**
	 * Schema and other updates for version 1.0.3
	 *
	 * @throws Exception
	 */
	private function update1_0_3()
	{
		$db = $this->getPlatformDb();

		$table   = '#__forseo_errors';
		$columns = $this->getTableColumns($table);
		if (empty($columns))
		{
			throw new \Exception('Cannot read columns of table ' . $table);
		}

		if (
			!empty($columns['hits'])
			&&
			!$this->hasIndex($table, 'hits', 'hits')
		) {
			$db->setQuery(
				'ALTER TABLE ' . $db->quoteName($table)
				. ' ADD KEY (`hits`) COMMENT "To sort by largest number of hits"')
			   ->execute();
		}

		$table   = '#__forseo_links';
		$columns = $this->getTableColumns($table);
		if (empty($columns))
		{
			throw new \Exception('Cannot read columns of table ' . $table);
		}

		if (
			!empty($columns['hits'])
			&&
			!$this->hasIndex($table, 'hits', 'hits')
		) {
			$db->setQuery(
				'ALTER TABLE ' . $db->quoteName($table)
				. ' ADD KEY (`hits`) COMMENT "To sort by largest number of hits"')
			   ->execute();
		}

		// Fix rules storage bug were lots of TYPE_NONE rules are stored
		$query = $db->getQuery(true)
					->delete($db->quoteName('#__forseo_rules'))
					->where($db->quoteName('type') . ' = 0');
		$db->setQuery($query)
		   ->execute();

		// Add hash_links columns to pages
		$table   = '#__forseo_pages';
		$columns = $this->getTableColumns($table);
		if (empty($columns))
		{
			throw new \Exception('Cannot read columns of table ' . $table);
		}

		if (empty($columns['hash_links']))
		{
			$db->setQuery(
				'ALTER TABLE ' . $db->quoteName($table)
				. ' ADD COLUMN ' . $db->quoteName('hash_links') . ' VARCHAR(40) NOT NULL DEFAULT \'\' COMMENT \'Links in content hash\' AFTER ' . $db->quoteName('hash'))
			   ->execute();
		}
	}

	/**
	 * Schema and other updates for version 1.0.4;
	 *
	 * @throws Exception
	 */
	private function update1_0_4()
	{
		$db = $this->getPlatformDb();

		$table   = '#__forseo_pages';
		$columns = $this->getTableColumns($table);
		if (empty($columns))
		{
			throw new \Exception('Cannot read columns of table ' . $table);
		}

		if (empty($columns['perf_status']))
		{
			$db->setQuery(
				'ALTER TABLE ' . $db->quoteName($table)
				. ' ADD COLUMN ' . $db->quoteName('perf_status') . ' TINYINT NOT NULL DEFAULT 0 COMMENT \'0: no data, 1: ok, 2: failing\' AFTER ' . $db->quoteName('status'))
			   ->execute();
		}
	}

	/**
	 * Schema and other updates for version 1.1.2.
	 *
	 * @throws Exception
	 */
	private function update1_1_2()
	{
		$db = $this->getPlatformDb();

		$table   = '#__forseo_sitemaps';
		$columns = $this->getTableColumns($table);
		if (empty($columns))
		{
			throw new \Exception('Cannot read columns of table ' . $table);
		}

		if (empty($columns['hash']))
		{
			$db->setQuery(
				'ALTER TABLE ' . $db->quoteName($table)
				. ' ADD COLUMN ' . $db->quoteName('hash') . ' VARCHAR(40) NOT NULL DEFAULT \'\' COMMENT \'SHA1 hash of the file content\' AFTER ' . $db->quoteName('crawl_id'))
			   ->execute();

			// we must invalidate and rebuild the sitemap, then submit it as the partials naming convention has changed.
			// However, this should not be done from the installation code, so instead we just delete existing records
			// and cached files

			$query = $db->getQuery(true)
						->delete($db->qn('#__forseo_sitemaps'));
			$db->setQuery($query)
			   ->execute();

			$this->deleteFolder(
				JPATH_ROOT . '/media/com_forseo/cache'
			);
		}
	}

	/**
	 * Schema and other updates for version 1.3.2
	 *
	 * @throws Exception
	 */
	private function update1_3_2()
	{
		// Insert task to inject sitemap link into robots.txt
		// This injection was done at each crawl end but this caused concurrency
		// issues. We now only inject the sitemaps lines from the admin, when settings are changed
		// or during the initial installation through this method.
		$this->insertInKeyStore(
			'default',
			'sitemap.injectInRobotsTxt',
			'true'
		);
	}

	/**
	 * Schema and other updates for version 1.4.0
	 *
	 * @throws Exception
	 */
	private function update1_4_0()
	{
		// Add hash_images columns to pages
		$db      = $this->getPlatformDb();
		$table   = '#__forseo_pages';
		$columns = $this->getTableColumns($table);
		if (empty($columns))
		{
			throw new \Exception('Cannot read columns of table ' . $table);
		}

		if (empty($columns['hash_images']))
		{
			$db->setQuery(
				'ALTER TABLE ' . $db->quoteName($table)
				. ' ADD COLUMN ' . $db->quoteName('hash_images') . ' VARCHAR(40) NOT NULL DEFAULT \'\' COMMENT \'Images in content hash\' AFTER ' . $db->quoteName('hash_links'))
			   ->execute();
		}
	}

	/**
	 * Schema and other updates for version 1.4.1
	 *
	 * @throws Exception
	 */
	private function update1_4_1()
	{
		// Add hash_images columns to pages
		$db      = $this->getPlatformDb();
		$table   = '#__forseo_pages';
		$columns = $this->getTableColumns($table);
		if (empty($columns))
		{
			throw new \Exception('Cannot read columns of table ' . $table);
		}

		if (
			!empty($columns['url'])
			&&
			!$this->hasIndex($table, 'url', 'url')
		) {
			$db->setQuery(
				'ALTER TABLE ' . $db->quoteName($table) . ' DROP INDEX (`url`) '
			)->execute();
		}

		if (
			!empty($columns['page_url'])
			&&
			$this->hasIndex($table, 'page_url', 'page_url')
		) {
			$db->setQuery(
				'ALTER TABLE ' . $db->quoteName($table) . ' DROP INDEX (`page_url`) '
			)->execute();
		}

		if (
			!empty($columns['url'])
			&&
			!empty($columns['page_url'])
			&&
			!$this->hasIndex($table, ['page_url', 'url'], 'main')
		) {
			$db->setQuery(
				'ALTER TABLE ' . $db->quoteName($table)
				. ' ADD UNIQUE INDEX main (`page_url`, `url`),'
				. ' ADD INDEX (`url`)'
			)->execute();
		}

		$table   = '#__forseo_sitemaps';
		$columns = $this->getTableColumns($table);
		if (empty($columns))
		{
			throw new \Exception('Cannot read columns of table ' . $table);
		}
		//   `image_count`         INT unsigned NOT NULL DEFAULT 0 COMMENT 'Total number of images in the sitemap'
		if (empty($columns['image_count']))
		{
			$db->setQuery(
				'ALTER TABLE ' . $db->quoteName($table)
				. ' ADD COLUMN ' . $db->quoteName('image_count') . ' INT UNSIGNED NOT NULL DEFAULT 0 COMMENT \'Total number of images in the sitemap\' AFTER ' . $db->quoteName('processed_url_count'))
			   ->execute();
		}
	}

	/**
	 * Schema and other updates for version 1.5.0
	 *
	 * @throws Exception
	 */
	private function update1_5_0()
	{
		// Detect if an sh404SEF URLs table is present. If so, add a flag
		// which may be used to auto-redirect from sh404SEF to Joomla SEF
		// automatically when disabling sh404SEF.
		$db     = $this->getPlatformDb();
		$tables = $db->getTableList();
		$prefix = $db->getPrefix();
		foreach ($tables as $table)
		{
			if ($table === $prefix . 'sh404sef_urls')
			{
				$this->insertInKeyStore(
					'default',
					'extensions.sh404sef.hasSefUrls',
					'true'
				);
				break;
			}
		}

		// change rules type column type, too small, for convenience
		$table   = '#__forseo_rules';
		$columns = $this->getTableColumns($table);
		if (empty($columns))
		{
			throw new \Exception('Cannot read columns of table ' . $table);
		}
		$db->setQuery(
			'ALTER TABLE ' . $db->quoteName($table)
			. ' MODIFY ' . $db->quoteName('type') . ' SMALLINT NOT NULL DEFAULT 0 COMMENT \'See Data - Rule object\''
		)->execute();

		$table   = '#__forseo_collected_urls';
		$columns = $this->getTableColumns($table);
		if (empty($columns))
		{
			throw new \Exception('Cannot read columns of table ' . $table);
		}
		//     `priority`  TINYINT NOT NULL DEFAULT 0 COMMENT 'Crawl priority, 0 is normal',
		if (empty($columns['priority']))
		{
			$db->setQuery(
				'ALTER TABLE ' . $db->quoteName($table)
				. ' ADD COLUMN ' . $db->quoteName('priority') . ' TINYINT NOT NULL DEFAULT 0 COMMENT \'Crawl priority, 0 is normal\' ')
			   ->execute();

			$db->setQuery(
				'ALTER TABLE ' . $db->quoteName($table)
				. ' ADD KEY (`priority`)')
			   ->execute();
		}

		$table   = '#__forseo_keystore';
		$columns = $this->getTableColumns($table);
		if (empty($columns))
		{
			throw new \Exception('Cannot read columns of table ' . $table);
		}

		//         `lock_expires_at` DATETIME NULL
		if (empty($columns['lock_expires_at']))
		{
			$db->setQuery(
				'ALTER TABLE ' . $db->quoteName($table)
				. ' ADD COLUMN ' . $db->quoteName('lock_expires_at') . ' DATETIME NULL AFTER ' . $db->quoteName('lock')
			)->execute();
		}

		$table   = '#__forseo_config';
		$columns = $this->getTableColumns($table);
		if (empty($columns))
		{
			throw new \Exception('Cannot read columns of table ' . $table);
		}

		//         `lock_expires_at` DATETIME NULL
		if (empty($columns['lock_expires_at']))
		{
			$db->setQuery(
				'ALTER TABLE ' . $db->quoteName($table)
				. ' ADD COLUMN ' . $db->quoteName('lock_expires_at') . ' DATETIME NULL AFTER ' . $db->quoteName('lock')
			)->execute();
		}
	}

	private function injectDefaultErrorPageRule()
	{
		$db = $this->getPlatformDb();

		// 1
		$alreadyInjected = $this->getFromKeyStore(
			'default',
			'error_page.defaultInjected'
		);

		if (!empty($alreadyInjected))
		{
			return;
		}
		// 2 if user already created some rules, don't do it ourselves
		$query    = $db->getQuery(true)
					   ->select('COUNT(' . $db->qn('id') . ')')
					   ->where($db->qn('type') . '= 140')
					   ->from($db->qn('#__forseo_rules'));
		$existing = $db->setQuery($query)->loadResult();
		if (!empty($existing))
		{
			return;
		}

		// should we enable the rules? we do so if:
		// - site is single language
		// - langage is xx-* with xx in a list of languages where we're pretty sure to have a translation.
		$enabled = '0';
		if (!Plugin\PluginHelper::isEnabled('system', 'languagefilter'))
		{
			$allowedLanguages    = ['en', 'fr', 'es', 'tr', 'it', 'pl', 'nl', 'de'];
			$frontendLanguageTag = ComponentHelper::getParams('com_languages')
												  ->get('site', 'en-GB');
			$family              = strtolower(substr($frontendLanguageTag, 0, 2));
			if (in_array($family, $allowedLanguages))
			{
				$enabled = '1';
			}
		}

		// now ready to insert rules
		$dt                       = new \DateTime('now', new \DateTimeZone('UTC'));
		$now                      = $dt->format('Y-m-d H:i:s');
		$actionErrorTitle         = Language\Text::_('COM_FORSEO_ERROR_TITLE_404');
		$actionErrorContent       = Language\Text::_('COM_FORSEO_ERROR_CONTENT_404');
		$actionErrorSuggestTitle  = Language\Text::_('COM_FORSEO_ERROR_SUGGEST_TITLE_404');
		$actionErrorNoSuggestText = '';
		$actionErrorShowDetails   = true;
		$actionErrorCode          = 404;
		$actionErrorSuggest       = true;
		$ruleTitle                = 'Default 404 error page';

		$this->doInjectDefaultErrorPageRule(
			$db,
			$now,
			$actionErrorTitle,
			$actionErrorContent,
			$actionErrorSuggestTitle,
			$actionErrorNoSuggestText,
			$actionErrorShowDetails,
			$actionErrorCode,
			$actionErrorSuggest,
			$ruleTitle,
			$enabled
		);


		$actionErrorTitle         = Language\Text::_('COM_FORSEO_ERROR_TITLE_CATCH_ALL');
		$actionErrorContent       = Language\Text::_('COM_FORSEO_ERROR_CONTENT_CATCH_ALL');
		$actionErrorSuggestTitle  = '';
		$actionErrorNoSuggestText = '';
		$actionErrorShowDetails   = true;
		$actionErrorCode          = 0;
		$actionErrorSuggest       = false;
		$ruleTitle                = 'Default catch-all error page';

		$this->doInjectDefaultErrorPageRule(
			$db,
			$now,
			$actionErrorTitle,
			$actionErrorContent,
			$actionErrorSuggestTitle,
			$actionErrorNoSuggestText,
			$actionErrorShowDetails,
			$actionErrorCode,
			$actionErrorSuggest,
			$ruleTitle,
			$enabled
		);

		$this->insertInKeyStore(
			'default',
			'error_page.defaultInjected',
			"true"
		);
	}

	private function doInjectDefaultErrorPageRule($db, $now, $actionErrorTitle, $actionErrorContent, $actionErrorSuggestTitle, $actionErrorNoSuggestText, $actionErrorShowDetails, $actionErrorCode, $actionErrorSuggest, $ruleTitle, $enabled)
	{
		$pageRecord = [
			'id'                                   => 0,
			'ordering'                             => 0,
			'orderAfter'                           => -1,
			'orderTarget'                          => -1,
			'orderDirection'                       => 'after',
			'type'                                 => 140,
			'title'                                => $ruleTitle,
			// when
			'enabled'                              => $enabled,
			'urlSpec'                              => '/{*}',
			'urlNegSpec'                           => '',
			'disregardQuery'                       => true,
			'disregardCase'                        => true,
			'includedLanguages'                    => [],
			'includedExtensions'                   => [],
			'viewSpec'                             => '',
			'viewNegSpec'                          => '',
			'includedCategories'                   => [],
			'excludedCategories'                   => [],
			'enableAfter'                          => $now,
			'enableUntil'                          => null,
			// what' =>  all
			'actionReappendQuery'                  => false,
			// what' =>  redirect
			'actionRedirectType'                   => 301,
			'actionRedirectTarget'                 => '/',
			// what' =>  replacer
			'actionReplacerLocation'               => 'content', //'any' | 'content' | 'modules' | 'head' | 'body'
			'actionReplacerProtectLinks'           => true,
			'actionReplacerCaseSensitive'          => false,
			'actionReplacerWholeWordsOnly'         => false,
			'actionReplacerType'                   => 'text', // text | link
			'actionReplacerTargetBlank'            => false,
			'actionReplacerNoFollow'               => false,
			'actionReplacerSource'                 => '',
			'actionReplacerTarget'                 => '',
			'actionReplacerMaxReplacements'        => 99999,
			// what' =>  canonical
			'actionCanonicalTarget'                => '/{*}',
			'actionCanonicalTargetUseCf'           => false,
			'actionCanonicalTargetCfId'            => [],
			// what' =>  waf
			'actionWafType'                        => 404,
			// what' =>  rawContent,
			'actionRawContentHeadTop'              => '',
			'actionRawContentHeadBottom'           => '',
			'actionRawContentBodyTop'              => '',
			'actionRawContentBodyBottom'           => '',
			// what => analytics
			'actionAnalyticsProvider'              => '',
			// Universal GA
			'actionAnalyticsUniversalgaId'         => '',
			'actionAnalyticsUniversalgaCustom'     => '',
			'actionAnalyticsGaAnonymize'           => true,
			'actionAnalyticsGaDisplayFeatures'     => false,
			'actionAnalyticsGaAdFeatures'          => true, // can only disable from code
			'actionAnalyticsGaLinkAttribution'     => false,
			'actionAnalyticsGaCustomDomain'        => 'auto',
			'actionAnalyticsGaCustomUrl'           => '',
			'actionAnalyticsGaCookieDomain'        => '',
			'actionAnalyticsGaOptions'             => [],
			// Global site
			'actionAnalyticsGlobalgaId'            => '',
			'actionAnalyticsGlobalgaCustom'        => '',
			// Google Tag manager
			'actionAnalyticsGtmId'                 => '',
			'actionAnalyticsGtmDatalayer'          => '',
			// Facebook Pixel
			'actionAnalyticsFbpixelId'             => '',
			'actionAnalyticsFbpixelCustom'         => '',
			// Matomo
			'actionAnalyticsMatomoId'              => '',
			'actionAnalyticsMatomoEndpoint'        => 'https://matomo.org/',
			'actionAnalyticsMatomoTrackWithoutJs'  => true,
			'actionAnalyticsMatomoCustom'          => '',
			// Clarity
			'actionAnalyticsClarityId'             => '',
			// Cloudflare web
			'actionAnalyticsCloudflareId'          => '',
			// Fathom
			'actionAnalyticsFathomId'              => '',
			'actionAnalyticsFathomCustom'          => '',
			// what => sd
			'actionSdType'                         => '',
			'actionSdUrlAuto'                      => true,
			'actionSdUrl'                          => '',
			'actionSdHeadlineAuto'                 => true,
			'actionSdHeadline'                     => '',
			'actionSdDescriptionAuto'              => true,
			'actionSdDescription'                  => '',
			'actionSdInLanguageAuto'               => true,
			'actionSdInLanguage'                   => '',
			'actionSdAuthorAuto'                   => true,
			'actionSdAuthor'                       => '',
			'actionSdAuthorUrl'                    => '',
			'actionSdImageAuto'                    => true,
			'actionSdImageUrl'                     => '',
			'actionSdImageAlt'                     => '',
			'actionSdImageWidth'                   => 0,
			'actionSdImageHeight'                  => 0,
			'actionSdImagePixels'                  => 0,
			'actionSdPublisherAuto'                => true,
			'actionSdPublisher'                    => '',
			'actionSdPublisherLogoAuto'            => true,
			'actionSdPublisherLogoUrl'             => '',
			'actionSdPublisherLogoAlt'             => '',
			'actionSdPublisherLogoWidth'           => '',
			'actionSdPublisherLogoHeight'          => '',
			'actionSdPublisherLogoPixels'          => '',
			'actionSdDatePublishedAuto'            => true,
			'actionSdDatePublished'                => '',
			'actionSdTimePublished'                => '',
			'actionSdDateModifiedAuto'             => true,
			'actionSdDateModified'                 => '',
			'actionSdTimeModified'                 => '',
			'actionSdAggregateRatingAuto'          => '',
			'actionSdRatingValue'                  => 0,
			'actionSdRatingCount'                  => 0,
			'actionSdWorstRating'                  => 0,
			'actionSdBestRating'                   => 0,
			'actionSdCustom'                       => '',
			// VideoObject
			'actionSdNameAuto'                     => true,
			'actionSdName'                         => '',
			'actionSdThumbnailUrlAuto'             => true,
			'actionSdThumbnailUrl'                 => '',
			'actionSdDateUploadedAuto'             => true,
			'actionSdDateUploaded'                 => '',
			'actionSdTimeUploaded'                 => '',
			'actionSdContentUrlAuto'               => true,
			'actionSdContentUrl'                   => '',
			// Course
			'actionSdProviderAuto'                 => true,
			'actionSdProvider'                     => '',
			// Event
			'actionSdLocationNameAuto'             => true,
			'actionSdLocationName'                 => '',
			'actionSdLocationAuto'                 => true,
			'actionSdLocation'                     => '',
			'actionSdDateStartedAuto'              => true,
			'actionSdDateStarted'                  => '',
			'actionSdTimeStarted'                  => '',
			'actionSdDateEndedAuto'                => true,
			'actionSdDateEnded'                    => '',
			'actionSdTimeEnded'                    => '',
			'actionSdOffersAuto'                   => true,
			'actionSdPerformerAuto'                => true,
			'actionSdPerformer'                    => '',
			'actionSdPerformerType'                => 'Person',
			'actionSdOrganizerAuto'                => true,
			'actionSdOrganizer'                    => '',
			'actionSdEventAttendanceModeAuto'      => true,
			'actionSdEventAttendanceMode'          => 'http://schema.org/OnlineEventAttendanceMode',
			'actionSdEventStatusAuto'              => true,
			'actionSdEventStatus'                  => ['http://schema.org/EventScheduled'],
			'actionSdOfferPriceAuto'               => true,
			'actionSdOfferPrice'                   => 0.0,
			'actionSdOfferPriceCurrencyAuto'       => true,
			'actionSdOfferPriceCurrency'           => 'USD',
			'actionSdOfferDateValidFromAuto'       => true,
			'actionSdOfferDateValidFrom'           => '',
			'actionSdOfferTimeValidFrom'           => '',
			'actionSdOfferAvailabilityAuto'        => true,
			'actionSdOfferAvailability'            => ['http://schema.org/InStock'],
			'actionSdOfferUrlAuto'                 => true,
			'actionSdOfferUrl'                     => '',
			// Product
			'actionSdBrandAuto'                    => true,
			'actionSdBrand'                        => null,
			'actionSdSkuAuto'                      => true,
			'actionSdSku'                          => '',
			'actionSdOfferDatePriceValidUntilAuto' => true,
			'actionSdOfferDatePriceValidUntil'     => '',
			'actionSdOfferTimePriceValidUntil'     => '',
			'actionSdOfferItemConditionAuto'       => true,
			'actionSdOfferItemCondition'           => ['http://schema.org/NewCondition'],
			'actionSdOfferItemConditionCustom'     => '',
			// Recipe
			'actionSdRecipeCategoryAuto'           => true,
			'actionSdRecipeCategory'               => '',
			'actionSdRecipeCuisineAuto'            => true,
			'actionSdRecipeCuisine'                => '',
			'actionSdKeywordsAuto'                 => true,
			'actionSdKeywords'                     => '',
			'actionSdRecipeIngredientAuto'         => true,
			'actionSdRecipeIngredient'             => '',
			'actionSdRecipeInstructionsAuto'       => true,
			'actionSdRecipeInstructions'           => '',
			'actionSdRecipeYieldAuto'              => true,
			'actionSdRecipeYield'                  => '',
			'actionSdPrepTimeAuto'                 => true,
			'actionSdPrepTime'                     => 0,
			'actionSdCookTimeAuto'                 => true,
			'actionSdCookTime'                     => 0,
			'actionSdTotalTimeAuto'                => true,
			'actionSdTotalTime'                    => 0,
			'actionSdCaloriesAuto'                 => true,
			'actionSdCalories'                     => 0,
			// FaqPage
			'actionSdFaqMode'                      => 2,
			'actionSdFaqQCss'                      => [],
			'actionSdFaqACss'                      => [],
			'actionSdFaqMainEntity'                => [],
			// What => sitemap
			'actionSmExclude'                      => false,
			'actionSmExcludeAge'                   => 0, // number of days
			'actionSmExcludeArchived'              => true,
			// What => error page
			'actionErrorTitle'                     => str_replace('\'', '&apos;', $actionErrorTitle),
			'actionErrorContent'                   => str_replace('\'', '&apos;', $actionErrorContent),
			'actionErrorSuggest'                   => $actionErrorSuggest,
			'actionErrorSuggestTitle'              => str_replace('\'', '&apos;', $actionErrorSuggestTitle),
			'actionErrorNoSuggestText'             => str_replace('\'', '&apos;', $actionErrorNoSuggestText),
			'actionErrorShowDetails'               => $actionErrorShowDetails,
			'actionErrorRandomImage'               => true,
			'actionErrorCode'                      => $actionErrorCode, // 401 | 403 | 404 | 500 | 0
			'actionErrorMenu'                      => []
		];

		// figure out the highest ordering value
		$query = 'select max(' . $db->qn('ordering') . ') from ' . $db->qn('#__forseo_rules') . ' where ' . $db->qn('type') . ' = ' . $db->q('140');
		$db->setQuery($query);
		$maxOrdering            = (int)$db->loadResult();
		$pageRecord['ordering'] = $maxOrdering + 1;

		$query = 'insert into ' . $db->qn('#__forseo_rules')
				 . ' ('
				 . $db->qn('type') . ',' . $db->qn('source') . ',' . $db->qn('title') . ',' . $db->qn('rule') . ',' . $db->qn('last_hit') . ',' . $db->qn('hits') . ',' . $db->qn('enabled') . ',' . $db->qn('valid') . ',' . $db->qn('enabled_after') . ',' . $db->qn('enabled_until') . ',' . $db->qn('ordering')
				 . ')'
				 . " VALUES (140, 0, '" . $ruleTitle . "','" . json_encode($pageRecord, JSON_UNESCAPED_UNICODE) . "', NULL, 0, $enabled, 1, '" . $now . "', NULL, " . $pageRecord['ordering'] . ")";

		$db->setQuery($query)
		   ->execute();

		return $db->insertid();
	}

	/**
	 * Schema and other updates for version 1.6.1;
	 *
	 * @throws Exception
	 */
	private function update1_6_1()
	{
		$db    = $this->getPlatformDb();
		$table = '#__forseo_rules';
		$query = $db->getQuery(true)
					->delete($db->qn($table))
					->where($db->qn('title') . ' = ' . $db->q('Built-in WAF rule'));

		$db->setQuery($query)
		   ->execute();
	}

	/**
	 * Schema and other updates for version 1.5.0
	 *
	 * @throws Exception
	 */
	private function update2_0_0()
	{
		$db = $this->getPlatformDb();

		// add source column
		$table   = '#__forseo_custom_meta';
		$columns = $this->getTableColumns($table);
		if (empty($columns))
		{
			throw new \Exception('Cannot read columns of table ' . $table);
		}

		// `source` TINYINT NOT NULL DEFAULT 0 COMMENT '0: user, 1: built in, 100: import sh404SEF',
		if (empty($columns['source']))
		{
			$db->setQuery(
				'ALTER TABLE ' . $db->quoteName($table)
				. ' ADD COLUMN ' . $db->quoteName('source') . ' TINYINT NOT NULL DEFAULT 0 COMMENT \'0: user, 1: built in, 100: import sh404SEF\' AFTER ' . $db->quoteName('id')
			)->execute();
		}

	}

	/**
	 * Schema and other updates for version 4.3.0
	 *
	 * Big change is switching to a traditional rules ordering scheme just based on the ordering column.
	 *
	 * @throws Exception
	 */
	private function update4_3_0()
	{
		$db = $this->getPlatformDb();

		// read flag in keystore, re-ordering may already have been done
		$query = $db->getQuery(true)
					->select('value')
					->from($db->qn('#__forseo_keystore'))
					->where($db->qn('scope') . '=' . $db->q('default'))
					->where($db->qn('key') . ' = ' . $db->q('lists.rules.orderingType'));

		$orderingType = $db->setQuery($query)
						   ->loadResult();
		if (
			!empty($orderingType)
			&&
			'orderingField' === $orderingType
		) {
			$this->injectDefaultErrorPageRule();

			return;
		}

		// add source column
		$table   = '#__forseo_rules';
		$columns = $this->getTableColumns($table);
		if (empty($columns))
		{
			throw new \Exception('Cannot read columns of table ' . $table);
		}

		// `ordering` int NOT NULL DEFAULT 0 COMMENT 'Ordering within type'
		$db->setQuery(
			'ALTER TABLE ' . $db->quoteName($table)
			. ' MODIFY ' . $db->quoteName('ordering') . ' INT NOT NULL DEFAULT 0 COMMENT \'Ordering within type\''
		)->execute();

		// transcribe all rules ordering stored in keystore to ordering field
		$this->reorderRules();

		// add index only after injecting the values, else it will fail

		// for a very short while we had that index called ordering, which we renamed the next day to type_ordering
		// some people may have already updated to 4.3.0 and have the index called ordering, so we need to drop it
		if (
			!empty($columns['type'])
			&&
			!empty($columns['ordering'])
			&&
			$this->hasIndex($table, ['type', 'ordering'], 'order')
		) {
			$db->setQuery(
				'ALTER TABLE ' . $db->quoteName($table) . ' DROP INDEX (`order`) '
			)->execute();
		}

		// immediately with non-unique values
		if (
			!empty($columns['type'])
			&&
			!empty($columns['ordering'])
			&&
			!$this->hasIndex($table, ['type', 'ordering'], 'type_ordering')
		) {
			$db->setQuery(
				'ALTER TABLE ' . $db->quoteName($table)
				. ' ADD UNIQUE INDEX type_ordering (`type`, `ordering`)'
			)->execute();
		}

		// Possibly inject a default error page handler
		// Previously done in 1.5.0 update handler but moved here
		// as we now use a different a ordering system and the rules
		// must be injected only after the ordering changes have been applied
		$this->injectDefaultErrorPageRule();
	}

	private function reorderRules()
	{
		// convert all rules ordering to ordering field
		$this->convertRulesOrderingToOrderingField();

		// write flag to keystore
		$this->insertInKeyStore(
			'default',
			'lists.rules.orderingType',
			'orderingField'
		);
	}

	private function convertRulesOrderingToOrderingField()
	{
		$db    = $this->getPlatformDb();
		$query = $db->getQuery(true)
					->select('value')
					->from($db->qn('#__forseo_keystore'))
					->where($db->qn('scope') . '=' . $db->q('default'))
					->where($db->qn('key') . ' = ' . $db->q('lists.rules.ordering'));

		$orderingListRaw = $db->setQuery($query)
							  ->loadResult();
		if (empty($orderingListRaw))
		{
			return;
		}

		$orderedIdsList = json_decode($orderingListRaw, true);
		if (empty($orderedIdsList))
		{
			return;
		}

		// we have an array of rules id, properly ordered
		foreach ($orderedIdsList as $ordering => $ruleId)
		{
			// read rule content field and update the 'ordering' property there.
			// Not sure it's needed, this will be done anyway when the rule is read and
			// converted to a Rule object?

			// write back
			$query = $db->getQuery(true)
						->update($db->qn('#__forseo_rules'))
						->set($db->qn('ordering') . ' = ' . $db->q($ordering))
						->where($db->qn('id') . ' = ' . $db->q($ruleId));

			$db->setQuery($query)
			   ->execute();
		}

	}

	/**
	 * Schema and other updates for version 4.3.0
	 *
	 * Big change is switching to a traditional rules ordering scheme just based on the ordering column.
	 *
	 * @throws Exception
	 */
	private function update5_2_0()
	{
		$db = $this->getPlatformDb();

		$table   = '#__forseo_perf_data';
		$columns = $this->getTableColumns($table);
		if (empty($columns))
		{
			throw new \Exception('Cannot read columns of table ' . $table);
		}

		if (empty($columns['inp']))
		{
			$db->setQuery(
				'ALTER TABLE ' . $db->quoteName($table)
				. ' ADD COLUMN ' . $db->quoteName('inp') . ' int unsigned DEFAULT 0 COMMENT \'INP value in 1/1000 ms\' AFTER ' . $db->quoteName('fid'))
			   ->execute();

			$db->setQuery(
				'ALTER TABLE ' . $db->quoteName($table)
				. ' ADD KEY (`inp`) COMMENT "To select by INP"')
			   ->execute();
		}

		$table   = '#__forseo_perf_data_agg';
		$columns = $this->getTableColumns($table);
		if (empty($columns))
		{
			throw new \Exception('Cannot read columns of table ' . $table);
		}

		if (empty($columns['inp']))
		{
			$db->setQuery(
				'ALTER TABLE ' . $db->quoteName($table)
				. ' ADD COLUMN ' . $db->quoteName('inp') . ' int unsigned DEFAULT 0 COMMENT \'INP value in 1/1000 ms\' AFTER ' . $db->quoteName('fid'))
			   ->execute();
		}

		if (empty($columns['inp_count']))
		{
			$db->setQuery(
				'ALTER TABLE ' . $db->quoteName($table)
				. ' ADD COLUMN ' . $db->quoteName('inp_count') . ' int unsigned DEFAULT 0 COMMENT \'INP data points\' AFTER ' . $db->quoteName('inp'))
			   ->execute();
		}
	}

	private function update6_8_0()
	{
		$path = \JPATH_ROOT . '/plugins/system/forseo/vendor/weeblr/wblib/v2/packages/platform/joomla/.idea';
		if (\is_dir($path))
		{
			$this->deleteFolder($path);
		}
	}

	/**
	 * Returns a list of columns found in a table.
	 *
	 * Note: do not memoize, multiple methods may run on same table, will need fresh content.
	 *
	 * @param string $table
	 *
	 * @return array
	 * @throws Exception
	 */
	private function getTableColumns($table)
	{
		$columns = $this->getPlatformDb()->getTableColumns($table, false);
		if (empty($columns))
		{
			throw new \Exception('Cannot read columns for table ' . $table);
		}

		return $columns;
	}

	/**
	 * Whether an index already exists on a column.
	 *
	 * @param string       $table
	 * @param array|string $columns
	 * @param string       $name
	 *
	 * @return bool
	 * @throws Exception
	 */
	private function hasIndex($table, $columns, $name)
	{
		$columns = is_array($columns)
			? $columns
			: [$columns];

		$keys = $this->getPlatformDb()->getTableKeys($table);
		if (empty($keys))
		{
			return false;
		}

		foreach ($keys as $key)
		{
			if ($key->Key_name === $name)
			{
				$indexOfKey = array_search($key->Column_name, $columns);
				if ($indexOfKey !== false)
				{
					unset($columns[$indexOfKey]);
				}
				$columns = array_values($columns);

				if (empty($columns))
				{
					return true;
				}
			}
		}

		return false;
	}

	private function addMessage($message)
	{
		if (empty($message['msg_id']))
		{
			Factory::getApplication()
				   ->enqueueMessage(
					   "Installation went fine but there was an error adding a message to be displayed on your 4SEO dashboard. Best to report this to Weeblr.com! (code 100)",
					   'warning'
				   );
			return;
		}

		$now = new \DateTime('now', new \DateTimeZone('UTC'));

		$message = array_merge(
			[
				'scope'         => 'default',
				'user_id'       => $this->getCurrentUser()->id,
				'type'          => self::TYPE_INFO,
				'dismiss_type'  => self::DISMISS_TYPE_POSTPONABLE | self::DISMISS_TYPE_DISMISSABLE,
				'postpone_spec' => self::DELAY_24H,
				'created_at'    => $now->format('Y-m-d H:i:s')
			],
			$message
		);

		$this->messages[$message['msg_id']] = $message;
	}

	private function getCurrentUser()
	{
		return version_compare(\JVERSION, '4.0', '<')
			? Factory::getUser()
			: Factory::getApplication()->getIdentity();
	}

	/**
	 * Read current app js and css files versions delivered with the app
	 * and delete all outdated versions.
	 *
	 * @return void
	 * @throws Exception
	 */
	private function cleanUpMedia()
	{
		$rootPath = \JPATH_ROOT . '/media/' . self::EXTENSION_MEDIA_FOLDER . '/vendor/weeblr/' . self::EXTENSION . '/assets/dist';

		$filesToKeep   = [];
		$canInvalidate = \function_exists('opcache_invalidate');
		foreach (self::ASSETS as $assetExtension => $assetList)
		{
			foreach ($assetList as $assetName)
			{
				$manifestFile = $rootPath . '/' . $assetName . '.' . $assetExtension . '.php';
				if (\file_exists($manifestFile))
				{
					if ($canInvalidate)
					{
						@opcache_invalidate($manifestFile, true);
					}
					$hashedFileName        = include $manifestFile;
					$currentHashedFullPath = $rootPath . '/' . $hashedFileName;
					if (\file_exists($currentHashedFullPath))
					{
						// iterate over any file of the same type but the naming structure is not
						// favorable here: the hash is stuck in the middle of the name!
						// ie: admin-0d0f866033.base.css
						$bits = \explode('.', $assetName, 2);
						$mask = 1 === \count($bits)
							? $assetName . '-{{hash}}.' . $assetExtension
							: $bits[0] . '-{{hash}}.' . $bits[1] . '.' . $assetExtension;

						$pattern = \preg_quote($mask, '~');
						$pattern = \str_replace('\{\{hash\}\}', '[^.]+', $pattern);
						$pattern = '~^' . $pattern . '$~';
						$files   = \scandir($rootPath);
						foreach ($files as $file)
						{
							if ('.' === $file || '..' === $file)
							{
								continue;
							}

							$filePath = $rootPath . '/' . $file;

							if (
								is_file($filePath)
								&&
								preg_match($pattern, $file)
								&&
								$hashedFileName !== $file
							) {
								\unlink($filePath);
							}
						}
					}
				}
			}
		}
	}

	private function insertMessages()
	{
		foreach ($this->messages as $message)
		{
			$this->insertMessage($message);
		}
	}

	private function insertMessage($message)
	{
		$db    = $this->getPlatformDb();
		$table = '#__forseo_messages';

		// does this message exist already?
		$query = $db->getQuery(true)
					->select('*')
					->from($db->qn($table))
					->where($db->qn('state') . '!=' . self::STATE_DISMISSED)
					->where($db->qn('msg_id') . ' = ' . $db->q($message['msg_id']));

		$existing = $db->setQuery($query)
					   ->loadObject();

		if (!empty($existing))
		{
			$query = $db->getQuery(true)
						->delete($db->qn($table))
						->where($db->qn('state') . '!=' . self::STATE_DISMISSED)
						->where($db->qn('msg_id') . ' = ' . $db->q($message['msg_id']));
			$db->setQuery($query)
			   ->execute();

			$existing = null;
		}

		if (empty($existing))
		{
			$o = (object)$message;
			$db->insertObject($table, $o);
		}
	}

	private function insertInKeyStore($scope, $key, $value, $largeValue = '')
	{
		$db    = $this->getPlatformDb();
		$table = '#__forseo_keystore';

		// does this message exist already?
		$query = $db->getQuery(true)
					->select('*')
					->from($db->qn($table))
					->where($db->qn('scope') . ' = ' . $db->q($scope))
					->where($db->qn('key') . ' = ' . $db->q($key));

		$existing = $db->setQuery($query)
					   ->loadObject();

		if (empty($existing))
		{
			$o              = new stdClass();
			$o->scope       = $scope;
			$o->key         = $key;
			$o->value       = $value;
			$o->large_value = $largeValue;
			$o->modified_at = Date::getInstance()->toSql();
			$db->insertObject($table, $o);
		}
	}

	private function getFromKeyStore($scope, $key)
	{
		$db    = $this->getPlatformDb();
		$table = '#__forseo_keystore';

		// does this message exist already?
		$query = $db->getQuery(true)
					->select('*')
					->from($db->qn($table))
					->where($db->qn('scope') . ' = ' . $db->q($scope))
					->where($db->qn('key') . ' = ' . $db->q($key));

		return $db->setQuery($query)
				  ->loadObject();
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

	private function deleteFolder($toDelete)
	{
		return Version::MAJOR_VERSION >= 6
			? \Joomla\Filesystem\Folder::delete($toDelete)
			: \Joomla\CMS\Filesystem\Folder::delete($toDelete);
	}
}
