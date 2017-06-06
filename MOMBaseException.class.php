<?php
/*NAMESPACE*/

class MOMBaseException extends MOMException
{
	/**
	  * EXCEPTION CONSTANTS BEGIN
	  * A pair exist for each exception constant
	  * An exception code and an exception message 
	  * Use $internalMessage for technical stuff
	  */ 
	const MISSING_DB_DEFINITION = 1;
	const MESSAGE_1 = 'Missing database definition, please define const DB in extending class or use setDbName()';
	
	const MISSING_TABLE_DEFINITION = 2;
	const MESSAGE_2 = 'Missing table definition, please define const TABLE in extending class';

	const MISSING_CONNECTION = 3;
	const MESSAGE_3 = 'Missing mysqli connection, please set one using ::setConnection(mysqli) or ::construct(mysqli)' ;
	
	const PRIMARY_KEY_NOT_DEFINED = 4;
	const MESSAGE_4 = 'Missing primary key definition, please define const COLUMN_PRIMARY_KEY in extending class';

	const COMPOUND_KEYS_NOT_DEFINED = 5;
	const MESSAGE_5 = 'Missing compound key defition, please define const COLUMN_COMPOUND_KEYS in extending class';

	const COMPOUND_KEYS_NOT_COMPOUND = 6;
	const MESSAGE_6 = 'When defining compound key definition with const COLUMN_COMPOUND_KEYS, please define a mininum of two keys in extending class';

	const COMPOUND_KEY_MISSING_VALUE = 7;
	const MESSAGE_7 = 'When trying to build a compound key, a value is missing on the object';

	const COMPOUND_KEY_MISSING_IN_WHERE = 8;
	const MESSAGE_8 = 'When trying to select using a compound key, a value is missing in array where clause';

	const CLASSNAME_IS_EMPTY = 9;
	const MESSAGE_9 = 'Using class information resulted in a empty class name, please submit a bugfix request at MySql-Object-Model github';

	const CLASSNAME_RECURSION_LEVEL_TO_DEEP = 10;
	const MESSAGE_10 = 'When resursivly searching for properties within models, resursion level became to high';

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

	const MEMCACHE_NOT_ENABLED_BUT_SET = 300;
	const MESSAGE_300 = 'Setting Memcached on object, but object does not have memcache enabled, remember to set memcache using constant USE_MEMCACHE = TRUE';

	/**
	  * EXCEPTION CONSTANTS END
	  */
}
