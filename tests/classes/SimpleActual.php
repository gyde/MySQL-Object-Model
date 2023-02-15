<?php

namespace tests\classes;

class SimpleActual extends \Gyde\Mom\Simple
{
    public const DB = 'mom';
    public const TABLE = 'mom_simple_test';

    public const USE_STATIC_CACHE = true;
    public const USE_MEMCACHE = true;

    public const COLUMN_PRIMARY_KEY = 'primary_key';
    public const COLUMN_DEFAULT_VALUE = 'state';
    public const COLUMN_CREATED = 'created';
    public const COLUMN_UPDATED = 'updated';
    public const COLUMN_IS_IT_ON = 'is_it_on';
    public const COLUMN_UNIQUE = 'unique';

    public const STATE_READY = 'READY';
    public const STATE_SET = 'SET';
    public const STATE_GO = 'GO';

    public static function getByUnique($unique)
    {
        $where = '`' . self::COLUMN_UNIQUE . '` = ' . self::escapeStatic($unique);
        return self::getOne($where);
    }

    public static function getByUniqueMemcached($unique)
    {
        $selector = self::COLUMN_UNIQUE;
        if (($entry = self::getCacheEntry($selector)) !== false) {
            return $entry;
        }

        $where = '`' . self::COLUMN_UNIQUE . '` = ' . self::escapeStatic($unique);
        $object = self::getOne($where);

        self::setCacheEntry($selector, $object);
    }

    public static function getByState($state)
    {
        $where = '`' . self::COLUMN_DEFAULT_VALUE . '` = ' . self::escapeStatic($state);
        return self::getAllByWhere($where);
    }
}
