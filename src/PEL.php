<?php

/**
 * PEL (PHP Extended Library)
 *
 * @package PEL
 */


spl_autoload_register(function ($className) {
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
	const LOG_DEBUG = 'debug';
	const LOG_INFO = 'info';
	const LOG_WARN = 'warn';
	const LOG_ERROR = 'error';
	const LOG_ALERT = 'alert';

	protected static $cookie;
	protected static $get;
	protected static $post;
	protected static $request;
	protected static $server;
	protected static $session;

	public static function onWindows() {
		return (strtolower(substr(PHP_OS, 0, 3)) === 'win');
	}

	public static function log($msg, $level = null) {
		if ($msg instanceof Exception) {
			$traceString = $msg->getTraceAsString();
			$msg = $msg->getMessage() .' in '. $msg->getFile() .':'. $msg->getLine();
			if ($level == self::LOG_WARN || $level == self::LOG_ERROR || $level == self::LOG_ALERT) {
				$msg .= "\n". $traceString;
			}
		} else if (is_callable(array($msg, '__toString'))) {
			$msg = $msg->__toString();
		}

		switch ($level) {
			default:
			case self::LOG_DEBUG:
			case self::LOG_INFO:
				$syslogPriority = LOG_INFO;
				break;
			case self::LOG_WARN:
				$syslogPriority = LOG_WARNING;
			case self::LOG_ERROR:
				$syslogPriority = LOG_ERR;
			case self::LOG_ALERT:
				$syslogPriority = LOG_ALERT;
				break;
		}

		syslog($syslogPriority, $msg);
		if (self::onWindows()) {
			// Since the windows event system is terrible
			file_put_contents(sys_get_temp_dir() .'/PEL.log', $msg . PHP_EOL, FILE_APPEND);
		}
	}

	public static function getLibPath() {
		return dirname(__DIR__) .'/lib/';
	}

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
