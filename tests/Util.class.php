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
		$memcache->addServer($_ENV['MEMCACHE_HOST'], 11211);
		return $memcache;
	}

}
