<?php

namespace PEL;

/**
 * PEL Data
 *
 * @package PEL
 */


/**
 * PEL Data
 *
 * @package PEL
 */
class Data implements \Iterator, \Countable
{
	/**
	 * data reference
	 *
	 * @var array
	 */
	protected $data = array();

	/**
	 * set up the object
	 *
	 * @param array $data
	 */
	public function __construct( array $input = NULL )
	{
		$this->load( $input );
	}
	
  public function export()
  {
      return $this->data;
  }

  public function load( $input )
  {
      if( $input === NULL ) return;
    
      if( is_array( $input ) || $input instanceof \Iterator ) {
          foreach( $input as $k=>$v ) $this->__set( $k, $v);
      }
      // all done.
  }
    
	/**
	 * Strip slashes from data
	 */
	public function stripSlashes()
	{
		$this->data = Util::stripSlashes($this->data);
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
		return next($this->data);
	}

	/**
	 * @see Iterator::rewind()
	 */
	public function rewind()
	{
		reset($this->data);
	}

	/**
	 * @see Iterator::valid()
	 */
	public function valid()
	{
		return ($this->key() !== NULL);
	}

	/**
	 * @see Countable::count()
	 */
	public function count()
	{
		return count($this->data);
	}
	
	/**
	 * see http://php.net/each
	 */
	public function each(){
		$key = $this->key();
		if( $key === NULL ) return FALSE;
		$this->next();
		return array( $key, $this->get($key) );
	}

 
	/**
	 * @see http://www.php.net/manual/en/function.array-keys.php
	 **/
	public function keys(){
		$args = func_get_args();
		if( count($args) < 1 ) return array_keys( $this->data);
		$search = array_shift( $args );
		$strict = array_shift( $args );
		return array_keys( $this->data, $search, $strict);
	}

	/**
	 * @see http://www.php.net/manual/en/function.array-push.php
	 **/
	public function push($v){
		return array_push($this->data, $v );
	}

	/**
	 * @see http://www.php.net/manual/en/function.array-pop.php
	 **/
	public function pop(){
		return array_pop($this->data);
	}

	/**
	 * @see http://www.php.net/manual/en/function.array-shift.php
	 **/
	public function shift(){
		return array_shift($this->data);
	}

	/**
	 * @see http://www.php.net/manual/en/function.array-unshift.php
	 **/
	public function unshift($v){
		return array_unshift( $this->data, $v );
	}

	/**
	 * @see http://www.php.net/manual/en/function.asort.php
	 **/
	public function sort($sort_flags = NULL){
		return asort($this->data, $sort_flags );
	}

	/**
	 * @see http://www.php.net/manual/en/function.arsort.php
	 **/
	public function rsort($sort_flags = NULL){
		return arsort($this->data, $sort_flags );
	}

	/**
	 * @see http://www.php.net/manual/en/function.ksort.php
	 **/
	public function ksort($sort_flags = NULL){
		return ksort($this->data, $sort_flags);
	}

	/**
	 * @see http://www.php.net/manual/en/function.ksort.php
	 **/
	public function krsort($sort_flags = NULL){
		return krsort($this->data, $sort_flags);
	}
}

?>