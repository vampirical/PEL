<?php

namespace PEL\Storage;

class S3 extends Provider
{
	/**
	 * Enable debug behavior and logging.
	 *
	 * @var bool
	 */
	protected $debug = false;

	protected $bucketExists = array();

	protected $s3;
	protected $bucket;

	public function __construct($accessKey, $secretKey, $bucket, $useSsl = false) {
		if (!function_exists('curl_version')) {
			throw new Exception('The curl extension is required for PEL\Storage\S3.');
		}

		require_once \PEL::getLibPath() .'s3-php5-curl/S3.php';

		$this->s3 = new \S3($accessKey, $secretKey, $useSsl);
		$this->bucket = $bucket;
	}

	protected function ensureBucketExists($bucket) {
		if (!isset($this->bucketExists[$bucket])) {
			// Allow a few tries to work around bucket creation race conditions.
			$try = 1;
			$maxTries = 3;
			while ($try <= $maxTries) {
				$bucketList = $this->s3->listBuckets();
				if (in_array($bucket, $bucketList)) {
					$this->bucketExists[$bucket] = true;
					break;
				} else {
					$result = @$this->s3->putBucket($bucket, \S3::ACL_PRIVATE);
					if ($result) {
						$this->bucketExists[$bucket] = true;
						break;
					} else if ($try == ($maxTries - 1)) {
						throw new Exception('Unable to create S3 bucket: '. $bucket);
					}
				}
				++$try;
			}
		}
		return $this->bucketExists[$bucket];
	}

	public function exists($key) {
		$info = $this->getInfo($key);
		return !!$info;
	}

	public function set($key, $value) {
		$this->ensureBucketExists($this->bucket);

		$putInput = array(
			'data' => $value,
			'type' => finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $value),
			'size' => mb_strlen($value, '8bit')
		);
		$result = $this->s3->putObject($putInput, $this->bucket, $key, \S3::ACL_PRIVATE);

		return $result;
	}

	public function setStream($key, $stream) {
		$this->ensureBucketExists($this->bucket);

		$streamPosition = ftell($stream);
		$head = fread($stream, 96);
		fseek($stream, $streamPosition);
		$mediaType = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $head);

		$stats = fstat($stream);
		$size = $stats['size'];

		$putInput = array(
			'fp' => $stream,
			'type' => $mediaType,
			'size' => $size
		);
		$result = $this->s3->putObject($putInput, $this->bucket, $key, \S3::ACL_PRIVATE);

		fseek($stream, $streamPosition);

		return $result;
	}

	public function setFile($key, $file) {
		if ($this->debug) {
			$start = microtime(true);
		}

		$this->ensureBucketExists($this->bucket);
		if ($this->debug) {
			\PEL::log('S3 setFile ensure bucket exists time: '. (microtime(true) - $start), \PEL::LOG_DEBUG);
			$putStart = microtime(true);
		}

		$result = $this->s3->putObject($this->s3->inputFile($file), $this->bucket, $key, \S3::ACL_PRIVATE);
		if ($this->debug) {
			\PEL::log('S3 setFile putObject time: '. (microtime(true) - $putStart), \PEL::LOG_DEBUG);

			\PEL::log('S3 setFile total time: '. (microtime(true) - $start), \PEL::LOG_DEBUG);
		}

		return $result;
	}

	public function get($key) {
		$object = @$this->s3->getObject($this->bucket, $key);
		return (isset($object->body)) ? $object->body : null;
	}

	public function getStream($key, $stream = null) {
		if (!$stream) {
			$fiftyMiB = 50 * 1024 * 1024;
			$stream = fopen("php://temp/maxmemory:$fiftyMiB", 'r+b');
		}

		$result = @$this->s3->getObject($this->bucket, $key, $stream);
		rewind($stream);

		if ($result->error) {
			return null;
		}

		return $stream;
	}

	public function getInfo($key) {
		$info = @$this->s3->getObjectInfo($this->bucket, $key);
		return ($info) ? $info : null;
	}

	public function delete($key) {
		$deleteResult = $this->s3->deleteObject($this->bucket, $key);
		return $deleteResult;
	}
}

?>
