<?php

namespace PEL\Tests\Db;

use \PEL\Db;

require_once dirname(dirname(__DIR__)) .'/src/PEL.php';
require_once __DIR__ .'/ExampleRecord.php';

class IteratorTest extends \PHPUnit_Framework_TestCase
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
		self::$connection->beginTransaction();
		for ($i = 1; $i <= 1000; ++$i) {
			self::$connection->exec('INSERT INTO example (value) VALUES (\'test-value-'. $i .'\')');
		}
		self::$connection->commit();
	}

	public static function tearDownAfterClass() {
		Db::unregisterConnection(self::$connectionName);
		self::$connection = null;
	}

	public function testSingleEqualMatch() {
		$iter = ExampleRecord::iter(null, self::$connection);
		$iter->where('value', 'test-value-1');
		$matches = 0;
		foreach ($iter as $record) {
			$this->assertEquals($record->id, '1');
			$this->assertEquals($record->value, 'test-value-1');
			++$matches;
		}
		if ($matches !== 1) {
			$this->fail('Got '. $matches .' matches instead of expected single match.');
		}
	}

	public function testAndMatch() {
		$iter = ExampleRecord::iter(null, self::$connection);
		$iter->where('id', '1');
		$iter->where('value', 'test-value-1');
		$matches = 0;
		foreach ($iter as $record) {
			$this->assertEquals($record->id, '1');
			$this->assertEquals($record->value, 'test-value-1');
			++$matches;
		}
		if ($matches !== 1) {
			$this->fail('Got '. $matches .' matches instead of expected single match.');
		}
	}

	public function testAndMismatch() {
		$iter = ExampleRecord::iter(null, self::$connection);
		$iter->where('id', '1');
		$iter->where('value', 'test-value-2');
		$matches = 0;
		foreach ($iter as $record) {
			++$matches;
		}
		if ($matches !== 0) {
			$this->fail('Got '. $matches .' match(es) instead of expected no matches.');
		}
	}

	public function testOrMatch() {
		$iter = ExampleRecord::iter(null, self::$connection);
		$iter->where('id', 'I do not match');
		$iter->where('value', 'test-value-1', null, Db::CONJUNCTION_OR);
		$matches = 0;
		foreach ($iter as $record) {
			$this->assertEquals($record->id, '1');
			$this->assertEquals($record->value, 'test-value-1');
			++$matches;
		}
		if ($matches !== 1) {
			$this->fail('Got '. $matches .' matches instead of expected single match.');
		}
	}

	public function testGreaterThanAndLessThanRangeMatch() {
		$iter = ExampleRecord::iter(null, self::$connection);
		$iter->where('id', '1', Db::COMPARISON_GREATER_THAN);
		$iter->where('id', '12', Db::COMPARISON_LESS_THAN);
		$iter->sort('id');
		$matches = 0;
		foreach ($iter as $record) {
			$this->assertEquals($record->id, $matches + 2);
			$this->assertEquals($record->value, 'test-value-'. ($matches + 2));
			++$matches;
		}
		if ($matches !== 10) {
			$this->fail('Got '. $matches .' matches instead of expected 10 matches.');
		}
	}

	public function testGreaterEqualAndLessEqualRangeMatch() {
		$iter = ExampleRecord::iter(null, self::$connection);
		$iter->where('id', '1', Db::COMPARISON_GREATER_EQUAL);
		$iter->where('id', '10', Db::COMPARISON_LESS_EQUAL);
		$iter->sort('id');
		$matches = 0;
		foreach ($iter as $record) {
			$this->assertEquals($record->id, $matches + 1);
			$this->assertEquals($record->value, 'test-value-'. ($matches + 1));
			++$matches;
		}
		if ($matches !== 10) {
			$this->fail('Got '. $matches .' matches instead of expected 10 matches.');
		}
	}

	public function testKeyedResultsWithContainerArrays() {
		$iter = ExampleRecord::iter('assoc', self::$connection);
		$iter->sort('id');
		$iter->limit(2);
		$keyed = $iter->results(array('id'));

		$this->assertEquals($keyed, array(
			'1' => array(
				array(
					'id' => '1',
					'value' => 'test-value-1'
				)
			),
			'2' => array(
				array(
					'id' => '2',
					'value' => 'test-value-2'
				)
			)
		));
	}

	public function testKeyedResultsWithoutContainerArrays() {
		$iter = ExampleRecord::iter('assoc', self::$connection);
		$iter->sort('id');
		$iter->limit(2);
		$keyed = $iter->results(array('id'), false);

		$this->assertEquals($keyed, array(
			'1' => array(
				'id' => '1',
				'value' => 'test-value-1'
			),
			'2' => array(
				'id' => '2',
				'value' => 'test-value-2'
			)
		));
	}
}

?>
