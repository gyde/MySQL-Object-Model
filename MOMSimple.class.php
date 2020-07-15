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
	  * @param \PDO $connection PDO connection
	  * @param \memcached $memcache memcache connection
	  * @param int $memcacheExpiration memcache expiration in seconds
	  */
	public function __construct(\PDO $connection = NULL, \Memcached $memcache = NULL, $memcacheExpiration = 0)
	{
		self::hasPrimaryKey();
		parent::__construct($connection, $memcache, $memcacheExpiration);
	}

	/**
	  * Get by primary key
	  * @param mixed $id
	  * @param bool $allowNull allow NULL to be return when empty id is provided
	  * @throws BaseException
	  * @throws MySQLException
	  * @return object
	  */
	public static function getById($id, $allowNull = false)
	{
		$class = get_called_class();
		self::checkDbAndTableConstants($class);
		self::hasPrimaryKey();

		if (empty($id) && $allowNull)
			return NULL;

		if (empty($id))
			throw new BaseException(BaseException::OBJECT_NOT_FOUND, get_called_class().'::'.__FUNCTION__.' got empty primary key value');

		$selector = static::getSelector([static::COLUMN_PRIMARY_KEY => $id]);

		// early return from cache
		if (($entry = self::getCacheEntry($selector)) !== FALSE)
			return $entry;

		$new = NULL;
		if (($row = self::getRowByIdStatic($id)) !== FALSE)
		{
			$new = new static();
			$new->fillByStatic($row);
		}

		self::setCacheEntry($selector, $new);

		return $new;
	}

	/**
	  * Get mysql row by primary key
	  * @param mixed $id
	  * @throws MySQLException
	  * @return resource(mysql resource) or false on failure
	  */
	private function getRowById($id)
	{
		$id = $this->escapeObject($id);
		$sql = self::getRowByIdSelect($id);
		$res = $this->queryObject($sql);

		return $res->fetch();
	}

	/**
	  * Get mysql row by primary key
	  * @param mixed $id escaped
	  * @throws MySQLException
	  * @return resource(mysql resource) or false on failure
	  */
	private static function getRowByIdStatic($id)
	{
		$id = self::escapeStatic($id);
		$sql = self::getRowByIdSelect($id);
		$res = self::queryStatic($sql);

		return $res->fetch();
	}

	/**
	  * Get SELECT statement for get by id
	  * @param mixed $id escaped primary key value
	  * @return string
	  */
	private static function getRowByIdSelect($id)
	{
		return
			'SELECT * FROM `'.self::getDbName().'`.`'.static::TABLE.'`'.
			' WHERE `'.static::COLUMN_PRIMARY_KEY.'` = '.$id;
	}

	/**
	  * Save the object
	  * @param mixed $metaData data needed by save method
	  * @throws MOMBaseException
	  * @see MOMBase
	  */
	public function save($metaData = null)
	{
		$sql = static::buildSaveSql();

		$this->tryToSave($sql);

		$keyname = static::COLUMN_PRIMARY_KEY;
		if ($this->isNew() && $this->__mbConnection->lastInsertId() != 0)
		{
			$id = $this->__mbConnection->lastInsertId();
		}
		else
			$id = $this->$keyname;

		if (($row = self::getRowById($id)) === false)
			throw new BaseException(BaseException::OBJECT_NOT_UPDATED, get_called_class().'->'.__FUNCTION__.' failed to update object with metadata from database');

		// fillByObject() will change the return value of isNew()
		$wasCreated = $this->isNew();
		$this->fillByObject($row);

		if ($wasCreated){
			static::setStaticEntry($this->__mbSelector, $this);
		}

		self::setMemcacheEntry($this->__mbSelector, $this, self::CONTEXT_OBJECT);
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
			'DELETE FROM `'.self::getDbName().'`.`'.static::TABLE.'`'.
			' WHERE `'.static::COLUMN_PRIMARY_KEY.'` = '.$this->escapeObject($id);

		static::tryToDelete($sql);

		$this->deleteCacheEntry($this->__mbSelector);
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
				!in_array($field['Default'], self::$__mbProtectedValueDefaults) &&
				!in_array($field['Extra'], self::$__mbProtectedValueExtras))
				$values[] = $this->escapeObjectPair($field['Field'], $field['Type']);

			if ($field['Key'] == 'PRI' && $field['Extra'] == 'auto_increment')
				$autoIncrement = TRUE;
		}

		if ($this->isNew())
		{
			if (!$autoIncrement)
			$values[] = ' `'.static::COLUMN_PRIMARY_KEY.'` = '.$this->escapeObject($this->$primaryKey);

			$sql =
				'INSERT INTO `'.self::getDbName().'`.`'.static::TABLE.'` SET'.
				' '.join(', ', $values);
		}
		else
		{
			$sql =
				'UPDATE `'.self::getDbName().'`.`'.static::TABLE.'` SET'.
				' '.join(', ', $values).
				' WHERE `'.static::COLUMN_PRIMARY_KEY.'` = '.$this->escapeObject($this->$primaryKey);
		}

		return $sql;
	}

	/**
	  * Get a rows unique identifier, e.g. primary key
	  * @return string
	  */
	protected function getRowIdentifier()
	{
		return $this->{static::COLUMN_PRIMARY_KEY};
	}

	/**
	  * Get static cache and memcache selector
	  * @param array $row
	  * @return string
	  */
	protected static function getSelector($row)
	{
		return static::COLUMN_PRIMARY_KEY.'_'.$row[static::COLUMN_PRIMARY_KEY];
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
		$this->__mbSerializeTimestamp = 0;
		$this->__mbStaticCacheTimestamp = 0;
	}
}
?>
