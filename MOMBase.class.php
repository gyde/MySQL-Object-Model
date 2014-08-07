<?php
/*NAMESPACE*/

use /*USE_NAMESPACE*/MOMBaseException as BaseException;
use /*USE_NAMESPACE*/MOMMySQLException as MySQLException;

abstract class MOMBase
{
	const RESERVED_PREFIX = '__mb';
	const GLOBAL_CONNECTION = '__mbGlobalConnection';

	const CONTEXT_STATIC = 'STATIC';
	const CONTEXT_OBJECT = 'OBJECT';

	/**
	  * Every object has its own mysqli connection
	  * If none is providede on object instansiation one is picked
	  * from $__mbConnections
	  * @var mysqli
	  */
	protected $__mbConnection = NULL;

	/**
	  * Defines object behavior on save / update
	  * @var bool $__mbNewObject
	  */
	protected $__mbNewObject = TRUE;

	/**
	  * Static cache with all model descriptions
	  * @var string[]
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
	  * Static cache with all mysqli connections
	  * Can contain a global connection, or one per extending class
	  * Depending on the use of setConnection
	  * @var mysqli[]
	  */
	protected static $__mbConnections = array();

	/**
	  * Constructs an object of extending class using the database fields
	  * Checks if the extending class has the correct consts and describes the extending class via mysqli
	  * @param \mysqli $connection mysqli connection
	  */
	public function __construct(\mysqli $connection = NULL)
	{
		if ($connection instanceOf \mysqli)
			$this->__mbConnection = $connection;
		else
			$this->__mbConnection = self::getConnection();

		$class = get_called_class();
		$this->checkDbAndTableConstants($class);
		$this->describe($class);

		foreach (self::$__mbDescriptions[$class] as $field)
		{
			if (!in_array($field['Default'], self::$__mbProtectedValueDefaults))
				$this->$field['Field'] = $field['Default'];
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
	abstract public static function getRowIdentifier($row);

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

		$sql = 'SELECT * FROM `'.static::DB.'`.`'.static::TABLE.'`';

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
	  * @throws \util\DatabaseException
	  * @return object[]
	  */
	public static function getAll ($order = NULL, $keyed = FALSE)
	{
		return self::getAllByWhereGeneric(NULL, $order, $keyed);
	}

	/**
	 * Get many objects by a MySQL WHERE clause
	 * @param string $where MySQL WHERE clause
	 * @param string $order MySQL ORDER clause
	 * @param bool $keyed if set, returns an associated array with unique key as array key
	 * @throws \util\DatabaseException
	 * @return object[]
	 */
	public static function getAllByWhere($where, $order = NULL, $keyed = FALSE)
	{
		return self::getAllByWhereGeneric($where, $order, $keyed);
	}

	/**
	 * Get many obejcts by a sql where clause
	 * @param string $where SQL where clause
	 * @param string $order SQL order clause
	 * @param bool $keyed if set, returns an associated array with unique key as array key
	 * @throws \util\DatabaseException
	 * @return object[]
	 */
	protected static function getAllByWhereGeneric($where = NULL, $order = NULL, $keyed = FALSE)
	{
		$many = array();

		$sql = 'SELECT * FROM `'.static::DB.'`.`'.static::TABLE.'`';

		if ($where)
			$sql .= ' WHERE '.$where;

		if ($order)
			$sql .= ' ORDER BY '.$order;

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
			'DELETE FROM `'.static::DB.'`.`'.static::TABLE.'`'.
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
		catch (MySqlException $e)
		{
			if ($e->getMysqlErrno() == MySqlException::ER_DUP_ENTRY)
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
	  * Describes the class statically
	  * Used when creating new objects, for default values and field info
	  * @param string $class classname of the extending class
	  */
	private function describe($class)
	{
		if (!array_key_exists($class, self::$__mbDescriptions))
		{
			$sql = 'DESCRIBE `'.static::DB.'`.`'.static::TABLE.'`';
			$res = $this->queryObject($sql);
			while (($row = $res->fetch_assoc()) !== NULL)
			{
				// If Field start is equal to self::RESERVED_PREFIX
				if (strpos($row['Field'], self::RESERVED_PREFIX) === 0)
					throw new BaseException(BaseException::RESERVED_VARIABLE_COMPROMISED, $class.' has a column named '.$row['Field'].', __mb is reserved for internal stuff');

				self::$__mbDescriptions[$class][] = $row;
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
	  * Escape a value using static mysqli connection
	  * @param string $value
	  * @param bool $quote wrap value in single quote
	  * @return string
	  */
	public static function escapeStatic($value, $quote = TRUE)
	{
		return self::escape(self::getConnection(), $value, $quote);
	}

	/**
	  * Escape a value using object mysqli connection
	  * @param string $value
	  * @param bool $quote wrap value in single quote
	  * @return string
	  */
	public function escapeObject($value, $quote = TRUE)
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
			$str .= $field['Field'].': '.var_export($this->$field['Field'])."\n";
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
	  * Get a mysqli connection to the database, either from classname based connections or the global connection
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
	  * @param mysqli $connection mysqli connection
	  */
	public static function setConnection(\mysqli $connection, $global = FALSE)
	{
		if ($global)
			static::$__mbConnections[self::GLOBAL_CONNECTION] = $connection;
		else
			static::$__mbConnections[get_called_class()] = $connection;
	}
	
	/**
	  * Checks if the extending class has needed info to use MOMBase
	  * @param string $classname classname of the extending class
	  */
	private static function checkDbAndTableConstants($classname)
	{
		if (!defined('static::DB'))
			throw new BaseException(BaseException::MISSING_TABLE_DEFINITION, $classname.' has no DB const');

		if (!defined('static::TABLE'))
			throw new BaseException(BaseException::MISSING_DB_DEFINITION, $classname.' has no TABLE const');
	}
}
