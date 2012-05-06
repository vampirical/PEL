<?php

namespace PEL;

/**
 * PEL Super Global Wrapper
 *
 * @package PEL
 */


/**
 * PEL Super Global Wrapper
 *
 * @package PEL
 */
class SuperGlobalWrapper implements \Iterator, \Countable
{
	/**
	 * Super global reference
	 *
	 * @var array
	 */
	protected $data = array();

	/**
	 * Iterator valid
	 *
	 * @var boolean
	 */
	protected $iteratorValid = false;

	/**
	 * Link to super global
	 *
	 * @param array $superGlobal
	 */
	public function link(&$superGlobal)
	{
		$this->data =& $superGlobal;
	}

	/**
	 * Strip slashes from data
	 */
	public function stripSlashes()
	{
		$this->data = DE::stripSlashes($this->data);
	}

	/**
	 * Magic get
	 *
	 * @param mixed $variable
	 * @return mixed
	 */
	public function __get($variable)
	{
		return (isset($this->data[$variable])) ? $this->data[$variable] : null;
	}

	/**
	 * Magic set
	 *
	 * @param mixed $variable
	 * @param mixed $value
	 * @return boolean
	 */
	public function __set($variable, $value)
	{
		$this->data[$variable] = $value;

		return true;
	}

	/**
	 * Isset
	 *
	 * @param mixed $variable
	 * @return boolean
	 */
	public function __isset($variable)
	{
		return isset($this->data[$variable]);
	}

	/**
	 * Unset
	 *
	 * @param mixed $variable
	 */
	public function __unset($variable)
	{
		unset($this->data[$variable]);
	}

	/**
	 * @see Iterator::current()
	 */
	public function current()
	{
		return current($this->data);
	}

	/**
	 * @see Iterator::key()
	 */
	public function key()
	{
		return key($this->data);
	}

	/**
	 * @see Iterator::next()
	 */
	public function next()
	{
		$this->iteratorValid = (next($this->data) !== false);
	}

	/**
	 * @see Iterator::rewind()
	 */
	public function rewind()
	{
		$this->iteratorValid = (reset($this->data) !== false);
	}

	/**
	 * @see Iterator::valid()
	 */
	public function valid()
	{
		return $this->iteratorValid;
	}

	/**
	 * @see Countable::count()
	 */
	public function count()
	{
		return count($this->data);
	}
}

?>
