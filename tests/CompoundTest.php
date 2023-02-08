<?php
namespace tests;

use tests\classes\CompoundActual;

class CompoundTest extends \PHPUnit\Framework\TestCase
{
	static $connection = NULL;
	static $memcache = NULL;
	static $skipTests = FALSE;
	static $skipTestsMessage = '';

	public static function setUpBeforeClass(): void
	{
		try
		{
			self::$connection = Util::getConnection();
			\Gyde\Mom\Base::setConnection(self::$connection, TRUE);
			$sqls[] = 'DROP TABLE IF EXISTS '.CompoundActual::DB.'.'.CompoundActual::TABLE.';';
			$sqls[] = 'CREATE TABLE '.CompoundActual::DB.'.'.CompoundActual::TABLE.' ('.
				' `'.CompoundActual::COLUMN_KEY1.'` INT(10) UNSIGNED NOT NULL'.
				', `'.CompoundActual::COLUMN_KEY2.'` INT(10) UNSIGNED NOT NULL'.
				', `'.CompoundActual::COLUMN_KEY3.'` INT(10) UNSIGNED NOT NULL'.
				', `'.CompoundActual::COLUMN_DEFAULT_VALUE.'` ENUM(\'READY\',\'SET\',\'GO\') NOT NULL DEFAULT \'READY\''.
				', `'.CompoundActual::COLUMN_UPDATED.'` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'.
				', `'.CompoundActual::COLUMN_UNIQUE.'` VARCHAR(32) CHARACTER SET ascii UNIQUE'.
				', PRIMARY KEY (`'.CompoundActual::COLUMN_KEY1.'`,`'.CompoundActual::COLUMN_KEY2.'`,`'.CompoundActual::COLUMN_KEY3.'`)'.
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
		\Gyde\Mom\Base::setMemcache(self::$memcache, 300);
	}

	public static function tearDownAfterClass(): void
	{
		self::$connection = Util::getConnection();
		$sql =
			'DROP TABLE '.CompoundActual::DB.'.'.CompoundActual::TABLE;

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
	}

	public function testSave()
	{
		$object1 = new CompoundActual(self::$connection);
		$object1->key1 = 1;
		$object1->key2 = 1;
		$object1->key3 = 1;
		$object1->unique = uniqid();
		$object1->save();
		$this->assertEquals($object1->state, 'READY');

		$ids = array('key1' => $object1->key1, 'key2' => $object1->key2, 'key3' => $object1->key3);
		$object2 = CompoundActual::getByIds($ids);
		$this->assertEquals($object1->key1, $object2->key1);
		$this->assertEquals($object1->key2, $object2->key2);
		$this->assertEquals($object1->key3, $object2->key3);
		$this->assertEquals($object1->unique, $object2->unique);

		$object3 = new CompoundActual(self::$connection);
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
		$object = CompoundActual::getByIds($ids);
		$this->assertNotNull($object);
		$object->delete();

		$object = CompoundActual::getByIds($ids);

		$this->assertNull($object, 'Object should not be in database');
	}

	public function testStaticCacheSingleton()
	{
		$ids = array(
			CompoundActual::COLUMN_KEY1 => 4,
			CompoundActual::COLUMN_KEY2 => 5,
			CompoundActual::COLUMN_KEY3 => 6
		);
		$where = array();

		$object1 = new CompoundActual(self::$connection);
		$object1->unique = uniqid();
		foreach ($ids as $key => $id)
		{
			$object1->{$key} = $id;
			$where[] = '`'.$key.'` = \''.$id.'\'';
		}
		$where = join(' AND ', $where);
		$object1->save();

		$object2 = CompoundActual::getByIds($ids);
		$this->assertSame($object1, $object2);

		CompoundActual::flushStaticEntries();

		$object3 = CompoundActual::getByIds($ids);
		$this->assertNotSame($object2, $object3);

		$object4 = CompoundActual::getByIds($ids);
		$this->assertSame($object3, $object4);

		// All other get* methods (getOne included) goes through getAllByWhereGeneric()
		// Meaning that if this test passes singletons should be ensured.
		$object5 = CompoundActual::getOne($where);
		$this->assertSame($object4, $object5);
	}
}
