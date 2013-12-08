<?php

namespace PEL\Tests\Db;

use \PEL\Db;

require_once dirname(dirname(__DIR__)) .'/src/PEL.php';
require_once __DIR__ .'/ExampleRecord.php';

class RecordTest extends \PHPUnit_Framework_TestCase
{
	protected static $connectionName;
	protected static $connection;

	protected static $generatedId;
	protected static $startValue = 'Save New';
	protected static $updateValue = 'Save Updated';

	protected static $loadedRecord;

	public static function setUpBeforeClass() {
		self::$connectionName = 'connection-'. rand();
		self::$connection = Db::createConnection(array('sqlite::memory:'), self::$connectionName);

		self::$connection->exec('CREATE TABLE example(id INTEGER PRIMARY KEY AUTOINCREMENT, value TEXT)');
	}

	public static function tearDownAfterClass() {
		Db::unregisterConnection(self::$connectionName);
		self::$connection = null;
	}

	public function testSaveNew() {
		$example = new ExampleRecord(self::$connection);
		$example->value = self::$startValue;
		try {
			$example->save();

			if (!is_numeric($example->id)) {
				$this->fail('The id failed to generate properly. Expected value, numeric. Actual value: '. $example->id);
			}
			$this->assertEquals($example->value, self::$startValue);

			self::$generatedId = $example->id;
		} catch (\Exception $e) {
			$this->fail($e);
		}
	}

	/**
	 * @depends testSaveNew
	 */
	public function testLoad() {
		$example = new ExampleRecord(self::$connection);
		$example->id = self::$generatedId;
		$loaded = $example->load();
		if ($loaded) {
			$this->assertEquals($example->value, self::$startValue);

			self::$loadedRecord = $example;
		} else {
			$this->fail('Failed to load: '. self::$connection->error());
		}
	}

	/**
	 * @depends testLoad
	 */
	public function testSaveUpdate() {
		$example = self::$loadedRecord;

		$idBeforeUpdate = $example->id;
		$example->value = self::$updateValue;
		try {
			$example->save();

			if ($example->id != $idBeforeUpdate) {
				$this->fail('The id was corrupted during the update save. Expected value, '. $idBeforeUpdate .'. Actual value: '. $example->id);
			}
			$this->assertEquals($example->value, self::$updateValue);

			$doubleCheck = new ExampleRecord(self::$connection);
			$doubleCheck->id = $idBeforeUpdate;
			$doubleCheck->load();
			$this->assertEquals($doubleCheck->value, self::$updateValue);
		} catch (\Exception $e) {
			$this->fail($e);
		}
	}
}

?>
