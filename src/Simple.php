<?php

namespace Gyde\Mom;

class Simple extends Base
{
    /**
      * Static object cache
      * Simple supports storing objects selected by id in a static cache
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
    public function __construct(\PDO $connection = null, \Memcached $memcache = null, $memcacheExpiration = 0)
    {
        self::hasPrimaryKey();
        parent::__construct($connection, $memcache, $memcacheExpiration);
    }

    /**
      * Get by primary key
      * @param mixed $id
      * @param bool $allowNull allow null to be return when empty id is provided
      * @throws BaseException
      * @throws MySQLException
      * @return object
      */
    public static function getById($id, $allowNull = false)
    {
        $class = get_called_class();
        static::checkDbAndTableConstants($class);
        self::hasPrimaryKey();

        if (empty($id) && $allowNull) {
            return null;
        }

        if (empty($id)) {
            throw new BaseException(BaseException::OBJECT_NOT_FOUND, get_called_class() . '::' . __FUNCTION__ . ' got empty primary key value');
        }

        $selector = static::getSelector([static::COLUMN_PRIMARY_KEY => $id]);

        // early return from cache
        if (($entry = static::getCacheEntry($selector)) !== false) {
            return $entry;
        }

        $new = null;
        if (($row = self::getRowByIdStatic($id)) !== false) {
            $new = new static();
            $new->fillByStatic($row);
        }

        static::setCacheEntry($selector, $new);

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
        $res = static::queryStatic($sql);

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
            'SELECT * FROM `' . static::getDbName() . '`.`' . static::TABLE . '`' .
            ' WHERE `' . static::COLUMN_PRIMARY_KEY . '` = ' . $id;
    }

    /**
      * Save the object
      * @param mixed $metaData data needed by save method
      * @throws BaseException
      * @see Base
      */
    public function save($metaData = null)
    {
        $sql = static::buildSaveSql();

        if ($sql !== null) {
            $this->tryToSave($sql);
        }

        $keyname = static::COLUMN_PRIMARY_KEY;
        if ($this->isNew() && $this->__mbConnection->lastInsertId() != 0) {
            $id = $this->__mbConnection->lastInsertId();
        } else {
            $id = $this->$keyname;
        }

        if (($row = self::getRowById($id)) === false) {
            throw new BaseException(BaseException::OBJECT_NOT_UPDATED, get_called_class() . '->' . __FUNCTION__ . ' failed to update object with metadata from database');
        }

        // fillByObject() will change the return value of isNew()
        $wasCreated = $this->isNew();
        $this->fillByObject($row);

        if ($wasCreated) {
            static::setStaticEntry($this->__mbSelector, $this);
        }

        static::setMemcacheEntry($this->__mbSelector, $this, self::CONTEXT_OBJECT);
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
        if (empty($id)) {
            throw new BaseException(BaseException::OBJECT_NOT_DELETED, get_called_class() . '->' . __FUNCTION__ . ' failed to delete, primary key was empty');
        }

        $sql =
            'DELETE FROM `' . static::getDbName() . '`.`' . static::TABLE . '`' .
            ' WHERE `' . static::COLUMN_PRIMARY_KEY . '` = ' . $this->escapeObject($id);

        static::tryToDelete($sql);

        $this->deleteCacheEntry($this->__mbSelector);
    }

    /**
      * Build save sql using extending class description
      * @return ?string
      */
    protected function buildSaveSql()
    {
        $values = array();
        $class = get_called_class();
        $primaryKey = static::COLUMN_PRIMARY_KEY;

        foreach (static::describe() as $field) {
            $name = $field['Field'];

            if (!property_exists($this, $name)) {
                continue;
            }

            if ($this->$name === null && $field['Null'] == 'NO') {
                continue;
            }

            if ($name === $primaryKey && (!$this->isNew() || $field['Extra'] == 'auto_increment')) {
                continue;
            }

            if (static::isFieldProtected($field['Field']) && isset($this->__mbOriginalValues[$name]) && $this->$name === $this->__mbOriginalValues[$name]) {
                continue;
            }

            $values[] = $this->escapeObjectPair($field['Field'], $field['Type']);
        }

        if ($this->isNew()) {
            return
                'INSERT INTO `' . static::getDbName() . '`.`' . static::TABLE . '` SET' .
                ' ' . join(', ', $values);
        } elseif (count($values) > 0) {
            return
                'UPDATE `' . static::getDbName() . '`.`' . static::TABLE . '` SET' .
                ' ' . join(', ', $values) .
                ' WHERE `' . static::COLUMN_PRIMARY_KEY . '` = ' . $this->escapeObject($this->$primaryKey);
        }

        return null;
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
        return static::COLUMN_PRIMARY_KEY . '_' . $row[static::COLUMN_PRIMARY_KEY];
    }

    /**
      * Checks if the extending class has defined a primary key
      * @throws BaseException
      */
    private static function hasPrimaryKey()
    {
        if (!defined('static::COLUMN_PRIMARY_KEY')) {
            throw new BaseException(BaseException::PRIMARY_KEY_NOT_DEFINED, get_called_class() . ' has no COLUMN_PRIMARY_KEY const');
        }
    }

    /**
      * When cloing a MySqlSimple object, the new object is no longer persistent
      * It will create a new entry when saved
      */
    public function __clone()
    {
        $primaryKey = static::COLUMN_PRIMARY_KEY;
        $this->$primaryKey = null;
        $this->__mbNewObject = true;
        $this->__mbSerializeTimestamp = 0;
        $this->__mbStaticCacheTimestamp = 0;
    }
}
