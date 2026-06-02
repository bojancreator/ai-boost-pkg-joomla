<?php
/**
 * Project: 4SEO
 *
 * @package                 4SEO
 * @copyright               Copyright Weeblr llc - 2020 - 2026
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 6.10.1.2660
 *
 * @date        2026-01-30
 */

namespace Weeblr\Forseo;

use Weeblr\Wblib\Forseo\Factory;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Db;
use Weeblr\Wblib\Forseo\Html;
use Weeblr\Wblib\Forseo\Messages;

use Weeblr\Forseo\Controller;
use Weeblr\Forseo\Helper;
use Weeblr\Forseo\Model;
use Weeblr\Forseo\Model\Integrations\Google;

use Weeblr\Wblib\Forseo\Integrations;
use Weeblr\Wblib\Forseo\Integrations\Googleapis\v1\Google as Googleapis;
use Weeblr\Wblib\Forseo\Integrations\Googleapis\v1\Google\Service;
use Weeblr\Wblib\Forseo\Integrations\Googleapis\v1\Google\Service\SiteVerification;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Facade for 3rd-party access.
 */
include_once __DIR__ . '/api.php';

/**
 * Extends the standard factory, builds a few specific objects
 */
Factory::get()->getThe('hook')->add(
	'wblib_factory_build_object_filter',
	function ($object, $factory, $method, $class, $args, $key)
	{

		switch ($class)
		{
			// override default factory use either sorting mode
			case Model\Rules::class:
				$appConfig          = $factory->getThis('forseo.config', 'app');
				$rulesSortingMethod = $appConfig->get('rulesSortingMethod', 'orderingField');

				return 'orderingField' === $rulesSortingMethod
					? new Model\Rules()
					: new Model\Ruleslegacy();

			// gather all versions info
			case 'forseo.versionInfo':
				$factory->enforceSingleton(
					$class,
					$method
				);

				return System\Version::get('forseo');

			case 'forseo.pageDataCollector':
				$factory->enforceSingleton(
					$class,
					$method
				);

				return new Controller\Pagedatacollector;

			case 'forseo.rulesController':
				$factory->enforceSingleton(
					$class,
					$method
				);

				return new Controller\Rules();

			case 'forseo.aliasesController':
				$factory->enforceSingleton(
					$class,
					$method
				);

				return new Controller\Aliases();

			case 'forseo.pageProcessor':
				$factory->enforceSingleton(
					$class,
					$method
				);

				return new Controller\Pageprocessor;

			case 'forseo.pageHelper':
				$factory->enforceSingleton(
					$class,
					$method
				);

				return new Helper\Page();

			case 'forseo.crawlerHelper':
				$factory->enforceSingleton(
					$class,
					$method
				);

				return new Helper\Crawler;

			case 'forseo.searchEnginesHelper':
				$factory->enforceSingleton(
					$class,
					$method
				);

				return new Helper\Searchengines();

			case 'forseo.linksCollectorHelper':
				$factory->enforceSingleton(
					$class,
					$method
				);

				return new Helper\Linkscollector();

			case 'forseo.robotsTxtHelper':
				$factory->enforceSingleton(
					$class,
					$method
				);

				return new Helper\Robotstxt();

			case 'forseo.requestInfo':
				$factory->enforceSingleton(
					$class,
					$method
				);

				return new Data\Requestinfo();

			case 'forseo.logger':
				$factory->enforceSingleton(
					$class,
					$method
				);

				$systemConfig = $factory->getThis('forseo.config', 'system');

				return new System\Log('forseo', $systemConfig->get('loggingPreset', System\Log::LOGGING_PRODUCTION));

			case 'forseo.config':
				$factory->enforceMultiton(
					$class,
					$method
				);

				$modelClass = in_array(
					$key,
					[
						'sh404sef',
						'integrations'
					]
				)
					? ucfirst($key) . 'config'
					: 'Config';

				$modelName = 'Weeblr\Forseo\Model\\' . $modelClass;

				return new $modelName($key);

			case 'forseo.keystore':
				$factory->enforceSingleton(
					$class,
					$method
				);

				return new Db\Keystore(
					[
						'tableName' => '#__forseo_keystore'
					]
				);

			case 'forseo.msgManager':
				$factory->enforceSingleton(
					$class,
					$method
				);

				return new Messages\Manager(
					[
						'namespace'         => 'forseo',
						'table'             => '#__forseo_messages',
						'defaultApiOptions' => [
							'authorizations' => [
								[
									'asset'  => 'com_forseo',
									'action' => 'core.manage'
								]
							],
							'auth_callback'  => null
						]
					]
				);

			case 'forseo.assetsManager':
				$factory->enforceSingleton(
					$class,
					$method
				);

				return new Html\Assetsmanager(
					[
						'filesPath'   => FORSEO_APP_ASSETS_BASE_PATH,
						'enableDebug' => false,
						'assetsMode'  => WBLIB_Forseo_OP_MODE == 'prod'
							? Html\Assetsmanager::PRODUCTION
							: Html\Assetsmanager::DEV
					]
				);

			case 'forseo.google.searchConsoleClient':
				$factory->enforceSingleton(
					$class,
					$method
				);

				$accessToken = Factory::get()
									  ->getA(Model\Oauth::class)
									  ->getAccessToken(
										  Google\Searchconsole::SERVICE_ID
									  );

				if (empty($accessToken) || $accessToken instanceof \Exception)
				{
					throw new \Exception(
						'Not authorized by Google' . ($accessToken instanceof \Exception ? ': ' . $accessToken->getMessage() : ''),
						System\Http::RETURN_UNAUTHORIZED
					);
				}

				$client = new Googleapis\Client();

				$client->setApplicationName(
					Google\Searchconsole::APPLICATION_NAME
				);

				$client->setAccessToken(
					$accessToken
				);

				return $client;

			case 'forseo.google.searchConsoleService':
				$factory->enforceSingleton(
					$class,
					$method
				);

				Integrations\Loader::googleapis();

				return new Service\SearchConsole(
					$factory->getThe('forseo.google.searchConsoleClient')
				);

			case 'forseo.google.siteVerificationService':
				$factory->enforceSingleton(
					$class,
					$method
				);

				Integrations\Loader::googleapis();

				return new Service\SiteVerification(
					$factory->getThe('forseo.google.searchConsoleClient')
				);

			case 'forseo.searchConsoleService':
				$factory->enforceSingleton(
					$class,
					$method
				);

				Integrations\Loader::googleapis();

				return new Google\Searchconsole;

			case 'forseo.variablesExpander':
				$factory->enforceSingleton(
					$class,
					$method
				);

				return new Model\Injector\Variables;
		}

		return $object;
	}
);

