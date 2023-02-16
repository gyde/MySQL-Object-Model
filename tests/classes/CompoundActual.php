<?php

namespace tests\classes;

class CompoundActual extends \Gyde\Mom\Compound
{
    public const DB = 'mom';
    public const TABLE = 'mom_compound_test';

    public const USE_STATIC_CACHE = true;

    public const COLUMN_COMPOUND_KEYS = 'key1,key2,key3';
    public const COLUMN_KEY1 = 'key1';
    public const COLUMN_KEY2 = 'key2';
    public const COLUMN_KEY3 = 'key3';
    public const COLUMN_DEFAULT_VALUE = 'state';
    public const COLUMN_CREATED = 'created';
    public const COLUMN_UPDATED = 'updated';
    public const COLUMN_UNIQUE = 'unique';

    public const STATE_READY = 'READY';
    public const STATE_SET = 'SET';
    public const STATE_GO = 'GO';

    public static function getByUnique($unique)
    {
        $where = '`' . self::COLUMN_UNIQUE . '` = ' . self::escapeStatic($unique);
        return self::getOne($where);
    }

    public static function getByState($state)
    {
        $where = '`' . self::COLUMN_DEFAULT_VALUE . '` = ' . self::escapeStatic($state);
        return self::getAllByWhere($where);
    }
}
