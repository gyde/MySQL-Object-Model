<?php
/*NAMESPACE*/

use /*USE_NAMESPACE*/MOMBaseException as BaseException;
use /*USE_NAMESPACE*/MOMMySQLException as MySQLException;

abstract class MOMBase implements \Serializable
{
	const RESERVED_PREFIX = '__mb';
	const GLOBAL_CONNECTION = '__mbGlobalConnection';
	const GLOBAL_MEMCACHE = '__mbGlobalMemcache';

	const CONTEXT_STATIC = 'STATIC';
	const CONTEXT_OBJECT = 'OBJECT';

	const USE_STATIC_CACHE = FALSE;
	const USE_MEMCACHE = FALSE;

	const CLASS_REVISION = 0;

	const VERBOSE_SQL = FALSE;
	const VERBOSE_STATIC_CACHE = FALSE;
	const VERBOSE_MEMCACHE = FALSE;

	/**
	  * Every object has its own PDO connection
	  * If none is providede on object instansiation one is picked
	  * from $__mbConnections
	  * @var \PDO $__mbConnection
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
	  * Defines when object was serialized (most likely for memcaching)
	  * @var int $__mbSerializeTimestamp
	  */
	protected $__mbSerializeTimestamp= 0;

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
	protected static $__mbProtectedClasses = array('/*USE_NAMESPACE*/MOMBase','/*USE_NAMESPACE*/MOMSimple','/*USE_NAMESPACE*/MOMCompound');

	/**
	  * Static cache with all PDO connections
	  * Can contain a global connection, or one per extending class
	  * Depending on the use of setConnection
	  * @var \PDO[]
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
	  * Checks if the extending class has the correct consts and describes the extending class via PDO
	  * @param \PDO $connection PDO connection
	  * @param \memcached $memcache memcache connection
	  * @param int $memcacheExpiration memcache expiration in seconds
	  */
	public function __construct(\PDO $connection = NULL, \Memcached $memcache = NULL, $memcacheExpiration = 0)
	{
		$class = get_called_class();
		$this->checkDbAndTableConstants($class);

		if ($connection instanceOf \PDO)
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
	  * @return string
	  */
	abstract protected function getRowIdentifier();

	/**
	  * Set database name
	  * @param string $name
	  */
	public static function setDbName($name)
	{
		static::$__mbDatabaseNames[get_called_class()] = $name;
	}

	/**
	  * Get database name defined using setDbName
	  * This will backtrack extending classes
	  * @return string
	  */
	public static function getDbName()
	{
		if (is_array(static::$__mbDatabaseNames))
		{
			if (($name = self::getNestedByClass(self::$__mbDatabaseNames, get_called_class())) !== FALSE)
				return $name;
		}

		if (!defined('static::DB'))
			throw new BaseException(BaseException::MISSING_DB_DEFINITION, get_called_class().' has no DB defined');

		return static::DB;
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
		if (($row = $res->fetch()) !== NULL)
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
		while (($row = $res->fetch()) !== false)
		{
			$new = new static();
			$new->fillByStatic($row);
			if ($keyed)
				$many[$new->getRowIdentifier()] = $new;
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
	  * @param string[] $row \PDO_result->fetch_assoc
	  */
	protected function fillByObject($row)
	{
		$this->fill($row);
	}

	/**
	  * Creates an object from static methods
	  * Binds the static PDO connection to the object
	  * @param string[] $row \PDO_result->fetch_assoc
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
	  * @param string[] $row \PDO_result->fetch_assoc
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
				throw new BaseException(BaseException::OBJECT_DUPLICATED_ENTRY, get_called_class().' tried to created new object, but primary key already exists, error: '.$e->getMysqlError(), $e);

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
			throw new BaseException(BaseException::OBJECT_NOT_DELETED, get_called_class().' tried to delete an object, error: '.$e->getMysqlError(), $e);
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
		if (array_key_exists($class, self::$__mbDescriptions))
		{
			if (static::VERBOSE_STATIC_CACHE)
				error_log('Getting static description for class: '.$class);

			return;
		}

		$selector = 'mb_DESCRIPTION';
		if (($entry = self::getMemcacheEntry($selector)) !== FALSE)
		{
			self::$__mbDescriptions[$class] = $entry;
		}
		else
		{
			$sql = 'DESCRIBE `'.self::getDbName().'`.`'.static::TABLE.'`';
			$res = $this->queryObject($sql);
			$description = array();
			while (($row = $res->fetch()) !== FALSE)
			{
				// If Field start is equal to self::RESERVED_PREFIX
				if (strpos($row['Field'], self::RESERVED_PREFIX) === 0)
					throw new BaseException(BaseException::RESERVED_VARIABLE_COMPROMISED, $class.' has a column named '.$row['Field'].', __mb is reserved for internal stuff');

				$description[$row['Field']] = $row;
			}
			self::$__mbDescriptions[$class] = $description;
			self::setMemcacheEntry($selector, $description);
		}
	}

	/**
	  * Get database fields from sql DESCRIBE
	  * @return string[]
	  */
	protected function getFields()
	{
		$fields = [];
		foreach (self::$__mbDescriptions[get_called_class()] as $field)
		{
			$fields[] = $field['Field'];
		}

		return $fields;
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
	 * @param bool $keyed if true return CONST => VALUE, otherwise VALUE => VALUE
	 * @return array<string,string>
	 */
	protected static function getConstants($prefix, $keyed = TRUE)
	{
		$reflection = new \ReflectionClass(get_called_class());
		$constants = $reflection->getConstants();
		$values = array();

		foreach ($constants as $name => $value)
		{
			if (strpos($name, $prefix) === 0)
			{
				if ($keyed)
					$values[$name] = $value;
				else
					$values[$value] = $value;
			}
		}

		return $values;
	}

	/**
	  * Query method for static methods
	  * @param string $sql
	  * @return mixed PDO_result or TRUE
	  * @throws MySQLException
	  */
	protected static function queryStatic($sql)
	{
		return self::query(self::getConnection(), $sql);
	}

	/**
	  * Query method for objects, always uses the objects specific PDO connection
	  * @param string $sql
	  * @return mixed PDO_result or TRUE
	  * @throws MySQLuException
	  */
	protected function queryObject($sql)
	{
		return self::query($this->__mbConnection, $sql);
	}

	/**
	  * Generic query method
	  * @param PDO $connection PDO connection
	  * @return mixed PDO_result or TRUE
	  * @throws MySQLException
	  */
	private static function query($connection, $sql)
	{
		if (static::VERBOSE_SQL)
			error_log($sql);
		$result = $connection->query($sql, \PDO::FETCH_ASSOC);
		if ($result === FALSE)
			throw new MySQLException($sql, $connection->errorCode, $connection->errorInfo[2]);

		return $result;
	}

	/**
	  * Escape object value
	  * Everything is escaped as strings except for NULL
	  * @param string $field
	  * @param string $type
	  * @return string
	  */
	protected function escapeObjectPair($field, $type = 'varchar')
	{
		if ($this->$field !== NULL)
		{
			if (strpos('int', $type) !== FALSE)
				return '`'.$field.'` = '.(int)$this->$field;

			return '`'.$field.'` = '.$this->escapeObject($this->$field);
		}
		else
			return '`'.$field.'` = NULL';
	}

	/**
	  * Escape a value using static PDO connection
	  * @param string $value
	  * @param bool $quote wrap value in single quote
	  * @return string
	  */
	protected static function escapeStatic($value)
	{
		return self::escape(self::getConnection(), $value);
	}

	/**
	  * Escape a value using object PDO connection
	  * @param string $value
	  * @return string
	  */
	protected function escapeObject($value)
	{
		return self::escape($this->__mbConnection, $value);
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
	  * Escape a value according to PDO connection
	  * @param PDO $connection mysql connection
	  * @param string $value
	  * @return string
	  */
	private static function escape($connection, $value)
	{
		if (!$connection instanceof \PDO)
			throw new BaseException(BaseException::MISSING_CONNECTION);
		return $connection->quote($value);
	}

	/**
	  * Get a PDO connection to a database server, either from classname based connections or the global connection
	  * If none is found an exception is thrown
	  * @throws MOMBaseException
	  * @return PDO
	  */
	private static function getConnection()
	{
		$connection = self::getNestedByClass(self::$__mbConnections, get_called_class());
		if ($connection !== FALSE)
			return $connection;

		if (isset(self::$__mbConnections[self::GLOBAL_CONNECTION]))
			return self::$__mbConnections[self::GLOBAL_CONNECTION];

		throw new BaseException(BaseException::MISSING_CONNECTION);
	}

	/**
	  * Set a database handler for the class
	  * If called directly on MOM classes, connection is set globally
	  * @param \PDO $connection PDO connection
	  * @param bool $global set the PDO connection globally
	  */
	public static function setConnection(\PDO $connection, $global = FALSE)
	{
		$class = get_called_class();
		if ($global || in_array($class, self::$__mbProtectedClasses) === TRUE)
			self::$__mbConnections[self::GLOBAL_CONNECTION] = $connection;
		else
			self::$__mbConnections[$class] = $connection;
	}

	/**
	  * Get a Memcached connection to a memcache server, either from classname based memcaches or the global memcache
	  * If none is found returns FALSE, aka memcaching is not enabled
	  * @return array<memcache => \Memcached, expiration => int>, returns FALSE when memcache is not enabled
	  */
	private static function getMemcache()
	{
		$memcache = self::getNestedByClass(self::$__mbConnections, get_called_class());
		if ($memcache !== FALSE)
			return $memcache;

		if (isset(self::$__mbMemcaches[self::GLOBAL_MEMCACHE]))
			return self::$__mbMemcaches[self::GLOBAL_MEMCACHE];

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
	public function getSerializeTimestamp()
	{
		return $this->__mbSerializeTimestamp;
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
	  * Get cached entry from either static or memcache
	  * @param string $selector
	  * @return mixed object|false
	  */
	protected static function getCacheEntry($selector)
	{
		// early return from static cache
		if (($entry = self::getStaticEntry($selector)) !== FALSE)
			return $entry;

		// early return from memcache
		if (($entry = self::getMemcacheEntry($selector)) !== FALSE)
		{
			self::setStaticEntry($selector, $entry);
			return $entry;
		}

		return FALSE;
	}

	/**
	  * Set cache entry for static and memcache
	  * @param string $selector
	  * @param object $object
	  */
	protected static function setCacheEntry($selector, $object)
	{
		/**
		  * Cache fetched object
		  * Objects that are NULL will be store in static cache but not memcache
		  * To reselect the same non exsistant element during a session is unnessesary but to put it in memcache would be unwise.
		  */
		self::setStaticEntry($selector, $object);
		if ($object !== NULL)
			self::setMemcacheEntry($selector, $object);
	}

	protected function deleteCacheEntry($selector)
	{
		$this->deleteMemcacheEntry($selector);
		self::deleteStaticEntry($selector);
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
			{
				if (static::VERBOSE_STATIC_CACHE)
					error_log('Getting static entry with class: '.$class.' and selector: '.$selector);
				return self::$__mbStaticCache[$class][$selector];
			}
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
			if (static::VERBOSE_STATIC_CACHE)
				error_log('Setting static entry with class: '.$class.' and selector: '.$selector);
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
			if (static::VERBOSE_STATIC_CACHE)
				error_log('Deleting static entry with class: '.$class.' and selector: '.$selector);
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
			if ($context == self::CONTEXT_STATIC)
			{
				if (($memcache = self::getMemcache()) !== FALSE)
				{
					if (static::VERBOSE_MEMCACHE)
						error_log('Setting memcache entry with selector: '.$selector);
					$memcache['memcache']->set(self::getMemcacheKey($selector), $value, $memcache['expiration']);
				}
			}
			else if ($context == self::CONTEXT_OBJECT)
			{
				if ($value->__mbMemcache !== FALSE)
				{
					if (static::VERBOSE_MEMCACHE)
						error_log('Setting memcache entry with selector: '.$selector);
					$value->__mbMemcache['memcache']->set(self::getMemcacheKey($selector), $value, $value->__mbMemcache['expiration']);
				}
			}
		}
	}

	/**
	  * Get an entry from memcache
	  * @param string $selector
	  * @param \PDO $connection PDO connection
	  * @return data will return FALSE on error or when memcache is not enabled
	  */
	protected static function getMemcacheEntry($selector, \PDO $connection = NULL)
	{
		if (!static::useMemcache())
			return FALSE;

		if (($memcache = self::getMemcache()) === FALSE)
			return FALSE;

		$data = $memcache['memcache']->get(self::getMemcacheKey($selector));
		if ($data === FALSE)
			return FALSE;

		if (static::VERBOSE_MEMCACHE)
			error_log('Got memcache entry with selector: '.$selector);
		return $data;
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
			{
				if (static::VERBOSE_MEMCACHE)
					error_log('Deleting memcache entry with selector: '.$selector);
				$this->__mbMemcache['memcache']->delete(self::getMemcacheKey($selector));
			}
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
		if (!defined('static::DB') && self::getDbName() === FALSE)
			throw new BaseException(BaseException::MISSING_DB_DEFINITION, $classname.' has no DB defined');

		if (!defined('static::TABLE'))
			throw new BaseException(BaseException::MISSING_TABLE_DEFINITION, $classname.' has no TABLE constant defined');
	}

	/**
	  * Get property based on class extentions hiarchy
	  * This will backtrack extending classes
	  * Note, this method is recursive
	  * @param array<string, mixed> $properties array of properties to search in, string is class name
	  * @param string $class name of class including namespace
	  * @param int $iteration which level of recursion
	  * @return string Will return FALSE if no property is found
	  */
	private static function getNestedByClass($properties, $class, $iteration = 0)
	{
		if (empty($class))
			throw new BaseException(BaseException::CLASSNAME_IS_EMPTY, 'Trying to get nested property by class, but classname is empty');

		if (isset($properties[$class]))
			return $properties[$class];

		// class has no parent (top of hierarki)
		$class = get_parent_class($class);
		if ($class === FALSE)
			return FALSE;

		if ($iteration > 100)
			throw new BaseException(BaseException::RECURSION_LEVEL_TO_DEEP, $class.' did not match any properties (resulted in infinite loop)');

		return self::getNestedByClass($properties, $class, ++$iteration);
	}

	/**
	  * Serialize object for memcache storage
	  * @return string serialized representation of object
	  */
	public function serialize()
	{
		$class = get_called_class();
		$data = [];
		foreach (self::$__mbDescriptions[$class] as $field)
		{
			$data[$field['Field']] = $this->$field['Field'];
		}
		$this->__mbSerializeTimestamp = time();
		$data['__mbSerializeTimestamp'] = $this->__mbSerializeTimestamp;

		return serialize($data);
	}

	/**
	  * Unserialize data to recreate object
	  * When object is loaded from serialized form default connection and memcache is restored
	  * @param string $data serialized data
	  */
	public function unserialize($data)
	{
		$class = get_called_class();
		$this->describe($class);
		$data = unserialize($data);
		foreach (self::$__mbDescriptions[$class] as $field)
		{
			$this->$field['Field'] = $data[$field['Field']];
		}
		$this->__mbSerializeTimestamp = $data['__mbSerializeTimestamp'];
		$this->__mbConnection = self::getConnection();
		$this->__mbMemcache = self::getMemcache();
	}
}
