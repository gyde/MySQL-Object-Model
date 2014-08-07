<?php
namespace /*NAMESPACE_SLASH*/tests;

class MOMSimpleActual extends \MOMSimple
{
	const DB = 'mom';
	const TABLE = 'mom_simple_test';

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
}
