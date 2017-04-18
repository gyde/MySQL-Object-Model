<?php
namespace tests;

use tests\classes\FooBar;
use tests\classes\Foo;
use tests\classes\Bar;

class ExtensionTest extends \PHPUnit_Framework_TestCase
{
	static $connection = NULL;
	static $memcache = NULL;
	static $skipTests = FALSE;
	static $skipTestsMessage = '';

	public static function setUpBeforeClass()
	{
		FooBar::setDbName('mom');
		self::$connection = mysqli_connect($_ENV['MYSQLI_HOST'], $_ENV['MYSQLI_USERNAME'], $_ENV['MYSQLI_PASSWD']);
		if (self::$connection !== FALSE && self::$connection->connect_errno == 0)
		{
			self::createTable(Foo::getDbName(), Foo::TABLE, Foo::COLUMN_PRIMARY_KEY);
			self::createTable(Bar::getDbName(), Bar::TABLE, Bar::COLUMN_PRIMARY_KEY);

			if (!self::$skipTests)
				\tests\mom\MOMBase::setConnection(self::$connection, TRUE);
		}
		else
		{
			self::$skipTests = TRUE;
			self::$skipTestsMessage = self::$connection->error;
		}

		self::$memcache = new \Memcached($_ENV['MEMCACHE_HOST']);
		if (self::$memcache !== FALSE)
		{
			\tests\mom\MOMBase::setMemcache(self::$memcache, 300);
		}
		else
		{
			self::$skipTests = TRUE;
		}
	}

	private static function createTable($dbName, $tableName, $primaryKey)
	{
		$sql =
			'CREATE TABLE `'.$dbName.'`.`'.$tableName.'` ('.
			' `'.$primaryKey.'` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'.
			', `state` ENUM(\'READY\',\'SET\',\'GO\') NOT NULL DEFAULT \'READY\''.
			', `updated` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'.
			', `unique` VARCHAR(32) CHARACTER SET ascii UNIQUE'.
			') ENGINE = InnoDB;';

		$res = self::$connection->query($sql);
		if ($res === FALSE)
		{
			self::$skipTestsMessage = self::$connection->error;
			self::$skipTests = TRUE;
		}
	}

	public static function tearDownAfterClass()
	{
		self::$connection = mysqli_connect($_ENV['MYSQLI_HOST'], $_ENV['MYSQLI_USERNAME'], $_ENV['MYSQLI_PASSWD']);
		$sqls[] =
			'DROP TABLE `'.Foo::getDbName().'`.`'.Foo::TABLE.'`';
		$sqls[] =
			'DROP TABLE `'.Bar::getDbName().'`.`'.Bar::TABLE.'`';

		foreach ($sqls as $sql)
			self::$connection->query($sql);
		self::$memcache = new \Memcached($_ENV['MEMCACHE_HOST']);
		self::$memcache->flush();
	}

	public function setUp()
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
