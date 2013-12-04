<?php

namespace PEL\Http;

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
class Request
{
	public $get;
	public $post;
	public $request;
	public $server;

	public $url;

	public $method;
	public $protocol;
	public $host;
	public $path;

	public $dir;
	public $dirParts;
	public $file;
	public $extension;

	public $time;
	public $userAgent;
	public $accept;
	public $acceptLanguage;
	public $acceptEncoding;
	public $acceptCharset;
	public $contentType;

	protected $body;

	public function __construct($requestUri = null)
	{
		if (!$requestUri) {
			$requestUri = $this->server->REQUEST_URI;
		}

		$this->get = \PEL::get();
		$this->post = \PEL::post();
		$this->request = \PEL::request();
		$this->server = \PEL::server();

		$this->method = strtolower($this->server->REQUEST_METHOD);
		$this->protocol = ($this->ssl()) ? 'https' : 'http'; // Protocol may be overriden by url later
		$this->host = ($this->server->HTTP_HOST) ? $this->server->HTTP_HOST : $this->server->SERVER_NAME;

		if (preg_match('/^(?:https?:\/\/)?'. $this->host .'/', $requestUri)) { // Full url, with or without protocol, in $requestUri
			if (preg_match('/^(https?):\/\//', $requestUri, $matches)) {
				$this->protocol = $matches[1];
				$this->url = $requestUri;
			} else {
				$this->url = $this->protocol .'://'. $requestUri;
			}
			$this->path = parse_url(substr($requestUri, strpos($requestUri, $this->host) + strlen($this->host)), PHP_URL_PATH);
		} else { // Path only in $requestUri
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
			$this->dirParts = explode('/', trim($this->dir, '/'));
			$this->file = substr($pathString, $lastSlashOffset);
		}

		$this->time = $this->server->REQUEST_TIME;
		$this->userAgent = $this->server->HTTP_USER_AGENT;
		$this->accept = $this->server->HTTP_ACCEPT;
		$this->acceptLanguage = $this->server->HTTP_ACCEPT_LANGUAGE;
		$this->acceptEncoding = $this->server->HTTP_ACCEPT_ENCODING;
		$this->acceptCharset = $this->server->HTTP_ACCEPT_CHARSET;
		$this->contentType = $this->server->HTTP_CONTENT_TYPE;
	}

	public function isSecure()
	{
		return (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on');
	}

	public function getMethod()
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

	public function getBody()
	{
		if (!$this->body) {
			$this->body = file_get_contents('php://input');
		}

		return $this->body;
	}
}

?>
