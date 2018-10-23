<?php
namespace tests;

use tests\classes\MOMSimpleActual;
use tests\classes\MOMSimpleActual2;

class MOMSimpleTest extends \PHPUnit_Framework_TestCase
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
			self::createTable(MOMSimpleActual::DB, MOMSimpleActual::TABLE);
			self::createTable(MOMSimpleActual2::DB.'2', MOMSimpleActual2::TABLE); }
		catch (\PDOException $e)
		{
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
			' `'.MOMSimpleActual::COLUMN_PRIMARY_KEY.'` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'.
			', `'.MOMSimpleActual::COLUMN_DEFAULT_VALUE.'` ENUM(\'READY\',\'SET\',\'GO\') NOT NULL DEFAULT \'READY\''.
			', `'.MOMSimpleActual::COLUMN_CREATED.'` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'.
			', `'.MOMSimpleActual::COLUMN_UNIQUE.'` VARCHAR(32) CHARACTER SET ascii UNIQUE'.
			') ENGINE = MYISAM;';

		foreach ($sqls as $sql)
		{
			$res = self::$connection->exec($sql);
		}
	}

	public static function tearDownAfterClass()
	{
		self::$connection = Util::getConnection();
		$sqls[] =
			'DROP TABLE `'.MOMSimpleActual::DB.'`.`'.MOMSimpleActual::TABLE.'`';
		$sqls[] =
			'DROP TABLE `'.MOMSimpleActual::DB.'2`.`'.MOMSimpleActual2::TABLE.'`';

		foreach ($sqls as $sql)
			self::$connection->query($sql);
		self::$memcache = Util::getMemcache();
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
		$object1 = new MOMSimpleActual();
		$object1->unique = uniqid();
		$this->assertEquals($object1->getSerializeTimestamp(), 0);
		$this->assertEquals($object1->isNew(), true);
		$object1->save();
		$this->assertEquals($object1->state, 'READY');
		$this->assertGreaterThan(0, $object1->getSerializeTimestamp());
		$this->assertEquals($object1->isNew(), false);

		$object2 = MOMSimpleActual::getById($object1->primary_key);
		$this->assertGreaterThan(0, $object2->getSerializeTimestamp());
		$this->assertEquals($object1->getSerializeTimestamp(), $object2->getSerializeTimestamp());
		$this->assertEquals($object1->primary_key, $object2->primary_key);
		$this->assertEquals($object1->state, $object2->state);
		$this->assertEquals($object1->created, $object2->created);
		$this->assertEquals($object1->unique, $object2->unique);

		sleep(1);

		$object3 = new MOMSimpleActual();
		$object3->state = 'SET';
		$object3->unique = uniqid();
		$object3->save();

		$this->assertGreaterThan(0, $object3->getSerializeTimestamp());
		$this->assertNotEquals($object2->getSerializeTimestamp(), $object3->getSerializeTimestamp());
		$this->assertNotEquals($object2->primary_key, $object3->primary_key);
		$this->assertNotEquals($object2->state, $object3->state);
		$this->assertNotEquals($object2->created, $object3->created);
		$this->assertNotEquals($object2->unique, $object3->unique);
	}

	public function testLimit()
	{
		$objects = MOMSimpleActual::getAll(null, false, 1);
		$this->assertEquals(1, count($objects));
	}

	public function testDuplicateKey()
	{
		$objects = MOMSimpleActual::getAll(null, false, 1);
		$object1 = reset($objects);

		try {
			$object2 = new MOMSimpleActual();
			$object2->state = 'SET';
			$object2->unique = $object1->unique;
			$object2->save();
		} catch (\tests\mom\MOMBaseException $e) {
			$this->assertEquals($e->getCode(), \tests\mom\MOMBaseException::OBJECT_DUPLICATED_ENTRY);
		}
	}

	public function testGetAll()
	{
		$objects = MOMSimpleActual::getAll();
		$this->assertEquals(2, count($objects));
	}

	public function testGetAllByWhere()
	{
		$objects = MOMSimpleActual::getByState(MOMSimpleActual::STATE_SET);
		$this->assertEquals(1, count($objects));
	}

	public function testGetAllLimit()
	{
		$objects = MOMSimpleActual::getAll(NULL, FALSE, 1, 1);
		$this->assertEquals(1, count($objects));
	}

	public function testClone()
	{
		$object1 = new MOMSimpleActual();
		$object1->state = MOMSimpleActual::STATE_GO;
		$object1->unique = uniqid();
		$object1->save();

		sleep(1);

		$object2 = clone $object1;
		$object2->unique = uniqid();
		$object2->save();

		$this->assertNotEquals($object1->primary_key, $object2->primary_key);
		$this->assertEquals($object1->state, $object2->state);
		$this->assertNotEquals($object1->created, $object2->created);
		$this->assertNotEquals($object1->unique, $object2->unique);
		$this->assertGreaterThan($object1->getSerializeTimestamp(), $object2->getSerializeTimestamp());
	}

	public function testUnique()
	{
		$object1 = new MOMSimpleActual();
		$object1->unique = uniqid();
		$object1->save();

		$object2 = MOMSimpleActual::getByUnique($object1->unique);

		$this->assertEquals($object1->primary_key, $object2->primary_key);
		$this->assertEquals($object1->state, $object2->state);
		$this->assertEquals($object1->created, $object2->created);
		$this->assertEquals($object1->unique, $object2->unique);
	}

	public function testSetDbName()
	{
		$objects = MOMSimpleActual::getAll();
		$this->assertCount(5, $objects);

		MOMSimpleActual2::setDbName(MOMSimpleActual::DB.'2');
		$this->assertEquals(MOMSimpleActual::getDbName(), 'mom');
		$this->assertEquals(MOMSimpleActual2::getDbName(), 'mom2');

		$objects = MOMSimpleActual2::getAll();
		$this->assertCount(0, $objects);

		$object1 = new MOMSimpleActual2();
		$object1->unique = uniqid();
		$object1->save();

		$objects = MOMSimpleActual2::getAll();
		$this->assertCount(1, $objects);

		MOMSimpleActual::setDbName(NULL);
	}

	/**
	  * Test memcache on user created memcache methods
	  * Uses memcache and static flush functions directly
	  */
	public function testMemcache()
	{
		// Locate some items we can cache
		$uniqueKeys = [];
		foreach ($objects = MOMSimpleActual::getAll() as $object)
		{
			$uniqueKeys[] = $object->{MOMSimpleActual::COLUMN_UNIQUE};
		}

		/**
		  * Make sure the memcache is empty and fetch all the objects using a
		  * user created memcaching method
		  * This tests PDO connection when objects are unserialized
		  */
		self::$memcache->flush();
		$object = null;
		foreach ($uniqueKeys as $uniqueKey)
		{
			$object = MOMSimpleActual::getByUniqueMemcached($uniqueKey);
		}
		// Unset the description cache in MOM
		$object->unDescribe();
		// Flush all the objects from the memcache
		$object::flushStaticEntries();

		// Refetch the objects from the memcache
		foreach ($uniqueKeys as $uniqueKey)
		{
			MOMSimpleActual::getByUniqueMemcached($uniqueKey);
		}
	}

	public function testDelete()
	{
		$objects = MOMSimpleActual::getAll();
		$this->assertCount(5, $objects);
		foreach ($objects as $object)
			$object->delete();

		$objects = MOMSimpleActual::getAll();

		$this->assertCount(0, $objects);
	}

	public function testAllowNull()
	{
		$object = MOMSimpleActual::getById('sdfasdfasdf', true);
		$this->assertEquals($object, null);
	}
}
?>
