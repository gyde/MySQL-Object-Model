<?php

namespace Gyde\Mom;

#[\AllowDynamicProperties]
abstract class Base
{
    public const RESERVED_PREFIX = '__mb';
    public const GLOBAL_CONNECTION = '__mbGlobalConnection';
    public const GLOBAL_MEMCACHE = '__mbGlobalMemcache';

    public const CONTEXT_STATIC = 'STATIC';
    public const CONTEXT_OBJECT = 'OBJECT';

    public const USE_STATIC_CACHE = false;
    public const USE_MEMCACHE = false;

    public const CLASS_REVISION = 0;
    public const CLASS_DESCRIPTION_SELECTOR = '__mbDescription';

    public const VERBOSE_SQL = false;
    public const VERBOSE_STATIC_CACHE = false;
    public const VERBOSE_MEMCACHE = false;

    /**
      * Every object has its own PDO connection
      * If none is providede on object instansiation one is picked
      * from $__mbConnections
      * @var \PDO $__mbConnection
      */
    protected $__mbConnection = null;

    /**
      * Every object has its own memcache connection (most likely shared and only used if memcache is enabled for the extending class)
      * If none is providede on object instansiation one is picked
      * from $__mbMemcaches if available either globally or by class
      * @var array<string, mixed> $__mbMemcache
      */
    protected $__mbMemcache = false;

    /**
      * Defines object behavior on save / update
      * @var bool $__mbNewObject
      */
    protected $__mbNewObject = true;

    /**
      * Defines when object was put in static cache
      * @var int $__mbStaticCacheTimestamp
      */
    protected $__mbStaticCacheTimestamp = 0;

    /**
      * Defines when object was serialized (most likely for memcaching)
      * @var int $__mbSerializeTimestamp
      */
    protected $__mbSerializeTimestamp = 0;

    /**
      * Stores the static cache and memcache selector
      * @var string
      */
    protected $__mbSelector = '';

    /**
      * Stores the original values values of protected fields
      * @var array<string, mixed>
      */
    protected $__mbOriginalValues = array();

    /**
      * Defines database names, this will override usage of constant DB
      * This is mapped by class name and supports nested extending
      * Use method setDbName()
      * @see getDbName
      * @var string $__mbDatabaseNames
      */
    protected static $__mbDatabaseNames = null;

    /**
      * Static cache for objects
      * @var string[][]
      */
    protected static $__mbStaticCache = array();

    /**
      * Static cache with all model descriptions
      * @var array<classname, array<string, string>>
      */
    private static $__mbDescriptions = array();

    /**
      * Values used by mysql as default values for columns
      * When these are picked up from the model description
      * nothing is inserted into the save or update query
      * unless their values are changed
      * for these fields
      * @var string[]
      */
    protected static $__mbProtectedValueDefaults = array('current_timestamp()', 'CURRENT_TIMESTAMP', 'NOW()');

    /**
      * Values used by mysql as extra values for columns
      * When these are picked up from the model description
      * nothing is inserted into the save or update query
      * unless their values are changed
      * for these fields
      * @var string[]
      */
    protected static $__mbProtectedValueExtras = array('on update current_timestamp()', 'on update CURRENT_TIMESTAMP');

    /**
      * Names of all basic classes in MySQL-Object-Model
      * Used as stop words when searching static arrays for
      * nested extending class data
      * @var string[]
      */
    protected static $__mbProtectedClasses = array(Base::class, Simple::class, Compound::class);

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
    public function __construct(\PDO $connection = null, \Memcached $memcache = null, $memcacheExpiration = 0)
    {
        $class = get_called_class();
        $this->checkDbAndTableConstants($class);

        if ($connection instanceof \PDO) {
            $this->__mbConnection = $connection;
        } else {
            $this->__mbConnection = self::getConnection();
        }

        if ($memcache instanceof \Memcache) {
            if (!static::useMemcache()) {
                throw new BaseException(BaseException::MEMCACHE_NOT_ENABLED_BUT_SET);
            }
            $options = array('memcache' => $memcache, 'expiration' => (int)$memcacheExpiration);
            $this->__mbMemcache = $options;
        } else {
            if (static::useMemcache()) {
                $this->__mbMemcache = self::getMemcache();
            }
        }

        $description = self::describe();

        foreach ($description as $field) {
            if (!in_array($field['Default'], self::$__mbProtectedValueDefaults)) {
                $this->{$field['Field']} = $field['Default'];
            } else {
                $this->{$field['Field']} = null;
            }
        }
    }

    /**
      * Save the object in the database
      * The object itself is updated with row data reselected
      * from the database, iorder to update default values from table definition
      * If save fails a BaseException should be thrown
      * @param mixed $metaData data needed by save method
      * @throws BaseException
      */
    abstract public function save($metaData = null);

    /**
      * Abstract method, build the sql statement used by save method
      * Often needs to be overwritten to support other MySQL patteren
      * @return string sql statement
      */
    abstract protected function buildSaveSql();

    /**
      * Delete the object in the database
      * If delete fails BaseException should be thrown
      * @throws BaseException
      */
    abstract public function delete();

    /**
      * Get a rows unique identifier, e.g. primary key, or a compound key
      * @return string
      */
    abstract protected function getRowIdentifier();

    /**
      * Get static cache and memcache selector
      * @param array $row
      * @throws BaseException
      * @return string
      */
    protected static function getSelector($row)
    {
        throw new BaseException(BaseException::GET_SELECTOR_NOT_DEFINED, get_called_class() . ' doesn\'t have a getSelector() method');
    }

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
        if (is_array(static::$__mbDatabaseNames)) {
            if (($name = self::getNestedByClass(self::$__mbDatabaseNames, get_called_class())) !== false) {
                return $name;
            }
        }

        if (!defined('static::DB')) {
            throw new BaseException(BaseException::MISSING_DB_DEFINITION, get_called_class() . ' has no DB defined');
        }

        return static::DB;
    }

    /**
      * Get one object
      * @param string $where MySQL where clause
      * @param string $order MySQL order clause
      * @return object
      */
    public static function getOne($where = null, $order = null)
    {
        $res = self::getAllByWhereGeneric($where, $order, false, 1, 0);

        if (count($res) == 0) {
            return null;
        }

        return $res[0];
    }

    /**
      * Get all objects
      * @param string $order MySQL order clause
      * @param bool $keyed if set, returns an associated array with unique key as array key
      * @param int $limit MySQL LIMIT clause
      * @param int $offset MySQL LIMIT clause (offset)
      * @param bool $buffered Use MySQL buffered query
      * @return object[]
      */
    public static function getAll($order = null, $keyed = false, $limit = null, $offset = null, $buffered = true)
    {
        return self::getAllByWhereGeneric(null, $order, $keyed, $limit, $offset, $buffered);
    }

    /**
      * Get many objects by a MySQL WHERE clause
      * @param string $where MySQL WHERE clause
      * @param string $order MySQL ORDER clause
      * @param bool $keyed if set, returns an associated array with unique key as array key
      * @param int $limit MySQL LIMIT clause
      * @param int $offset MySQL LIMIT clause (offset)
      * @return object[]
      */
    public static function getAllByWhere($where, $order = null, $keyed = false, $limit = null, $offset = null)
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
      * @param bool $buffered Use MySQL buffered query
      * @return object[]
     */
    protected static function getAllByWhereGeneric($where = null, $order = null, $keyed = false, $limit = null, $offset = null, $buffered = true)
    {
        $many = array();

        $sql = 'SELECT * FROM `' . self::getDbName() . '`.`' . static::TABLE . '`';

        if ($where) {
            $sql .= ' WHERE ' . $where;
        }

        if ($order) {
            $sql .= ' ORDER BY ' . $order;
        }

        if ($limit !== null || $offset !== null) {
            $sql .= ' LIMIT ' . (int)$offset . ',' . (int)$limit;
        }

        $res = self::queryStatic($sql, $buffered);
        while (($row = $res->fetch()) !== false) {
            $selector = static::getSelector($row);
            $entry = self::getCacheEntry($selector);

            if ($entry === false) {
                $entry = new static();
                $entry->fillByStatic($row);
                self::setCacheEntry($selector, $entry);
            }

            if ($keyed) {
                $many[$entry->getRowIdentifier()] = $entry;
            } else {
                $many[] = $entry;
            }
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
        if (empty($where)) {
            throw new BaseException(BaseException::OBJECTS_NOT_DELETED);
        }

        $sql =
            'DELETE FROM `' . self::getDbName() . '`.`' . static::TABLE . '`' .
            ' WHERE ' . $where;

        try {
            self::queryStatic($sql);
        } catch (BaseException $e) {
            throw new BaseException(BaseException::OBJECTS_NOT_DELETED, $e->getMessage(), $e);
        }
    }

    /**
      * Get object count
      * @return int
      */
    public static function getCount()
    {
        return self::getCountByWhere();
    }

    /**
      * Get object count by a sql where clause
      * @param string $where
      * @return int
      */
    public static function getCountByWhere($where = null)
    {
        $sql = 'SELECT COUNT(*) FROM `' . self::getDbName() . '`.`' . static::TABLE . '`';

        if (!empty($where)) {
            $sql .= ' WHERE ' . $where;
        }

        $result = self::queryStatic($sql);
        return $result->fetchColumn(0);
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
        foreach ($row as $key => $value) {
            $this->$key = $value;
            if (static::isFieldProtected($key)) {
                $this->__mbOriginalValues[$key] = $value;
            }
        }
        $this->__mbNewObject = false;
        $this->__mbSelector = static::getSelector($row);
    }

    /**
      * Trys to save the object
      * @param string $sql
      */
    protected function tryToSave($sql)
    {
        try {
            $this->queryObject($sql);
        } catch (MySQLException $e) {
            if ($e->getMysqlErrno() == MySQLException::ER_DUP_ENTRY) {
                throw new BaseException(BaseException::OBJECT_DUPLICATED_ENTRY, get_called_class() . ' tried to created new object, but primary key already exists, error: ' . $e->getMysqlError(), $e);
            }

            throw new BaseException(BaseException::OBJECT_NOT_SAVED, $e->getMysqlError(), $e);
        }
    }

    /**
      * Trys to delete the object
      * @param string $sql
      */
    protected function tryToDelete($sql)
    {
        try {
            $this->queryObject($sql);
        } catch (MySQLException $e) {
            throw new BaseException(BaseException::OBJECT_NOT_DELETED, get_called_class() . ' tried to delete an object, error: ' . $e->getMysqlError(), $e);
        }
    }

    /**
      * Describes the called class using DESCRIBE
      * Caches model in static cache and in Memcache(if enabled)
      * Used when creating new objects, for default values and field info
      * Entry in memcache will be keyed using classname and CLASS_REVISION
      * @throws BaseException
      * @return array<string, string>
      */
    protected static function describe()
    {
        $class = get_called_class();
        if (array_key_exists($class, self::$__mbDescriptions) && is_array(self::$__mbDescriptions[$class])) {
            if (static::VERBOSE_STATIC_CACHE) {
                error_log('Getting static description for class: ' . $class);
            }

            return self::$__mbDescriptions[$class];
        }

        if (($entry = self::getMemcacheEntry(self::CLASS_DESCRIPTION_SELECTOR)) !== false) {
            self::$__mbDescriptions[$class] = $entry;
            return $entry;
        }

        $sql = 'DESCRIBE `' . self::getDbName() . '`.`' . static::TABLE . '`';
        $res = self::queryStatic($sql);
        $description = array();
        while (($row = $res->fetch()) !== false) {
            // If Field start is equal to self::RESERVED_PREFIX
            if (strpos($row['Field'], self::RESERVED_PREFIX) === 0) {
                throw new BaseException(BaseException::RESERVED_VARIABLE_COMPROMISED, $class . ' has a column named ' . $row['Field'] . ', __mb is reserved for internal stuff');
            }

            $description[$row['Field']] = $row;
        }

        self::setMemcacheEntry(self::CLASS_DESCRIPTION_SELECTOR, $description);

        if (static::VERBOSE_STATIC_CACHE) {
            error_log('Setting static description for class: ' . $class);
        }
        self::$__mbDescriptions[$class] = $description;

        return $description;
    }

    /**
      * Uncache the description of the class in static and memcache
      * This method is used for unit tests and expert level usage
      * @tags advanced
      */
    public function unDescribe()
    {
        $class = get_called_class();

        self::deleteMemcacheEntry(self::CLASS_DESCRIPTION_SELECTOR);

        if (static::VERBOSE_STATIC_CACHE) {
            error_log('Deleting static description for class: ' . $class);
        }
        static::$__mbDescriptions[$class] = null;
    }

    /**
      * Get database fields from sql DESCRIBE
      * @return string[]
      */
    protected function getFields()
    {
        $fields = [];
        foreach (self::describe() as $field) {
            $fields[] = $field['Field'];
        }

        return $fields;
    }

    /**
      * Returns if a field are considered protected
      * @param string $field
      * @return boolean
      */
    protected static function isFieldProtected(string $field)
    {
        $fields = self::describe();
        if (!isset($fields[$field])) {
            return false;
        }
        return in_array($fields[$field]['Default'], self::$__mbProtectedValueDefaults) ||
        in_array($fields[$field]['Extra'], self::$__mbProtectedValueExtras);
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
        foreach ($consts as $constName => $constValue) {
            if (strpos($constName, $preFix) === 0 && $constValue == $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns an array of constant values based on $prefix
     * @param string $prefix
     * @param bool $keyed if true return CONST => VALUE, otherwise VALUE => VALUE
     * @return array<string,string>
     */
    protected static function getConstants($prefix, $keyed = true)
    {
        $reflection = new \ReflectionClass(get_called_class());
        $constants = $reflection->getConstants();
        $values = array();

        foreach ($constants as $name => $value) {
            if (strpos($name, $prefix) === 0) {
                if ($keyed) {
                    $values[$name] = $value;
                } else {
                    $values[$value] = $value;
                }
            }
        }

        return $values;
    }

    /**
      * Query method for static methods
      * @param string $sql
      * @param bool $buffered Use MySQL buffered query
      * @return mixed PDO_result or true
      * @throws MySQLException
      */
    protected static function queryStatic($sql, $buffered = true)
    {
        if (!$buffered) {
            self::describe();
        }

        return static::query(self::getConnection(), $sql, $buffered);
    }

    /**
      * Query method for objects, always uses the objects specific PDO connection
      * @param string $sql
      * @return mixed PDO_result or true
      * @throws MySQLuException
      */
    protected function queryObject($sql)
    {
        return static::query($this->__mbConnection, $sql);
    }

    /**
      * Generic query method
      * @param PDO $connection PDO connection
      * @param string $sql SQL Statement
      * @param bool $buffered Use MySQL buffered query
      * @return mixed PDO_result or true
      * @throws MySQLException
      */
    protected static function query($connection, $sql, $buffered = true)
    {
        if (static::VERBOSE_SQL) {
            error_log($sql);
        }

        if (!$buffered) {
            $connection->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        }

        try {
            $result = $connection->query($sql, \PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new MySQLException($sql, $e->getMessage(), $e->errorInfo[1]);
        } finally {
            $connection->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        }

        return $result;
    }

    /**
      * Prepare method for static methods
      * @param string $sql
      * @return mixed PDOStatement or false
      * @throws MySQLException
      */
    protected static function prepareStatic($sql)
    {
        return static::prepare(self::getConnection(), $sql);
    }

    /**
      * Prepare method for objects, always uses the objects specific PDO connection
      * @param string $sql
      * @return mixed PDOStatement or false
      * @throws MySQLuException
      */
    protected function prepareObject($sql)
    {
        return static::prepare($this->__mbConnection, $sql);
    }

    /**
      * Generic query method
      * @param PDO $connection PDO connection
      * @return mixed PDOStatement or false
      * @throws MySQLException
      */
    protected static function prepare($connection, $sql)
    {
        if (static::VERBOSE_SQL) {
            error_log($sql);
        }

        try {
            $result = $connection->prepare($sql);
        } catch (\PDOException $e) {
            throw new MySQLException($sql, $e->getMessage(), $e->errorInfo[1]);
        }

        return $result;
    }

    /**
      * Escape object value
      * Everything is escaped as strings except for null
      */
    protected function escapeObjectPair(string $field, string $type = 'varchar'): string
    {
        if ($this->$field === null) {
            return '`' . $field . '` = null';
        }

        $puretype = explode('(', $type, 2)[0];
        if (strpos($puretype, 'int') !== false) {
            return '`' . $field . '` = ' . (int)$this->$field;
        }

        return '`' . $field . '` = ' . $this->escapeObject($this->$field);
    }

    /**
      * Escape a value using static PDO connection
      * @param string $value
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
        $str = 'Instance of ' . $class . ':' . "\n";
        foreach (self::describe() as $field) {
            $str .= $field['Field'] . ': ' . var_export($this->{$field['Field']}, true) . "\n";
        }

        return $str;
    }

    /**
      * Escape a value according to PDO connection
      * @param mixed $value
      * @return mixed
      */
    private static function escape(\PDO $connection, $value)
    {
        if (!$connection instanceof \PDO) {
            throw new BaseException(BaseException::MISSING_CONNECTION);
        }
        return $connection->quote($value);
    }

    /**
      * Get a PDO connection to a database server, either from classname based connections or the global connection
      * If none is found an exception is thrown
      * @throws BaseException
      * @return PDO
      */
    private static function getConnection()
    {
        $connection = self::getNestedByClass(self::$__mbConnections, get_called_class());
        if ($connection !== false) {
            return $connection;
        }

        if (isset(self::$__mbConnections[self::GLOBAL_CONNECTION])) {
            return self::$__mbConnections[self::GLOBAL_CONNECTION];
        }

        throw new BaseException(BaseException::MISSING_CONNECTION);
    }

    /**
      * Set a database handler for the class
      * If called directly on MOM classes, connection is set globally
      * @param \PDO $connection PDO connection
      * @param bool $global set the PDO connection globally
      */
    public static function setConnection(\PDO $connection, $global = false)
    {
        $class = get_called_class();
        if ($global || in_array($class, self::$__mbProtectedClasses) === true) {
            self::$__mbConnections[self::GLOBAL_CONNECTION] = $connection;
        } else {
            self::$__mbConnections[$class] = $connection;
        }
    }

    /**
      * Get a Memcached connection to a memcache server, either from classname based memcaches or the global memcache
      * If none is found returns false, aka memcaching is not enabled
      * @return array<memcache => \Memcached, expiration => int>, returns false when memcache is not enabled
      */
    private static function getMemcache()
    {
        $memcache = self::getNestedByClass(self::$__mbMemcaches, get_called_class());
        if ($memcache !== false) {
            return $memcache;
        }

        if (isset(self::$__mbMemcaches[self::GLOBAL_MEMCACHE])) {
            return self::$__mbMemcaches[self::GLOBAL_MEMCACHE];
        }

        return false;
    }

    /**
      * Set a memcache handler for the extending class
      * If called directly on Base, connection is set globally
      * @param \Memcached $memcache
      * @param int $expiration
      * @param bool $global set the memcache globally
      */
    public static function setMemcache(\Memcached $memcache, $expiration, $global = false)
    {
        $class = get_called_class();
        $options = array('memcache' => $memcache, 'expiration' => (int)$expiration);
        if ($global || $class == __CLASS__) {
            static::$__mbMemcaches[self::GLOBAL_MEMCACHE] = $options;
        } else {
            static::$__mbMemcaches[$class] = $options;
        }
    }

    /** Check if an object is new
      *
      * New objects has never been saved to the database
      * @return bool
      */
    public function isNew()
    {
        return $this->__mbNewObject;
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
        if (($entry = self::getStaticEntry($selector)) !== false) {
            return $entry;
        }

        // early return from memcache
        if (($entry = self::getMemcacheEntry($selector)) !== false) {
            self::setStaticEntry($selector, $entry);
            return $entry;
        }

        return false;
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
          * Objects that are null will be store in static cache but not memcache
          * To reselect the same non exsistant element during a session is unnessesary but to put it in memcache would be unwise.
          */
        self::setStaticEntry($selector, $object);
        if ($object !== null) {
            self::setMemcacheEntry($selector, $object);
        }
    }

    /**
      * Delete entries from memcache and static cache
      * @param string $selector
      * @param bool $customSelector overwrites prepended string which is normally added to memcache selector
      */
    protected function deleteCacheEntry($selector, $customSelector = false)
    {
        $this->deleteMemcacheEntry($selector, $customSelector);
        self::deleteStaticEntry($selector);
    }

    /**
      * Get entry from static cache
      * @param string $selector
      * @return object, object[] or false if no data is found
      */
    protected static function getStaticEntry($selector)
    {
        if (static::useStaticCache()) {
            $class = get_called_class();
            if (isset(self::$__mbStaticCache[$class][$selector])) {
                if (static::VERBOSE_STATIC_CACHE) {
                    error_log('Getting static entry with class: ' . $class . ' and selector: ' . $selector);
                }
                return self::$__mbStaticCache[$class][$selector];
            }
        }

        return false;
    }

    /**
      * set entry in static cache
      * @param string $selector
      * @param mixed $value provide object or array of objects
      */
    protected static function setStaticEntry($selector, $value)
    {
        if (static::useStaticCache()) {
            $class = get_called_class();
            if ($value instanceof Base) {
                $value->__mbStaticCacheTimestamp = time();
            } elseif (is_array($value)) {
                foreach ($value as $element) {
                    if ($element instanceof Base) {
                        $element->__mbStaticCacheTimestamp = time();
                    }
                }
            }
            if (static::VERBOSE_STATIC_CACHE) {
                error_log('Setting static entry with class: ' . $class . ' and selector: ' . $selector);
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
        if (static::useStaticCache()) {
            $class = get_called_class();
            if (static::VERBOSE_STATIC_CACHE) {
                error_log('Deleting static entry with class: ' . $class . ' and selector: ' . $selector);
            }
            unset(self::$__mbStaticCache[$class][$selector]);
        }
    }

    /**
      * Allow static cache to be flushed for an entire class
      */
    public static function flushStaticEntries()
    {
        $class = get_called_class();
        if (static::VERBOSE_STATIC_CACHE) {
            error_log('Deleting all static entries with class: ' . $class);
        }

        self::$__mbStaticCache[$class] = null;
    }

    /**
      * Set an entry in memcache
      * @param string $selector
      * @param mixed $value
      */
    protected static function setMemcacheEntry($selector, $value, $context = self::CONTEXT_STATIC)
    {
        if (static::useMemcache()) {
            $key = self::getMemcacheKey($selector);
            if ($context == self::CONTEXT_STATIC) {
                if (($memcache = self::getMemcache()) !== false) {
                    if (static::VERBOSE_MEMCACHE) {
                        error_log('Setting memcache entry with selector: ' . $key);
                    }
                    $memcache['memcache']->set($key, $value, $memcache['expiration']);
                }
            } elseif ($context == self::CONTEXT_OBJECT) {
                if ($value->__mbMemcache !== false) {
                    if (static::VERBOSE_MEMCACHE) {
                        error_log('Setting memcache entry with selector: ' . $key);
                    }
                    $value->__mbMemcache['memcache']->set($key, $value, $value->__mbMemcache['expiration']);
                }
            }
        }
    }

    /**
      * Get an entry from memcache
      * @param string $selector
      * @param \PDO $connection PDO connection
      * @return data will return false on error or when memcache is not enabled
      */
    protected static function getMemcacheEntry($selector, \PDO $connection = null)
    {
        if (!static::useMemcache()) {
            return false;
        }

        if (($memcache = self::getMemcache()) === false) {
            return false;
        }

        $key = self::getMemcacheKey($selector);
        $data = $memcache['memcache']->get($key);
        if ($data === false) {
            return false;
        }

        if (static::VERBOSE_MEMCACHE) {
            error_log('Got memcache entry with selector: ' . $key);
        }
        return $data;
    }

    /**
      * Delete an entry in memcache
      * @param string $selector
      * @param bool $customSelector
      */
    protected function deleteMemcacheEntry($selector, $customSelector = false)
    {
        if (static::useMemcache()) {
            if ($this->__mbMemcache !== false) {
                $key = self::getMemcacheKey($selector, $customSelector);
                if (static::VERBOSE_MEMCACHE) {
                    error_log('Deleting memcache entry with selector: ' . $key);
                }
                $this->__mbMemcache['memcache']->delete($key);
            }
        }
    }

    /**
      * Get the memcache key for the specified selector
      * Prepends called class name
      * @param string $selector
      * @param bool $customSelector
      * @return string
      */
    protected static function getMemcacheKey($selector, $customSelector = false)
    {
        if ($customSelector) {
            return $selector;
        }

        return get_called_class() . '_' . static::CLASS_REVISION . '_' . $selector;
    }

    /**
      * Checks if the extending class is using static object caching
      * @return bool
      */
    protected static function useStaticCache()
    {
        return (bool)static::USE_STATIC_CACHE;
    }

    /**
      * Checks if the extending class is using static object caching
      * @return bool
      */
    protected static function useMemcache()
    {
        return (bool)static::USE_MEMCACHE;
    }

    /**
      * Checks if the extending class has needed info to use Base
      * @param string $classname classname of the extending class
      */
    protected static function checkDbAndTableConstants($classname)
    {
        if (!defined('static::DB') && self::getDbName() === false) {
            throw new BaseException(BaseException::MISSING_DB_DEFINITION, $classname . ' has no DB defined');
        }

        if (!defined('static::TABLE')) {
            throw new BaseException(BaseException::MISSING_TABLE_DEFINITION, $classname . ' has no TABLE constant defined');
        }
    }

    /**
      * Get property based on class extentions hiarchy
      * This will backtrack extending classes
      * Note, this method is recursive
      * @param array<string, mixed> $properties array of properties to search in, string is class name
      * @param string $class name of class including namespace
      * @param int $iteration which level of recursion
      * @return string Will return false if no property is found
      */
    private static function getNestedByClass($properties, $class, $iteration = 0)
    {
        if (empty($class)) {
            throw new BaseException(BaseException::CLASSNAME_IS_EMPTY, 'Trying to get nested property by class, but classname is empty');
        }

        if (isset($properties[$class])) {
            return $properties[$class];
        }

        // class has no parent (top of hierarki)
        $class = get_parent_class($class);
        if ($class === false) {
            return false;
        }

        if ($iteration > 100) {
            throw new BaseException(BaseException::RECURSION_LEVEL_TO_DEEP, $class . ' did not match any properties (resulted in infinite loop)');
        }

        return self::getNestedByClass($properties, $class, ++$iteration);
    }

    /**
      * Serialize object for memcache storage
      * @return array serialized representation of object
      */
    public function __serialize()
    {
        $class = get_called_class();
        $data = [];
        foreach (self::describe() as $field) {
            $data[$field['Field']] = $this->{$field['Field']};
        }
        $this->__mbSerializeTimestamp = time();
        $data['__mbSerializeTimestamp'] = $this->__mbSerializeTimestamp;
        $data['__mbNewObject'] = $this->__mbNewObject;
        $data['__mbSelector'] = $this->__mbSelector;
        $data['__mbOriginalValues'] = $this->__mbOriginalValues;

        return $data;
    }

    /**
      * Unserialize data to recreate object
      * When object is loaded from serialized form default connection and memcache is restored
      * @param array $data serialized data
      */
    public function __unserialize($data)
    {
        $this->__mbConnection = self::getConnection();
        $description = self::describe();
        foreach ($description as $field) {
            $this->{$field['Field']} = $data[$field['Field']];
        }
        $this->__mbSerializeTimestamp = $data['__mbSerializeTimestamp'];
        $this->__mbNewObject = $data['__mbNewObject'];
        $this->__mbSelector = $data['__mbSelector'];
        $this->__mbOriginalValues = $data['__mbOriginalValues'];
        $this->__mbMemcache = self::getMemcache();
    }
}
