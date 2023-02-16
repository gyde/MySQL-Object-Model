<?php

namespace tests;

class Util
{
    public const DATETIME_REGEX = '/^[12]\\d{3}-(?:0[1-9]|1[012])-(?:[0-2]\\d|3[01]) (?:[01]\\d|2[0-3]):[0-5]\\d:[0-5]\\d$/';

    /**
      * Get memcached object with server added
      * @return \Memcached
      */
    public static function getMemcache()
    {
        $memcache = new \Memcached();
        $memcache->addServer($_SERVER['MEMCACHE_HOST'], 11211);
        return $memcache;
    }

    /**
      * Get PDO object
      * @return \PDO
      */
    public static function getConnection()
    {
        $pdo = new \PDO('mysql:host=' . $_SERVER['MYSQL_HOST'], $_SERVER['MYSQL_USERNAME'], $_SERVER['MYSQL_PASSWD']);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }
}
