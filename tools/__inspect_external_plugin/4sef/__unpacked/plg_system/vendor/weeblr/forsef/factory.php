<?php
/**
 * Project: 4SEF
 *
 * @package                 4SEF
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 2.6.2.644
 *
 * @date        2025-06-02
 */

namespace Weeblr\Forsef;

use Weeblr\Wblib\Forsef\Factory as WblibFactory;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Db;
use Weeblr\Wblib\Forsef\Html;
use Weeblr\Wblib\Forsef\Messages;
use Weeblr\Wblib\Forsef\Wb;

use Weeblr\Forsef\Model;
use Weeblr\Forsef\Controller;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Facade for 3rd-party access.
 */
include_once __DIR__ . '/api.php';

/**
 * Extends the standard factory, builds a few specific objects
 */
WblibFactory::get()->getThe('hook')->add(
	'wblib_factory_build_object_filter',
	function ($object, $factory, $method, $class, $args, $key) {

		switch ($class)
		{
			// gather all versions info
			case 'forsef.versionInfo':
				$factory->enforceSingleton(
					$class,
					$method
				);

				return System\Version::get('forsef');

			case 'forsef.platformController':
				$factory->enforceSingleton(
					$class,
					$method
				);

				return new Controller\Platform();

			case 'forsef.router':
				$factory->enforceSingleton(
					$class,
					$method
				);

				return new Model\Router();

			case 'forsef.builder':
				$factory->enforceSingleton(
					$class,
					$method
				);

				return new Model\Builder();

			case 'forsef.parser':
				$factory->enforceSingleton(
					$class,
					$method
				);

				return new Model\Parser();

			case 'forsef.legacyLayer':
				$factory->enforceSingleton(
					$class,
					$method
				);

				return new Model\Legacy();

			case 'forsef.extensionPlugin':
				$factory->enforceMultiton(
					$class,
					$method
				);

				$pluginName = 'Weeblr\Forsef\Platform\Extensions\\' . ucfirst(Wb\lTrim($key, 'com_'));

				if (
					empty($pluginName)
					||
					!class_exists($pluginName)
				)
				{
					$pluginName = 'Weeblr\Forsef\Platform\Extensions\Fallback';
				}

				return new $pluginName($key);

			case 'forsef.rulesController':
				$factory->enforceSingleton(
					$class,
					$method
				);

				return new Controller\Rules();

			case 'forsef.requestInfo':
				$factory->enforceSingleton(
					$class,
					$method
				);

				return new Data\Requestinfo();

			case 'forsef.logger':
				$factory->enforceSingleton(
					$class,
					$method
				);

				$systemConfig = Factory::get()->getThis('forsef.config', 'system');

				return new System\Log('forsef', $systemConfig->get('loggingPreset', System\Log::LOGGING_PRODUCTION));

			case 'forsef.config':
				$factory->enforceMultiton(
					$class,
					$method
				);

				$modelClass = in_array($key, ['extensions', 'routing', 'sh404sef'])
					? ucfirst($key) . 'config'
					: 'Config';

				$modelName = 'Weeblr\Forsef\Model\\' . $modelClass;

				return new $modelName($key);

			case 'forsef.keystore':
				$factory->enforceSingleton(
					$class,
					$method
				);

				return new Db\Keystore(
					[
						'tableName' => '#__forsef_keystore'
					]
				);

			case 'forsef.msgManager':
				$factory->enforceSingleton(
					$class,
					$method
				);

				return new Messages\Manager(
					[
						'namespace'         => 'forsef',
						'table'             => '#__forsef_messages',
						'defaultApiOptions' => [
							'authorizations' => [
								[
									'asset'  => 'com_forsef',
									'action' => 'core.manage'
								]
							],
							'auth_callback'  => null
						]
					]
				);

			case 'forsef.assetsManager':
				$factory->enforceSingleton(
					$class,
					$method
				);

				return new Html\Assetsmanager(
					[
						'filesPath'   => FORSEF_APP_ASSETS_BASE_PATH,
						'enableDebug' => false,
						'assetsMode'  => WBLIB_Forsef_OP_MODE == 'prod'
							? Html\Assetsmanager::PRODUCTION
							: Html\Assetsmanager::DEV
					]
				);

		}

		return $object;
	}
);

/**
 * Alias for original wbLib factory that 4SEF extends.
 */
class Factory
{
	public static function get()
	{
		return WblibFactory::get();
	}
}
