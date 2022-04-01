<?php
namespace tests;

class Util
{
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
		$pdo = new \PDO('mysql:host='.$_SERVER['MYSQL_HOST'], $_SERVER['MYSQL_USERNAME'], $_SERVER['MYSQL_PASSWD']);
		$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		return $pdo;
	}
}
