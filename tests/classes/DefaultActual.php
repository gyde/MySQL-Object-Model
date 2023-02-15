<?php

namespace tests\classes;

class DefaultActual extends \Gyde\Mom\Simple
{
    public const DB = 'mom';
    public const TABLE = 'mom_default_test';

    public const USE_STATIC_CACHE = true;
    public const USE_MEMCACHE = true;

    public const COLUMN_PRIMARY_KEY = 'primary_key';
    public const COLUMN_DEFAULT_VALUE = 'state';
    public const COLUMN_UPDATED = 'updated';
    public const COLUMN_UNIQUE = 'unique';

    public const STATE_READY = 'READY';
    public const STATE_SET = 'SET';
    public const STATE_GO = 'GO';
}
