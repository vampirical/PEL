<?php

namespace PEL\Storage;

class S3 extends Provider
{
	protected $bucketExists = array();

	protected $s3;
	protected $bucket;

	public function __construct($accessKey, $secretKey, $bucket) {
		if (!function_exists('curl_version')) {
			throw new Exception('The curl extension is required for PEL\Storage\S3.');
		}

		require_once \PEL::getLibPath() .'s3-php5-curl/S3.php';

		$this->s3 = new \S3($accessKey, $secretKey);
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
		return ($info !== false);
	}

	public function put($key, $value) {
		$this->ensureBucketExists($this->bucket);
		$putResult = $this->s3->putObject($value, $this->bucket, $key, \S3::ACL_PRIVATE);
		return $putResult;
	}

	public function putFile($key, $file) {
		$this->ensureBucketExists($this->bucket);
		$putResult = $this->s3->putObject($this->s3->inputFile($file), $this->bucket, $key, \S3::ACL_PRIVATE);
		return $putResult;
	}

	public function get($key) {
		$object = @$this->s3->getObject($this->bucket, $key);
		return (isset($object->body)) ? $object->body : null;
	}

	public function getInfo($key) {
		$info = @$this->s3->getObjectInfo($this->bucket, $key);
		return $info;
	}

	public function delete($key) {
		$deleteResult = $this->s3->deleteObject($this->bucket, $key);
		return $deleteResult;
	}
}

?>
