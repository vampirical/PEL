<?php

namespace PEL\Storage;

abstract class Provider
{
	protected $blacklist = array();

	/**
	 * Add a blacklisted regex.
	 *
	 * @param	string $regex
	 *
	 * @return void
	 */
	public function blacklist($regex) {
		$this->blacklist[] = $regex;
	}

	/**
	 * Check whether a key is valid for this provider.
	 *
	 * @param	string $key
	 *
	 * @return bool
	 */
	public function allowed($key) {
		$blacklisted = false;
		foreach ($this->blacklist as $blacklistRegex) {
			if (preg_match($blacklistRegex, $key)) {
				$blacklisted = true;
				break;
			}
		}
		return !$blacklisted;
	}

	/**
	 * Whether a key exists
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	abstract public function exists($key);

	/**
	 * Store a value
	 *
	 * @param string $key
	 * @param string  $value
	 *
	 * @return int|bool|null On success, can return the number of bytes written or simple bool true. On failure, can return bool false or null.
	 */
	abstract public function put($key, $value);

	/**
	 * Store a value from a stream
	 *
	 * @todo Maybe.
	 *
	 * @param string       $key
	 * @param resource $stream
	 *
	 * @return int|bool|null On success, can return the number of bytes written or simple bool true. On failure, can return bool false or null.
	 */
	//abstract public function putStream($key, $stream);

	/**
	 * Store a value from a file
	 *
	 * @param string $key
	 * @param string  $file   Path to file.
	 *
	 * @return int|bool|null On success, can return the number of bytes written or simple bool true. On failure, can return bool false or null.
	 */
	abstract public function putFile($key, $file);

	/**
	 * Get a value by key
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	abstract public function get($key);

	/**
	 * Get a stream by key
	 *
	 * @param string $key
	 *
	 * @return resource
	 */
	public function getStream($key) {
		$response = $this->get($key);
		if ($response) {
			$resource = fopen('php://temp', 'r+b');
			fwrite($resource, $response);
			return $resource;
		} else {
			return $response;
		}
	}

	/**
	 * Get meta data about a key
	 *
	 * @param string $key
	 *
	 * @return array array('time' => int|null, 'hash' => string|null, 'type' => string|null, 'size' => int|null)
	 */
	abstract public function getInfo($key);

	/**
	 * Delete a key
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	abstract public function delete($key);
}

?>
