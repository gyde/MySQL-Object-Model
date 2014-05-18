<?php
/*NAMESPACE*/

class MOMBaseException extends \Exception
{
	/**
	  * EXCEPTION CONSTANTS BEGIN
	  * A pair exist for each exception constant
	  * An exception code and an exception message 
	  * Use $internalMessage for technical stuff
	  */ 
	const MISSING_DB_DEFINITION = 1;
	const MESSAGE_1 = 'Missing database definition, please define const DB in extending class';
	
	const MISSING_TABLE_DEFINITION = 2;
	const MESSAGE_2 = 'Missing table definition, please define const TABLE in extending class';

	const MISSING_CONNECTION = 3;
	const MESSAGE_3 = 'Missing mysqli connection, please set one using ::setConnection(mysqli) or ::construct(mysqli)' ;
	
	const PRIMARY_KEY_NOT_DEFINED = 4;
	const MESSAGE_4 = 'Missing primary key definition, please define const COLUMN_PRIMARY_KEY in extending class';

	/** Future use with MOMCompound
	const COMPOUND_KEYS_NOT_DEFINED = 5;
	const MESSAGE_5 = 'Missing compound key defition, please define const COLUMN_COMPOUND_KEYS in extending class';

	const COMPOUND_KEYS_NOT_COMPOUND = 6;
	const MESSAGE_6 = 'Whend defining compound key definition with const COLUMN_COMPOUND_KEYS, please define a mininum if two keys in extending class';
	*/

	const OBJECT_NOT_SAVED = 100;
	const MESSAGE_100 = 'Object could not be saved (created/updated)'; 

	const OBJECT_NOT_DELETED = 101;
	const MESSAGE_101 = 'Object could not be deleted';

	const OBJECT_DUPLICATED_ENTRY = 102;
	const MESSAGE_102 = 'Object with this primary key already exists';

	const OBJECT_NOT_FOUND = 104;
	const MESSAGE_104 = 'Object could not be fetched';

	const OBJECT_NOT_UPDATED = 105;
	const MESSAGE_105 = 'Object could not be updated';

	const OBJECTS_NOT_DELETED = 200;
	const MESSAGE_200 = 'Several objects could not be deleted';
	
	const OBJECTS_NOT_FOUND = 201;
	const MESSAGE_201 = 'Objects could not be fetched';

	/**
	  * EXCEPTION CONSTANTS END
	  */

	private $internalMessage = '';

	private static $constants = array();

	/**
	  * Construct a message using a code
	  * Will translate the code into a message
	  * @param int $code
	  * @param string $internalMessage
	  * @param Exception $previous
	  */
	public function __construct ($code, $internalMessage = '', $previous = NULL)
	{
		parent::__construct($this->getConstant('MESSAGE_'.$code), $code, $previous);
		$this->internalMessage = $internalMessage;
	}

	/**
	  * Returns the internal message, used for debugging, may contain sensitive information
	  * DO NOT SHOW END USERS
	  * @return string
	  */
	public function getInternalMessage()
	{
		return $this->internalMessage;
	}
	
	/**
	 * Returns the constant value
	 * Uses ReflectionClass to read constants on the class
	 * @param string $name
	 * @return value
	 */
	private function getConstant($name)
	{
		$reflection = new \ReflectionClass($this);
		return $reflection->getConstant($name);
	}
}
