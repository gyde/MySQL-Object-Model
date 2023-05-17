<?php

namespace tests;

use tests\classes\CompoundActual;

class CompoundTest extends \PHPUnit\Framework\TestCase
{
    private static $connection = null;
    private static $memcache = null;
    private static $skipTests = false;
    private static $skipTestsMessage = '';
    private static $autoIncrement = 1;

    public static function setUpBeforeClass(): void
    {
        try {
            self::$connection = Util::getConnection();
            \Gyde\Mom\Base::setConnection(self::$connection, true);
            $sqls[] = 'DROP TABLE IF EXISTS ' . CompoundActual::DB . '.' . CompoundActual::TABLE . ';';
            $sqls[] = 'CREATE TABLE ' . CompoundActual::DB . '.' . CompoundActual::TABLE . ' (' .
                ' `' . CompoundActual::COLUMN_KEY1 . '` INT(10) UNSIGNED NOT NULL' .
                ', `' . CompoundActual::COLUMN_KEY2 . '` INT(10) UNSIGNED NOT NULL' .
                ', `' . CompoundActual::COLUMN_KEY3 . '` INT(10) UNSIGNED NOT NULL' .
                ', `' . CompoundActual::COLUMN_DEFAULT_VALUE . '` ENUM(\'READY\',\'SET\',\'GO\',\'intermediate\') NOT NULL DEFAULT \'READY\'' .
                ', `' . CompoundActual::COLUMN_CREATED . '` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP' .
                ', `' . CompoundActual::COLUMN_UPDATED . '` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP' .
                ', `' . CompoundActual::COLUMN_UNIQUE . '` VARCHAR(32) CHARACTER SET ascii UNIQUE' .
                ', PRIMARY KEY (`' . CompoundActual::COLUMN_KEY1 . '`,`' . CompoundActual::COLUMN_KEY2 . '`,`' . CompoundActual::COLUMN_KEY3 . '`)' .
                ') ENGINE = MYISAM;';

            foreach ($sqls as $sql) {
                $res = self::$connection->exec($sql);
            }
        } catch (\PDOException $e) {
            self::$skipTests = true;
            self::$skipTestsMessage = $e->getMessage();
        }

        self::$memcache = Util::getMemcache();
        \Gyde\Mom\Base::setMemcache(self::$memcache, 300);
    }

    public static function tearDownAfterClass(): void
    {
        self::$connection = Util::getConnection();
        $sql = 'DROP TABLE ' . CompoundActual::DB . '.' . CompoundActual::TABLE;

        self::$connection->query($sql);
        self::$memcache = new \Memcached($_SERVER['MEMCACHE_HOST']);
        self::$memcache->flush();
    }

    public function setUp(): void
    {
        if (self::$skipTests) {
            echo("\n" . self::$skipTestsMessage . "\n");
            $this->markTestSkipped(self::$skipTestsMessage);
        }
    }

    private function uniqueKeys(): array
    {
        return [
            CompoundActual::COLUMN_KEY1 => self::$autoIncrement++,
            CompoundActual::COLUMN_KEY2 => self::$autoIncrement++,
            CompoundActual::COLUMN_KEY3 => self::$autoIncrement++
        ];
    }

    public function testSave()
    {
        $ids = $this->uniqueKeys();

        $object1 = new CompoundActual(self::$connection);
        foreach ($ids as $key => $value) {
            $object1->$key = $value;
        }
        $object1->unique = uniqid();
        $object1->save();
        $this->assertEquals($object1->state, 'READY');

        $object2 = CompoundActual::getByIds($ids);
        $this->assertEquals($object1->key1, $object2->key1);
        $this->assertEquals($object1->key2, $object2->key2);
        $this->assertEquals($object1->key3, $object2->key3);
        $this->assertEquals($object1->unique, $object2->unique);

        $object3 = new CompoundActual(self::$connection);
        $object3->key1 = $object1->key1;
        $object3->key2 = self::$autoIncrement++;
        $object3->key3 = $object1->key3;
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
        $ids = $this->uniqueKeys();
        $object1 = new CompoundActual(self::$connection);
        foreach ($ids as $key => $value) {
            $object1->$key = $value;
        }
        $object1->unique = uniqid();
        $object1->save();

        $object2 = CompoundActual::getByIds($ids);
        $this->assertNotNull($object2);
        $object2->delete();

        $object2 = CompoundActual::getByIds($ids);

        $this->assertNull($object2, 'Object should not be in database');
    }

    public function testStaticCacheSingleton()
    {
        $ids = $this->uniqueKeys();
        $where = array();

        $object1 = new CompoundActual(self::$connection);
        $object1->unique = uniqid();
        foreach ($ids as $key => $id) {
            $object1->$key = $id;
            $where[] = '`' . $key . '` = \'' . $id . '\'';
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

    public function testProtectedFields()
    {
        $object = new CompoundActual();
        foreach ($this->uniqueKeys() as $key => $value) {
            $object->$key = $value;
        }
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

        $object = new CompoundActual();
        foreach ($this->uniqueKeys() as $key => $value) {
            $object->$key = $value;
        }
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
