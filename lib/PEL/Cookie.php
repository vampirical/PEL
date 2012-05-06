<?php

namespace PEL;

/**
 * PEL Cookie
 *
 * @package PEL
 */


/**
 * PEL Cookie
 *
 * @package PEL
 */
class Cookie extends SuperGlobalWrapper
{
	public function __construct()
	{
		$this->link($_COOKIE);

		if (get_magic_quotes_gpc()) {
			$this->stripSlashes();
		}
	}

	/**
	 * Magic set
	 *
	 * @param string $property
	 * @param mixed $value
	 * @return boolean
	 */
	public function __set($property, $value)
	{
		return $this->set($property, $value);
	}

	/**
	 * Set cookie value
	 *
	 * @param string $name
	 * @param string $value
	 * @param integer $expires
	 * @param string $path
	 * @param string $domain
	 * @param boolean $secure
	 * @param boolean $httpOnly
	 * @return boolean
	 */
	public function set($name, $value = null, $expires = null, $path = null, $domain = null, $secure = null, $httpOnly = null)
	{
		parent::__set($name, $value);

		return setcookie($name, $value, $expires, $path, $domain, $secure, $httpOnly);
	}
}

?>
