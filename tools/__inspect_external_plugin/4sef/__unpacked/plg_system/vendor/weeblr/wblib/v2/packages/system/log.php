<?php
/**
 * Project:                 4SEF
 *
 * @package          4SEF
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          @build_version_full_build@
 * @date                2025-06-02
 */

namespace Weeblr\Wblib\Forsef\System;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Base;

// no direct access
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

class Log extends Base\Base
{
	const DEBUG = 'debug';
	const INFO = 'info';
	const ERROR = 'error';
	const ALERT = 'alert';
	const CUSTOM = 'custom';

	// logging level presets
	const LOGGING_NONE = 'none';
	const LOGGING_PRODUCTION = 'production';
	const LOGGING_DETAILED = 'detailed';

	/**
	 * Detailed Logging timeout after 30mns
	 */
	const DETAILED_LOGGING_TIMEOUT = 1800;

	/**
	 * @var array Predefined logging levels constants.
	 */
	protected $predefinedLevels = [];

	// list of levels that must be logged (empty array will disabled logging)
	protected $config = [];

	protected $uuid = null;

	protected $prefix = 'default';

	/**
	 * Log constructor.
	 *
	 * Sets default logging levels values, based on current WP_DEBUG
	 * or persisted configuration
	 *
	 * @param   string  $prefix
	 * @param   string  $preset
	 */
	public function __construct($prefix, $preset = self::LOGGING_PRODUCTION)
	{
		parent::__construct();

		$this->prefix = $prefix;

		$this->predefinedLevels = [
			self::LOGGING_NONE       => [],
			self::LOGGING_PRODUCTION => [
				self::ERROR,
				self::ALERT,
				self::CUSTOM
			],
			self::LOGGING_DETAILED   => [
				self::DEBUG,
				self::INFO,
				self::ERROR,
				self::ALERT,
				self::CUSTOM
			]
		];

		$defaultConfig =
			[
				'preset'            => $preset,
				'preset_disable_on' => 0,
				'log_level'         => $this->predefinedLevels[$preset]
			];

		$this->config = $defaultConfig;
	}

	/**
	 * Store configuration, provided by main process
	 *
	 * @param   string  $logLevel  One of the predefined logging levels
	 * @param   bool    $persist
	 */
	public function configure($logLevel, $persist = false)
	{
		if (
			!array_key_exists(
				$logLevel,
				$this->predefinedLevels
			)
		)
		{
			return;
		}

		// set the new config
		$this->config['preset']    = $logLevel;
		$this->config['log_level'] = self::$predefinedLevels[$logLevel];
	}

	/**
	 * Static facade for cases where the library itself needs to log an error.
	 */
	public static function libraryError(...$args)
	{
		$logger = new static('wblib', self::LOGGING_DETAILED);

		return $logger->error(...$args);
	}

	/**
	 * Static facade for cases where the library itself needs to log debug info.
	 */
	public static function libraryDebug(...$args)
	{
		$logger = new static('wblib', self::LOGGING_DETAILED);

		return $logger->debug(...$args);
	}

	/**
	 * Static facade for cases where the library itself needs to log debug info.
	 */
	public static function libraryAlert(...$args)
	{
		$logger = new static('wblib', self::LOGGING_DETAILED);

		return $logger->alert(...$args);
	}

	/**
	 * Static facade for cases where the library itself needs to log debug info.
	 */
	public static function libraryCustom(...$args)
	{
		$logger = new static('wblib', self::LOGGING_DETAILED);

		return $logger->custom(...$args);
	}

	/**
	 * Log a message with level Error
	 *
	 * @param   string message
	 * @param   mixed various params to be sprintfed into the msg
	 *
	 * @return boolean true if success
	 */
	public function error(...$args)
	{
		return $this->_log('errors', self::ERROR, ['category' => $this->prefix], $args);
	}

	public function alert(...$args)
	{
		return $this->_log('alerts', self::ALERT, ['category' => $this->prefix], $args);
	}

	public function debug(...$args)
	{
		return $this->_log('debug', self::DEBUG, ['category' => $this->prefix], $args);
	}

	public function info(...$args)
	{
		return $this->_log('info', self::INFO, ['category' => $this->prefix], $args);
	}

	public function custom($category, ...$args)
	{
		return $this->_log($this->prefix, self::CUSTOM, ['category' => $category], $args);
	}

	/**
	 * Whether a given logging level is enabled and should be logged
	 *
	 * @param   String  $level
	 *
	 * @return bool
	 */
	protected function levelIsEnabled($level)
	{
		return in_array(
			$level,
			Wb\arrayGet(
				$this->config,
				'log_level',
				[]
			)
		);
	}

	/**
	 * Prepare logging to file
	 *
	 * @param           $file
	 * @param   string  $level
	 * @param           $options
	 * @param   null    $args
	 *
	 * @return bool
	 */
	protected function _log($file, $level = self::INFO, $options = [], $args = null)
	{
		// nothing to do, go away asap
		if (!$this->levelIsEnabled($level))
		{
			return true;
		}

		// something to do, process message
		if (count($args) > 1)
		{
			// use sprintf
			$message = call_user_func_array('sprintf', $args);
		}
		else
		{
			$message = $args[0];  // no variable parts, just use first element as a string
		}

		// include user details in logging
		$user       = $this->platform->getUser();
		$userString = $user->guest ? 'guest' : $user->id . ' (' . $user->email . ')';

		// do logging
		// note: cannot use Exceptions here, as one plugin throwing an exception
		// would prevent other plugins to be fired
		$params = [
			'file'     => $file,
			'priority' => $level,
			'type'     => $level,
			'user'     => $userString,
			'message'  => $message
		];

		// merge in additional options set by caller
		// include: format and timestamp
		if (is_array($options))
		{
			$params = array_merge($params, $options);
		}

		return $this->_logToFile($params);
	}

	protected function _logToFile($params)
	{
		// check params
		$defaultParams = [
			'file'              => 'info',
			'category'          => 'wbLib',
			'date'              => Date::getSiteNow(
				'Y-m-d',
				true // refresh
			),
			'time'              => Date::getSiteNow(
				'H:i:s',
				true
			),
			'message'           => 'No logging message, probably an error',
			'user'              => '-',
			'priority'          => self::INFO,
			'text_entry_format' => "{DATE}\t{TIME}\t{TYPE}\t{C-IP}\t{USER}\t{MESSAGE}",
			'timestamp'         => Date::getSiteNow('Y-m-d'),
			'prefix'            => 'wblib'
		];

		$liveParams = array_merge($defaultParams, $params);

		// files and path
		$logPath = $this->platform->getLogsPath() . '/' . $liveParams['category'] . '/' . $liveParams['file'];
		$this->platform->createFolders($logPath);
		$logFile = $logPath . '/log_' . $liveParams['file'] . '.' . $liveParams['timestamp'] . '.log.php';

		if (!file_exists($logFile))
		{
			$header = "<?php
// wbLib log file			
defined('WBLIB_VERSION') || die;

DATE\tTIME\tTYPE\tIP\tUSER\tMESSAGE
";
		}
		else
		{
			$header = "\n";
		}

		// build up the record
		$log         = str_replace('{DATE}', $liveParams['date'], $liveParams['text_entry_format']);
		$log         = str_replace('{TIME}', $liveParams['time'], $log);
		$log         = str_replace('{TYPE}', $liveParams['type'], $log);
		$log         = str_replace('{C-IP}', Http::getIpAddress(), $log);
		$log         = str_replace('{USER}', $liveParams['user'], $log);
		$log         = str_replace('{MESSAGE}', $liveParams['message'], $log);
		$fullMessage = $header . $log;

		// write to log file
		file_put_contents($logFile, $fullMessage, FILE_APPEND);

		return true;
	}
}
