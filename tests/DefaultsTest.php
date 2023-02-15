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
            ', `' . DefaultActual::COLUMN_DEFAULT_VALUE . '` ENUM(\'READY\',\'SET\',\'GO\') NOT NULL DEFAULT \'READY\'' .
            ', `' . DefaultActual::COLUMN_UPDATED . '` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT \'0000-00-00 00:00:00\'' .
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
        $this->assertEquals($object1->updated, '0000-00-00 00:00:00');
        $object1->unique = uniqid();
        $object1->save();
        $this->assertNotEquals($object1->updated, '0000-00-00 00:00:00');
    }
}
