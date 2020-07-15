<?php
namespace tests;

use tests\classes\MOMDefaultActual;

class MOMDefaultTest extends \PHPUnit\Framework\TestCase
{
	static $connection = NULL;
	static $memcache = NULL;
	static $skipTests = FALSE;
	static $skipTestsMessage = '';

	public static function setUpBeforeClass(): void
	{
		try {
			self::$connection = Util::getConnection();
			\tests\mom\MOMBase::setConnection(self::$connection, TRUE);
			self::createTable(MOMDefaultActual::DB, MOMDefaultActual::TABLE); 
		} catch (\PDOException $e) {
			self::$skipTests = TRUE;
			self::$skipTestsMessage = $e->getMessage();
		}

		self::$memcache = Util::getMemcache();
		\tests\mom\MOMBase::setMemcache(self::$memcache, 300);
	}

	private static function createTable($dbName, $tableName)
	{
		$sqls[] = 'DROP TABLE IF EXISTS `'.$dbName.'`.`'.$tableName.';';
		$sqls[] = 'CREATE TABLE `'.$dbName.'`.`'.$tableName.'` ('.
			' `'.MOMDefaultActual::COLUMN_PRIMARY_KEY.'` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'.
			', `'.MOMDefaultActual::COLUMN_DEFAULT_VALUE.'` ENUM(\'READY\',\'SET\',\'GO\') NOT NULL DEFAULT \'READY\''.
			', `'.MOMDefaultActual::COLUMN_UPDATED.'` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT \'0000-00-00 00:00:00\''.
			', `'.MOMDefaultActual::COLUMN_UNIQUE.'` VARCHAR(32) CHARACTER SET ascii UNIQUE'.
			') ENGINE = MYISAM;';
		
		foreach ($sqls as $sql)
		{
			$res = self::$connection->exec($sql);
		}
	}

	public static function tearDownAfterClass(): void
	{
		self::$connection = Util::getConnection();
		$sqls[] =
			'DROP TABLE `'.MOMDefaultActual::DB.'`.`'.MOMDefaultActual::TABLE.'`';

		foreach ($sqls as $sql)
			self::$connection->query($sql);
		self::$memcache = Util::getMemcache();
		self::$memcache->flush();
	}

	public function setUp(): void
	{
		if (self::$skipTests)
		{
			echo("\n".self::$skipTestsMessage."\n");
			$this->markTestSkipped(self::$skipTestsMessage);
		}
	}

	public function testDefault()
	{
		$object1 = new MOMDefaultActual();
		$object1->save();
		$this->assertEquals($object1->updated, '0000-00-00 00:00:00');
		$object1->unique = uniqid();
		$object1->save();
		$this->assertNotEquals($object1->updated, '0000-00-00 00:00:00');
	}
}
