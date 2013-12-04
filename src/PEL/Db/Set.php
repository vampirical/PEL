<?php

namespace PEL\Db;

/**
 * PEL Db Set
 *
 * @package PEL
 */


/**
 * PEL Db Set
 *
 * @package PEL
 */
class Set implements \Iterator, \ArrayAccess
{
	protected $items;
	protected $position;

	public function __construct(Iterator $iter)
	{
		$this->items = iterator_to_array($iter);
	}

	// Iterator

	public function rewind()
	{
		$this->position = 0;
	}

	public function current()
	{
		return $this->items[$this->position];
	}

	public function key()
	{
		return $this->position;
	}

	public function next()
	{
		++$this->position;
	}

	public function valid()
	{
		return isset($this->items[$this->position]);
	}

	// ArrayAccess

	public function offsetSet($offset, $value)
	{
		if (is_null($offset)) {
			$this->items[] = $value;
		} else {
			$this->items[$offset] = $value;
		}
	}

	public function offsetExists($offset)
	{
		return isset($this->items[$offset]);
	}

	public function offsetUnset($offset)
	{
		unset($this->items[$offset]);
	}

	public function offsetGet($offset)
	{
		return isset($this->items[$offset]) ? $this->items[$offset] : null;
	}
}

?>
