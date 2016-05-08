<?php
/*NAMESPACE*/

use /*USE_NAMESPACE*/MOMBaseException as BaseException;
use /*USE_NAMESPACE*/MOMMySQLException as MySQLException;

class MOMSimple extends MOMBase
{
	/**
	  * Static object cache
	  * MOMSimple supports storing objects selected by id in a static cache
	  * @var array<classname, array<primary_key_value, Object>>
	  */
	protected static $__mbObjectCache = array();

	/**
	  * Constructs an object of the extending class using parent constructor
	  * Checks if a primary key has been defined on the extending class
	  * @param \mysqli $connection mysqli connection
	  */
	public function __construct(\mysqli $connection = NULL)
	{
		self::hasPrimaryKey();
		parent::__construct($connection);
	}

	/**
	  * Get by primary key
	  * @param mixed $id
	  * @throws BaseException
	  * @throws MySQLException
	  * @return object
	  */
	public static function getById($id)
	{
		$class = get_called_class();
		self::checkDbAndTableConstants($class);
		self::hasPrimaryKey();

		if (empty($id))
			throw new BaseException(BaseException::OBJECT_NOT_FOUND, get_called_class().'::'.__FUNCTION__.' got empty primary key value');

		$selector = self::getSelector($id);
		// early return from static cache
		if (($entry = self::getStaticEntry($selector)) !== FALSE)
			return $entry;

		// early return from memcache
		if (($entry = self::getMemcacheEntry($selector)) !== FALSE)
		{
			self::setStaticEntry($selector, $value);
			return $entry;
		}

		$new = NULL;
		if (($row = self::getRowById($id, self::CONTEXT_STATIC)) !== NULL)
		{
			$new = new static();
			$new->fillByStatic($row);
		}

		/**
		  * Cache fetched object
		  * Objects that are NULL will be store in static cache but not memcache
		  * To reselect the same non exsistant element during a session is unnessesary but to put it in memcache would be unwise.
		  */
		self::setStaticEntry($selector, $new);
		if ($new !== NULL)
			self::setMemcacheEntry($selector, $new);
	
		return $new;
	}

	/**
	  * Get mysql row by primary key
	  * @param mixed $id
	  * @throws MySQLException
	  * @return resource(mysql resource) or NULL on failure
	  */
	private function getRowById($id, $context)
	{
		if ($context == self::CONTEXT_STATIC)
			$id = self::escapeStatic($id);
		else if ($context == self::CONTEXT_OBJECT)
			$id = $this->escapeObject($id);

		$sql = 
			'SELECT * FROM `'.static::DB.'`.`'.static::TABLE.'`'.
			' WHERE `'.static::COLUMN_PRIMARY_KEY.'` = '.$id;

		$res = NULL;
		if ($context == self::CONTEXT_STATIC)
		{
			$res = self::queryStatic($sql);
		}
		else if ($context == self::CONTEXT_OBJECT)
		{
			$res = $this->queryObject($sql);
		}
			
		return $res->fetch_assoc();
	}

	/**
	 * Save the object
	 * @throws BaseException
	 */
	public function save()
	{
		$sql = static::buildSaveSql();

		$this->tryToSave($sql);
		
		$keyname = static::COLUMN_PRIMARY_KEY;
		if ($this->__mbNewObject)
			$id = $this->__mbConnection->insert_id;
		else
			$id = $this->$keyname;

		if (($row = self::getRowById($id, self::CONTEXT_OBJECT)) === NULL)
			throw new BaseException(BaseException::OBJECT_NOT_UPDATED, get_called_class().'->'.__FUNCTION__.' failed to update object with metadata from database');
		
		$this->fillByObject($row);

		$selector = self::getSelector($id);
		if ($this->__mbNewObject)
			static::setStaticEntry($selector, $this);

		self::setMemcacheEntry($selector, $this, self::CONTEXT_OBJECT);
	}

	/**
	 * Delete the object
	 * Will throw exception on all failures, if no exception, then object is deleted
	 * @throws BaseException
	 */
	public function delete()
	{
		$keyname = static::COLUMN_PRIMARY_KEY;
		$id = $this->$keyname;
		if (empty($id))
			throw new BaseException(BaseException::OBJECT_NOT_DELETED, get_called_class().'->'.__FUNCTION__.' failed to delete, primary key was empty');
		
		$sql = 
			'DELETE FROM `'.static::DB.'`.`'.static::TABLE.'`'.
			' WHERE `'.static::COLUMN_PRIMARY_KEY.'` = '.$this->escapeObject($id);

		static::tryToDelete($sql);

		$selector = self::getSelector($id);
		$this->deleteMemcacheEntry($selector);
		self::deleteStaticEntry($selector);
	}

	/**
	  * Build save sql using extending class description
	  * @return string
	  */
	protected function buildSaveSql()
	{
		$values = array();
		$class = get_called_class();
		$primaryKey = static::COLUMN_PRIMARY_KEY;
		$autoIncrement = FALSE;
		foreach (static::$__mbDescriptions[$class] as $field)
		{
			//Ensures that the primay key and mysql protected value defaults are not in values array
			if ($field['Field'] !== $primaryKey && 
				!in_array($field['Default'], self::$__mbProtectedValueDefaults))
				$values[] = $this->escapeObjectPair($field['Field']);

			if ($field['Key'] == 'PRI' && $field['Extra'] == 'auto_increment')
				$autoIncrement = TRUE;
		}

		if ($this->__mbNewObject)
		{
			if (!$autoIncrement)
			$values[] = ' `'.static::COLUMN_PRIMARY_KEY.'` = '.$this->escapeObject($this->$primaryKey);

			$sql = 
				'INSERT INTO `'.static::DB.'`.`'.static::TABLE.'` SET'.
				' '.join(', ', $values);
		}
		else
		{
			$sql = 
				'UPDATE `'.static::DB.'`.`'.static::TABLE.'` SET'.
				' '.join(', ', $values).
				' WHERE `'.static::COLUMN_PRIMARY_KEY.'` = '.$this->escapeObject($this->$primaryKey);
		}

		return $sql;
	}

	/**
	  * Get a rows unique identifier, e.g. primary key
	  * @param string[] $row mysqli_result->fetch_assoc
	  * @return string
	  */
	protected static function getRowIdentifier($row)
	{
		return $row[static::COLUMN_PRIMARY_KEY];
	}

	/**
	  * Get static cache and memcache selector
	  * @param string $id
	  * @return string
	  */
	private static function getSelector($id)
	{
		return static::COLUMN_PRIMARY_KEY.'_'.$id;
	}

	/**
	  * Checks if the extending class has defined a primary key
	  * @throws BaseException
	  */
	private static function hasPrimaryKey()
	{
		if (!defined('static::COLUMN_PRIMARY_KEY'))
			throw new BaseException(BaseException::PRIMARY_KEY_NOT_DEFINED, get_called_class().' has no COLUMN_PRIMARY_KEY const');
	}

	/**
	  * When cloing a MySqlSimple object, the new object is no longer persistent 
	  * It will create a new entry when saved
	  */
	public function __clone()
	{
		$primaryKey = static::COLUMN_PRIMARY_KEY;
		$this->$primaryKey = NULL;
		$this->__mbNewObject = TRUE;
		$this->__mbMemcacheTimestamp = 0;
		$this->__mbStaticCacheTimestamp = 0;
	}
}
?>
