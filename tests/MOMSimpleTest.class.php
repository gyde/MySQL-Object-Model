<?php
namespace /*NAMESPACE_SLASH*/tests;

class MOMSimpleTest extends \PHPUnit_Framework_TestCase
{
	static $connection = NULL;
	static $memcache = NULL;
	static $skipTests = FALSE;
	static $skipTestsMessage = '';

	public static function setUpBeforeClass()
	{
		self::$connection = mysqli_connect($_ENV['MYSQLI_HOST'], $_ENV['MYSQLI_USERNAME'], $_ENV['MYSQLI_PASSWD']);
		if (self::$connection !== FALSE && self::$connection->connect_errno == 0)
		{
			$sql =
				'CREATE TABLE '.MOMSimpleActual::DB.'.'.MOMSimpleActual::TABLE.' ('.
				' `'.MOMSimpleActual::COLUMN_PRIMARY_KEY.'` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'.
				', `'.MOMSimpleActual::COLUMN_DEFAULT_VALUE.'` ENUM(\'READY\',\'SET\',\'GO\') NOT NULL DEFAULT \'READY\''.
				', `'.MOMSimpleActual::COLUMN_UPDATED.'` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'.
				', `'.MOMSimpleActual::COLUMN_UNIQUE.'` VARCHAR(32) CHARACTER SET ascii UNIQUE'.
				') ENGINE = MYISAM;';

			$res = self::$connection->query($sql);
			if ($res !== FALSE)
			{
				\MOMBase::setConnection(self::$connection, TRUE);
			}
			else
			{
				self::$skipTestsMessage = self::$connection->error;
				self::$skipTests = TRUE;
			}

		}
		else
		{
			self::$skipTests = TRUE;
		}

		self::$memcache = new \Memcached($_ENV['MEMCACHE_HOST']);
		if (self::$memcache !== FALSE)
		{
			\MOMBase::setMemcache(self::$memcache, 300);
		}
		else
		{
			self::$skipTests = TRUE;
		}
	}

	public static function tearDownAfterClass()
	{
		self::$connection = mysqli_connect($_ENV['MYSQLI_HOST'], $_ENV['MYSQLI_USERNAME'], $_ENV['MYSQLI_PASSWD']);
		$sql =
			'DROP TABLE '.MOMSimpleActual::DB.'.'.MOMSimpleActual::TABLE;

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
	}

	public function testSave()
	{
		$object1 = new MOMSimpleActual();
		$object1->unique = uniqid();
		$this->assertEquals($object1->getMemcacheTimestamp(), 0);
		$object1->save();
		$this->assertEquals($object1->state, 'READY');
		$this->assertGreaterThan(0, $object1->getMemcacheTimestamp()); 

		$object2 = MOMSimpleActual::getById($object1->primary_key);
		$this->assertGreaterThan(0, $object2->getMemcacheTimestamp()); 
		$this->assertEquals($object1->getMemcacheTimestamp(), $object2->getMemcacheTimestamp());
		$this->assertEquals($object1->primary_key, $object2->primary_key);
		$this->assertEquals($object1->state, $object2->state);
		$this->assertEquals($object1->updated, $object2->updated);
		$this->assertEquals($object1->unique, $object2->unique);

		sleep(1);

		$object3 = new MOMSimpleActual();
		$object3->state = 'SET';
		$object3->unique = uniqid();
		$object3->save();

		$this->assertGreaterThan(0, $object3->getMemcacheTimestamp()); 
		$this->assertNotEquals($object2->getMemcacheTimestamp(), $object3->getMemcacheTimestamp());
		$this->assertNotEquals($object2->primary_key, $object3->primary_key);
		$this->assertNotEquals($object2->state, $object3->state);
		$this->assertNotEquals($object2->updated, $object3->updated);
		$this->assertNotEquals($object2->unique, $object3->unique);
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
		$this->assertNotEquals($object1->updated, $object2->updated);
		$this->assertNotEquals($object1->unique, $object2->unique);
		$this->assertGreaterThan($object1->getMemcacheTimestamp(), $object2->getMemcacheTimestamp());
	}

	public function testUnique()
	{
		$object1 = new MOMSimpleActual();
		$object1->unique = uniqid();
		$object1->save();

		$object2 = MOMSimpleActual::getByUnique($object1->unique);

		$this->assertEquals($object1->primary_key, $object2->primary_key);
		$this->assertEquals($object1->state, $object2->state);
		$this->assertEquals($object1->updated, $object2->updated);
		$this->assertEquals($object1->unique, $object2->unique);
	}
	
	public function testDelete()
	{
		$objects = MOMSimpleActual::getAll();
		foreach ($objects as $object)
			$object->delete();

		$objects = MOMSimpleActual::getAll();

		$this->assertCount(0, $objects);
	}
}
?>
