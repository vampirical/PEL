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

	public static function setUpBeforeClass() {
		self::$s = new Storage();

		self::$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR .'pel-storage-test-'. rand(0, PHP_INT_MAX);
		self::$provider = new Filesystem(self::$tmpDir);
		self::$s->addProvider(self::$provider);
	}

	public static function tearDownAfterClass() {
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
		$this->assertEquals($getResult, $testValue);
	}

	/**
	 * @depends testSetAndGet
	 */
	public function testGetTempFile() {
		$testKey = 'tempFile';
		$testValue = "testing\ntesting 1 2 3\n\n";

		$setResult = self::$s->set($testKey, $testValue);
		$this->assertTrue($setResult);

		$tempFile = self::$s->getTempFile($testKey);
		$tempFileContent = file_get_contents($tempFile);
		$this->assertEquals($tempFileContent, $testValue, 'Temp file content does not match test value.');

		usleep(5000000);
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
		$this->assertEquals($getResult, null, 'Blacklisted get succeeded.');

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
		$this->assertEquals($getResult, null, 'Blacklisted read succeeded instead of failing.');

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
		$this->assertEquals($getResult, $testValue, 'Non-blacklisted read failed.');

		$existsResult = self::$s->exists($testKey);
		$this->assertTrue($existsResult, 'Non-blacklisted exists failed.');
	}
}

?>
