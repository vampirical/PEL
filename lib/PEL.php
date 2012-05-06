<?php

require_once __DIR__ .'/PEL/Uuid.php';
require_once __DIR__ .'/PEL/Util.php';
require_once __DIR__ .'/PEL/SuperGlobalWrapper.php';
require_once __DIR__ .'/PEL/Cookie.php';
require_once __DIR__ .'/PEL/Get.php';
require_once __DIR__ .'/PEL/Post.php';
require_once __DIR__ .'/PEL/Request.php';
require_once __DIR__ .'/PEL/Server.php';
require_once __DIR__ .'/PEL/Session.php';
require_once __DIR__ .'/PEL/HttpRequest.php';

/**
 * PHP Extended Library
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
			self::$cookie = new PEL\Cookie();
		}
		return ($key === null) ? self::$cookie : self::$cookie->$key;
	}

	public static function get($key = null)
	{
		if (!isset(self::$get)) {
			self::$get = new PEL\Get();
		}
		return ($key === null) ? self::$get : self::$get->$key;
	}
	
	public static function post($key = null)
	{
		if (!isset(self::$post)) {
			self::$post = new PEL\Post();
		}
		return ($key === null) ? self::$post : self::$post->$key;
	}
	
	public static function request($key = null)
	{
		if (!isset(self::$request)) {
			self::$request = new PEL\Request();
		}
		return ($key === null) ? self::$request : self::$request->$key;
	}
	
	public static function server($key = null)
	{
		if (!isset(self::$server)) {
			self::$server = new PEL\Server();
		}
		return ($key === null) ? self::$server : self::$server->$key;
	}
	
	public static function session($key = null)
	{
		if (!isset(self::$session)) {
			self::$session = new PEL\Session();
		}
		return ($key === null) ? self::$session : self::$session->$key;
	}
}

?>
