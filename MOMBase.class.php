<?php
/*NAMESPACE*/

use /*USE_NAMESPACE*/MOMBaseException as BaseException;
use /*USE_NAMESPACE*/MOMMySQLException as MySQLException;

abstract class MOMBase
{
	const RESERVED_PREFIX = '__mb';
	const GLOBAL_CONNECTION = '__mbGlobalConnection';
	const GLOBAL_MEMCACHE = '__mbGlobalMemcache';

	const CONTEXT_STATIC = 'STATIC';
	const CONTEXT_OBJECT = 'OBJECT';

	const USE_STATIC_CACHE = FALSE;
	const USE_MEMCACHE = FALSE;

	const CLASS_REVISION = 0;

	/**
	  * Every object has its own mysqli connection
	  * If none is providede on object instansiation one is picked
	  * from $__mbConnections
	  * @var \mysqli $__mbConnection
	  */
	protected $__mbConnection = NULL;

	/**
	  * Every object has its own memcache connection (most likely shared and only used if memcache is enabled for the extending class)
	  * If none is providede on object instansiation one is picked
	  * from $__mbMemcaches if available either globally or by class
	  * @var array<string, mixed> $__mbMemcache
	  */
	protected $__mbMemcache = FALSE;

	/**
	  * Defines object behavior on save / update
	  * @var bool $__mbNewObject
	  */
	protected $__mbNewObject = TRUE;

	/**
	  * Defines when object was put in static cache
	  * @var int $__mbStaticCacheTimestamp
	  */
	protected $__mbStaticCacheTimestamp = 0;

	/**
	  * Defines when object was put in memcache
	  * @var int $__mbMemcacheTimestamp
	  */
	protected $__mbMemcacheTimestamp = 0;

	/**
	  * Defines database names, this will override usage of constant DB
	  * This is mapped by class name and supports nested extending
	  * Use method setDbName()
	  * @see getDbName
	  * @var string $__mbDatabaseNames
	  */
	protected static $__mbDatabaseNames = NULL;

	/**
	  * Static cache for objects
	  * @var string[][]
	  */
	protected static $__mbStaticCache = array();

	/**
	  * Static cache with all model descriptions
	  * @var array<classname, array<string, string>>
	  */
	protected static $__mbDescriptions = array();

	/**
	  * Values used by mysql as default values for columns
	  * When these are picked up from the model description 
	  * nothing is inserted into the save or update query
	  * for these fields
	  * @var string[]
	  */
	protected static $__mbProtectedValueDefaults = array('CURRENT_TIMESTAMP', 'NOW()');

	/**
	  * Names of all basic classes in MySQL-Object-Model
	  * Used as stop words when searching static arrays for 
	  * nested extending class data
	  * @var string[]
	  */
	protected static $__mbProtectedClasses = array('MOMBase','MOMSimple','MOMCompound');

	/**
	  * Static cache with all mysqli connections
	  * Can contain a global connection, or one per extending class
	  * Depending on the use of setConnection
	  * @var \mysqli[]
	  */
	protected static $__mbConnections = array();

	/**
	  * Static cache with all memcache connections
	  * Can contain a global connection, or one per extending class
	  * Depending on the use of setMemcache
	  * @var \memcached[]
	  */
	protected static $__mbMemcaches = array();

	/**
	  * Constructs an object of extending class using the database fields
	  * Checks if the extending class has the correct consts and describes the extending class via mysqli
	  * @param \mysqli $connection mysqli connection
	  * @param \memcached $memcache memcache connection
	  * @param int $memcacheExpiration memcache expiration in seconds
	  */
	public function __construct(\mysqli $connection = NULL, \Memcached $memcache = NULL, $memcacheExpiration = 0)
	{
		$class = get_called_class();
		$this->checkDbAndTableConstants($class);

		if ($connection instanceOf \mysqli)
			$this->__mbConnection = $connection;
		else
			$this->__mbConnection = self::getConnection();

		if ($memcache instanceOf \Memcache)
		{
			if (!self::useMemcache())
				throw new BaseException(BaseException::MEMCACHE_NOT_ENABLED_BUT_SET);
			$options = array('memcache' => $memcache, 'expiration' => (int)$memcacheExpiration);
			$this->__mbMemcache = $options;
		}
		else
		{
			if (self::useMemcache())
				$this->__mbMemcache = self::getMemcache();
		}

		$this->describe($class);

		foreach (self::$__mbDescriptions[$class] as $field)
		{
			if (!in_array($field['Default'], self::$__mbProtectedValueDefaults))
				$this->$field['Field'] = $field['Default'];
			else
				$this->$field['Field'] = NULL;
		}
	}

	/**
	  * Save the object in the database
	  * The object itself is updated with row data reselected 
	  * from the database, iorder to update default values from table definition
	  * If save fails a MOMBaseException should be thrown
	  * @throws MOMBaseException
	  */
	abstract public function save();

	/**
	  * Abstract method, build the sql statement used by save method
	  * Often needs to be overwritten to support other MySQL patteren
	  * @return string sql statement
	  */
	abstract protected function buildSaveSql();

	/**
	  * Delete the object in the database
	  * If delete fails MOMBaseException should be thrown
	  * @throws MOMBaseException
	  */
	abstract public function delete();

	/**
	  * Get a rows unique identifier, e.g. primary key, or a compound key
	  * @param string[] $row mysqli_result->fetch_assoc
	  * @return string
	  */
	abstract protected static function getRowIdentifier($row);

	/**
	  * Set database name
	  * @param string $name
	  */
	public static function setDbName($name)
	{
		static::$__mbDatabaseNames[get_called_class()] = $name;
	}

	/**
	  * Get database name
	  * @return string
	  */
	public static function getDbName()
	{
		if (is_array(static::$__mbDatabaseNames))
		{
			if (($name = self::getNestedDbName(get_called_class())) !== FALSE)
				return $name;
		}

		return static::DB;
	}

	/**
	  * Get database name defined using setDbName
	  * This will backtrack extending classes 
	  * Note, this method is recursive
	  * @param string $class
	  * @return string Will return FALSE if no db name is found
	  */
	protected static function getNestedDbName($class)
	{
		if (in_array($class, self::$__mbProtectedClasses))
			return FALSE;

		if (isset(self::$__mbDatabaseNames[$class]))
			return self::$__mbDatabaseNames[$class];

		return self::getNestedDbName(get_parent_class($class));
	}

	/**
	  * Get one object
	  * @param string $where MySQL where clause
	  * @param string $order MySQL order clause
	  * @return object
	  * @throws \util\DatabaseException
	  */
	public static function getOne ($where = NULL, $order = NULL)
	{
		$new = NULL;

		$sql = 'SELECT * FROM `'.self::getDbName().'`.`'.static::TABLE.'`';

		if ($where)
			$sql .= ' WHERE '.$where;

		if ($order)
			$sql .= ' ORDER BY '.$order;

		$sql .= ' LIMIT 1';

		$res = self::queryStatic($sql);
		if (($row = $res->fetch_assoc()) !== NULL)
		{
			$new = new static();
			$new->fillByStatic($row);
		}

		return $new;
	}
	
	/**
	  * Get all objects
	  * @param string $order MySQL order clause
	  * @param bool $keyed if set, returns an associated array with unique key as array key
	  * @param int $limit MySQL LIMIT clause
	  * @param int $offset MySQL LIMIT clause (offset)
	  * @throws \util\DatabaseException
	  * @return object[]
	  */
	public static function getAll ($order = NULL, $keyed = FALSE, $limit = NULL, $offset = NULL)
	{
		return self::getAllByWhereGeneric(NULL, $order, $keyed, $limit, $offset);
	}

	/**
	  * Get many objects by a MySQL WHERE clause
	  * @param string $where MySQL WHERE clause
	  * @param string $order MySQL ORDER clause
	  * @param bool $keyed if set, returns an associated array with unique key as array key
	  * @param int $limit MySQL LIMIT clause
	  * @param int $offset MySQL LIMIT clause (offset)
	  * @throws \util\DatabaseException
	  * @return object[]
	  */
	public static function getAllByWhere($where, $order = NULL, $keyed = FALSE, $limit = NULL, $offset = NULL)
	{
		return self::getAllByWhereGeneric($where, $order, $keyed, $limit, $offset);
	}

	/**
	  * Get many obejcts by a sql where clause
	  * @param string $where SQL where clause
	  * @param string $order SQL order clause
	  * @param bool $keyed if set, returns an associated array with unique key as array key
	  * @param int $limit MySQL LIMIT clause
	  * @param int $offset MySQL LIMIT clause (offset)
	  * @throws \util\DatabaseException
	  * @return object[]
	 */
	protected static function getAllByWhereGeneric($where = NULL, $order = NULL, $keyed = FALSE, $limit = NULL, $offset = NULL)
	{
		$many = array();

		$sql = 'SELECT * FROM `'.self::getDbName().'`.`'.static::TABLE.'`';

		if ($where)
			$sql .= ' WHERE '.$where;

		if ($order)
			$sql .= ' ORDER BY '.$order;

		if ($limit !== NULL || $offset !== NULL)
			$sql .= ' LIMIT '.(int)$offset.','.(int)$limit;

		$res = self::queryStatic($sql);
		while (($row = $res->fetch_assoc()) !== NULL)
		{
			$new = new static();
			$new->fillByStatic($row);
			if ($keyed)
				$many[static::getRowIdentifier($row)] = $new;
			else
				$many[] = $new;
		}

		return $many;
	}

	
	/**
	 * Delete a single object by a MySQL WHERE clause
	 * @param string $where MySQL WHERE clause
	 * @throws MySQLException
	 */
	protected static function deleteAllByWhere($where)
	{
		if (empty($where))
			throw new BaseException(BaseException::OBJECTS_NOT_DELETED);

		$sql = 
			'DELETE FROM `'.self::getDbName().'`.`'.static::TABLE.'`'.
			' WHERE '.$where;

		try
		{
			self::queryStatic($sql);
		}
		catch (BaseException $e)
		{
			throw new BaseException(BaseException::OBJECTS_NOT_DELETED, $e->getMessage(), $e);
		}
	}

	/**
	  * Updates an obejct from object methods 
	  * @param string[] $row \mysqli_result->fetch_assoc
	  */
	protected function fillByObject($row)
	{
		$this->fill($row);
	}

	/**
	  * Creates an object from static methods
	  * Binds the static mysqli connection to the object
	  * @param string[] $row \mysqli_result->fetch_assoc
	  */
	protected function fillByStatic($row)
	{
		$this->fill($row);
		$this->__mbConnection = self::getConnection();
		$this->__mbMemcache = self::getMemcache();
	}

	/**
	  * Fills object 
	  * Creates public vars on object with name of row key, and value of row value
	  * Sets object to be old, meaning that its been fetched from database
	  * @param string[] $row \mysqli_result->fetch_assoc
	  */
	protected function fill($row)
	{
		foreach ($row as $key => $value)
		{
			$this->$key = $value;
		}
		$this->__mbNewObject = FALSE;
	}

	/**
	  * Trys to save the object
	  * @param string $sql
	  */
	protected function tryToSave($sql)
	{
		try
		{
			$this->queryObject($sql);
		}
		catch (MySQLException $e)
		{
			if ($e->getMysqlErrno() == MySQLException::ER_DUP_ENTRY)
				throw new BaseException(BaseException::OBJECT_DUPLICATED_ENTRY, get_called_class().' tried to created new object, but primary key already exists', $e);

			throw new BaseException(BaseException::OBJECT_NOT_SAVED, $e->getMysqlError(), $e);
		}
	}

	/**
	  * Trys to delete the object
	  * @param string $sql
	  */
	protected function tryToDelete($sql)
	{
		try
		{
			$this->queryObject($sql);
		}
		catch (MysqlException $e)
		{
			throw new BaseException(BaseException::OBJECT_NOT_DELETED, get_called_class().' tried to delete an object', $e);
		}
	}

	/**
	  * Describes the class using DESCRIBE 
	  * Caches model in static cache and in Memcache(if enabled)
	  * Used when creating new objects, for default values and field info
	  * Entry in memcache will be keyed using classname and CLASS_REVISION
	  * @param string $class classname of the extending class
	  */
	private function describe($class)
	{
		if (!array_key_exists($class, self::$__mbDescriptions))
		{
			$selector = self::getMemcacheKey('DESCRIPTION');
			if (($entry = self::getMemcacheEntry($selector)) !== FALSE)
			{
				self::$__mbDescriptions[$class] = $entry;
			}
			else
			{
				$sql = 'DESCRIBE `'.self::getDbName().'`.`'.static::TABLE.'`';
				$res = $this->queryObject($sql);
				$description = array();
				while (($row = $res->fetch_assoc()) !== NULL)
				{
					// If Field start is equal to self::RESERVED_PREFIX
					if (strpos($row['Field'], self::RESERVED_PREFIX) === 0)
						throw new BaseException(BaseException::RESERVED_VARIABLE_COMPROMISED, $class.' has a column named '.$row['Field'].', __mb is reserved for internal stuff');

					$description[] = $row;
				}
				self::$__mbDescriptions[$class] = $description;
				self::setMemcacheEntry($selector, $description);
			}
		}
	}

	/**
	 * Return if $value is valid with respect to constants with $prefix
	 * @param string $preFix
	 * @param mixed $value
	 * @return bool
	 */
	protected static function validateConst($preFix, $value)
	{
		$reflection = new \ReflectionClass(get_called_class());
		$consts = $reflection->getConstants();
		foreach ($consts as $constName => $constValue)
		{
			if (strpos($constName, $preFix) === 0)
			{
				if ($constValue == $value)
					return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Returns an array of constant values based on $prefix
	 * @param string $prefix
	 * @return array Key: constant name. Value: constant value
	 */
	protected static function getConstants($prefix)
	{
		$reflection = new \ReflectionClass(get_called_class());
		$constants = $reflection->getConstants();
		$values = array();

		foreach ($constants as $name => $value)
		{
			if (strpos($name, $prefix) === 0)
				$values[$name] = $value;
		}

		return $values;
	}

	/**
	  * Query method for static methods
	  * @param string $sql
	  * @param enum(\MYSQLI_USE_RESULT, \MYSQLI_STORE_RESULT) $resultmode
	  * @return mixed mysqli_result or TRUE
	  * @throws MySQLException
	  */
	protected static function queryStatic($sql, $resultmode = \MYSQLI_STORE_RESULT)
	{
		return self::query(self::getConnection(), $sql, $resultmode);
	}

	/**
	  * Query method for objects, always uses the objects specific mysqli connection
	  * @param string $sql
	  * @param enum(\MYSQLI_USE_RESULT, \MYSQLI_STORE_RESULT) $resultmode
	  * @return mixed mysqli_result or TRUE
	  * @throws MySQLuException
	  */
	protected function queryObject($sql, $resultmode = \MYSQLI_STORE_RESULT)
	{
		return self::query($this->__mbConnection, $sql, $resultmode);
	}

	/**
	  * Generic query method
	  * @param mysqli $connection mysqli connection
	  * @param enum(\MYSQLI_USE_RESULT, \MYSQLI_STORE_RESULT) $resultmode
	  * @return mixed mysqli_result or TRUE
	  * @throws MySQLException
	  */
	private static function query($connection, $sql, $resultmode)
	{
		$result = $connection->query($sql, $resultmode);
		if ($result === FALSE)
			throw new MySQLException($sql, $connection->error, $connection->errno);

		return $result;
	}

	/**
	  * Escape object value
	  * Everything is escaped as strings except for NULL
	  * TODO Optimize for int and other
	  * @param string $field 
	  * @return string
	  */
	protected function escapeObjectPair($field)
	{
		if ($this->$field !== NULL)
			return '`'.$field.'` = '.$this->escapeObject($this->$field);
		else
			return '`'.$field.'` = NULL';
	}

	/**
	  * Escape a value using static mysqli connection
	  * @param string $value
	  * @param bool $quote wrap value in single quote
	  * @return string
	  */
	protected static function escapeStatic($value, $quote = TRUE)
	{
		return self::escape(self::getConnection(), $value, $quote);
	}

	/**
	  * Escape a value using object mysqli connection
	  * @param string $value
	  * @param bool $quote wrap value in single quote
	  * @return string
	  */
	protected function escapeObject($value, $quote = TRUE)
	{
		return self::escape($this->__mbConnection, $value, $quote);
	}

	/**
	  * Added toString method, to format object to simple output
	  * @return string
	  */
	public function __toString()
	{
		$class = get_called_class();
		$str = 'Instance of '.$class.':'."\n";
		foreach (self::$__mbDescriptions[$class] as $field)
		{
			$str .= $field['Field'].': '.var_export($this->$field['Field'], TRUE)."\n";
		}

		return $str;
	}

	/**
	  * Escape a value according to mysqli connection
	  * @param mysqli $connection mysql connection
	  * @param string $value
	  * @param bool $quote wrap value in single quote
	  * @return string
	  */
	private static function escape($connection, $value, $quote = TRUE)
	{
		if ($quote)
			return '\''.$connection->real_escape_string($value).'\'';
		else
			return $connection->real_escape_string($value);
	}

	/**
	  * Get a mysqli connection to a database server, either from classname based connections or the global connection
	  * If none is found an exception is thrown
	  * @throws MOMBaseException
	  * @return mysqli
	  */
	private static function getConnection()
	{
		$class = get_called_class();
		if (isset(self::$__mbConnections[$class]))
			return self::$__mbConnections[$class];
		else if (isset(self::$__mbConnections[self::GLOBAL_CONNECTION]))
			return self::$__mbConnections[self::GLOBAL_CONNECTION];
		else
			throw new BaseException(BaseException::MISSING_CONNECTION);
	}

	/**
	  * Set a database handler for the class
	  * If called directly on MOMBase, connection is set globally
	  * @param \mysqli $connection mysqli connection
	  * @param bool $global set the mysqli connection globally
	  */
	public static function setConnection(\mysqli $connection, $global = FALSE)
	{
		$class = get_called_class();
		if ($global || $class == __CLASS__)
			static::$__mbConnections[self::GLOBAL_CONNECTION] = $connection;
		else
			static::$__mbConnections[$class] = $connection;
	}

	/**
	  * Get a Memcached connection to a memcache server, either from classname based memcaches or the global memcache
	  * If none is found returns FALSE, aka memcaching is not enabled
	  * @return array<memcache => \Memcached, expiration => int>, returns FALSE when memcache is not enabled
	  */
	private static function getMemcache()
	{
		$class = get_called_class();
		if (isset(self::$__mbMemcaches[$class]))
			return self::$__mbMemcaches[$class];
		else if (isset(self::$__mbMemcaches[self::GLOBAL_MEMCACHE]))
			return self::$__mbMemcaches[self::GLOBAL_MEMCACHE];
		else
			return FALSE;
	}

	/**
	  * Set a memcache handler for the extending class
	  * If called directly on MOMBase, connection is set globally
	  * @param \Memcached $memcache 
	  * @param int $expiration
	  * @param bool $global set the memcache globally
	  */
	public static function setMemcache(\Memcached $memcache, $expiration, $global = FALSE)
	{
		$class = get_called_class();
		$options = array('memcache' => $memcache, 'expiration' => (int)$expiration);
		if ($global || $class == __CLASS__)
			static::$__mbMemcaches[self::GLOBAL_MEMCACHE] = $options;
		else
			static::$__mbMemcaches[$class] = $options;
	}

	/**
	  * Get the internal memcache timestamp
	  * This is set on the object once its added to memcache
	  * @return int
	  */
	public function getMemcacheTimestamp()
	{
		return $this->__mbMemcacheTimestamp;
	}

	/**
	  * Get the internal static cache timestamp
	  * This is set on the object once its added to the static cache
	  * @return int
	  */
	public function getStaticCacheTimestamp()
	{
		return $this->__mbStaticCacheTimestamp;
	}

	/**
	  * Get entry from static cache
	  * @param string $selector
	  * @return object, object[] or FALSE if no data is found
	  */
	protected static function getStaticEntry($selector)
	{
		if (static::useStaticCache())
		{
			$class = get_called_class();
			if (isset(self::$__mbStaticCache[$class][$selector]))
				return self::$__mbStaticCache[$class][$selector];
		}

		return FALSE;
	}

	/**
	  * set entry in static cache
	  * @param string $selector
	  * @param mixed $value provide object or array of objects
	  */
	protected static function setStaticEntry($selector, $value)
	{
		if (static::useStaticCache())
		{
			$class = get_called_class();
			if ($value instanceOf MOMBase)
				$value->__mbStaticCacheTimestamp = time();
			else if (is_array($value))
			{
				foreach ($value as $element)
				{
					if ($element instanceOf MOMBase)
						$value->__mbStaticCacheTimestamp = time();
				}
			}
			self::$__mbStaticCache[$class][$selector] = $value;
		}
	}

	/**
	  * set entry in static cache
	  * @param string $selector
	  */
	protected static function deleteStaticEntry($selector)
	{
		if (static::useStaticCache())
		{
			$class = get_called_class();
			unset(self::$__mbStaticCache[$class][$selector]);
		}
	}

	/**
	  * Set an entry in memcache
	  * @param string $selector
	  * @param mixed $value
	  */
	protected static function setMemcacheEntry($selector, $value, $context = self::CONTEXT_STATIC)
	{
		if (static::useMemcache())
		{
			if ($value instanceOf MOMBase)
				$value->__mbMemcacheTimestamp = time();
			else if (is_array($value))
			{
				foreach ($value as $element)
				{
					if ($element instanceOf MOMBase)
						$element->__mbMemcacheTimestamp = time();
				}
			}

			if ($context == self::CONTEXT_STATIC)
			{
				if (($memcache = self::getMemcache()) !== FALSE)
					$memcache['memcache']->set(self::getMemcacheKey($selector), $value, $memcache['expiration']);
			}
			else if ($context == self::CONTEXT_OBJECT)
			{
				if ($value->__mbMemcache !== FALSE)
					$value->__mbMemcache['memcache']->set(self::getMemcacheKey($selector), $value, $value->__mbMemcache['expiration']);

			}
		}
	}

	/**
	  * Get an entry from memcache
	  * @param string $selector
	  * @return data will return FALSE on error or when memcache is not enabled
	  */
	protected static function getMemcacheEntry($selector)
	{
		if (static::useMemcache())
		{
			if (($memcache = self::getMemcache()) !== FALSE)
				return $memcache['memcache']->get(self::getMemcacheKey($selector));
		}

		return FALSE;
	}

	/**
	  * Delete an entry in memcache
	  * @param string $selector
	  */
	protected function deleteMemcacheEntry($selector)
	{
		if (static::useMemcache())
		{
			if ($this->__mbMemcache !== FALSE)
				$this->__mbMemcache['memcache']->delete(self::getMemcacheKey($selector));
		}
	}

	/**
	  * Get the memcache key for the specified selector
	  * Prepends called class name
	  * @param string $selector
	  * @return string
	  */
	protected static function getMemcacheKey($selector)
	{
		return get_called_class().'_'.static::CLASS_REVISION.'_'.$selector;
	}

	/**
	  * Checks if the extending class is using static object caching
	  * @return bool
	  */
	protected static function useStaticCache()
	{
		if (static::USE_STATIC_CACHE)
			return TRUE;
		else
			return FALSE;
	}

	/**
	  * Checks if the extending class is using static object caching
	  * @return bool
	  */
	protected static function useMemcache()
	{
		if (static::USE_MEMCACHE)
			return TRUE;
		else
			return FALSE;
	}
	
	/**
	  * Checks if the extending class has needed info to use MOMBase
	  * @param string $classname classname of the extending class
	
   	 */
	protected static function checkDbAndTableConstants($classname)
	{
		if (!defined('static::DB') && self::getNestedDbName() === FALSE)
			throw new BaseException(BaseException::MISSING_TABLE_DEFINITION, $classname.' has no DB defined');

		if (!defined('static::TABLE'))
			throw new BaseException(BaseException::MISSING_DB_DEFINITION, $classname.' has no TABLE constant defined');
	}
}
