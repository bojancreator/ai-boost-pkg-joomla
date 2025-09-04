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

namespace Weeblr\Forsef\Model\Admin;

use Weeblr\Forsef\Data;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;


// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Redirect extends Base\Base
{
	/**
	 * Hook handler ran when a URL pair is manually customized.
	 *
	 * @param array  $data
	 * @param array  $customizedSefs
	 * @param string $originalBasePath
	 * @param string $originalSef
	 * @param string $extraPathLeadingSlash
	 *
	 * @return void
	 * @throws \Throwable
	 */
	public function onUrlCustomized($data, $originalBasePath, $customizedSefs, $originalSef, $extraPathLeadingSlash, $customizeDuplicates)
	{
		$this->addRedirect($data, $originalBasePath, $customizedSefs, $originalSef, $extraPathLeadingSlash, $customizeDuplicates);
	}

	/**
	 * After a URL has been customized, add a redirect from the previous SEF to the new one.
	 * Also handles updating any existing redirects related to either.
	 *
	 * @param array  $data
	 * @param string $originalBasePath
	 * @param string $originalBasePath
	 * @param string $originalSef
	 * @param string $extraPathLeadingSlash
	 * @param bool   $customizeDuplicates
	 *
	 * @return $this
	 * @throws \Throwable
	 */
	private function addRedirect($data, $originalBasePath, $customizedSefs, $originalSef, $extraPathLeadingSlash, $customizeDuplicates)
	{
		$newBasePath = Wb\arrayGet(
			$data,
			'base_path'
		);

		if ($newBasePath === $originalBasePath)
		{
			$this->factory->getThe('forsef.logger')->error(__METHOD__ . ': original SEF and customized one are the same, cannot add a redirect for ' . print_r($originalBasePath, true));

			return $this;
		}

		// redirect the modified URL
		$this->createRedirect(
			$originalSef,
			Wb\arrayGet(
				$data,
				'sef',
				''
			)
		);

		// redirect all the variants
		if ($customizeDuplicates)
		{
			foreach ($customizedSefs as $customizedSef)
			{
				if ($originalSef === $customizedSef)
				{
					continue;
				}

				$targetSef = $newBasePath
							 . $extraPathLeadingSlash
							 . StringHelper::substr($customizedSef, StringHelper::strlen($originalBasePath));

				$this->createRedirect(
					$customizedSef,
					$targetSef
				);
			}
		}

		return $this;
	}

	/**
	 * Tries to redirect source to target, taking into account
	 * possibly pre-existing redirect chains.
	 *
	 * @param $sourceSef
	 * @param $targetSef
	 * @return void
	 * @throws \Throwable
	 */
	private function createRedirect($sourceSef, $targetSef)
	{
		if ($sourceSef === $targetSef)
		{
			return;
		}

		$dbHelper = $this->factory->getThe('db');
		$table    = '#__forsef_redirects';

		try
		{
			$dbHelper->db()->transactionStart();
			// do we already have a target -> source redirect? would cause a loop. If so delete it
			// but still add source -> target.
			// We want A -> B
			// If we already have
			// B -> A
			// we must delete B -> A
			$dbHelper->delete(
				$table,
				[
					'source' => $sourceSef,
					'target' => $targetSef
				]
			);

			// Any redirects that target the source must be modified to target our target.
			// We want A -> B
			// If we already have
			// C -> A
			// D -> A
			// we must replace them with
			// C -> B
			// D -> B
			$dbHelper->update(
				$table,
				[
					'target' => $targetSef
				],
				[
					'target' => $sourceSef
				]
			);

			// already exists for some reason?
			$exists = $dbHelper->count(
				$table,
				'*',
				[
					'source' => $sourceSef,
					'target' => $targetSef
				]
			);

			if (empty($exists))
			{
				$this->factory
					->getA(Data\Redirect::class)
					->set(
						[
							'source' => $sourceSef,
							'target' => $targetSef
						]
					)->store();
			}

			$dbHelper->db()->transactionCommit();
		}
		catch (\Throwable $e)
		{
			$dbHelper->db()->transactionRollback();
			throw $e;
		}
	}
}
