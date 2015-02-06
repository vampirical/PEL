<?php

namespace PEL\Tests;

use \PEL\Storage;
use \PEL\Storage\Filesystem;

require_once dirname(__DIR__) .'/src/PEL.php';

class StorageTest extends \PHPUnit_Framework_TestCase
{
	protected static $s;
	protected static $provider;
	protected static $tmpDir;

	protected static function initStorage() {
		self::$s = new Storage();

		self::$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR .'pel-storage-test-'. rand(0, PHP_INT_MAX);
		self::$provider = new Filesystem(self::$tmpDir);
		self::$s->addProvider(self::$provider);
	}

	public static function setUpBeforeClass() {
		self::initStorage();
	}

	public static function tearDownAfterClass() {
		self::initStorage(); // Re-init storage to clear blacklists

		self::$s->delete('setAndGet');
		self::$s->delete('setStreamAndGet');
		self::$s->delete('setAndGetStream');
		self::$s->delete('setStreamAndGetStream');
		self::$s->delete('tempFile');
		self::$s->delete('exists');
		self::$s->delete('delete');
		self::$s->delete('providerBlacklist/blacklist-both');
		self::$s->delete('providerBlacklist/blacklist-read');
		self::$s->delete('providerBlacklist/blacklist-write');

		if (!empty(self::$tmpDir)) {
			exec('rm -rf '. self::$tmpDir);
		}
	}

	public function testSetAndGet() {
		$testKey = 'setAndGet';
		$testValue = "testing\ntesting 1 2 3\n\n";

		$setResult = self::$s->set($testKey, $testValue);
		$this->assertTrue($setResult);

		$getResult = self::$s->get($testKey);
		$this->assertEquals($testValue, $getResult);
	}

	public function testSetStreamAndGet() {
		$testKey = 'setStreamAndGet';
		$testValue = "testing\ntesting 1 2 3\n\n";
		$testFile = tempnam(sys_get_temp_dir(), 'pel-test-storage-');

		file_put_contents($testFile, $testValue);

		$setResult = self::$s->setStream($testKey, fopen($testFile, 'rb'));
		$this->assertTrue($setResult);

		$getResult = self::$s->get($testKey);
		$this->assertEquals($testValue, $getResult);
	}

	public function testSetAndGetStream() {
		$testKey = 'setAndGetStream';
		$testValue = "testing\ntesting 1 2 3\n\n";
		$testFile = tempnam(sys_get_temp_dir(), 'pel-test-storage-');

		$setResult = self::$s->set($testKey, $testValue);
		$this->assertTrue($setResult);

		$getResult = self::$s->getStream($testKey);
		$this->assertEquals($testValue, stream_get_contents($getResult));
	}

	public function testSetStreamAndGetStream() {
		$testKey = 'setStreamAndGetStream';
		$testValue = "testing\ntesting 1 2 3\n\n";
		$testFile = tempnam(sys_get_temp_dir(), 'pel-test-storage-');

		file_put_contents($testFile, $testValue);

		$setResult = self::$s->setStream($testKey, fopen($testFile, 'rb'));
		$this->assertTrue($setResult);

		$getResult = self::$s->getStream($testKey);
		$this->assertEquals($testValue, stream_get_contents($getResult));
	}

	/**
	 * @depends testSetAndGet
	 */
	public function testGetAsTempFile() {
		$testKey = 'tempFile';
		$testValue = "testing\ntesting 1 2 3\n\n";

		$setResult = self::$s->set($testKey, $testValue);
		$this->assertTrue($setResult);

		$tempFile = self::$s->getAsTempFile($testKey);
		$tempFileContent = file_get_contents($tempFile);
		$this->assertEquals($testValue, $tempFileContent, 'Temp file content does not match test value.');
	}

	/**
	 * @depends testSetAndGet
	 */
	public function testExists() {
		$testKey = 'exists';
		$testValue = "testing\ntesting 1 2 3\n\n";

		$existsResult = self::$s->exists($testKey);
		$this->assertFalse($existsResult);

		$setResult = self::$s->set($testKey, $testValue);
		$this->assertTrue($setResult);

		$existsResult = self::$s->exists($testKey);
		$this->assertTrue($existsResult);
	}

	/**
	 * @depends testSetAndGet
	 * @depends testExists
	 */
	public function testDelete() {
		$testKey = 'delete';
		$testValue = "testing\ntesting 1 2 3\n\n";

		$setResult = self::$s->set($testKey, $testValue);
		$this->assertTrue($setResult);

		self::$s->delete($testKey);

		$existsResult = self::$s->exists($testKey);
		$this->assertFalse($existsResult);
	}

	/**
	 * @depends testSetAndGet
	 */
	public function testProviderBlacklist() {
		$testKey = 'providerBlacklist/blacklist-both';
		$testValue = "testing\ntesting 1 2 3\n\n";

		$setResult = self::$s->set($testKey, $testValue);
		$this->assertTrue($setResult, 'Pre-blacklist set failed.');

		$existsResult = self::$s->exists($testKey);
		$this->assertTrue($existsResult, 'Pre-blacklist exists failed.');

		// Lazy cheat. Check that double writes work so we can test read and writes
		// post-blacklist at the same time rather than doing the many small test
		// cases that are really called for here.
		$setResult = self::$s->set($testKey, $testValue);
		$this->assertTrue($setResult, 'Pre-blacklist over-set failed.');

		self::$provider->blacklist('/.*blacklist-both/');

		$setResult = self::$s->set($testKey, $testValue);
		$this->assertFalse($setResult, 'Blacklisted set succeeded.');

		$getResult = self::$s->get($testKey);
		$this->assertEquals(null, $getResult, 'Blacklisted get succeeded.');

		$existsResult = self::$s->exists($testKey);
		$this->assertFalse($existsResult, 'Blacklisted exists succeeded.');
	}

	/**
	 * @depends testSetAndGet
	 */
	public function testProviderReadBlacklist() {
		$testKey = 'providerBlacklist/blacklist-read';
		$testValue = "testing\ntesting 1 2 3\n\n";

		$setResult = self::$s->set($testKey, $testValue);
		$this->assertTrue($setResult, 'Pre-blacklist set failed.');

		$existsResult = self::$s->exists($testKey);
		$this->assertTrue($existsResult, 'Pre-blacklist exists failed.');

		self::$provider->readBlacklist('/.*blacklist-read/');

		$setResult = self::$s->set($testKey, $testValue);
		$this->assertTrue($setResult, 'Non-blacklisted set failed.');

		$getResult = self::$s->get($testKey);
		$this->assertEquals(null, $getResult, 'Blacklisted read succeeded instead of failing.');

		$existsResult = self::$s->exists($testKey);
		$this->assertFalse($existsResult, 'Blacklisted exists succeeded instead of failing.');
	}

	/**
	 * @depends testSetAndGet
	 */
	public function testProviderWriteBlacklist() {
		$testKey = 'providerBlacklist/blacklist-write';
		$testValue = "testing\ntesting 1 2 3\n\n";

		$setResult = self::$s->set($testKey, $testValue);
		$this->assertTrue($setResult, 'Pre-blacklist set failed.');

		$existsResult = self::$s->exists($testKey);
		$this->assertTrue($existsResult, 'Pre-blacklist exists failed.');

		self::$provider->writeBlacklist('/.*blacklist-write/');

		$setResult = self::$s->set($testKey, $testValue);
		$this->assertFalse($setResult, 'Blacklisted set succeeded.');

		$getResult = self::$s->get($testKey);
		$this->assertEquals($testValue, $getResult, 'Non-blacklisted read failed.');

		$existsResult = self::$s->exists($testKey);
		$this->assertTrue($existsResult, 'Non-blacklisted exists failed.');
	}
}

?>
