<?php
namespace /*NAMESPACE_SLASH*/tests;

class MOMCompoundActual extends \MOMCompound
{
	const DB = 'mom';
	const TABLE = 'mom_compound_test';

	const COLUMN_COMPOUND_KEYS = 'key1,key2,key3';
	const COLUMN_KEY1 = 'key1';
	const COLUMN_KEY2 = 'key2';
	const COLUMN_KEY3 = 'key3';
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
