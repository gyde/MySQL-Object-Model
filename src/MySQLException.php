<?php

namespace Gyde\Mom;

class MySQLException extends \Exception
{
    public const ER_DUP_ENTRY = 1062;
    public const ER_LOCK_WAIT_TIMEOUT = 1205;
    public const ER_LOCK_DEADLOCK = 1213;

    private $mysqlQuery = null;
    private $mysqlError = null;
    private $mysqlErrno = null;

    /**
      * Constructs a MySQLException containing mysql error info
      * @param string $mysqlQuery
      * @param string $mysqlError
      * @param int $mysqlErrno Mysql Error Code
      */
    public function __construct($mysqlQuery, $mysqlError, $mysqlErrno)
    {
        parent::__construct($mysqlError . ' QUERY[' . $mysqlQuery . ']', $mysqlErrno);
        $this->mysqlQuery = $mysqlQuery;
        $this->mysqlError = $mysqlError;
        $this->mysqlErrno = $mysqlErrno;
    }

    /**
      * Get mysql query
      * @return string
      */
    public function getMysqlQuery()
    {
        return $this->mysqlQuery;
    }

    /**
      * Get mysql error
      * @return string
      */
    public function getMysqlError()
    {
        return $this->mysqlError;
    }

    /**
      * Get mysql errno
      * @return int
      */
    public function getMysqlErrno()
    {
        return $this->mysqlErrno;
    }
}
