<?php
/*NAMESPACE*/

class MOMException extends \Exception
{
	private $internalMessage = '';

	private static $constants = array();

	/**
	  * Construct a message using a code
	  * Will translate the code into a message
	  * @param int $code
	  * @param string $internalMessage
	  * @param Exception $previous
	  */
	public function __construct ($code, $internalMessage = '', $previous = NULL)
	{
		parent::__construct($this->getConstant('MESSAGE_'.$code), $code, $previous);
		$this->internalMessage = $internalMessage;
	}

	/**
	  * Returns the internal message, used for debugging, may contain sensitive information
	  * DO NOT SHOW END USERS
	  * @return string
	  */
	public function getInternalMessage()
	{
		return $this->internalMessage;
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
