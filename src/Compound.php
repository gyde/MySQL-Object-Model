<?php

namespace Gyde\Mom;

class Compound extends Base
{
    /**
      * Constructs an object of the extending class using parent constructor
      * Checks if compound keys has been defined on the extending class
      * @param \PDO $connection PDO connection
      * @param \memcached $memcache memcache connection
      * @param int $memcacheExpiration memcache expiration in seconds
      */
    public function __construct(?\PDO $connection = null, ?\Memcached $memcache = null, $memcacheExpiration = 0)
    {
        self::hasCompoundKeys();
        parent::__construct($connection, $memcache, $memcacheExpiration);
    }

    /**
      * Get by compound key
      * @param mixed[] $ids
      * @throws BaseException
      * @throws MySQLException
      * @return object
      */
    public static function getByIds(array $ids)
    {
        $class = get_called_class();
        static::checkDbAndTableConstants($class);
        self::hasCompoundKeys();

        // early return from cache
        $selector = static::getSelector($ids);
        if (($entry = static::getCacheEntry($selector)) !== false) {
            return $entry;
        }

        $new = null;

        if (($row = self::getRowByIdsStatic($ids)) !== false) {
            $new = new static();
            $new->fillByStatic($row);
        }

        static::setCacheEntry($selector, $new);

        return $new;
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

        $ids = array();
        foreach (static::getCompoundKeys() as $key) {
            $ids[$key] = $this->$key;
        }

        if (($row = self::getRowByIds($ids)) == false) {
            throw new BaseException(BaseException::OBJECT_NOT_UPDATED, get_called_class() . '->' . __FUNCTION__ . ' failed to update object with data from database');
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
      * Will throw exceptions on all errors, if no exception, then object is deleted
      * @throws BaseException
      */
    public function delete()
    {
        $keys = $this->getKeyPairs();

        $sql =
            'DELETE FROM `' . static::getDbName() . '`.`' . static::TABLE . '`' .
            ' WHERE ' . join(' AND ', $keys);

        static::tryToDelete($sql);

        $this->deleteCacheEntry($this->__mbSelector);
    }

    /**
      * Build sql statement for saving a compound object
      * @return ?string
      */
    protected function buildSaveSql()
    {
        $values = $this->getValuePairs();
        $keys = $this->getKeyPairs();

        if ($this->isNew()) {
            $values = array_merge($keys, $values);

            return
                'INSERT INTO `' . static::getDbName() . '`.`' . static::TABLE . '` SET' .
                ' ' . join(', ', $values);
        } elseif (count($values) > 0) {
            return
                'UPDATE `' . static::getDbName() . '`.`' . static::TABLE . '` SET' .
                ' ' . join(', ', $values) .
                ' WHERE ' . join(' AND ', $keys);
        }

        return null;
    }

    /**
      * Get object value pairs
      * Returns mysql field and value string
      * Several rows of pairs like these: `field` = 'value'
      * @return string[]
      */
    protected function getValuePairs()
    {
        $values = [];
        $compoundKeys = static::getCompoundKeys();
        foreach (static::describe() as $field) {
            $name = $field['Field'];

            if (!property_exists($this, $name) || in_array($name, $compoundKeys)) {
                continue;
            }

            if ($this->$name === null && $field['Null'] == 'NO') {
                continue;
            }

            if (static::isFieldProtected($field['Field']) && array_key_exists($name, $this->__mbOriginalValues) && $this->$name === $this->__mbOriginalValues[$name]) {
                continue;
            }

            $values[] = $this->escapeObjectPair($field['Field'], $field['Type']);
        }

        return $values;
    }

    /**
      * Explode constant COLUMN_COMPOUND_KEYS
      * @return string[]
      */
    protected static function getCompoundKeys()
    {
        return array_map('trim', explode(',', static::COLUMN_COMPOUND_KEYS));
    }

    /**
      * Get mysql row by primary key
      * @param mixed $id escaped
      * @throws MySQLException
      * @return resource(mysql resource) or false on failure
      */
    private function getRowByIds($ids)
    {
        $sql = self::buildCompoundSql($ids, array($this, 'escapeObject'));

        $res = $this->queryObject($sql);

        return $res->fetch();
    }

    /**
      * Get mysql row by primary key
      * @param mixed $id escaped
      * @throws MySQLException
      * @return resource(mysql resource) or false on failure
      */
    private static function getRowByIdsStatic($ids)
    {
        $sql = self::buildCompoundSql($ids, [static::class, 'escapeStatic']);

        $res = static::queryStatic($sql);

        return $res->fetch();
    }

    /**
      * Get object key pairs
      * Returns mysql field and value string
      * Several rows of pairs like these: `field` = 'value'
      * @throws BaseException
      * @return string[]
      */
    private function getKeyPairs()
    {
        $wheres = [];
        $description = static::describe();
        foreach (static::getCompoundKeys() as $key) {
            if ($description[$key]['Extra'] == 'auto_increment') {
                throw new BaseException(BaseException::COMPOUND_KEY_AUTO_INCREMENT, get_called_class() . ' uses a table with a compound key, where one of the fields is set to auto increment, please use Simple instead.');
            }
            if (!isset($this->$key)) {
                throw new BaseException(BaseException::COMPOUND_KEY_MISSING_VALUE, get_called_class() . '->' . __FUNCTION__ . ' failed to save object to database, ' . $key . ' is not set on object');
            }

            $wheres[] = $this->escapeObjectPair($key, $description[$key]['Type']);
        }

        return $wheres;
    }

    /**
      * Build sql statement used for fetching compound object
      * @param string[] $ids contains key => value pairs that make up the compound key
      * @param callback $callback
      * @return string sql statement
      */
    private static function buildCompoundSql($ids, $callback)
    {
        $wheres = array();
        foreach (static::getCompoundKeys() as $key) {
            if (!array_key_exists($key, $ids)) {
                throw new BaseException(BaseException::COMPOUND_KEY_MISSING_IN_WHERE, get_called_class() . '->' . __FUNCTION__ . ' failed to fetch object from database, ' . $key . ' is not present amoung ids');
            }

            if ($ids[$key] !== null) {
                $wheres[] = '`' . $key . '` = ' . call_user_func($callback, $ids[$key]);
            } else {
                $wheres[] = '`' . $key . '` = null';
            }
        }

        $sql =
            'SELECT * FROM `' . static::getDbName() . '`.`' . static::TABLE . '`' .
            ' WHERE ' . join(' AND ', $wheres);

        return $sql;
    }

    /**
      * Get a rows unique identifier, e.g. primary key, or a compound key
      * @return string
      */
    protected function getRowIdentifier()
    {
        $identifier = [];
        foreach (static::getCompoundKeys() as $key) {
            $identifier[] = $this->{$key};
        }

        return json_encode($identifier);
    }

    /**
      * Get static cache and memcache selector
      * @param array $row
      * @return string
      */
    protected static function getSelector($row)
    {
        self::validateIds($row);

        $selector = [];
        foreach (static::getCompoundKeys() as $key) {
            $selector[] = $key . '_' . $row[$key];
        }

        return join('_', $selector);
    }

    /**
      * Checks if the extending class has defined a primary key
      * @throws BaseException
      */
    private static function hasCompoundKeys()
    {
        if (!defined('static::COLUMN_COMPOUND_KEYS')) {
            throw new BaseException(BaseException::COMPOUND_KEYS_NOT_DEFINED, get_called_class() . ' has no COLUMN_COMPOUND_KEYS const');
        }
    }

    /**
      * Validates that all compound keys are present in ids
      * @param array<string, mixed> $ids
      * @throws BaseException
      */
    private static function validateIds($ids)
    {
        $difs = array_diff(static::getCompoundKeys(), array_keys($ids));
        if (count($difs) != 0) {
            throw new BaseException(BaseException::COMPOUND_KEY_MISSING_IN_WHERE, get_called_class() . '->' . __FUNCTION__ . ' failed to fetch object from database, ' . $difs[0] . ' is not present amoung ids');
        }
    }
}
