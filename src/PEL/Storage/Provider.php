<?php

namespace PEL\Storage;

abstract class Provider
{
	protected $blacklist = array();
	protected $readBlacklist = array();
	protected $writeBlacklist = array();

	/**
	 * Add a blacklisted regex, affects both read and writes.
	 *
	 * @param	string $regex
	 *
	 * @return void
	 */
	public function blacklist($regex) {
		$this->blacklist[] = $regex;
	}

	/**
	 * Add a read only blacklisted regex.
	 *
	 * @param	string $regex
	 *
	 * @return void
	 */
	public function readBlacklist($regex) {
		$this->readBlacklist[] = $regex;
	}

	/**
	 * Add a write only blacklisted regex.
	 *
	 * @param	string $regex
	 *
	 * @return void
	 */
	public function writeBlacklist($regex) {
		$this->writeBlacklist[] = $regex;
	}

	/**
	 * Check whether a key is valid for this provider.
	 *
	 * @param	string $key
	 * @param string $type \PEL\Storage::ACCESS_READ or \PEL\Storage::ACCESS_WRITE, defaults to ACCESS_WRITE
	 *
	 * @return bool
	 */
	public function allowed($key, $type = \PEL\Storage::ACCESS_WRITE) {
		$blacklisted = false;

		foreach ($this->blacklist as $blacklistRegex) {
			if (preg_match($blacklistRegex, $key)) {
				$blacklisted = true;
				break;
			}
		}

		if ($type === \PEL\Storage::ACCESS_READ) {
			foreach ($this->readBlacklist as $blacklistRegex) {
				if (preg_match($blacklistRegex, $key)) {
					$blacklisted = true;
					break;
				}
			}
		} else if ($type === \PEL\Storage::ACCESS_WRITE) {
			foreach ($this->writeBlacklist as $blacklistRegex) {
				if (preg_match($blacklistRegex, $key)) {
					$blacklisted = true;
					break;
				}
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
	abstract public function set($key, $value);

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
	//abstract public function setStream($key, $stream);

	/**
	 * Store a value from a file
	 *
	 * @param string $key
	 * @param string  $file   Path to file.
	 *
	 * @return int|bool|null On success, can return the number of bytes written or simple bool true. On failure, can return bool false or null.
	 */
	abstract public function setFile($key, $file);

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
