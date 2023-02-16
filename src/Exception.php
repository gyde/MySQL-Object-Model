<?php

namespace Gyde\Mom;

class Exception extends \Exception
{
    private $internalMessage = '';

    private static $constants = array();
    private static $language = null;

    /**
      * Construct a message using a code
      * Will translate the code into a message
      * @param int $code
      * @param string $internalMessage
      * @param Exception $previous
      */
    public function __construct($code, $internalMessage = '', $previous = null)
    {
        $message = false;
        if (self::$language !== null) {
            $message = $this->getConstant('MESSAGE_' . $code . '_' . self::$language);
        }

        if ($message === false) {
            $message = $this->getConstant('MESSAGE_' . $code);
        }

        parent::__construct($message, $code, $previous);
        $this->internalMessage = $internalMessage;
    }

    /**
      * Returns the internal message, used for debugging, may contain sensitive information
      * DO NOT SHOW END USERS
      * @return string
      */
    public function getInternalMessage()
    {
        if ($this->internalMessage === null) {
            return 'No internal exception message was set';
        }

        return $this->internalMessage;
    }

    /**
      * Defines a language to use for exception message constants
      * If constant matching language is not found, the default is used
      * @param string $language
      */
    public static function setLanguage($language)
    {
        self::$language = mb_strtoupper($language);
    }

    /**
     * Returns the constant value
     * Uses ReflectionClass to read constants on the class
     * @param string $name
     * @return value
     */
    private function getConstant($name)
    {
        $reflection = new \ReflectionClass($this);
        return $reflection->getConstant($name);
    }
}
