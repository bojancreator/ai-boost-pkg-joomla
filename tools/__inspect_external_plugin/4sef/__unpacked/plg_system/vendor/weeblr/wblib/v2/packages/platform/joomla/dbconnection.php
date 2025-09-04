<?php
/**
 * @build_title_build       @
 *
 * @package                 4SEF
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 2.6.2.644
 *
 * 2025-06-02
 */

namespace Weeblr\Wblib\Forsef\Platform\Joomla;

use Weeblr\Wblib\Forsef\Wb;
use Joomla\CMS\Factory;

use Weeblr\Wblib\Forsef\Platform\Platformdbconnectioninterface;

/* Security check to ensure this file is being included by a parent file.*/
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 *
 * Interface to Joomla! database driver
 *
 * @author weeblr
 *
 */
class Dbconnection implements Platformdbconnectioninterface
{
	protected $db = null;

	protected $_uniqueId;

	public function __construct(...$args)
		// Legacy signature
		//	public function __construct($uniqueId = '', $db = null)
	{
		$uniqueId = '';
		$db       = null;

		if (\is_array($args) && \count($args) === 1 && \is_array($args[0]))
		{
			// new format, all options in an array
			$uniqueId = Wb\arrayGet($args[0], 'unique_id', '');
			$db       = Wb\arrayGet($args[0], 'db', null);
		}

		// Legacy arguments, first is $uniqueId
		if (\count($args) > 0 && \is_string($args[0]) && !empty(\trim($args[0])))
		{
			$uniqueId = $args[0];
		}

		// Legacy argurments, second is an object
		if (\count($args) === 2 && \is_object($args[1]) && !empty($args[1]))
		{
			$db = $args[1];
		}

		if (\version_compare(\JVERSION, '4.0', '<'))
		{
			$this->_uniqueId = $uniqueId ?? Factory::getConfig()->get('secret');
			$this->db        = $db ?? Factory::getDbo();
		}
		else
		{
			$this->_uniqueId = $uniqueId ?? Factory::getApplication()->get('secret');
			$this->db        = $db ?? Factory::getContainer()->get('db');
		}
	}

	public function getQuery()
	{
		return $this->db->getQuery();
	}

	public function getPrefix()
	{
		return $this->db->getPrefix();
	}

	public function quote($data, $escape = true)
	{
		return $this->db->quote($data, $escape = true);
	}

	public function quoteName($data)
	{
		return $this->db->quoteName($data);
	}

	public function quoteTable($data)
	{
		return $this->db->quoteName($data);
	}

	public function escape($data, $extra = false)
	{
		return $this->db->escape($data);
	}

	public function getNullDate()
	{
		return $this->db->getNullDate();
	}

	public function getTableList()
	{
		return $this->db->getTableList();
	}

	public function setQuery($query, $offset = 0, $limit = 0)
	{
		return $this->db->setquery($query, $offset, $limit);
	}

	public function loadAssoc()
	{
		return $this->db->loadAssoc();
	}

	public function loadAssocList($key = null, $column = null)
	{
		return $this->db->loadAssocList($key, $column);
	}

	public function loadColumn($offset = 0)
	{
		return $this->db->loadColumn($offset);
	}

	public function loadObject()
	{
		return $this->db->loadObject();
	}

	public function loadObjectList($key = '')
	{
		return $this->db->loadObjectList($key);
	}

	public function loadResult()
	{
		return $this->db->loadResult();
	}

	public function loadRow()
	{
		return $this->db->loadRow();
	}

	public function loadRowList($key = null)
	{
		return $this->db->loadRowList($key);
	}

	public function getInsertId()
	{
		return $this->db->insertId();
	}

	/**
	 * Start a transation.
	 *
	 * @return mixed
	 */
	public function transactionStart()
	{
		return $this->db->transactionStart();
	}

	/**
	 * Commit queries in transaction.
	 *
	 * @return mixed
	 */
	public function transactionCommit()
	{
		return $this->db->transactionCommit();
	}

	/**
	 * Rollback queries from transaction.
	 *
	 * @return mixed
	 */
	public function transactionRollback()
	{
		return $this->db->transactionRollback();
	}

	public function execute()
	{
		return $this->db->execute();
	}

	public function getTableColumns($table, $typeOnly = true)
	{
		return $this->db->getTableColumns($table, $typeOnly);
	}

	public function dropTable($table, $ifExists = true)
	{
		return $this->db->dropTable($table, $ifExists);
	}
}
