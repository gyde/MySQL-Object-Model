<?php
namespace tests;

class MOMSimpleActual extends \MOMSimple
{
	const DB = 'mom';
	const TABLE = 'mom_simple_test';

	const USE_STATIC_CACHE = TRUE;
	const USE_MEMCACHE = TRUE;

	const COLUMN_PRIMARY_KEY = 'primary_key';
	const COLUMN_DEFAULT_VALUE = 'state';
	const COLUMN_UPDATED = 'updated';
	const COLUMN_UNIQUE = 'unique';

	const STATE_READY = 'READY';
	const STATE_SET = 'SET';
	const STATE_GO = 'GO';

	public static function getByUnique($unique)
	{
		$where = '`'.self::COLUMN_UNIQUE.'` = '.self::escapeStatic($unique);
		return self::getOne($where);
	}

	public static function getByState($state)
	{
		$where = '`'.self::COLUMN_DEFAULT_VALUE.'` = '.self::escapeStatic($state);
		return self::getAllByWhere($where);
	}
}
