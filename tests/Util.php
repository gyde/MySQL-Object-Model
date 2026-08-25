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

    /**
     * Sometimes the tests requires a class to be made un-instanciable
     * In those cases we need another way to "unDescribe" them
     */
    public static function unDescribe(string $class)
    {
        if ($class::USE_MEMCACHE) {
            $getMemcacheKeyRef = new \ReflectionMethod($class . '::getMemcacheKey');
            $memcacheKey = $getMemcacheKeyRef->invoke(null, '__mbDescription');

            $getMemcacheRef = new \ReflectionMethod($class . '::getMemcache');
            $memcache = $getMemcacheRef->invoke(null);

            $memcache['memcache']->delete($memcacheKey);
        }

        $descriptionsRef = new \ReflectionProperty(\Gyde\Mom\Base::class, '__mbDescriptions');
        $descriptions = $descriptionsRef->getValue();
        $descriptions[$class] = null;
        $descriptionsRef->setValue(null, $descriptions);

        $keyValidatedRef = new \ReflectionProperty(\Gyde\Mom\Base::class, '__mbKeyValidated');
        $validationList = $descriptionsRef->getValue();
        $validationList[$class] = false;
        $keyValidatedRef->setValue(null, $validationList);
    }
}
