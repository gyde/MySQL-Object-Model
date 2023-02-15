<?php

namespace tests;

use tests\classes\SimpleActual;
use tests\classes\SimpleActual2;

class SimpleTest extends \PHPUnit\Framework\TestCase
{
    private static $connection = null;
    private static $memcache = null;
    private static $skipTests = false;
    private static $skipTestsMessage = '';

    public static function setUpBeforeClass(): void
    {
        try {
            self::$connection = Util::getConnection();
            \Gyde\Mom\Base::setConnection(self::$connection, true);
            self::createTable(SimpleActual::DB, SimpleActual::TABLE);
            self::createTable(SimpleActual2::DB . '2', SimpleActual2::TABLE);
        } catch (\PDOException $e) {
            self::$skipTests = true;
            self::$skipTestsMessage = $e->getMessage();
        }

        self::$memcache = Util::getMemcache();
        \Gyde\Mom\Base::setMemcache(self::$memcache, 300);
    }

    private static function createTable($dbName, $tableName)
    {
        $sqls[] = 'DROP TABLE IF EXISTS `' . $dbName . '`.`' . $tableName . '`';
        $sqls[] = 'CREATE TABLE `' . $dbName . '`.`' . $tableName . '` (' .
            ' `' . SimpleActual::COLUMN_PRIMARY_KEY . '` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' .
            ', `' . SimpleActual::COLUMN_DEFAULT_VALUE . '` ENUM(\'READY\',\'SET\',\'GO\') NOT NULL DEFAULT \'READY\'' .
            ', `' . SimpleActual::COLUMN_CREATED . '` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP' .
            ', `' . SimpleActual::COLUMN_UPDATED . '` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP' .
            ', `' . SimpleActual::COLUMN_IS_IT_ON . '` BOOLEAN DEFAULT 0' .
            ', `' . SimpleActual::COLUMN_UNIQUE . '` VARCHAR(32) CHARACTER SET ascii UNIQUE' .
            ') ENGINE = MYISAM';

        foreach ($sqls as $sql) {
            $res = self::$connection->exec($sql);
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::$connection = Util::getConnection();
        $sqls[] = 'DROP TABLE `' . SimpleActual::DB . '`.`' . SimpleActual::TABLE . '`';
        $sqls[] = 'DROP TABLE `' . SimpleActual::DB . '2`.`' . SimpleActual2::TABLE . '`';

        foreach ($sqls as $sql) {
            self::$connection->query($sql);
        }
        self::$memcache = Util::getMemcache();
        self::$memcache->flush();
    }

    public function setUp(): void
    {
        if (self::$skipTests) {
            echo("\n" . self::$skipTestsMessage . "\n");
            $this->markTestSkipped(self::$skipTestsMessage);
        }
    }
    public function testSave()
    {
        $object1 = new SimpleActual();
        $object1->unique = uniqid();
        $this->assertEquals($object1->getSerializeTimestamp(), 0);
        $this->assertEquals($object1->isNew(), true);
        $object1->save();
        $this->assertEquals($object1->state, 'READY');
        $this->assertGreaterThan(0, $object1->getSerializeTimestamp());
        $this->assertEquals($object1->isNew(), false);

        $object2 = SimpleActual::getById($object1->primary_key);
        $this->assertGreaterThan(0, $object2->getSerializeTimestamp());
        $this->assertEquals($object1->getSerializeTimestamp(), $object2->getSerializeTimestamp());
        $this->assertEquals($object1->primary_key, $object2->primary_key);
        $this->assertEquals($object1->state, $object2->state);
        $this->assertEquals($object1->created, $object2->created);
        $this->assertEquals($object1->unique, $object2->unique);

        sleep(1);

        $object3 = new SimpleActual();
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
        $objects = SimpleActual::getAll(null, false, 1);
        $this->assertEquals(1, count($objects));
    }

    public function testDuplicateKey()
    {
        $objects = SimpleActual::getAll(null, false, 1);
        $object1 = reset($objects);

        try {
            $object2 = new SimpleActual();
            $object2->state = 'SET';
            $object2->unique = $object1->unique;
            $object2->save();
        } catch (\Gyde\Mom\BaseException $e) {
            $this->assertEquals($e->getCode(), \Gyde\Mom\BaseException::OBJECT_DUPLICATED_ENTRY);
        }
    }

    public function testGetAll()
    {
        $objects = SimpleActual::getAll();
        $this->assertEquals(2, count($objects));
    }

    public function testGetAllByWhere()
    {
        $objects = SimpleActual::getByState(SimpleActual::STATE_SET);
        $this->assertEquals(1, count($objects));
    }

    public function testGetAllLimit()
    {
        $objects = SimpleActual::getAll(null, false, 1, 1);
        $this->assertEquals(1, count($objects));
    }

    public function testClone()
    {
        $object1 = new SimpleActual();
        $object1->state = SimpleActual::STATE_GO;
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
        $object1 = new SimpleActual();
        $object1->unique = uniqid();
        $object1->save();

        $object2 = SimpleActual::getByUnique($object1->unique);

        $this->assertEquals($object1->primary_key, $object2->primary_key);
        $this->assertEquals($object1->state, $object2->state);
        $this->assertEquals($object1->created, $object2->created);
        $this->assertEquals($object1->unique, $object2->unique);
    }

    public function testSetDbName()
    {
        $objects = SimpleActual::getAll();
        $this->assertCount(5, $objects);

        SimpleActual2::setDbName(SimpleActual::DB . '2');
        $this->assertEquals(SimpleActual::getDbName(), 'mom');
        $this->assertEquals(SimpleActual2::getDbName(), 'mom2');

        $objects = SimpleActual2::getAll();
        $this->assertCount(0, $objects);

        $object1 = new SimpleActual2();
        $object1->unique = uniqid();
        $object1->save();

        $objects = SimpleActual2::getAll();
        $this->assertCount(1, $objects);

        SimpleActual::setDbName(null);
    }

    /**
      * Test memcache on user created memcache methods
      * Uses memcache and static flush functions directly
      */
    public function testMemcache()
    {
        // Locate some items we can cache
        $uniqueKeys = [];
        foreach ($objects = SimpleActual::getAll() as $object) {
            $uniqueKeys[] = $object->{SimpleActual::COLUMN_UNIQUE};
        }

        /**
          * Make sure the memcache is empty and fetch all the objects using a
          * user created memcaching method
          * This tests PDO connection when objects are unserialized
          */
        self::$memcache->flush();
        $object = null;
        foreach ($uniqueKeys as $uniqueKey) {
            $object1 = SimpleActual::getByUniqueMemcached($uniqueKey);
        }
        // Unset the description cache in MOM
        $object1->unDescribe();
        // Flush all the objects from the memcache
        $object1::flushStaticEntries();

        // Refetch the objects from the memcache
        foreach ($uniqueKeys as $uniqueKey) {
            $object2 = SimpleActual::getByUniqueMemcached($uniqueKey);
        }

        $this->assertEquals($object1, $object2);
    }

    public function testDelete()
    {
        $objects = SimpleActual::getAll();
        $this->assertCount(5, $objects);
        foreach ($objects as $object) {
            $object->delete();
        }

        $objects = SimpleActual::getAll();

        $this->assertCount(0, $objects);
    }

    public function testAllowNull()
    {
        $object = SimpleActual::getById('sdfasdfasdf', true);
        $this->assertEquals($object, null);
    }

    public function testStaticCacheSingleton()
    {
        $object1 = new SimpleActual();
        $object1->{SimpleActual::COLUMN_UNIQUE} = uniqid();
        $object1->save();

        $id = $object1->{SimpleActual::COLUMN_PRIMARY_KEY};

        $object2 = SimpleActual::getById($id);
        $this->assertSame($object1, $object2);

        SimpleActual::flushStaticEntries();

        $object3 = SimpleActual::getById($id);
        $this->assertNotSame($object2, $object3);

        $object4 = SimpleActual::getById($id);
        $this->assertSame($object3, $object4);

        // All other get* methods (getOne included) goes through getAllByWhereGeneric()
        // Meaning that if this test passes singletons should be ensured.
        $object5 = SimpleActual::getOne('`' . SimpleActual::COLUMN_PRIMARY_KEY . '` = \'' . $id . '\'');
        $this->assertSame($object4, $object5);
    }

    public function testBoolean()
    {
        $object1 = new SimpleActual();
        $object1->{SimpleActual::COLUMN_UNIQUE} = uniqid();
        $object1->{SimpleActual::COLUMN_IS_IT_ON} = false;
        $object1->save();
        $this->assertEquals($object1->{SimpleActual::COLUMN_IS_IT_ON}, 0);

        $object2 = new SimpleActual();
        $object2->{SimpleActual::COLUMN_UNIQUE} = uniqid();
        $object2->{SimpleActual::COLUMN_IS_IT_ON} = true;
        $object2->save();
        $this->assertEquals($object2->{SimpleActual::COLUMN_IS_IT_ON}, 1);
    }

    public function testUnbufferedSql()
    {
        $objects = SimpleActual::getAll(null, false, null, null, false);

        $this->assertEquals(count($objects), 3);
    }

    public function testProtectedFields()
    {
        $object = new SimpleActual();
        $created1 = $object->created;
        $updated1 = $object->updated;

        $object->save();
        $created2 = $object->created;
        $updated2 = $object->updated;

        $this->assertSame(1, preg_match(Util::DATETIME_REGEX, $created2), 'Not valid datetime string');
        $this->assertNotEquals($created1, $created2);
        $this->assertSame(1, preg_match(Util::DATETIME_REGEX, $updated2), 'Not valid datetime string');
        $this->assertNotEquals($updated1, $updated2);

        sleep(1);
        $object->unique = uniqid();
        $object->save();
        $created3 = $object->created;
        $updated3 = $object->updated;

        $this->assertEquals($created2, $created3);
        $this->assertSame(1, preg_match(Util::DATETIME_REGEX, $updated3), 'Not valid datetime string');
        $this->assertGreaterThan($updated2, $updated3);
    }

    public function testProtectedFieldsOverwrite()
    {
        $newDate = '2000-01-01 13:37:00';

        $object = new SimpleActual();
        $object->created = $newDate;
        $object->updated = $newDate;
        $object->save();

        $this->assertEquals($newDate, $object->created);
        $this->assertEquals($newDate, $object->updated);

        $newDate = '2000-01-02 13:37:00';

        $object->created = $newDate;
        $object->updated = $newDate;
        $object->save();

        $this->assertEquals($newDate, $object->created);
        $this->assertEquals($newDate, $object->updated);
    }
}
