<?php
/*NAMESPACE*/

use /*USE_NAMESPACE*/MOMBaseException as BaseException;
use /*USE_NAMESPACE*/MOMMySQLException as MySQLException;

class MOMSimple extends MOMBase
{
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
		self::hasPrimaryKey();

		if (empty($id))
			throw new BaseException(BaseException::OBJECT_NOT_FOUND, get_called_class().'::'.__FUNCTION__.' got empty primary key value');

		$new = NULL;
		if (($row = self::getRowById($id, self::CONTEXT_STATIC)) !== NULL)
		{
			$new = new static();
			$new->fillByStatic($row);
		}
	
		return $new;
	}

	/**
	  * Get mysql row by primary key
	  * @param mixed $id escaped
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
			
		return $row = $res->fetch_assoc();
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
			throw new BaseException(BaseException::OBJECT_NOT_UPDATED, get_called_class().'->'.__FUNCTION__.' failed to update object with data from database');
		
		$this->fillByObject($row);
	}

	/**
	 * Delete the object
	 * Will throw exception on all failures, if no exception, then object is deleted
	 * @throws BaseException
	 */
	public function delete()
	{
		$keyname = static::COLUMN_PRIMARY_KEY;
		$keyvalue = $this->$keyname;
		if (empty($keyvalue))
			throw new BaseException(BaseException::OBJECT_NOT_DELETED, get_called_class().'->'.__FUNCTION__.' failed to delete, primary key was empty');
		
		$sql = 
			'DELETE FROM `'.static::DB.'`.`'.static::TABLE.'`'.
			' WHERE `'.static::COLUMN_PRIMARY_KEY.'` = '.$this->escapeObejct($keyvalue);

		static::tryToDelete($sql);
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
	public static function getRowIdentifier($row)
	{
		return $row[static::COLUMN_PRIMARY_KEY];
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
	}
}
?>
