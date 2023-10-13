<?php

namespace tests\classes;

class CompoundAutoIncrement extends \Gyde\Mom\Compound
{
    public const DB = 'mom';
    public const TABLE = 'mom_compound_auto_increment_test';

    public const USE_STATIC_CACHE = true;

    public const COLUMN_COMPOUND_KEYS = 'key1,key2,key3';
    public const COLUMN_KEY1 = 'key1';
    public const COLUMN_KEY2 = 'key2';
    public const COLUMN_KEY3 = 'key3';
}
