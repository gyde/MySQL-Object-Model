<?php

namespace Gyde\Mom;

class BaseException extends Exception
{
    /**
      * EXCEPTION CONSTANTS BEGIN
      * A pair exist for each exception public constant
      * An exception code and an exception message
      * Use $internalMessage for technical stuff
      */
    public const MISSING_DB_DEFINITION = 1;
    public const MESSAGE_1 = 'Missing database definition, please define public const DB in extending class or use setDbName()';

    public const MISSING_TABLE_DEFINITION = 2;
    public const MESSAGE_2 = 'Missing table definition, please define public const TABLE in extending class';

    public const MISSING_CONNECTION = 3;
    public const MESSAGE_3 = 'Missing PDO connection, please set one using ::setConnection(\PDO) or ::public construct(\PDO)' ;

    public const PRIMARY_KEY_NOT_DEFINED = 4;
    public const MESSAGE_4 = 'Missing primary key definition, please define public const COLUMN_PRIMARY_KEY in extending class';

    public const COMPOUND_KEYS_NOT_DEFINED = 5;
    public const MESSAGE_5 = 'Missing compound key defition, please define public const COLUMN_COMPOUND_KEYS in extending class';

    public const COMPOUND_KEYS_NOT_COMPOUND = 6;
    public const MESSAGE_6 = 'When defining compound key definition with public const COLUMN_COMPOUND_KEYS, please define a mininum of two keys in extending class';

    public const COMPOUND_KEY_MISSING_VALUE = 7;
    public const MESSAGE_7 = 'When trying to build a compound key, a value is missing on the object';

    public const COMPOUND_KEY_MISSING_IN_WHERE = 8;
    public const MESSAGE_8 = 'When trying to select using a compound key, a value is missing in array where clause';

    public const CLASSNAME_IS_EMPTY = 9;
    public const MESSAGE_9 = 'Using class information resulted in a empty class name, please submit a bugfix request at MySql-Object-Model github';

    public const CLASSNAME_RECURSION_LEVEL_TO_DEEP = 10;
    public const MESSAGE_10 = 'When resursivly searching for properties within models, resursion level became to high';

    public const GET_SELECTOR_NOT_DEFINED = 11;
    public const MESSAGE_11 = 'A static getSelector() method must be defined in classes extending Base';

    public const COMPOUND_KEY_AUTO_INCREMENT = 12;
    public const MESSAGE_12 = 'MOM does not support tables with compound keys that use auto increment';

    public const OBJECT_NOT_SAVED = 100;
    public const MESSAGE_100 = 'Object could not be saved (created/updated)';

    public const OBJECT_NOT_DELETED = 101;
    public const MESSAGE_101 = 'Object could not be deleted';

    public const OBJECT_DUPLICATED_ENTRY = 102;
    public const MESSAGE_102 = 'Object with this primary key already exists';

    public const OBJECT_NOT_FOUND = 104;
    public const MESSAGE_104 = 'Object could not be fetched';

    public const OBJECT_NOT_UPDATED = 105;
    public const MESSAGE_105 = 'Object could not be updated';

    public const OBJECTS_NOT_DELETED = 200;
    public const MESSAGE_200 = 'Several objects could not be deleted';

    public const OBJECTS_NOT_FOUND = 201;
    public const MESSAGE_201 = 'Objects could not be fetched';

    public const MEMCACHE_NOT_ENABLED_BUT_SET = 300;
    public const MESSAGE_300 = 'Setting Memcached on object, but object does not have memcache enabled, remember to set memcache using public constant USE_MEMCACHE = TRUE';

    /**
      * EXCEPTION CONSTANTS END
      */
}
