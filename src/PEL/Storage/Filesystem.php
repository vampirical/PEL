<?php

namespace PEL\Storage;

class Filesystem extends Provider
{
	protected $baseDir;

	protected static function ensurePathExists($path) {
		$dirName = pathinfo($path, PATHINFO_DIRNAME);
		if (!is_dir($dirName . DIRECTORY_SEPARATOR)) {
			if (!@mkdir($dirName, 0777, true)) {
				return false;
			}
		}
		return true;
	}

	public function __construct($baseDir) {
		$this->baseDir = \PEL\String::slashify($baseDir);
	}

	public function getKeyPath($key) {
		return $this->baseDir . $key;
	}

	public function exists($key) {
		$path = $this->getKeyPath($key);
		return file_exists($path);
	}

	public function put($key, $value) {
		$path = $this->getKeyPath($key);
		$pathExists = self::ensurePathExists($path);
		$filePutResult = @file_put_contents($path, $value);
		if ($filePutResult === false) {
			if (!$pathExists) {
				throw new Exception('Unable to create dir ('. $path .').');
			}
			return $filePutResult;
		}
		$fullyWritten = ($filePutResult == mb_strlen($value, '8bit'));
		if (!$fullyWritten) {
			if (!$this->delete($path)) {
				\PEL::log('Failed to write all bytes to disk and subsequent delete was unsuccessful: '. $path, \PEL::LOG_ERROR);
			}
		}
		return $fullyWritten;
	}

	public function putFile($key, $file) {
		$path = $this->getKeyPath($key);
		$pathExists = self::ensurePathExists($path);
		$result = copy($file, $path);
		if (!$result && !$pathExists) {
			throw new Exception('Unable to create dir ('. $path .').');
		}
		return $result;
	}

	public function get($key) {
		$path = $this->getKeyPath($key);
		return (is_file($path)) ? file_get_contents($path) : null;
	}

	public function getStream($key) {
		$path = $this->getKeyPath($key);
		return (is_file($path)) ? fopen($path, 'rb') : null;
	}

	public function getInfo($key) {
		$path = $this->getKeyPath($key);
		return array(
			'time' => @filemtime($path),
			'hash' => @md5_file($path),
			'type' => @mime_content_type($path),
			'size' => @filesize($path)
		);
	}

	public function delete($key) {
		return unlink($this->getKeyPath($key));
	}
}

?>
