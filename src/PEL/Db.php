<?php

namespace PEL;

/**
 * PEL Db
 *
 * @package PEL
 */


/**
 * PEL Db
 *
 * @package PEL
 */
class Db
{
	const COMPARISON_EQUAL = '=';
	const COMPARISON_NOT_EQUAL = '!=';
	const COMPARISON_GREATER_THAN = '>';
	const COMPARISON_LESS_THAN = '<';
	const COMPARISON_GREATER_EQUAL = '>=';
	const COMPARISON_LESS_EQUAL = '<=';
	const COMPARISON_LIKE = 'LIKE';
	const COMPARISON_ILIKE = 'ILIKE'; // Postgresql specific
	const COMPARISON_NOT_LIKE = 'NOT LIKE';
	const COMPARISON_IN = 'IN';
	const COMPARISON_NOT_IN = 'NOT IN';
	const COMPARISON_IS_NULL = 'IS NULL';
	const COMPARISON_IS_NOT_NULL = 'IS NOT NULL';
	const COMPARISON_REGEX = '~'; // Postgresql specific
	const COMPARISON_IREGEX = '~*'; // Postgresql specific

	const CONJUNCTION_AND = 'AND';
	const CONJUNCTION_OR = 'OR';

	const ORDER_ASC = 'ASC';
	const ORDER_DESC = 'DESC';

	const VALUE_NOT_NULL = 'NOT-NULL';

	const PROCESS_VALUES_LOCAL = 'local';
	const PROCESS_VALUES_DB = 'db';

	protected static $registeredConnections = array();
	protected static $connections = array();
	protected static $defaultConnectionName = null;

	public static $errorLog = array();

	/**
	 * Log error
	 *
	 * Log to self::$errorLog and syslog
	 *
	 * @uses self::logSilentError()
	 *
	 * @param string $error
	 * @param array  $data
	 *
	 * @return void
	 */
	public static function logError($error, $data = array())
	{
		self::logSilentError($error, $data);
		syslog(LOG_ERR, __CLASS__ .' error: '. $error);
	}

	/**
	 * Log error silently
	 *
	 * Log to self::$errorLog
	 *
	 * @param string $error
	 * @param array  $data
	 *
	 * @return void
	 */
	public static function logSilentError($error, $data = array())
	{
		self::$errorLog[] = array('error' => $error, 'data' => $data);
	}

	public static function createConnection($params, $name = null)
	{
		$refClass = new \ReflectionClass('\PEL\Db\Connection');
		$instance = $refClass->newInstanceArgs($params);

		self::$connections[$name] = $instance;

		return $instance;
	}

	public static function registerConnection($params, $name = null)
	{
		self::$registeredConnections[$name] = $params;
	}

	public static function unregisterConnection($name = null)
	{
		unset(self::$registeredConnections[$name]);
	}

	public static function getDefaultConnectionName() {
		return self::$defaultConnectionName;
	}

	public static function setDefaultConnectionName($name) {
		self::$defaultConnectionName = $name;
	}

	public static function getConnection($name = null)
	{
		if ($name === null && self::$defaultConnectionName !== null) {
			$name = self::$defaultConnectionName;
		}

		$connection = null;
		if (isset(self::$connections[$name])) {
			$connection = self::$connections[$name];
		} else if (isset(self::$registeredConnections[$name])) {
			$registered = self::$registeredConnections[$name];
			$connection = self::createConnection($registered, $name);
		}

		return $connection;
	}

	public static function detectParamType(&$value)
	{
		$type = \PDO::PARAM_STR;
		if ($value === true || $value === false) {
			$type = \PDO::PARAM_BOOL;
		} else if ($value === null || $value == self::VALUE_NOT_NULL) {
			$type = \PDO::PARAM_NULL;
		} else if (is_int($value)) {
			$type = \PDO::PARAM_INT;
		} else if (is_double($value)) {
			$type = \PDO::PARAM_STR; // TODO *sigh* PDO stupidity, need to retest every now and again
		} else if (strlen($value) > 1000) {
			$type = \PDO::PARAM_LOB;
		} else if (is_string($value)) {
			$type = \PDO::PARAM_STR;
		} else if (is_resource($value)) {
			$type = \PDO::PARAM_LOB; // Per docs should work but per experience you need to actually pull the resource content out of the resource manually
		}

		return $type;
	}

	public static function mapType($type, $dbType)
	{
		$outputType = $type;

		$type = strtolower($type);
		$dbType = strtolower($dbType);

		switch ($type) {
			case 'serial':
				switch ($dbType) {
					case 'mysql':
						$outputType = 'INT';
						break;
					case 'sqlite':
						$outputType = 'INTEGER';
						break;
				}
				break;
			case 'bigserial':
				switch ($dbType) {
					case 'mysql':
						$outputType = 'BIGINT';
						break;
					case 'sqlite':
						$outputType = 'INTEGER';
						break;
				}
				break;
			case 'boolean':
				switch ($dbType) {
					case 'mysql':
						$outputType = 'TINYINT UNSIGNED';
						break;
					case 'sqlite':
						$outputType = 'INTEGER';
						break;
				}
				break;
			case 'bytea':
				switch ($dbType) {
					case 'mysql':
						$outputType = 'VARBINARY';
						break;
					case 'sqlite':
						$outputType = 'INTEGER';
						break;
				}
				break;
			case 'int':
			case 'integer':
				switch ($dbType) {
					case 'sqlite':
						$outputType = 'INTEGER';
						break;
				}
				break;
			case 'bigint':
			case 'smallint':
				switch ($dbType) {
					case 'sqlite':
						$outputType = 'INTEGER';
						break;
				}
				break;
			case 'varchar':
			case 'character varying':
				switch ($dbType) {
					case 'mysql':
					case 'sqlite':
						$outputType = 'VARCHAR';
						break;
				}
				break;
			case 'char':
			case 'character':
				break;
			case 'text':
				break;
			case 'money':
				switch ($dbType) {
					case 'mysql':
						$outputType = 'DECIMAL(12,2)';
						break;
					case 'sqlite':
						$outputType = 'REAL';
						break;
				}
				break;
			case 'numeric':
				switch ($dbType) {
					case 'mysql':
						$outputType = 'NUMERIC(12,2)';
						break;
					case 'sqlite':
						$outputType = 'REAL';
						break;
				}
				break;
			case 'real':
				break;
			case 'time':
				switch ($dbType) {
					case 'sqlite':
						$outputType = 'VARCHAR';
						break;
				}
				break;
			case 'date':
				switch ($dbType) {
					case 'sqlite':
						$outputType = 'VARCHAR';
						break;
				}
				break;
			case 'timestamp':
				switch ($dbType) {
					case 'sqlite':
						$outputType = 'INTEGER';
						break;
				}
				break;
		}

		return $outputType;
	}

	public static function getDefaultForType($type, $dbType)
	{
		$default = null;

		$type = strtolower($type);
		$dbType = strtolower($dbType);

		switch ($type) {
			case 'serial':
				break;
			case 'bigserial':
				break;
			case 'boolean':
				switch ($dbType) {
					default:
						$default = false;
						break;
					case 'sqlite':
						$default = 0;
						break;
				}
				break;
			case 'bytea':
				$default = '';
				break;
			case 'int':
			case 'integer':
			case 'bigint':
			case 'smallint':
			case 'money':
			case 'numeric':
			case 'real':
				$default = 0;
				break;
			case 'varchar':
			case 'character varying':
			case 'char':
			case 'character':
			case 'text':
				$default = '';
				break;
			case 'time':
				$default = array_pop(explode('T', gmdate('c')));
				break;
			case 'date':
				$default = array_shift(explode('T', gmdate('c')));
				break;
			case 'timestamp':
				switch ($dbType) {
					default:
						$default = gmdate('c');
						break;
					case 'sqlite':
						$default = time();
						break;
				}
				break;
		}

		return $outputType;
	}
}

?>
