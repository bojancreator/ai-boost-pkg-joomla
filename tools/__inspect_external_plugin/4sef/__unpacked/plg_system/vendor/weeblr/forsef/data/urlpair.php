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
 */

namespace Weeblr\Forsef\Data;

use Weeblr\Forsef\Helper;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Db;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Urlpair extends Db\Dataobject
{
	const AUTO   = 0;
	const CUSTOM = 1;

	const CANONICAL = 0;
	const DUPLICATE = 1;

	// Future use maybe
	const DISABLED = 0;
	const ENABLED  = 1;

	/**
	 * @var array List of max length per column.
	 */
	protected $autotrimSpec = [
		'sef'         => 2048,
		'base_path'   => 2048,
		'extra_path'  => 50,
		'nonsef'      => 2048,
		'base_nonsef' => 2048
	];

	/**
	 * @var string Database table associated with this instance.
	 */
	protected $table = '#__forsef_urls';

	/**
	 * @var array Holds data read/written to db. Also a specification of the columns.
	 */
	protected $defaults = [
		'id'          => 0,
		'sef'         => '',
		'base_path'   => '',
		'extra_path'  => '',
		'nonsef'      => '',
		'base_nonsef' => '',
		'platform'    => '',
		'custom'      => self::AUTO,
		'duplicate'   => self::CANONICAL,
		'extension'   => '',
		'hits'        => 0,
		'last_hit'    => null,
		'state'       => self::ENABLED
	];

	/**
	 * @var array List of types that should be enforced if present for properties.
	 */
	protected $dataTypes = [
		'id'        => System\Convert::INT,
		'sef'       => System\Convert::STRING,
		'nonsef'    => System\Convert::STRING,
		'platform'  => System\Convert::STRING,
		'custom'    => System\Convert::INT,
		'duplicate' => System\Convert::INT,
		'extension' => System\Convert::STRING,
		'hits'      => System\Convert::INT,
		'last_hit'  => System\Convert::INT,
		'state'     => System\Convert::INT,
	];

	/**
	 * @var string[] List of columns and their id which can be searched.
	 */
	protected $searchableColumns = [
		'sef'    => 'sef',
		'nonsef' => 'nonsef'
	];

	/**
	 * @var string[] List of columns and their id which can be ordered by.
	 */
	protected $orderableColumns = [
		'sef',
		'hits',
		'nonsef',
		'extension'
	];

	/**
	 * @var Helper\Sef
	 */
	protected $sefHelper;

	/**
	 * @var Helper\Nonsef
	 */
	protected $nonSefHelper;

	/**
	 * Associate this instance to a database table.
	 *
	 * @param string $table
	 *
	 * @throws \Exception
	 */
	public function __construct($table = '')
	{
		parent::__construct($table);

		$this->sefHelper    = $this->factory->getA(Helper\Sef::class);
		$this->nonSefHelper = $this->factory->getA(Helper\Nonsef::class);
	}

	/**
	 * Load instance from db by searching for a given base path.
	 *
	 * Base path is the sef with trailing pagination and page suffix segment removed.
	 *
	 * @param string $basePath
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function loadPerBasepath($basePath)
	{
		return $this->loadPerColumn(
			[
				'base_path' => $basePath
			]
		);
	}

	/**
	 * Load instance from db by searching for a given SEF URL.
	 * Only loads main URL, not any duplicate.
	 *
	 * @param string $sef
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function loadPerCanonicalSef($sef)
	{
		return $this->loadPerColumn(
			[
				'sef'       => $sef,
				'duplicate' => self::CANONICAL
			]
		);
	}

	/**
	 * Load instance from db by searching for a given non-SEF URL.
	 *
	 * @param string $nonSef
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function loadPerNonSef($nonSef)
	{
		return $this->loadPerColumn(
			[
				'nonsef' => $nonSef
			]
		);
	}

	/**
	 * Load instance from db by searching for a given non-SEF URL.
	 *
	 * @param array $queryVars
	 * @param bool  $stripPagination
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function loadPerBaseNonSef($queryVars, $stripPagination = true)
	{
		if ($stripPagination)
		{
			$queryVars = $this->nonSefHelper->stripPaginationVars($queryVars);
		}
		$helper = $this->factory->getA(Helper\Nonsef::class);
		return $this->loadWhere(
			[
				'base_nonsef' => $helper->buildNormalizedNonSef(
					$queryVars
				),
				'duplicate'   => self::CANONICAL
			]
		);
	}

	/**
	 * Load instance from db by searching for a given non-SEF URL.
	 *
	 * @param string $sef
	 * @param string $nonSef
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function loadPerUrlPair($sef, $nonSef)
	{
		return $this->loadPerColumn(
			[
				'sef'    => $sef,
				'nonsef' => $nonSef,
			]
		);
	}

	/**
	 * A chance to massage data before storing it.
	 *
	 * @param $storeOptions
	 *                     bool noDuplicateCheck
	 * @return bool
	 * @throws \Exception
	 */
	public function beforeStore($storeOptions = [])
	{
		// non-sef can be an array of vars or a full non-sef URL.
		if (is_array($this->data['nonsef']))
		{
			$nonSefVars = $this->data['nonsef'];
		}
		else
		{
			// url-decoded
			parse_str(
				Wb\lTrim(
					$this->data['nonsef'],
					'index.php?'
				),
				$nonSefVars
			);
		}

		if (empty($nonSefVars))
		{
			$this->factory->getThe('forsef.logger')->debug('Trying to store a UrlPair with an empty non-SEF. Sef is: ' . Wb\arrayGet($this->data, 'sef') . ', platform is: ' . Wb\arrayGet($this->data, 'platform'));

			return false;
		}

		// Update extension value
		$this->data['extension'] = Wb\arrayGet(
			$nonSefVars,
			'option',
			''
		);

		$nonSefHelper = $this->factory->getA(Helper\Nonsef::class);

		$this->data['nonsef']      = $nonSefHelper->buildNormalizedNonSef($nonSefVars);
		$this->data['base_nonsef'] = $nonSefHelper->buildNormalizedNonSef(
			$nonSefHelper->stripPaginationVars(
				$nonSefVars
			)
		);

		$this->conformUrl();

		//	Does this exist already?
		$existing = $this->db->selectAssoc(
			$this->table,
			'*',
			[
				'sef'    => $this->data['sef'],
				'nonsef' => $this->data['nonsef']
			]
		);

		if (Wb\arrayIsTruthy($storeOptions, 'noDuplicateCheck', false))
		{
			return true;
		}

		if (!empty($existing))
		{
			// don't check for duplicates if existing
			$this->data['id']        = Wb\arrayGet($existing, 'id');
			$this->data['duplicate'] = Wb\arrayGet($existing, 'duplicate');
			return true;
		}

		// Handle duplicates: if there already exists a record for same SEF but different non-SEF
		// then this is a duplicate.
		$canonicalId             = $this->db->selectResult(
			$this->table,
			'id',
			[
				'sef'       => $this->data['sef'],
				['nonsef', '!=', $this->data['nonsef']],
				['id', '!=', $this->getId()],
				'duplicate' => self::CANONICAL
			]
		);
		$this->data['duplicate'] = empty($canonicalId)
			? self::CANONICAL
			: self::DUPLICATE;

		return true;
	}

	/**
	 * Apply conforming to created URLs before storing it.
	 *
	 * @return void
	 */
	private function conformUrl()
	{
		$this->data = $this->sefHelper->conformUrlPairData($this->data);
	}

	/**
	 * Creating a URL pair:
	 *
	 * - id is empty. Behave as if the URL was created by a plugin BUT still needs to be marked as customized
	 *
	 * @param array $urlData
	 */
	public function create($urlData)
	{
		$urlData['custom'] = self::CUSTOM;
		unset($urlData['__meta']);
		$this->set($urlData)
			 ->addPlatformUrl()
			 ->store();

		return [
			'data'  => $this->get(),
			'count' => 1,
			'total' => 1,
		];
	}

	/**
	 * Find the Joomla SEF URL for a a given SEF, and store it in the "platform" field.
	 *
	 * @return $this
	 * @throws \Throwable
	 */
	private function addPlatformUrl()
	{
		$nonSef = $this->get('nonsef');
		if (empty($nonSef))
		{
			return $this;
		}

		try
		{
			$router = $this->factory
				->getThe('forsef.router')
				->disable4SefBuilding();
			$sef    = System\Route::makeRootRelative(
				$this->platform->route($nonSef),
				true
			);
			$this->set(
				'platform',
				$sef
			);
			$router->enable4SefBuilding();
		}
		catch (\Throwable $e)
		{
			if (!empty($router))
			{
				$router->enable4SefBuilding();
			}

			throw $e;
		}

		return $this;
	}

	/**
	 * Customizing an existing pair. Possible changes are:
	 *
	 * - customize SEF, with an option to also customize all duplicates
	 * - make duplicate the main URL
	 *
	 * @param int   $id
	 * @param array $urlData
	 * @param array $options
	 * @throws \Throwable
	 */
	public function modify($id, $urlData, $options)
	{
		$metaData = Wb\arrayGet($urlData, '__meta', []);
		unset($urlData['__meta']);

		$action = Wb\arrayGet($metaData, 'action', null); // customizeSef | makeMain

		if ('customizeSef' === $action)
		{
			$this->customizeSef(
				$urlData,
				$metaData
			);
		}

		if ('makeMain' === $action)
		{
			$this->makeMain(
				$urlData,
				$metaData
			);
		}

		return [
			'data'  => $this->get(),
			'count' => 1,
			'total' => 1,
		];
	}

	/**
	 * Assign a custom SEF URL to the URL pair, optionally customizing as well
	 * any duplicates it may have.
	 *
	 * @param array $urlData
	 * @param array $metaData
	 * @return $this
	 * @throws \Throwable
	 */
	private function customizeSef($urlData, $metaData)
	{
		$urlData['custom'] = self::CUSTOM;

		$newBasePath         = Wb\arrayGet($urlData, 'base_path', '');
		$extraPath           = Wb\arrayGet($urlData, 'extra_path', '');
		$customizeDuplicates = Wb\arrayGet($metaData, 'customizeDuplicates', true);
		$originalBasePath    = Wb\arrayGet($metaData, 'originalBasePath', true);
		$originalSef         = Wb\arrayGet($urlData, 'sef', '');
		$originalExtraPath   = $extraPath;

		$routingConfig         = $this->factory->getThis('forsef.config', 'routing');
		$suffix                = $routingConfig->get('suffix', '');
		$extraPathLeadingSlash = '';

		// ensure consistency of base path
		$newBasePath          = $this->sefHelper->conform($newBasePath);
		$urlData['base_path'] = $newBasePath;

		// special case: changing the base path AND changing a trailing slash
		// into the suffix
		if (
			!empty($suffix)
			&&
			Wb\endsWith($newBasePath, $suffix)
			&&
			Wb\endsWith($originalBasePath, '/')
		)
		{
			$extraPathLeadingSlash = '/';
		}

		// special case: user manually adds the suffix (typically to
		// a category page, that normally ends with a slash)
		if (
			!empty($suffix)
			&&
			Wb\endsWith($newBasePath, $suffix)
		)
		{
			$newBasePath          = Wb\rTrim($newBasePath, $suffix);
			$urlData['base_path'] = $newBasePath;
			if (empty($extraPath))
			{
				$extraPath             = $suffix;
				$urlData['extra_path'] = $extraPath;
			}

			$urlData['sef'] = $newBasePath . $extraPath;
		}

		// special case: users removes a trailing slash
		// That trailing slash must be moved to the extra_path, if any
		// so that it's not missing when the extra_path is added to the base_path
		$modifiedSegment = Wb\lTrim(
			$originalBasePath,
			$newBasePath
		);
		if ('/' === $modifiedSegment)
		{
			$extraPathLeadingSlash = '/';
		}

		if (
			!empty($extraPathLeadingSlash)
			&&
			!empty($extraPath)
			&&
			$suffix !== $extraPath
		)
		{
			$extraPath             = $extraPathLeadingSlash . $extraPath;
			$urlData['extra_path'] = $extraPath;
		}

		try
		{
			$urlData['sef'] = preg_replace(
				'~^' . preg_quote($originalBasePath) . '~',
				$newBasePath,
				$urlData['sef']
			);


			// Figure out all modified SEF URLs, to allow 3rd-parties to react to the change.
			// As 4SEO (our main target) works off SEF URLs, we don't need duplicates.
			$customizedSefs = $this->db->selectColumn(
				$this->table,
				'sef',
				[
					'base_path' => $originalBasePath,
					'duplicate' => self::CANONICAL
				]
			);

			$this->db->db()->transactionStart();

			$this->set($urlData)
				 ->store();

			if ($customizeDuplicates)
			{
				// all duplicates are modified, canonical stays the same
				$this->db->update(
					$this->table,
					[
						'custom'     => self::CUSTOM,
						'sef'        => $urlData['sef'],
						'base_path'  => $newBasePath,
						'extra_path' => $extraPath,
					],
					[
						'base_path'  => $originalBasePath,
						'extra_path' => $originalExtraPath,
						'duplicate'  => self::DUPLICATE
					]
				);
			}

			if (!$customizeDuplicates)
			{
				// we only customize one URL. If customizing a main URL, it means it may have had duplicates and one of them
				// now needs to become the canonical URL. We pick the oldest one.
				$this->db->setQueryAnd(
					'update ' . $this->db->qn($this->table)
					. ' set ' . $this->db->qn('duplicate') . ' = ' . self::CANONICAL
					. ' where ' . $this->db->qn('sef') . ' = ' . $this->db->q($originalBasePath)
					. ' limit 1'
				)->execute();
			}

			// in addition, as a base_path has been customized, all URLs derived from that base path are also
			// customized. Typically page 2, 3 and more of a category.
			// we only do this when customizing all duplicates. When trying to do it on a single URL, it breaks
			// because all duplicates are in fact customized anyway, we can't distinguish between variants of original
			// customized URLs and variants of the duplicates - because they all have the same base_path anyway.
			if ($customizeDuplicates)
			{
				$this->db->setQueryAnd(
					'update ' . $this->db->qn($this->table)
					. ' set ' . $this->db->qn('custom') . ' = ' . self::CUSTOM
					. ', ' . $this->db->qn('sef') . ' = concat(' . $this->db->q($newBasePath) . ', ' . $this->db->q($extraPathLeadingSlash) . ', substr(' . $this->db->qn('sef') . ', ' . (StringHelper::strlen($originalBasePath) + 1) . '))'
					. ', ' . $this->db->qn('base_path') . ' = ' . $this->db->q($newBasePath)
					. ' where ' . $this->db->qn('base_path') . ' = ' . $this->db->q($originalBasePath)
					. (
					$customizeDuplicates
						? ''
						: ' and ' . $this->db->qn('duplicate') . ' = ' . self::CANONICAL
					)
				)->execute();
			}

			if (!$customizeDuplicates)
			{
				// we only customize one URL. If customizing a canonical URL, it means it may have had duplicates and one of them
				// now needs to become the canonical URL. We pick the oldest one.
				$this->db->setQueryAnd(
					'update ' . $this->db->qn($this->table)
					. ' set ' . $this->db->qn('duplicate') . ' = ' . self::CANONICAL
					. ' where ' . $this->db->qn('base_path') . ' = ' . $this->db->q($originalBasePath)
					. ' and ' . $this->db->qn('duplicate') . ' = ' . self::DUPLICATE
					. ' and ' . $this->db->qn('sef') . ' != ' . $this->db->q($originalBasePath)
					. ' limit 1'
				)->execute();
			}

			$this->db->db()->transactionCommit();

			/**
			 * Run hook after a URL has been customized to allow 3rd-party actions.
			 *
			 * @api     forsef
			 * @package 4SEF\action\sef
			 * @var forsef_url_customized
			 * @since   1.0.0
			 * @since   4.6.0 Added $originalSef parameter
			 * @since   4.8.0 Added $customizeDuplicates parameter
			 *
			 * @param array   $urlPair
			 * @param string  $originalBasePath
			 * @param array   $customizedSefs
			 * @param string  $originalSef
			 * @param string  $extraPathLeadingSlash
			 * @param boolean $customizeDuplicates
			 *
			 * @return void
			 *
			 */
			$this->factory->getThe('hook')->run(
				'forsef_url_customized',
				$this->get(),
				$originalBasePath,
				$customizedSefs,
				$originalSef,
				$extraPathLeadingSlash,
				$customizeDuplicates
			);
		}
		catch (\Throwable $e)
		{
			$this->db->db()->transactionRollback();
			throw $e;
		}

		return $this;
	}

	/**
	 * Make current URL pair the main one for a duplicates set.
	 *
	 * @param array $urlData
	 * @param array $metaData
	 * @return $this
	 * @throws \Throwable
	 */
	public function makeMain($urlData, $metaData)
	{
		$previousMain = (int)Wb\arrayGet($metaData, 'previousMain');
		if (empty($previousMain))
		{
			throw new \Exception('Trying to make a URL main but no previous main specified');
		}

		try
		{
			$this->db->db()->transactionStart();

			$this->set($urlData)
				 ->set(
					 'duplicate',
					 self::CANONICAL
				 )->store(
					[
						'noDuplicateCheck' => true
					]
				);

			$this->factory
				->getA(static::class)
				->load($previousMain)
				->set(
					'duplicate',
					self::DUPLICATE
				)->store(
					[
						'noDuplicateCheck' => true
					]
				);

			$this->db->db()->transactionCommit();
		}
		catch (\Throwable $e)
		{
			$this->db->db()->transactionRollback();
			throw $e;
		}

		return $this;
	}

}
