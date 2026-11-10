<?php

namespace tests;

use tests\classes\DefaultActual;

class DefaultTest extends \PHPUnit\Framework\TestCase
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
            self::createTable(DefaultActual::DB, DefaultActual::TABLE);
        } catch (\PDOException $e) {
            self::$skipTests = true;
            self::$skipTestsMessage = $e->getMessage();
        }

        self::$memcache = Util::getMemcache();
        \Gyde\Mom\Base::setMemcache(self::$memcache, 300);
    }

    private static function createTable($dbName, $tableName)
    {
        $sqls[] = 'DROP TABLE IF EXISTS `' . $dbName . '`.`' . $tableName . '`;';
        $sqls[] = 'CREATE TABLE `' . $dbName . '`.`' . $tableName . '` (' .
            ' `' . DefaultActual::COLUMN_PRIMARY_KEY . '` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' .
            ', `' . DefaultActual::COLUMN_DEFAULT_VALUE . '` ENUM(\'READY\',\'SET\',\'GO\',\'intermediate\') NOT NULL DEFAULT \'READY\'' .
            ', `' . DefaultActual::COLUMN_CREATED . '` DATETIME NOT NULL DEFAULT current_timestamp()' .
            ', `' . DefaultActual::COLUMN_NULLABLE_CREATED . '` DATETIME NULL DEFAULT current_timestamp()' .
            ', `' . DefaultActual::COLUMN_UPDATED . '` DATETIME ON UPDATE CURRENT_TIMESTAMP NULL DEFAULT NULL' .
            ', `' . DefaultActual::COLUMN_UNIQUE . '` VARCHAR(32) CHARACTER SET ascii UNIQUE' .
            ') ENGINE = MYISAM;';

        foreach ($sqls as $sql) {
            $res = self::$connection->exec($sql);
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::$connection = Util::getConnection();
        $sqls[] = 'DROP TABLE `' . DefaultActual::DB . '`.`' . DefaultActual::TABLE . '`';

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

    public function testDefault()
    {
        $object1 = new DefaultActual();
        $object1->save();

        $this->assertNotEquals($object1->created, null);
        $this->assertEquals($object1->updated, null);
        $this->assertEquals($object1->state, 'READY');

        $object1->unique = uniqid();
        $object1->state = DefaultActual::STATE_SET;
        $object1->save();

        $this->assertEquals($object1->state, DefaultActual::STATE_SET);
        $this->assertNotEquals($object1->updated, null);
    }

    public function testNullableCurrentTimestampDefault()
    {
        // A nullable column with DEFAULT current_timestamp() must not have null
        // inserted explicitly — MySQL should apply the DEFAULT instead
        $object = new DefaultActual();
        $object->save();

        $this->assertNotNull(
            $object->nullable_created,
            'nullable DATETIME NULL DEFAULT current_timestamp() should be set by MySQL on INSERT, not forced to null'
        );
        $this->assertSame(1, preg_match(Util::DATETIME_REGEX, $object->nullable_created));

        // An explicit null assignment must be respected on subsequent saves
        $object->nullable_created = null;
        $object->save();
        $this->assertNull($object->nullable_created, 'Explicitly assigning null to a nullable protected column should persist');
    }
}
