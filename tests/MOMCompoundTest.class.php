<?php
namespace tests;

use tests\classes\MOMCompoundActual;

class MOMCompoundTest extends \PHPUnit_Framework_TestCase
{
	static $connection = NULL;
	static $memcache = NULL;
	static $skipTests = FALSE;
	static $skipTestsMessage = '';

	public static function setUpBeforeClass()
	{
		try
		{
			self::$connection = Util::getConnection();
			\tests\mom\MOMBase::setConnection(self::$connection, TRUE);
			$sqls[] = 'DROP TABLE IF EXISTS '.MOMCompoundActual::DB.'.'.MOMCompoundActual::TABLE.';';
			$sqls[] = 'CREATE TABLE '.MOMCompoundActual::DB.'.'.MOMCompoundActual::TABLE.' ('.
				' `'.MOMCompoundActual::COLUMN_KEY1.'` INT(10) UNSIGNED NOT NULL'.
				', `'.MOMCompoundActual::COLUMN_KEY2.'` INT(10) UNSIGNED NOT NULL'.
				', `'.MOMCompoundActual::COLUMN_KEY3.'` INT(10) UNSIGNED NOT NULL'.
				', `'.MOMCompoundActual::COLUMN_DEFAULT_VALUE.'` ENUM(\'READY\',\'SET\',\'GO\') NOT NULL DEFAULT \'READY\''.
				', `'.MOMCompoundActual::COLUMN_UPDATED.'` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'.
				', `'.MOMCompoundActual::COLUMN_UNIQUE.'` VARCHAR(32) CHARACTER SET ascii UNIQUE'.
				', PRIMARY KEY (`'.MOMCompoundActual::COLUMN_KEY1.'`,`'.MOMCompoundActual::COLUMN_KEY2.'`,`'.MOMCompoundActual::COLUMN_KEY3.'`)'.
				') ENGINE = MYISAM;';

			foreach ($sqls as $sql)
			{
				$res = self::$connection->exec($sql);
			}
		}
		catch (\PDOException $e)
		{
			self::$skipTests = TRUE;
			self::$skipTestsMessage = $e->getMessage();
		}

		self::$memcache = Util::getMemcache();
		\tests\mom\MOMBase::setMemcache(self::$memcache, 300);
	}

	public static function tearDownAfterClass()
	{
		self::$connection = Util::getConnection();
		$sql =
			'DROP TABLE '.MOMCompoundActual::DB.'.'.MOMCompoundActual::TABLE;

		self::$connection->query($sql);
		self::$memcache = new \Memcached($_SERVER['MEMCACHE_HOST']);
		self::$memcache->flush();
	}

	public function setUp()
	{
		if (self::$skipTests)
		{
			echo("\n".self::$skipTestsMessage."\n");
			$this->markTestSkipped(self::$skipTestsMessage);
		}
	}

	public function testSave()
	{
		$object1 = new MOMCompoundActual(self::$connection);
		$object1->key1 = 1;
		$object1->key2 = 1;
		$object1->key3 = 1;
		$object1->unique = uniqid();
		$object1->save();
		$this->assertEquals($object1->state, 'READY');

		$ids = array('key1' => $object1->key1, 'key2' => $object1->key2, 'key3' => $object1->key3);
		$object2 = MOMCompoundActual::getByIds($ids);
		$this->assertEquals($object1->key1, $object2->key1);
		$this->assertEquals($object1->key2, $object2->key2);
		$this->assertEquals($object1->key3, $object2->key3);
		$this->assertEquals($object1->unique, $object2->unique);

		$object3 = new MOMCompoundActual(self::$connection);
		$object3->key1 = 1;
		$object3->key2 = 2;
		$object3->key3 = 1;
		$object3->state = 'SET';
		$object3->unique = uniqid();
		$object3->save();

		$this->assertEquals($object1->key1, $object3->key1);
		$this->assertNotEquals($object1->key2, $object3->key2);
		$this->assertEquals($object1->key3, $object3->key3);
		$this->assertNotEquals($object1->unique, $object3->unique);
	}

	public function testDelete()
	{
		$ids = array('key1' => 1, 'key2' => 1, 'key3' => 1);
		$object = MOMCompoundActual::getByIds($ids);
		$this->assertNotNull($object);
		$object->delete();

		$object = MOMCompoundActual::getByIds($ids);

		$this->assertNull($object, NULL);
	}

	public function testStaticCacheSingleton()
	{
		$ids = array(
			MOMCompoundActual::COLUMN_KEY1 => 4,
			MOMCompoundActual::COLUMN_KEY2 => 5,
			MOMCompoundActual::COLUMN_KEY3 => 6
		);
		$where = array();

		$object1 = new MOMCompoundActual(self::$connection);
		$object1->unique = uniqid();
		foreach ($ids as $key => $id)
		{
			$object1->{$key} = $id;
			$where[] = '`'.$key.'` = \''.$id.'\'';
		}
		$where = join(' AND ', $where);
		$object1->save();

		$object2 = MOMCompoundActual::getByIds($ids);
		$this->assertSame($object1, $object2);

		MOMCompoundActual::flushStaticEntries();

		$object3 = MOMCompoundActual::getByIds($ids);
		$this->assertNotSame($object2, $object3);

		$object4 = MOMCompoundActual::getByIds($ids);
		$this->assertSame($object3, $object4);

		// All other get* methods (getOne included) goes through getAllByWhereGeneric()
		// Meaning that if this test passes singletons should be ensured.
		$object5 = MOMCompoundActual::getOne($where);
		$this->assertSame($object4, $object5);
	}
}
