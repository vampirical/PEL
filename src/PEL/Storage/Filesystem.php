<?php

namespace PEL\Storage;

class Filesystem extends Provider
{
	protected $baseDir;

	protected static function ensurePathExists($path) {
		$dirName = pathinfo($path, PATHINFO_DIRNAME);
		if (!is_dir($dirName . DIRECTORY_SEPARATOR)) {
			exec('mkdir --mode=0777 --parents '. escapeshellarg($dirName) .' 2>&1', $mkdirOutput, $mkdirReturnValue);
			if ($mkdirReturnValue !== 0) {
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

	public function set($key, $value) {
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

	public function setStream($key, $stream) {
		$path = $this->getKeyPath($key);
		$pathExists = self::ensurePathExists($path);
		$pathHandle = fopen($path, 'wb');

		$streamPosition = ftell($stream);
		$stats = fstat($stream);
		$size = $stats['size'];

		$bytesWritten = 0;
		while (!feof($stream)) {
			$bytesWritten += fwrite($pathHandle, fread($stream, 8196));
		}
		fclose($pathHandle);

		$fullyWritten = ($bytesWritten === $size);
		if (!$fullyWritten) {
			if (!$this->delete($path)) {
				\PEL::log('Failed to write all bytes to disk and subsequent delete was unsuccessful: '. $path, \PEL::LOG_ERROR);
			}
		}

		fseek($stream, $streamPosition);

		return $fullyWritten;
	}

	public function setFile($key, $file) {
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

	public function getStream($key, $stream = null) {
		$path = $this->getKeyPath($key);

		if (is_file($path)) {
			$handle = fopen($path, 'rb');

			if ($stream) {
				while (!feof($handle)) {
					fwrite($stream, fread($handle, 8196));
				}
				fclose($handle);

				return $stream;
			}

			return $handle;
		}

		return null;
	}

	public function getInfo($key) {
		$path = $this->getKeyPath($key);

		$info = null;
		if (file_exists($path)) {
			$info = array(
				'time' => @filemtime($path),
				'hash' => @md5_file($path),
				'type' => @finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path),
				'size' => @filesize($path)
			);
		}

		return $info;
	}

	public function delete($key) {
		$keyPath = $this->getKeyPath($key);
		if (is_link($keyPath)) {
			$keyPath = readlink($keyPath);
		}

		if (is_file($keyPath)) {
			return unlink($keyPath);
		} else if (is_dir($keyPath)) {
			$fsIter = new \FilesystemIterator($keyPath);
			$emptyDir = !$fsIter->valid();
			if ($emptyDir) {
				return rmdir($keyPath);
			} else {
				\PEL::log('Unable to delete('. $key .') as it is a non-empty directory.', \PEL::LOG_ERROR);
			}
		}

		return false;
	}
}

?>
