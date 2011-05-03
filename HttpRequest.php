<?php

namespace PEL;

/**
 * PEL HTTP Request
 *
 * @package PEL
 */


/**
 * PEL HTTP Request
 *
 * @package PEL
 */
class HttpRequest
{
	public $url;
	public $method;

	public $protocol;
	public $host;
	public $path;

	public $dir;
	public $file;
	public $extension;

	public $dirParts;

	public $get;
	public $post;
	public $request;

	public $time;
	public $userAgent;
	public $accept;
	public $acceptLanguage;
	public $acceptEncoding;
	public $acceptCharset;

	protected $body;

	public function __construct($requestUri = null)
	{
		$this->method = strtolower($_SERVER['REQUEST_METHOD']);
		// Protocol may be overriden by url later
		$this->protocol = ($this->ssl()) ? 'https' : 'http';

		$this->host = $_SERVER['HTTP_HOST'];
		$requestUri = ($requestUri === null) ? $_SERVER['REQUEST_URI'] : $requestUri;
		if (preg_match('/^(?:https?:\/\/)?'. $this->host .'/', $requestUri)) { // Full url in REQUEST_URI
			if (preg_match('/^(https?):\/\//', $requestUri, $matches)) {
				$this->protocol = $matches[1];
				$this->url = $requestUri;
			} else {
				$this->url = $this->protocol .'://'. $requestUri;
			}
			$this->path = parse_url(substr($requestUri, strpos($requestUri, $this->host) + strlen($this->host)), PHP_URL_PATH);
		} else { // Path only in REQUEST_URI
			$this->path = parse_url($requestUri, PHP_URL_PATH);
			if (strpos($this->path, '/') !== 0) {
				$this->path = '/'. $this->path;
			}
			$this->url = $this->protocol .'://'. $this->host . $this->path;
		}

		if (!empty($this->path) && strlen($this->path) > 1) {
			$pathString = $this->path;
			$matchCount = preg_match('/.*\.(\w*)$/', $pathString, $matches);
			if ($matchCount) {
				$this->extension = $matches[1];
				$pathString = substr($pathString, 0, -(strlen($this->extension) + 1));
			}
			$lastSlashOffset = strrpos($pathString, '/') + 1;
			$this->dir = substr($pathString, 0, $lastSlashOffset);
			$this->file = substr($pathString, $lastSlashOffset);

			$this->dirParts = explode('/', trim($this->dir, '/'));
		}

		$this->get = new Get();
		$this->post = new Post();
		$this->request = new Request();

		if (isset($_SERVER['REQUEST_TIME'])) $this->time = $_SERVER['REQUEST_TIME'];
		if (isset($_SERVER['HTTP_USER_AGENT'])) $this->userAgent = $_SERVER['HTTP_USER_AGENT'];
		if (isset($_SERVER['HTTP_ACCEPT'])) $this->accept = $_SERVER['HTTP_ACCEPT'];
		if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) $this->acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
		if (isset($_SERVER['HTTP_ACCEPT_ENCODING'])) $this->acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'];
		if (isset($_SERVER['HTTP_ACCEPT_CHARSET'])) $this->acceptCharset = $_SERVER['HTTP_ACCEPT_CHARSET'];
	}

	public function ssl()
	{
		return (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on');
	}

	public function getBody()
	{
		if (!$this->body) {
			$this->body = file_get_contents('php://input');
		}

		return $this->body;
	}

	public function method()
	{
		$method = $this->method;

		if ($method == 'post') {
			if (isset($this->request->put)) {
				$method = 'put';
			} else if (isset($this->request->delete)) {
				$method = 'delete';
			} else if (isset($this->request->_method)) {
				$method = strtolower($this->request->_method);
			} else if (isset($_SERVER['X-HTTP-Method-Override'])) {
				$method = strtolower($_SERVER['X-HTTP-Method-Override']);
			}
		}

		return $method;
	}
}

?>
