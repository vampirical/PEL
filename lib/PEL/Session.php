<?php

namespace PEL;

/**
 * PEL Session
 *
 * @package PEL
 */


/**
 * PEL Session
 *
 * @package PEL
 */
class Session extends SuperGlobalWrapper
{
	protected $started = false;

	public function start()
	{
		$sid = session_id();
		if (empty($sid)) {
			session_start();
		}
		$this->link($_SESSION);
		$this->started = true;
	}

	public function ensureStarted()
	{
		if (!$this->started) {
			$this->start();
		}
	}

	public function regenerateId() {
		return session_regenerate_id(true);
	}

	public function destroy()
	{
		$_SESSION = array();
		if(isset($_COOKIE[$this->name()])) {
			setcookie($this->name(), '', (time() - 42000), '/');
		}
		session_destroy();
		$this->started = false;
	}

	public function reset()
	{
		$this->destroy();
		$this->ensureStarted();
	}

	public function write()
	{
		$sessionId = $this->id();
		session_write_close();
		session_id($sessionId);
		session_start();
	}

	public function writeClose()
	{
		session_write_close();
	}

	public function id()
	{
		return session_id();
	}

	public function name()
	{
		return session_name();
	}

	public function __get($variable)
	{
		$this->ensureStarted();
		return parent::__get($variable);
	}

	public function __set($variable, $value)
	{
		$this->ensureStarted();
		return parent::__set($variable, $value);
	}

	public function __isset($variable)
	{
		$this->ensureStarted();
		return parent::__isset($variable);
	}

	public function __unset($variable)
	{
		$this->ensureStarted();
		return parent::__unset($variable);
	}

	public function getArray()
	{
		return $this->data;
	}
}

?>
