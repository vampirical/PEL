<?php

/**
 * PEL (PHP Extended Library)
 *
 * @package PEL
 */


spl_autoload_register(function($className) {
  if (strpos($className, 'PEL\\') === 0) {
    require __DIR__ .'/'. str_replace('\\', '/', $className) . '.php';
  }
});

/**
 * PEL (PHP Extended Library)
 *
 * @package PEL
 */
class PEL
{
	protected static $cookie;
	protected static $get;
	protected static $post;
	protected static $request;
	protected static $server;
	protected static $session;

	public static function cookie($key = null)
	{
		if (!isset(self::$cookie)) {
			self::$cookie = new PEL\SuperGlobal\Cookie();
		}
		return ($key === null) ? self::$cookie : self::$cookie->$key;
	}

	public static function get($key = null)
	{
		if (!isset(self::$get)) {
			self::$get = new PEL\SuperGlobal\Get();
		}
		return ($key === null) ? self::$get : self::$get->$key;
	}

	public static function post($key = null)
	{
		if (!isset(self::$post)) {
			self::$post = new PEL\SuperGlobal\Post();
		}
		return ($key === null) ? self::$post : self::$post->$key;
	}

	public static function request($key = null)
	{
		if (!isset(self::$request)) {
			self::$request = new PEL\SuperGlobal\Request();
		}
		return ($key === null) ? self::$request : self::$request->$key;
	}

	public static function server($key = null)
	{
		if (!isset(self::$server)) {
			self::$server = new PEL\SuperGlobal\Server();
		}
		return ($key === null) ? self::$server : self::$server->$key;
	}

	public static function session($key = null)
	{
		if (!isset(self::$session)) {
			self::$session = new PEL\SuperGlobal\Session();
		}
		return ($key === null) ? self::$session : self::$session->$key;
	}
}

?>
