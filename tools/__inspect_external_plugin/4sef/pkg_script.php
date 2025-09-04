<?php
/**
 * Project: 4SEF
 *
 * @package          4SEF
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          2.6.2.644
 * @date        2025-06-02
 *
 * build 0.9.0.437
 */

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language;
use Joomla\CMS\Filesystem;

// No direct access
defined('_JEXEC') or die;

/**
 * Class Pkg_ForsefInstallerScript
 *
 * https://docs.joomla.org/J3.x:Developing_an_MVC_Component/Adding_an_install-uninstall-update_script_file
 *
 */
class Pkg_ForsefInstallerScript
{
	const EXTENSION              = 'forsef';
	const EXTENSION_MEDIA_FOLDER = 'com_forsef';
	const ASSETS                 = [
		'js'  => [
			'admin'
		],
		'css' => [
			'admin.base',
			'admin'
		]
	];

	const MIN_PLATFORM_VERSION     = '3.9.0';
	const MAX_PLATFORM_VERSION     = '6.0';
	const INCLUDE_PLATFORM_VERSION = '[]';
	const EXCLUDE_PLATFORM_VERSION = '[]';

	const MIN_PHP_VERSION     = '7.1.0';
	const MAX_PHP_VERSION     = '';
	const INCLUDE_PHP_VERSION = '[]';
	const EXCLUDE_PHP_VERSION = '[]';

	const TYPE_DANGER  = '1_danger';
	const TYPE_WARNING = '2_warning';
	const TYPE_INFO    = '3_info';

	const STATE_CREATED   = 0;
	const STATE_PENDING   = 1;
	const STATE_DISMISSED = 2;

	const DISMISS_TYPE_NONE        = 0;
	const DISMISS_TYPE_POSTPONABLE = 1;
	const DISMISS_TYPE_DISMISSABLE = 2;

	const DELAY_5MN  = 'PT5M';
	const DELAY_10MN = 'PT10M';
	const DELAY_15MN = 'PT15M';
	const DELAY_30MN = 'PT30M';
	const DELAY_1H   = 'PT1H';
	const DELAY_24H  = 'P1D';
	const DELAY_1W   = 'P1W';
	const DELAY_2W   = 'P2W';
	const DELAY_1M   = 'P1M';
	const DELAY_3M   = 'P3M';

	/**
	 * @var array Stores messages that should be added to the messaging system. All output at once after an install/update.
	 */
	private $messages = [];

	/**
	 * @var string[] Those caches will be cleared at postflight.
	 */
	private $cachesToClean = [
		'4sef_updates'
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
						   '4SEF requires Joomla! version between %s and %s (you are using %s). Aborting installation',
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
		)
		{
			Factory::getApplication()
				   ->enqueueMessage(
					   sprintf(
						   '4SEF requires PHP version between %s and %s (you are using %s). Aborting installation',
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
						'element'  => 'forsef',
						'ordering' => 'last'
					],
					[
						'folder'  => 'installer',
						'element' => 'forsef'
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
						$extension = Table::getInstance('Extension');
						$extension->load($pluginId);
						$extension->bind($overrides);
						$extension->store();
					}
					else
					{
						Factory::getApplication()->enqueueMessage('4SEF: Error updating plugin DB record: ' . $plugin['folder'] . ' / ' . $plugin['element']);
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
				$logoUrl         = Uri::root(true) . '/media/com_forsef/vendor/weeblr/forsef/assets/images/logo/forsef.svg';
				$thankYouMessage = Language\Text::_('COM_FORSEF_THANK_YOU_INSTALL');
				$html            = <<<HTML

<h1 style="margin:4rem 0 2rem;text-align:center;">{$thankYouMessage}</h1>
HTML;

				if (in_array($type, ['install', 'discover_install']))
				{
					$useMsg = Language\Text::sprintf(
						'COM_FORSEF_INSTALL_ADMIN_LINK',
						'index.php?option=com_forsef&src=installComplete#/forsef/dashboard'
					);
				}
				if ('update' === $type)
				{
					$useMsg = Language\Text::sprintf(
						'COM_FORSEF_UPDATE_ADMIN_LINK',
						'index.php?option=com_forsef&src=updateComplete#/forsef/dashboard'
					);
				}

				$html .= <<<HTML
<p style="text-align:center;">{$useMsg}</p>
<p style="margin: 4rem 0;text-align: center"><img alt="" width="150" src="{$logoUrl}"></p>
HTML;
				echo $html;
			}
		}
		catch (\Throwable $e)
		{
			$message = $e->getMessage();
			if (empty($message))
			{
				$message = '<pre>' . sprintf('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString()) . '</pre>';
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
				  ->from('#__forsef_config')
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
						if (0 === strpos($table, '#__forsef_'))
						{
							$toDelete[] = $table;
						}
					}

					if (!empty($toDelete))
					{
						Factory::getApplication()->enqueueMessage('4SEF was configured to delete its data and configuration when uninstalling.');
						Factory::getApplication()->enqueueMessage(count($toDelete) . ' 4SEF database tables to delete...');
						foreach ($toDelete as $table)
						{
							Factory::getApplication()->enqueueMessage('Deleting ' . $table . ' table...');
							$db->dropTable($table);
						}
						Factory::getApplication()->enqueueMessage('All 4SEF code and data was removed from this site.');
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
		$versions = ['0.8.0', '1.2.0'];

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
					'Error performing update, this extension may not work properly. Please try again or contact Weeblr support for assistance. Details: <pre>' . $e->getMessage() . '</pre>'
				);
			}
		}
	}

	/**
	 * Schema and other updates for version 1.5.0
	 *
	 * @throws Exception
	 */
	private function update0_8_0()
	{
		// Add dailies stat tables if missing.
		$db     = $this->getPlatformDb();
		$tables = $db->getTableList();
		$prefix = $db->getPrefix();
		$found  = false;
		foreach ($tables as $table)
		{
			if ($table === $prefix . 'forsef_stats_dailies')
			{
				$found = true;
				break;
			}
		}

		if (!$found)
		{
			$db->setQuery(
				"CREATE TABLE IF NOT EXISTS " . $db->quoteName('#__forsef_stats_dailies')
				. "(
" . $db->quoteName('id') . "           INT unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
" . $db->quoteName('period_start') . " DATETIME         NOT NULL COMMENT 'UTC date time of the collection period start',
" . $db->quoteName('hits') . "         INT unsigned DEFAULT 0 COMMENT 'Number of hits for the period',
" . $db->quoteName('hits_bots') . "    INT unsigned DEFAULT 0 COMMENT 'Number of hits by bots for the period',
" . $db->quoteName('hits_se') . "      INT unsigned DEFAULT 0 COMMENT 'Number of hits by search engines for the period',

    PRIMARY KEY (" . $db->quoteName('id') . "),
    KEY (" . $db->quoteName('period_start') . ")

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;"
			)->execute();
		}
	}

	/**
	 * Schema and other updates for version 1.5.0
	 *
	 * @throws Exception
	 */
	private function update1_2_0()
	{
		// contentContentTitleInsertArticleId is now a tri-state int: 0 = disabled, 1 = insert before, 2 = insert after
		$db    = $this->getPlatformDb();
		$table = '#__forsef_config';

		$query = $db->getQuery(true)
					->select('*')
					->from($db->qn($table))
					->where($db->qn('key') . '= "extensions"');

		$existingConfig = $db->setQuery($query)
							 ->loadObject();
		if (empty($existingConfig) || empty($existingConfig->value))
		{
			return;
		}

		$decodedConfig = json_decode($existingConfig->value, true);
		if (
			empty($decodedConfig)
			||
			!isset($decodedConfig['contentContentTitleInsertArticleId'])
			||
			!is_bool($decodedConfig['contentContentTitleInsertArticleId'])
		)
		{
			return;
		}

		$decodedConfig['contentContentTitleInsertArticleId'] = true === $decodedConfig['contentContentTitleInsertArticleId']
			? 1
			: 0;

		$query = $db->getQuery(true)
					->update($db->qn($table))
					->set($db->qn('value') . ' = ' . $db->q(json_encode($decodedConfig)))
					->where($db->qn('key') . '= "extensions"');

		$db->setQuery($query)
		   ->execute();
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

	private function addMessage($message)
	{
		if (empty($message['msg_id']))
		{
			Factory::getApplication()
				   ->enqueueMessage(
					   "Installation went fine but there was an error adding a message to be displayed on your 4SEF dashboard. Best to report this to Weeblr.com! (code 100)",
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
							)
							{
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
		$table = '#__forsef_messages';

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
