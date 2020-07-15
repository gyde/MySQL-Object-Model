<?php
namespace tests;

use tests\classes\FooBar;
use tests\classes\Foo;
use tests\classes\Bar;

class ExtensionTest extends \PHPUnit\Framework\TestCase
{
	static $connection = NULL;
	static $memcache = NULL;
	static $skipTests = FALSE;
	static $skipTestsMessage = '';

	public static function setUpBeforeClass(): void
	{
		FooBar::setDbName('mom');
		try 
		{
			self::$connection = Util::getConnection();
			\tests\mom\MOMBase::setConnection(self::$connection, TRUE);
			self::createTable(Foo::getDbName(), Foo::TABLE, Foo::COLUMN_PRIMARY_KEY);
			self::createTable(Bar::getDbName(), Bar::TABLE, Bar::COLUMN_PRIMARY_KEY);
		}
		catch (\PDOException $e)
		{
			self::$skipTests = TRUE;
			self::$skipTestsMessage = $e->getMessage();
		}

		self::$memcache = Util::getMemcache();
		\tests\mom\MOMBase::setMemcache(self::$memcache, 300);
	}

	private static function createTable($dbName, $tableName, $primaryKey)
	{
		$sqls[] = 'DROP TABLE IF EXISTS `'.$dbName.'`.`'.$tableName.';';
		$sqls[] = 'CREATE TABLE `'.$dbName.'`.`'.$tableName.'` ('.
			' `'.$primaryKey.'` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'.
			', `state` ENUM(\'READY\',\'SET\',\'GO\') NOT NULL DEFAULT \'READY\''.
			', `updated` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'.
			', `unique` VARCHAR(32) CHARACTER SET ascii UNIQUE'.
			') ENGINE = InnoDB;';

		foreach ($sqls as $sql)
		{
			$res = self::$connection->exec($sql);
		}
	}

	public static function tearDownAfterClass(): void
	{
		self::$connection = Util::getConnection();
		$sqls[] =
			'DROP TABLE `'.Foo::getDbName().'`.`'.Foo::TABLE.'`';
		$sqls[] =
			'DROP TABLE `'.Bar::getDbName().'`.`'.Bar::TABLE.'`';

		foreach ($sqls as $sql)
			self::$connection->query($sql);
		self::$memcache = new \Memcached($_SERVER['MEMCACHE_HOST']);
		self::$memcache->flush();
	}

	public function setUp(): void
	{
		if (self::$skipTests)
		{
			echo("\n".self::$skipTestsMessage."\n");
			$this->markTestSkipped(self::$skipTestsMessage);
		}
		else
		{
		}
	}

	public function testSave()
	{
		$object1 = new classes\Foo();
		$object1->unique = uniqid();
		$object1->save();
		$this->assertEquals($object1->state, 'READY');

		$object2 = classes\Foo::getById($object1->foo_id);
		$this->assertEquals($object1->foo_id, $object2->foo_id);
		$this->assertEquals($object1->state, $object2->state);
		$this->assertEquals($object1->updated, $object2->updated);
		$this->assertEquals($object1->unique, $object2->unique);

		$object3 = new classes\Bar();
		$object3->state = 'SET';
		$object3->unique = uniqid();
		$object3->save();

		$this->assertEquals($object3->state, 'SET');
	}
}
