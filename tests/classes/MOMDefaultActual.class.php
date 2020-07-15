<?php
namespace tests\classes;

class MOMDefaultActual extends \tests\mom\MOMSimple
{
	const DB = 'mom';
	const TABLE = 'mom_default_test';

	const USE_STATIC_CACHE = TRUE;
	const USE_MEMCACHE = TRUE;

	const COLUMN_PRIMARY_KEY = 'primary_key';
	const COLUMN_DEFAULT_VALUE = 'state';
	const COLUMN_UPDATED = 'updated';
	const COLUMN_UNIQUE = 'unique';

	const STATE_READY = 'READY';
	const STATE_SET = 'SET';
	const STATE_GO = 'GO';
}
