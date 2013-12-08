<?php

namespace PEL\Tests;

use \PEL\Db;

require_once dirname(__DIR__) .'/src/PEL.php';

class DbTest extends \PHPUnit_Framework_TestCase
{
	protected function checkSqliteConnection($connection) {
		$result = $connection->query('SELECT CURRENT_TIMESTAMP');
		$currentTimestamp = $result->fetchColumn();

		$this->assertRegExp('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $currentTimestamp);
	}

	public function testCreateConnectionSqlite() {
		$connectionName = 'connection-'. rand();
		$connection = Db::createConnection(array('sqlite::memory:'), $connectionName);

		$this->checkSqliteConnection($connection);
	}

	/**
	 * @depends testCreateConnectionSqlite
	 */
	public function testRegisterAndGetConnection() {
		$connectionName = 'connection-'. rand();
		Db::registerConnection(array('sqlite::memory:'), $connectionName);
		$connection = Db::getConnection($connectionName);

		$this->checkSqliteConnection($connection);
	}
}

?>
